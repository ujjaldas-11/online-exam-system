<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

date_default_timezone_set('Asia/Kolkata');

$exam_id = int_param($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) {
    redirect('control-exams.php');
}

$message = '';
$message_type = '';

// Handle Emergency Teacher Controls
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['reset_student_attempt'])) {
        $student_id = int_param($_POST['student_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE exam_attempts SET status = 'in_progress' WHERE exam_id = ? AND student_id = ?");
            $stmt->execute([$exam_id, $student_id]);
            $message = "Student attempt has been unlocked and set to In Progress.";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to reset student attempt.");
            $message_type = 'error';
        }
    } elseif (isset($_POST['force_submit_attempt'])) {
        $attempt_id = int_param($_POST['attempt_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE exam_attempts SET status = 'completed', submitted_at = NOW() WHERE id = ?");
            $stmt->execute([$attempt_id]);
            $message = "Attempt #$attempt_id has been forced to completed status.";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to submit attempt.");
            $message_type = 'error';
        }
    }
}

try {
    $examStmt = $pdo->prepare("
        SELECT e.*, s.name AS subject_name, s.department, s.semester,
            TIMESTAMPDIFF(SECOND, NOW(), DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE)) AS seconds_left
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.id = ?
    ");
    $examStmt->execute([$exam_id]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        die("Examination not found.");
    }

    // Fetch all students belonging to the target department & semester
    $rosterStmt = $pdo->prepare("
        SELECT st.id AS student_id, st.name, st.roll_number, st.email,
            ea.id AS attempt_id, ea.status AS attempt_status, ea.score, ea.started_at, ea.submitted_at,
            (SELECT COUNT(*) FROM student_answers WHERE attempt_id = ea.id AND selected_option IS NOT NULL AND selected_option != '') AS answered_count,
            (SELECT COUNT(*) FROM exam_violations WHERE attempt_id = ea.id) AS violation_count
        FROM students st
        LEFT JOIN exam_attempts ea ON st.id = ea.student_id AND ea.exam_id = ?
        WHERE st.department = ? AND st.semester = ? AND st.status = 'active'
        ORDER BY st.roll_number ASC
    ");

    $rosterStmt->execute([$exam_id, $exam['department'], $exam['semester']]);
    $students = $rosterStmt->fetchAll();

    // Summary counts
    $total_enrolled = count($students);
    $in_progress_count = 0;
    $completed_count = 0;
    $not_started_count = 0;
    $total_violations = 0;

    foreach ($students as $st) {
        if (empty($st['attempt_id'])) {
            $not_started_count++;
        } elseif ($st['attempt_status'] === 'completed') {
            $completed_count++;
        } else {
            $in_progress_count++;
        }
        $total_violations += (int) $st['violation_count'];
    }

} catch (PDOException $e) {
    log_error("Proctor panel error for exam $exam_id", $e);
    die("Database Error loading proctor panel.");
}

$page_title = 'Live Proctor: ' . e($exam['title']) . ' • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div style="margin-bottom: 16px;">
        <a href="control-exams.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span class="material-symbols-outlined icon-sm">arrow_back</span> Back to Exam Controls
        </a>
    </div>

    <div class="page-header">
        <div>
            <h1 style="display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-outlined icon-lg">visibility</span> Live Classroom Proctoring Panel
            </h1>
            <p>Monitoring: <strong><?= e($exam['title']) ?></strong> (<?= e($exam['subject_name']) ?> • <?= e($exam['department']) ?>, Sem <?= e((string)$exam['semester']) ?>)</p>
        </div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <?php if (!empty($exam['access_pin'])): ?>
                <div class="badge badge-pending" style="font-size: 1rem; padding: 8px 16px; font-family: var(--font-mono); display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">key</span> Classroom PIN: <strong><?= e($exam['access_pin']) ?></strong>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- Real-time Stats Grid -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-num"><?= $total_enrolled ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm">group</span> Class Roster</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-success);">
            <div class="stat-num" style="color: var(--color-success);"><?= $in_progress_count ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm" style="color: var(--color-success);">sensors</span> Answering Now</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-info);">
            <div class="stat-num" style="color: var(--color-info);"><?= $completed_count ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm" style="color: var(--color-info);">check_circle</span> Submitted</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" style="color: var(--color-text-secondary);"><?= $not_started_count ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm" style="color: var(--color-text-secondary);">hourglass_empty</span> Not Started</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-error);">
            <div class="stat-num" style="color: var(--color-error);"><?= $total_violations ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm" style="color: var(--color-error);">warning</span> Cheating Flags</div>
        </div>
    </div>

    <!-- Live Student Roster Table -->
    <div class="card">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Classroom Live Status Roster</span>
            <small style="color: var(--color-text-secondary); font-weight: normal;">Auto-refreshing every 5 seconds</small>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Roll Number</th>
                        <th>Student Name</th>
                        <th>Status</th>
                        <th>Progress</th>
                        <th>Score</th>
                        <th>Violations</th>
                        <th style="text-align: right;">Emergency Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--color-text-secondary); padding: 32px;">No active students enrolled in this department/semester.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($students as $st): ?>
                            <tr>
                                <td><strong><?= e($st['roll_number']) ?></strong></td>
                                <td><?= e($st['name']) ?></td>
                                <td>
                                    <?php if (empty($st['attempt_id'])): ?>
                                        <span class="badge badge-inactive">Not Started</span>
                                    <?php elseif ($st['attempt_status'] === 'completed'): ?>
                                        <span class="badge badge-active">Submitted</span>
                                    <?php else: ?>
                                        <span class="badge badge-running">In Progress</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($st['attempt_id'])): ?>
                                        <?= (int) $st['answered_count'] ?> / <?= (int) $exam['total_questions_to_ask'] ?> Qs
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($st['attempt_status'] === 'completed'): ?>
                                        <strong><?= sprintf('%.2f', (float)$st['score']) ?></strong> / <?= (int) $exam['total_marks'] ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ((int)$st['violation_count'] > 0): ?>
                                        <span class="badge badge-rejected" title="Tab switches or fullscreen exit recorded" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="material-symbols-outlined icon-xs">warning</span> <?= (int)$st['violation_count'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--color-success); font-weight: bold; display: inline-flex; align-items: center; gap: 2px;">
                                            <span class="material-symbols-outlined icon-xs">check</span> 0
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if (!empty($st['attempt_id'])): ?>
                                        <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                            <?php if ($st['attempt_status'] === 'completed'): ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Allow student ' . $st['name'] . ' to resume and continue their attempt?'), ENT_QUOTES, 'UTF-8') ?>);">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="student_id" value="<?= (int) $st['student_id'] ?>">
                                                    <button type="submit" name="reset_student_attempt" class="btn btn-secondary btn-sm">Unlock / Resume</button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm(<?= htmlspecialchars(json_encode('Force submit examination for ' . $st['name'] . '?'), ENT_QUOTES, 'UTF-8') ?>);">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="attempt_id" value="<?= (int) $st['attempt_id'] ?>">
                                                    <button type="submit" name="force_submit_attempt" class="btn btn-warning btn-sm">Force Submit</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    setTimeout(function() {
        window.location.reload();
    }, 5000);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>

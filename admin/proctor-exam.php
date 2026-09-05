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

// Enforce horizontal access control: only exam author or superadmin can proctor
if (!is_superadmin()) {
    $chkOwner = $pdo->prepare("SELECT created_by FROM exams WHERE id = ?");
    $chkOwner->execute([$exam_id]);
    $creator = $chkOwner->fetchColumn();
    if ($creator !== false && $creator !== null && (int)$creator !== (int)($_SESSION['admin_id'] ?? 0)) {
        set_flash('error', "Access Denied: You do not have permission to proctor this exam.");
        redirect('control-exams.php');
    }
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

            $attStmt = $pdo->prepare("SELECT id FROM exam_attempts WHERE exam_id = ? AND student_id = ?");
            $attStmt->execute([$exam_id, $student_id]);
            $attId = $attStmt->fetchColumn();

            require_once __DIR__ . '/../utils/websocket-pusher.php';
            WebSocketPusher::emit("exam:{$exam_id}", "student_started", [
                'student_id' => $student_id,
                'attempt_id' => (int) $attId,
            ]);

            if (function_exists('log_admin_action')) {
                log_admin_action(
                    $pdo,
                    'reset_student_attempt',
                    'exam_attempts',
                    (int) $attId,
                    "Invigilator unlocked attempt #$attId for student #$student_id back to in_progress."
                );
            }

            $message = "Student attempt has been unlocked and set to In Progress.";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to reset student attempt.");
            $message_type = 'error';
        }
    } elseif (isset($_POST['force_submit_attempt'])) {
        $attempt_id = int_param($_POST['attempt_id'] ?? 0);
        try {
            $attStmt = $pdo->prepare("SELECT student_id, exam_id FROM exam_attempts WHERE id = ?");
            $attStmt->execute([$attempt_id]);
            $attRow = $attStmt->fetch(PDO::FETCH_ASSOC);

            if (!$attRow) {
                throw new Exception("Exam attempt #$attempt_id not found.");
            }

            $targetStudentId = (int) $attRow['student_id'];
            $targetExamId = (int) $attRow['exam_id'];

            require_once __DIR__ . '/../services/ExamEngine.php';
            $result = ExamEngine::submitExam($pdo, $targetStudentId, $targetExamId);

            if (!empty($result['success'])) {
                $finalScore = (float) ($result['score'] ?? 0);

                require_once __DIR__ . '/../utils/websocket-pusher.php';
                WebSocketPusher::emit("exam:{$exam_id}", "exam_submitted", [
                    'student_id' => $targetStudentId,
                    'attempt_id' => $attempt_id,
                    'score' => $finalScore,
                ]);

                if (function_exists('log_admin_action')) {
                    log_admin_action(
                        $pdo,
                        'force_submit_exam',
                        'exam_attempts',
                        $attempt_id,
                        "Invigilator force-submitted attempt #$attempt_id (Student ID: $targetStudentId, Exam ID: $targetExamId). Graded Score: " . sprintf('%.2f', $finalScore)
                    );
                }

                $message = "Attempt #$attempt_id has been successfully auto-graded and marked completed (Score: " . sprintf('%.2f', $finalScore) . ").";
                $message_type = 'success';
            } else {
                $message = "Failed to force submit attempt: " . ($result['error'] ?? 'Unknown error');
                $message_type = 'error';
            }
        } catch (Exception $e) {
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
            <div id="proctor-live-badge-container"></div>
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
            <div class="stat-num" id="summary-total-enrolled"><?= $total_enrolled ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm">group</span> Class Roster</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-success);">
            <div class="stat-num" id="summary-in-progress" style="color: var(--color-success);"><?= $in_progress_count ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm" style="color: var(--color-success);">sensors</span> Answering Now</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-info);">
            <div class="stat-num" id="summary-completed" style="color: var(--color-info);"><?= $completed_count ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm" style="color: var(--color-info);">check_circle</span> Submitted</div>
        </div>
        <div class="stat-card">
            <div class="stat-num" id="summary-not-started" style="color: var(--color-text-secondary);"><?= $not_started_count ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm" style="color: var(--color-text-secondary);">hourglass_empty</span> Not Started</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-error);">
            <div class="stat-num" id="summary-total-violations" style="color: var(--color-error);"><?= $total_violations ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm" style="color: var(--color-error);">warning</span> Cheating Flags</div>
        </div>
    </div>

    <!-- Live Student Roster Table -->
    <div class="card">
        <div class="card-title" style="display: flex; justify-content: space-between; align-items: center;">
            <span>Classroom Live Status Roster</span>
            <small style="color: var(--color-text-secondary); font-weight: normal;">Live Real-Time Sync</small>
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
                            <tr id="student-row-<?= (int)$st['student_id'] ?>" data-student-id="<?= (int)$st['student_id'] ?>" data-attempt-id="<?= (int)($st['attempt_id'] ?? 0) ?>" data-total-questions="<?= (int)$exam['total_questions_to_ask'] ?>" data-total-marks="<?= (int)$exam['total_marks'] ?>">
                                <td><strong><?= e($st['roll_number']) ?></strong></td>
                                <td><?= e($st['name']) ?></td>
                                <td class="col-status">
                                    <?php if (empty($st['attempt_id'])): ?>
                                        <span class="badge badge-inactive">Not Started</span>
                                    <?php elseif ($st['attempt_status'] === 'completed'): ?>
                                        <span class="badge badge-active">Submitted</span>
                                    <?php else: ?>
                                        <span class="badge badge-running">In Progress</span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-answered">
                                    <?php if (!empty($st['attempt_id'])): ?>
                                        <?= (int) $st['answered_count'] ?> / <?= (int) $exam['total_questions_to_ask'] ?> Qs
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="col-score">
                                    <?php if ($st['attempt_status'] === 'completed'): ?>
                                        <strong><?= sprintf('%.2f', (float)$st['score']) ?></strong> / <?= (int) $exam['total_marks'] ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td class="col-violations">
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
                                <td class="col-actions" style="text-align: right;">
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

<script src="<?= asset_url('../assets/js/proctor-socket.js') ?>"></script>
<script>
    ExamifyProctor.init({
        examId: <?= (int) $exam_id ?>,
        wsUrl: '<?= htmlspecialchars((string) get_env('WS_PUBLIC_URL', ((function_exists('is_ssl') && is_ssl()) ? 'wss://' : 'ws://') . (!empty($_SERVER['HTTP_HOST']) ? explode(':', $_SERVER['HTTP_HOST'])[0] : 'localhost') . ':8085'), ENT_QUOTES, 'UTF-8') ?>'
    });
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>

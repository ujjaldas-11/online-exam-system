<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

date_default_timezone_set('Asia/Kolkata');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (isset($_POST['start_exam'])) {
        $exam_id = int_param($_POST['exam_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("SELECT status FROM exams WHERE id = ?");
            $stmt->execute([$exam_id]);
            $status = $stmt->fetchColumn();

            if (!$status) {
                $message = "Exam not found.";
                $message_type = 'error';
            } elseif (in_array($status, ['inactive', 'scheduled'], true)) {
                $pdo->prepare("UPDATE exams SET status = 'active', start_time = NOW() WHERE id = ?")->execute([$exam_id]);
                $message = "Exam has been started successfully. Students can now access and join.";
                $message_type = 'success';
            } else {
                $message = "Exam cannot be started in its current status: $status.";
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to start exam.");
            $message_type = 'error';
        }
    } elseif (isset($_POST['delete_exam'])) {
        $exam_id = int_param($_POST['exam_id'] ?? 0);
        try {
            $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$exam_id]);
            $message = "Exam deleted successfully.";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = "Cannot delete exam: Student attempts are linked to it.";
            $message_type = 'error';
        }
    } elseif (isset($_POST['add_time'])) {
        $exam_id = int_param($_POST['exam_id'] ?? 0);
        $extra_minutes = int_param($_POST['extra_minutes'] ?? 5);

        if ($extra_minutes < 1 || $extra_minutes > 120) {
            $message = "Time extension must be between 1 and 120 minutes.";
            $message_type = 'error';
        } else {
            try {
                $upStmt = $pdo->prepare("UPDATE exams SET duration_minutes = duration_minutes + ? WHERE id = ?");
                $upStmt->execute([$extra_minutes, $exam_id]);
                $message = "Added +$extra_minutes minutes to exam duration.";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to extend exam time.");
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['end_exam'])) {
        $exam_id = int_param($_POST['exam_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE exams SET status = 'ended' WHERE id = ?")->execute([$exam_id]);
            $message = "Exam has been ended for all students.";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to end exam.");
            $message_type = 'error';
        }
    }
}

try {
    $exams = $pdo->query("
        SELECT e.*, s.name AS subject_name, s.department, s.semester,
            (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id) AS total_attempts
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        ORDER BY e.id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to fetch exams in control-exams", $e);
    $exams = [];
}



$page_title = 'Exam Control Center • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>Exam Control & Proctoring Center</h1>
            <p>Launch surprise tests, manage classroom PINs, add emergency time, and monitor live progress</p>
        </div>
        <a href="manage-exam.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">add</span> Create New Exam
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">All Examinations (<?= count($exams) ?>)</div>

        <div style="margin-bottom: 10px;">
            <?php include '../components/searchbar.php' ?>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50px;">ID</th>
                        <th>Title & Subject</th>
                        <th>Format & Timing</th>
                        <th>PIN</th>
                        <th>Status</th>
                        <th>Attempts</th>
                        <th style="text-align: right;">Classroom Controls</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--color-text-secondary); padding: 32px;">No exams configured yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exams as $exam): ?>
                            <?php
                            $display_status = 'NOT STARTED';
                            $badge_class = 'badge-notstarted';

                            if ($exam['status'] === 'active') {
                                $start_timestamp = strtotime($exam['start_time']);
                                $duration_seconds = $exam['duration_minutes'] * 60;
                                $end_timestamp = $start_timestamp + $duration_seconds;

                                if (time() >= $end_timestamp) {
                                    $display_status = 'ENDED';
                                    $badge_class = 'badge-ended';
                                } else {
                                    $display_status = 'RUNNING';
                                    $badge_class = 'badge-running';
                                }
                            } elseif ($exam['status'] === 'ended') {
                                $display_status = 'ENDED';
                                $badge_class = 'badge-ended';
                            }
                            ?>
                            <tr>
                                <td>#<?= e((string)$exam['id']) ?></td>
                                <td>
                                    <strong><?= e($exam['title']) ?></strong><br>
                                    <small style="color: var(--color-text-secondary);">
                                        <?= e($exam['subject_name']) ?> (<?= e($exam['department']) ?>, Sem <?= e((string)$exam['semester']) ?>)
                                    </small>
                                </td>
                                <td>
                                    <div><?= e((string)$exam['total_questions_to_ask']) ?> Qs • <?= e((string)$exam['duration_minutes']) ?> mins</div>
                                    <?php if ($exam['start_time']): ?>
                                        <small style="color: var(--color-text-secondary);">Started: <?= date('h:i A', strtotime($exam['start_time'])) ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($exam['access_pin'])): ?>
                                        <span class="badge badge-pending" style="font-family: var(--font-mono); font-size: 0.85rem; display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="material-symbols-outlined icon-xs">key</span> <?= e($exam['access_pin']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--color-text-muted); font-size: 0.85rem;">None</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $badge_class ?>" style="display: inline-flex; align-items: center; gap: 4px;">
                                        <?php if ($display_status === 'RUNNING'): ?>
                                            <span class="material-symbols-outlined icon-xs">play_circle</span>
                                        <?php endif; ?>
                                        <?= $display_status ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= e((string)$exam['total_attempts']) ?></strong>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap;">
                                        <?php if ($display_status === 'NOT STARTED'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Start this examination now? Students will be able to join immediately.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                                <button type="submit" name="start_exam" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                                    <span class="material-symbols-outlined icon-xs">play_arrow</span> Start Exam
                                                </button>
                                            </form>
                                        <?php elseif ($display_status === 'RUNNING'): ?>
                                            <a href="proctor-exam.php?exam_id=<?= $exam['id'] ?>" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                                <span class="material-symbols-outlined icon-xs">visibility</span> Live Proctor
                                            </a>

                                            <!-- Add Time Form -->
                                            <form method="POST" style="display: inline;">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                                <input type="hidden" name="extra_minutes" value="5">
                                                <button type="submit" name="add_time" class="btn btn-secondary btn-sm" title="Add +5 minutes for all students">+5m</button>
                                            </form>
                                        <?php endif; ?>

                                        <?php if ($display_status === 'ENDED' || (int)$exam['total_attempts'] > 0): ?>
                                            <a href="view-results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                                <span class="material-symbols-outlined icon-xs">leaderboard</span> Results
                                            </a>
                                        <?php endif; ?>

                                        <!-- Delete Exam -->
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to permanently delete this exam?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                            <button type="submit" name="delete_exam" class="btn btn-danger btn-sm" title="Delete Exam" style="display: inline-flex; align-items: center; justify-content: center; padding: 6px 8px;">
                                                <span class="material-symbols-outlined icon-sm">delete</span>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

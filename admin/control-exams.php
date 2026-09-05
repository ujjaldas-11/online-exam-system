<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../services/ExamEngine.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../components/status-badge.php';

date_default_timezone_set('Asia/Kolkata');

// Automatically sync exam statuses
ExamEngine::syncExamStatuses($pdo);

$message = '';
$message_type = '';

if (isset($_GET['download_offline']) && isset($_GET['exam_id'])) {
    $dl_exam_id = (int)$_GET['exam_id'];

    if (!can_admin_manage_exam($pdo, $dl_exam_id)) {
        http_response_code(403);
        die('Access Denied: You do not have permission to download offline papers for this examination.');
    }

    require_once '../services/PdfService.php';

    // 1. Fetch Exam Meta
    $stmt = $pdo->prepare("
        SELECT e.*, a.name as creator_name, s.department, s.semester 
        FROM exams e 
        LEFT JOIN admins a ON e.created_by = a.id
        LEFT JOIN subjects s ON e.subject_id = s.id 
        WHERE e.id = ?
    ");
    $stmt->execute([$dl_exam_id]);
    $examMeta = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($examMeta) {
        $limit = max(1, (int) $examMeta['total_questions_to_ask']);
        // 2. Fetch Questions based on target unit
        if ($examMeta['target_units'] === 'all') {
            $qStmt = $pdo->prepare("SELECT * FROM questions WHERE subject_id = ? ORDER BY RAND() LIMIT $limit");
            $qStmt->execute([$examMeta['subject_id']]);
        } else {
            $qStmt = $pdo->prepare("SELECT * FROM questions WHERE subject_id = ? AND unit_number = ? ORDER BY RAND() LIMIT $limit");
            $qStmt->execute([$examMeta['subject_id'], $examMeta['target_units']]);
        }
        $paperQuestions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Generate PDF and force download ('D')
        PdfService::generateOfflineExamPaperPdf($examMeta, $paperQuestions, 'D');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $exam_id = int_param($_POST['exam_id'] ?? 0);
    $isOwnerOrSuper = can_admin_manage_exam($pdo, $exam_id);

    if (!$isOwnerOrSuper) {
        $message = "Access Denied: You can only control exams you have authored.";
        $message_type = 'error';
    } elseif (isset($_POST['start_exam'])) {
        try {
            $stmt = $pdo->prepare("SELECT status FROM exams WHERE id = ?");
            $stmt->execute([$exam_id]);
            $status = $stmt->fetchColumn();

            if (!$status) {
                $message = "Exam not found.";
                $message_type = 'error';
            } elseif (in_array($status, ['inactive', 'scheduled'], true)) {
                $pdo->prepare("UPDATE exams SET status = 'active', start_time = NOW() WHERE id = ?")->execute([$exam_id]);
                log_admin_action($pdo, 'start_exam', 'exam', $exam_id, "Launched/Started exam #$exam_id");
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
            log_admin_action($pdo, 'delete_exam', 'exam', $exam_id, "Deleted exam #$exam_id");
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
                log_admin_action($pdo, 'extend_exam_time', 'exam', $exam_id, "Added +$extra_minutes mins to exam #$exam_id duration");

                require_once __DIR__ . '/../utils/websocket-pusher.php';
                WebSocketPusher::emit("exam:{$exam_id}", "time_extended", [
                    'extra_minutes' => $extra_minutes,
                ]);
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
            log_admin_action($pdo, 'end_exam', 'exam', $exam_id, "Ended exam #$exam_id for all students");

            require_once __DIR__ . '/../utils/websocket-pusher.php';
            WebSocketPusher::emit("exam:{$exam_id}", "exam_ended", [
                'exam_id' => $exam_id,
            ]);

            $message = "Exam has been ended for all students.";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to end exam.");
            $message_type = 'error';
        }
    } elseif (isset($_POST['publish_results'])) {
        $exam_id = int_param($_POST['exam_id'] ?? 0);
        try {
            $chkStmt = $pdo->prepare("SELECT status, start_time, duration_minutes FROM exams WHERE id = ?");
            $chkStmt->execute([$exam_id]);
            $targetExam = $chkStmt->fetch();

            $examOngoing = false;
            if ($targetExam && $targetExam['status'] === 'active') {
                $startTs = !empty($targetExam['start_time']) ? strtotime($targetExam['start_time']) : time();
                $durationSec = ((int)$targetExam['duration_minutes']) * 60;
                if (time() < ($startTs + $durationSec)) {
                    $examOngoing = true;
                }
            }

            if ($examOngoing) {
                $message = "Cannot publish results: Exam #$exam_id is still ongoing.";
                $message_type = 'error';
            } else {
                $pdo->prepare("UPDATE exams SET results_published = 1 WHERE id = ?")->execute([$exam_id]);
                log_admin_action($pdo, 'publish_results', 'exam', $exam_id, "Published results for exam #$exam_id to students");
                $message = "Results for exam #$exam_id have been published to students.";
                $message_type = 'success';
            }
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to publish results.");
            $message_type = 'error';
        }
    } elseif (isset($_POST['unpublish_results'])) {
        $exam_id = int_param($_POST['exam_id'] ?? 0);
        try {
            $pdo->prepare("UPDATE exams SET results_published = 0 WHERE id = ?")->execute([$exam_id]);
            log_admin_action($pdo, 'unpublish_results', 'exam', $exam_id, "Unpublished results for exam #$exam_id");
            $message = "Results for exam #$exam_id are now hidden from students.";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to unpublish results.");
            $message_type = 'error';
        }
    }
}

try {
    $exams = $pdo->query("
        SELECT e.*, s.name AS subject_name, s.department, s.semester,
            a.name AS creator_name, a.role AS creator_role, a.status AS creator_status,
            (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id) AS total_attempts
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        LEFT JOIN admins a ON e.created_by = a.id
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

                            if ($exam['status'] === 'scheduled') {
                                if (!empty($exam['start_time']) && time() >= strtotime($exam['start_time'])) {
                                    $exam['status'] = 'active';
                                }
                            }

                            if ($exam['status'] === 'scheduled') {
                                $display_status = 'SCHEDULED';
                                $badge_class = 'badge-pending';
                            } elseif ($exam['status'] === 'active') {
                                $start_timestamp = !empty($exam['start_time']) ? strtotime($exam['start_time']) : time();
                                $duration_seconds = $exam['duration_minutes'] * 60;
                                $end_timestamp = $start_timestamp + $duration_seconds;

                                if (time() >= $end_timestamp || (!empty($exam['end_time']) && time() >= strtotime($exam['end_time']))) {
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
                            $is_ongoing = ($display_status === 'RUNNING');
                            ?>
                            <tr>
                                <td>#<?= e((string)$exam['id']) ?></td>
                                <td>
                                    <strong><?= e($exam['title']) ?></strong><br>
                                    <small style="color: var(--color-text-secondary);">
                                        <?= e($exam['subject_name']) ?> (<?= e($exam['department']) ?>, Sem <?= e((string)$exam['semester']) ?>)
                                    </small>
                                    <div style="font-size: 0.76rem; color: var(--color-text-secondary); margin-top: 2px;">
                                        By: <strong><?= e($exam['creator_name'] ?? 'System') ?></strong>
                                        <?php if (($exam['creator_status'] ?? '') === 'retired'): ?>
                                            <span class="badge badge-warning" style="font-size: 0.65rem; padding: 1px 4px;">Retired</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div><?= e((string)$exam['total_questions_to_ask']) ?> Qs • <?= e((string)$exam['duration_minutes']) ?> mins</div>
                                    <?php if ($exam['status'] === 'scheduled' && !empty($exam['start_time'])): ?>
                                        <small style="color: var(--color-text-secondary); display: block;">Starts: <?= date('M d, h:i A', strtotime($exam['start_time'])) ?></small>
                                    <?php elseif (!empty($exam['start_time'])): ?>
                                        <small style="color: var(--color-text-secondary); display: block;">Started: <?= date('h:i A', strtotime($exam['start_time'])) ?></small>
                                    <?php endif; ?>
                                    <?php if (!empty($exam['end_time'])): ?>
                                        <small style="color: var(--color-text-secondary); display: block;">Closes: <?= date('M d, h:i A', strtotime($exam['end_time'])) ?></small>
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
                                    <?= render_status_badge($display_status, 'exam') ?>
                                    <?php if ($display_status === 'ENDED' || $exam['status'] === 'ended'): ?>
                                        <div style="margin-top: 4px;">
                                            <?php if (!empty($exam['results_published'])): ?>
                                                <span class="badge badge-active" style="font-size: 0.72rem; padding: 2px 6px; display: inline-flex; align-items: center; gap: 2px;" title="Scores and answers visible to students">
                                                    <span class="material-symbols-outlined" style="font-size: 13px;">visibility</span> Published
                                                </span>
                                            <?php else: ?>
                                                <span class="badge badge-warning" style="font-size: 0.72rem; padding: 2px 6px; display: inline-flex; align-items: center; gap: 2px;" title="Scores and answers hidden from students">
                                                    <span class="material-symbols-outlined" style="font-size: 13px;">visibility_off</span> Hidden
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?= e((string)$exam['total_attempts']) ?></strong>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end; flex-wrap: wrap;">
                                        <a href="?download_offline=true&exam_id=<?= $exam['id'] ?>" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                                            <span class="material-symbols-outlined icon-sm">print</span> Print Offline Paper
                                        </a>
                                        <?php if ($display_status === 'NOT STARTED' || $display_status === 'SCHEDULED'): ?>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Start this examination now? Students will be able to join immediately.');">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                                <button type="submit" name="start_exam" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                                    <span class="material-symbols-outlined icon-xs">play_arrow</span> Start Now
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

                                            <?php if (empty($exam['results_published'])): ?>
                                                <?php if ($is_ongoing): ?>
                                                    <button type="button" class="btn btn-success btn-sm" disabled style="display: inline-flex; align-items: center; gap: 4px;" title="Cannot publish results while the exam is still ongoing">
                                                        <span class="material-symbols-outlined icon-xs">publish</span> Publish
                                                    </button>
                                                <?php else: ?>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Publish results for exam #<?= $exam['id'] ?> to students? Students will immediately see their scores and answer breakdowns.');">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                                        <button type="submit" name="publish_results" class="btn btn-success btn-sm" style="display: inline-flex; align-items: center; gap: 4px;" title="Release scores and answers to students">
                                                            <span class="material-symbols-outlined icon-xs">publish</span> Publish
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Unpublish results for exam #<?= $exam['id'] ?>? Scores and answers will be hidden from students.');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                                    <button type="submit" name="unpublish_results" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;" title="Hide scores and answers from students">
                                                        <span class="material-symbols-outlined icon-xs">visibility_off</span> Unpublish
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php endif; ?>

                                        <!-- Delete Exam -->
                                        <?php if($isAdminSuper): ?>
                                            <form method="POST" style="display: inline;" data-confirm="Are you sure you want to permanently delete this exam and all student submissions?" data-confirm-title="Delete Examination" data-confirm-btn="Delete Exam">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="exam_id" value="<?= $exam['id'] ?>">
                                                <button type="submit" name="delete_exam" class="btn btn-danger btn-sm" title="Delete Exam" style="display: inline-flex; align-items: center; justify-content: center; padding: 6px 8px;">
                                                    <span class="material-symbols-outlined icon-sm">delete</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
include __DIR__ . '/../components/confirm-modal.php';
include __DIR__ . '/../components/footer.php';
?>

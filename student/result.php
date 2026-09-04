<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/ExamEngine.php';

$student_id = (int) $_SESSION['student_id'];
$exam_id = int_param($_POST['exam_id'] ?? $_GET['exam_id'] ?? 0);

if ($exam_id <= 0) {
    redirect('dashboard.php');
}

// 1. Check existing attempt or process POST submission
try {
    $checkStmt = $pdo->prepare("
        SELECT ea.id, ea.score, ea.total_questions, ea.status,
               e.title, e.total_marks, e.status AS exam_status, e.results_published,
               e.duration_minutes, e.start_time
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.student_id = ? AND ea.exam_id = ?
        LIMIT 1
    ");
    $checkStmt->execute([$student_id, $exam_id]);
    $attempt = $checkStmt->fetch();

    if (!$attempt) {
        die("Error: No examination attempt found for this exam.");
    }

    $attempt_id = (int) $attempt['id'];
    $total_marks = (float) $attempt['total_marks'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        $res = ExamEngine::submitExam($pdo, $student_id, $exam_id);
        if (!empty($res['error'])) {
            die("Error grading examination: " . e($res['error']));
        }
        $score = (float) $res['score'];

        require_once __DIR__ . '/../utils/websocket-pusher.php';
        WebSocketPusher::emit("exam:{$exam_id}", "exam_submitted", [
            'student_id' => $student_id,
            'attempt_id' => $attempt_id,
            'score' => $score,
        ]);
    } else {
        if ($attempt['status'] !== 'completed') {
            redirect("exam.php?id=$exam_id");
        }
        $score = (float) $attempt['score'];
    }

    // Determine if the entire exam is finished and published by admin
    $is_exam_ended = ($attempt['exam_status'] === 'ended');
    if ($attempt['exam_status'] === 'active' && !empty($attempt['start_time'])) {
        $durationSec = (int)$attempt['duration_minutes'] * 60;
        if (time() >= (strtotime($attempt['start_time']) + $durationSec)) {
            $is_exam_ended = true;
        }
    }
    $is_published = !empty($attempt['results_published']);
    $can_view_results = $is_exam_ended && $is_published;

    // 2. Fetch Detailed Stats only if results can be viewed
    if ($can_view_results) {
        $statsStmt = $pdo->prepare("
            SELECT
                SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
                SUM(CASE WHEN is_correct = 0 AND selected_option IS NOT NULL AND selected_option != '' THEN 1 ELSE 0 END) as wrong_count,
                SUM(CASE WHEN selected_option IS NULL OR selected_option = '' THEN 1 ELSE 0 END) as skipped_count
            FROM student_answers
            WHERE attempt_id = :attempt_id
        ");
        $statsStmt->execute([':attempt_id' => $attempt_id]);
        $stats = $statsStmt->fetch();

        $correct_count = (int) ($stats['correct_count'] ?? 0);
        $wrong_count = (int) ($stats['wrong_count'] ?? 0);
        $skipped_count = (int) ($stats['skipped_count'] ?? 0);
    }

} catch (PDOException $e) {
    log_error("Result calculation error for student $student_id, exam $exam_id", $e);
    die("Database error calculating score. Please contact your instructor.");
}

$percentage = ($total_marks > 0) ? round(($score / $total_marks) * 100) : 0;
$page_title = $can_view_results ? 'Exam Result • Examify' : 'Exam Submitted • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<div class="container" style="max-width: 700px;">
    <?php if (!$can_view_results): ?>
        <!-- PENDING RESULTS PUBLICATION VIEW (Score visible, review & PDF locked) -->
        <div class="card" style="text-align: center; padding: 40px 24px;">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #dcfce7; color: #16a34a; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <span class="material-symbols-outlined" style="font-size: 36px;">task_alt</span>
            </div>

            <h1 style="font-size: 1.75rem; font-weight: 800; color: var(--color-dark); margin-bottom: 6px;">
                Exam Submitted Successfully
            </h1>
            <p style="color: var(--color-text-secondary); font-size: 1rem; margin-bottom: 24px;">
                Your responses for <strong><?= e($attempt['title']) ?></strong> have been securely recorded. Examination Result summary:
            </p>

            <!-- Student Total Score Display -->
            <div style="background: var(--color-primary-soft); border: 2px solid var(--color-primary-light); border-radius: var(--radius-lg); padding: 22px 24px; margin-bottom: 24px;">
                <div style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--color-primary); letter-spacing: 0.5px;">Your Total Score</div>
                <div style="font-size: 2.8rem; font-weight: 800; color: var(--color-primary); line-height: 1.1; margin: 6px 0;">
                    <?= sprintf('%.2f', $score) ?> <span style="font-size: 1.4rem; color: var(--color-text-secondary); font-weight: 600;">/ <?= e((string)$total_marks) ?></span>
                </div>
                <div style="font-weight: 700; font-size: 1.05rem; color: <?= $percentage >= 50 ? 'var(--color-success)' : 'var(--color-error)' ?>;">
                    Score Percentage: <?= $percentage ?>%
                </div>
            </div>

            <!-- Admin Publish Warning Container -->
            <div style="background: var(--color-bg); border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 20px 24px; margin-bottom: 28px; text-align: left;">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 10px; flex-wrap: wrap; gap: 8px;">
                    <span style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--color-text-secondary); letter-spacing: 0.5px;">Results Status</span>
                    <?php if (!$is_exam_ended): ?>
                        <span class="badge badge-active" style="display: inline-flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined icon-xs">sensors</span> Examination Ongoing
                        </span>
                    <?php else: ?>
                        <span class="badge badge-warning" style="display: inline-flex; align-items: center; gap: 4px;">
                            <span class="material-symbols-outlined icon-xs">hourglass_top</span> Awaiting Publication
                        </span>
                    <?php endif; ?>
                </div>

                <p style="color: var(--color-text); font-size: 0.92rem; line-height: 1.6; margin: 0 0 10px;">
                    <?php if (!$is_exam_ended): ?>
                        This examination session is currently ongoing in the classroom. To maintain academic integrity, detailed answer breakdowns and downloadable scorecard PDFs remain locked until the entire examination ends and the administrator publishes the results.
                    <?php else: ?>
                        The examination session has concluded. Official scorecard PDFs and detailed question answer reviews are currently being finalized and will be unlocked as soon as the administrator publishes the results.
                    <?php endif; ?>
                </p>
                <div style="font-size: 0.85rem; color: var(--color-text-muted); display: flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-xs">info</span>
                    Scorecard PDF download and answer key review will be available once results are published by the instructor.
                </div>
            </div>

            <div style="display: flex; justify-content: center; gap: 12px; flex-wrap: wrap;">
                <a href="dashboard.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px;">
                    <span class="material-symbols-outlined icon-sm">dashboard</span> Return to Dashboard
                </a>
                <button type="button" class="btn btn-secondary" disabled style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 20px; opacity: 0.6; cursor: not-allowed;" title="Scorecard PDF download is locked until admin publishes results">
                    <span class="material-symbols-outlined icon-sm">lock</span> Download PDF (Locked)
                </button>
            </div>
        </div>
    <?php else: ?>
        <!-- PUBLISHED RESULTS VIEW -->
        <div class="card" style="text-align: center; padding: 40px 24px;">
            <div style="margin-bottom: 12px;">
                <span class="material-symbols-outlined icon-2xl" style="color: <?= $percentage >= 50 ? 'var(--color-success)' : 'var(--color-primary)' ?>;">
                    <?= $percentage >= 50 ? 'celebration' : 'auto_stories' ?>
                </span>
            </div>

            <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--color-dark); margin-bottom: 4px;">
                <?= e($attempt['title']) ?>
            </h1>
            <p style="color: var(--color-text-secondary); margin-bottom: 24px;">Examination Result & Performance Breakdown</p>

            <!-- Final Score Display -->
            <div style="background: var(--color-primary-soft); border: 2px solid var(--color-primary-light); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 28px;">
                <div style="font-size: 0.9rem; font-weight: 700; text-transform: uppercase; color: var(--color-primary); letter-spacing: 0.5px;">Your Total Score</div>
                <div style="font-size: 3rem; font-weight: 800; color: var(--color-primary); line-height: 1.1; margin: 6px 0;">
                    <?= sprintf('%.2f', $score) ?> <span style="font-size: 1.5rem; color: var(--color-text-secondary); font-weight: 600;">/ <?= e((string)$total_marks) ?></span>
                </div>
                <div style="font-weight: 700; font-size: 1.1rem; color: <?= $percentage >= 50 ? 'var(--color-success)' : 'var(--color-error)' ?>;">
                    Score Percentage: <?= $percentage ?>%
                </div>
            </div>

            <!-- Metrics Grid -->
            <div class="stats" style="margin-bottom: 32px;">
                <div class="stat-card" style="background: var(--color-success-bg); border-color: #86efac;">
                    <div class="stat-num" style="color: var(--color-success);"><?= $correct_count ?></div>
                    <div class="stat-label" style="color: #15803d;">Correct Answers</div>
                </div>

                <div class="stat-card" style="background: var(--color-error-bg); border-color: #fecaca;">
                    <div class="stat-num" style="color: var(--color-error);"><?= $wrong_count ?></div>
                    <div class="stat-label" style="color: #b91c1c;">Wrong Answers</div>
                </div>

                <div class="stat-card" style="background: var(--color-gray-100);">
                    <div class="stat-num" style="color: var(--color-text-secondary);"><?= $skipped_count ?></div>
                    <div class="stat-label">Skipped / Unanswered</div>
                </div>
            </div>

            <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
                <a href="dashboard.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">dashboard</span> Return to Dashboard
                </a>
                <a href="review-exam.php?attempt_id=<?= $attempt_id ?>" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">analytics</span> Review Answers
                </a>
                <a href="download-card.php?attempt_id=<?= $attempt_id ?>" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">picture_as_pdf</span> Download Scorecard PDF
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

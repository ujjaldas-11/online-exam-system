<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/ExamEngine.php';

$student_id = (int) $_SESSION['student_id'];
$exam_id = int_param($_POST['exam_id'] ?? $_GET['exam_id'] ?? 0);
$student_name = $_SESSION['name'];

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
    $is_exam_ended = ExamEngine::isExamEnded($attempt);
    $is_published = !empty($attempt['results_published']);
    $can_view_results = $is_exam_ended && $is_published;

    // 2. Fetch Detailed Stats only if results can be viewed
    if ($can_view_results) {
        $stats = ExamEngine::getAttemptStats($pdo, $attempt_id);
        $correct_count = $stats['correct_count'];
        $wrong_count = $stats['wrong_count'];
        $skipped_count = $stats['skipped_count'];
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

< <div class="container" style="max-width: 700px;">    
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
                <div style="font-size: 0.9rem; font-weight: 700; text-transform: uppercase; color: var(--color-primary); letter-spacing: 0.5px;"><?= $student_name ?> - Your Total Score</div>
                <div style="font-size: 3rem; font-weight: 800; color: var(--color-primary); line-height: 1.1; margin: 6px 0;">
                    <?= sprintf('%.2f', $score) ?> <span style="font-size: 1.5rem; color: var(--color-text-secondary); font-weight: 600;">/ <?= e((string)$total_marks) ?></span>
                </div>
                <div style="font-weight: 700; font-size: 1.1rem; color: <?= $percentage >= 50 ? 'var(--color-success)' : 'var(--color-error)' ?>;">
                    Score Percentage: <?= $percentage ?>%
                </div>
            </div>


            <?php if (!$can_view_results): ?>

                    <!-- Confidential Status Display (Score hidden until published) -->
                    <div style="background: var(--color-primary-soft); border: 2px solid var(--color-primary-light); border-radius: var(--radius-lg); padding: 22px 24px; margin-bottom: 24px;">
                        <div style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--color-primary); letter-spacing: 0.5px;">Assessment Outcome</div>
                        <div style="font-size: 1.65rem; font-weight: 800; color: var(--color-dark); line-height: 1.2; margin: 8px 0;">
                            Submission Received — Results Pending
                        </div>
                        <div style="font-weight: 600; font-size: 0.95rem; color: var(--color-text-secondary);">
                             Your answersheet will be available for review and download once the exam ends and your instructor officially publishes the results.
                            <br><br>
                            <em style="color: #000; text-decoration: italic;">
                                Note: Question breakdowns remain confidential until publication.
                            </em>
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
            <?php endif; ?>
        </div>
    </div>

<?php include __DIR__ . '/../components/footer.php'; ?>

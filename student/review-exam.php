<?php

require_once __DIR__ . '/student-guard.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/sanitize.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../services/ExamEngine.php';


$student_id = (int) $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$dept = $_SESSION['department'] ?? '';
$roll = $_SESSION['roll_number'] ?? '';

if (empty($_GET['attempt_id'])) {
    die('Invalid request. No exam attempt specified.');
}
$attempt_id = int_param($_GET['attempt_id']);



try {
    $examStmt = $pdo->prepare("
        SELECT e.title, e.total_marks, e.status AS exam_status, e.results_published,
               e.duration_minutes, e.start_time,
               ea.score, ea.total_questions, ea.submitted_at
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.id = ? AND ea.student_id = ? AND ea.status = 'completed'
    ");

    $examStmt->execute([$attempt_id, $student_id]);
    $examOverview = $examStmt->fetch();

    if (!$examOverview) {
        die('Exam not found or you do not have permission to view this.');
    }

    $is_ended = ExamEngine::isExamEnded($examOverview);
    $is_published = !empty($examOverview['results_published']);

    if (!$is_ended || !$is_published) {
        $page_title = 'Answers Not Released • Examify';
        include __DIR__ . '/../components/header.php';
        include __DIR__ . '/../components/student-navbar.php';
        ?>
        <div class="container" style="max-width: 600px; text-align: center; padding: 60px 20px;">
            <div class="card" style="padding: 40px 24px;">
                <div style="width: 68px; height: 68px; border-radius: 50%; background: #fef3c7; color: #d97706; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                    <span class="material-symbols-outlined" style="font-size: 36px;">visibility_off</span>
                </div>
                <h1 style="font-size: 1.6rem; font-weight: 800; color: var(--color-dark); margin-bottom: 8px;">
                    Answer Review Not Available
                </h1>
                <p style="color: var(--color-text-secondary); font-size: 1rem; line-height: 1.5; margin-bottom: 24px;">
                    Question answer keys and reviews for <strong><?= e($examOverview['title']) ?></strong> are not published yet. Answers will become visible once the entire examination concludes and your instructor publishes the results.
                </p>
                <a href="dashboard.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">dashboard</span> Return to Dashboard
                </a>
            </div>
        </div>
        <?php
        include __DIR__ . '/../components/footer.php';
        exit;
    }

    $questions = ExamEngine::getAttemptReviewQuestions($pdo, $attempt_id);

    // Secure PDF Answer Sheet Generation (Only accessible after exam has ended & results are published)
    if (isset($_GET['download_answers'])) {
        require_once __DIR__ . '/../services/PdfService.php';
        $pdfStudent = [
            'name' => $student_name,
            'roll_number' => $roll,
            'department' => $dept
        ];
        $pdfExam = [
            'title' => $examOverview['title']
        ];
        
        PdfService::generateDetailedAnswerSheetPdf($pdfStudent, $pdfExam, $questions, 'D');
        exit;
    }
} catch (PDOException $e) {
    log_error("Failed to load review for attempt $attempt_id", $e);
    die('Database error loading exam review. Please try again later.');
}

$page_title = 'Review Exam • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';

$total_qs = (int) ($examOverview['total_questions'] ?? 0);
$total_marks = (float) ($examOverview['total_marks'] ?? 0);
$marks_each = ($total_qs > 0) ? round($total_marks / $total_qs, 2) : 0;
?>

<div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">
    <div style="margin-bottom: 16px;">
        <a href="dashboard.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-xs">arrow_back</span> Back to Dashboard
        </a>
    </div>
    <!-- Exam Summary Header -->
    <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="margin: 0 0 8px 0; color: #1e293b; font-size: 24px;"><?= e($examOverview['title']) ?></h1>
            <p style="margin: 0; color: #64748b;">Submitted on: <?= date('d M Y, h:i A', strtotime($examOverview['submitted_at'])) ?></p>
            <p style="margin: 4px 0;">Name: <strong><?= e($student_name) ?></strong> (Roll: <?= e($roll) ?>)</p>
            <p style="margin: 4px 0;">Department: <strong><?= e($dept) ?></strong></p>
        </div>
        <div style="text-align: right;">
            <div style="display: inline-flex; flex-direction: column; align-items: center; justify-content: center; width: 110px; height: 110px; border: 3px solid #6366f1; border-radius: 50%; background: #eef2ff;">
                <span style="font-size: 1.5rem; font-weight: 800; color: #4f46e5;"><?= sprintf('%.2f', (float)$examOverview['score']) ?></span>
                <span style="font-size: 0.75rem; color: #64748b; font-weight: 600;">/ <?= e((string)$total_marks) ?></span>
            </div>
            <div style="margin-top: 8px;">
                <a href="?download_answers=true&attempt_id=<?= $attempt_id ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">rule</span> Download Detailed Answer Sheet
                </a>
            </div>
        </div>
    </div>

    <!-- Questions Loop -->
    <?php
    $qNumber = 1;
    foreach ($questions as $q):
        ?>
        <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 20px;">
            <div style="display: flex; gap: 15px; margin-bottom: 16px;">
                <div style="background: #f1f5f9; color: #475569; font-weight: bold; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                    <?= $qNumber++ ?>
                </div>
                <h3 style="margin: 0; padding-top: 6px; color: #1e293b; font-size: 16px; line-height: 1.5;">
                    <?= nl2br(e($q['question_text'])) ?>
                    <?php if (($q['question_type'] ?? 'single') === 'multiple'): ?>
                        <span class="badge badge-warning" style="font-size: 0.72rem; margin-left: 8px; vertical-align: middle;">Multiple Answer</span>
                    <?php elseif (($q['question_type'] ?? '') === 'case_study'): ?>
                        <span class="badge badge-info" style="font-size: 0.72rem; margin-left: 8px; vertical-align: middle;">Case Study</span>
                    <?php elseif (($q['question_type'] ?? '') === 'assertion_reason'): ?>
                        <span class="badge badge-secondary" style="font-size: 0.72rem; margin-left: 8px; vertical-align: middle;">Assertion-Reason</span>
                    <?php elseif (($q['question_type'] ?? '') === 'matching'): ?>
                        <span class="badge badge-outline" style="font-size: 0.72rem; margin-left: 8px; vertical-align: middle;">Matching</span>
                    <?php endif; ?>
                </h3>
            </div>

            <?php if (empty($q['selected_option'])): ?>
                <div style="background: #fef08a; color: #854d0e; padding: 8px 12px; border-radius: 6px; font-size: 14px; margin-bottom: 16px; font-weight: 500;">
                    You did not answer this question. (Correct Key: Option <?= e((string)$q['correct_option']) ?>)
                </div>
            <?php else: ?>
                <div style="font-size: 0.85rem; margin-bottom: 12px; color: <?= (int)$q['is_correct'] === 1 ? '#059669' : '#dc2626' ?>; font-weight: 600;">
                    Result: <?= (int)$q['is_correct'] === 1 ? '✓ Correct' : '✗ Incorrect' ?>
                    &nbsp;•&nbsp; Your Answer: <strong>Option <?= e((string)$q['selected_option']) ?></strong>
                    &nbsp;•&nbsp; Correct Key: <strong>Option <?= e((string)$q['correct_option']) ?></strong>
                </div>
            <?php endif; ?>

            <div style="display: flex; flex-direction: column; gap: 10px; padding-left: 50px;">
                <?php
                $options = [
                    'A' => $q['option_a'] ?? '',
                    'B' => $q['option_b'] ?? '',
                    'C' => $q['option_c'] ?? '',
                    'D' => $q['option_d'] ?? ''
                ];

                $correctTokens = array_filter(array_map('trim', explode(',', (string)($q['correct_option'] ?? ''))));
                $selectedTokens = array_filter(array_map('trim', explode(',', (string)($q['selected_option'] ?? ''))));

                foreach ($options as $letter => $text):
                    if ($text === '' || $text === null) {
                        continue;
                    }

                    $bgColor = '#f8fafc';
                    $borderColor = '#e2e8f0';
                    $textColor = '#475569';
                    $badgeText = '';

                    $isTargetCorrect = in_array($letter, $correctTokens, true);
                    $isSelected = in_array($letter, $selectedTokens, true);

                    if ($isTargetCorrect && $isSelected) {
                        $bgColor = '#ecfdf5';
                        $borderColor = '#10b981';
                        $textColor = '#065f46';
                        $badgeText = '<span style="color: #059669; font-weight: bold; font-size: 0.85rem;">✓ Selected & Correct</span>';
                    } elseif ($isTargetCorrect) {
                        $bgColor = '#ecfdf5';
                        $borderColor = '#10b981';
                        $textColor = '#065f46';
                        $badgeText = '<span style="color: #059669; font-weight: bold; font-size: 0.85rem;">✓ Correct Key</span>';
                    } elseif ($isSelected) {
                        $bgColor = '#fef2f2';
                        $borderColor = '#ef4444';
                        $textColor = '#991b1b';
                        $badgeText = '<span style="color: #dc2626; font-weight: bold; font-size: 0.85rem;">✗ Your Choice</span>';
                    }
                    ?>

                    <div style="padding: 12px 16px; border-radius: 8px; border: 1px solid <?= $borderColor ?>; background: <?= $bgColor ?>; color: <?= $textColor ?>; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="margin-right: 8px; opacity: 0.7;"><?= $letter ?>.</strong>
                            <?= e((string) $text) ?>
                        </div>
                        <?php if ($badgeText): ?>
                            <div><?= $badgeText ?></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="text-align: center; margin-top: 30px;">
        <a href="dashboard.php" class="btn btn-primary" style="padding: 12px 24px;">Back to Dashboard</a>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

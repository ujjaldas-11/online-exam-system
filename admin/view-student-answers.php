<?php
require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../services/PdfService.php';

$attempt_id = int_param($_GET['attempt_id'] ?? 0);

if ($attempt_id <= 0) {
    die('Invalid request: No attempt specified.');
}

// 1. Fetch Attempt, Student, and Exam attemptData
$attemptDataStmt = $pdo->prepare("
    SELECT 
        s.name, s.roll_number, s.department, s.semester, 
        e.id AS exam_id, e.title, e.total_marks,
        ea.score, ea.total_questions, ea.submitted_at
    FROM exam_attempts ea
    JOIN students s ON ea.student_id = s.id
    JOIN exams e ON ea.exam_id = e.id
    WHERE ea.id = ?
");
$attemptDataStmt->execute([$attempt_id]);
$attemptData = $attemptDataStmt->fetch(PDO::FETCH_ASSOC);

if (!$attemptData) {
    die("Attempt not found.");

}
    // echo "<pre style='background:#111; color:#0f0; padding:20px; font-size:16px;'>";
    // print_r($attemptData);
    // die("</pre>");  

// 2. Fetch Q&A Data
$qaStmt = $pdo->prepare('
    SELECT q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option,
           sa.selected_option, sa.is_correct
    FROM student_answers sa
    JOIN questions q ON sa.question_id = q.id
    WHERE sa.attempt_id = ?
    ORDER BY sa.id ASC
');
$qaStmt->execute([$attempt_id]);
$qaData = $qaStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Handle PDF Download Request
if (isset($_GET['download']) && $_GET['download'] === 'pdf') {
    $pdfStudent = ['name' => $attemptData['name'], 'roll_number' => $attemptData['roll_number']];
    $pdfExam = ['title' => $attemptData['title']];
    PdfService::generateDetailedAnswerSheetPdf($pdfStudent, $pdfExam, $qaData, 'D');
}

// Calculate Stats for the UI
$correct = 0;
$wrong = 0;
$skipped = 0;
foreach ($qaData as $qa) {
    if ($qa['is_correct'])
        $correct++;
    elseif ($qa['selected_option'])
        $wrong++;
    else
        $skipped++;
}

$page_title = 'Review Answers • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div style="margin-bottom: 16px;">
        <a href="view-results.php?exam_id=<?= $attemptData['exam_id'] ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span class="material-symbols-outlined icon-sm">arrow_back</span> Back to Results
        </a>
    </div>

        <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 16px;">
            <div>
                <h1><?= e($attemptData['name'] ?? 'Unknown Student') ?>'s Answer Sheet</h1>
                <p>
                    <strong>Exam:</strong> <?= e($attemptData['title'] ?? 'Unknown Exam') ?> <br>
                    <strong>Roll No:</strong> <?= e($attemptData['roll_number'] ?? 'N/A') ?> 
                    (<?= e($attemptData['department'] ?? 'General') ?>, Sem <?= e((string)($attemptData['semester'] ?? '')) ?>) <br>
                    <strong>Score:</strong> <?= sprintf('%.2f', (float)($attemptData['score'] ?? 0)) ?> / <?= e((string)($attemptData['total_marks'] ?? '0')) ?>
                </p>
            </div>
        <div>
        <!-- The PDF Download Button -->
        <a href="?attempt_id=<?= $attempt_id ?>&download=pdf" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">picture_as_pdf</span> Download PDF
        </a>
    </div>
</div>

    <!-- Quick Stats -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="text-align: center; padding: 16px; background: #f0fdf4; border-color: #bbf7d0;">
            <div style="font-size: 24px; font-weight: 800; color: #16a34a;"><?= $correct ?></div>
            <div style="font-size: 14px; color: #15803d;">Correct</div>
        </div>
        <div class="card" style="text-align: center; padding: 16px; background: #fef2f2; border-color: #fecaca;">
            <div style="font-size: 24px; font-weight: 800; color: #dc2626;"><?= $wrong ?></div>
            <div style="font-size: 14px; color: #b91c1c;">Wrong</div>
        </div>
        <div class="card" style="text-align: center; padding: 16px; background: #f8fafc; border-color: #e2e8f0;">
            <div style="font-size: 24px; font-weight: 800; color: #64748b;"><?= $skipped ?></div>
            <div style="font-size: 14px; color: #475569;">Skipped</div>
        </div>
    </div>

    <!-- Questions Loop -->
    <?php $qNum = 1;
    foreach ($qaData as $qa): ?>
        <?php
        $selected = $qa['selected_option'];
        $is_correct = (bool) $qa['is_correct'];

        // Determine border color based on status
        if ($is_correct)
            $borderColor = '#22c55e';  // Green
        elseif ($selected)
            $borderColor = '#ef4444';  // Red
        else
            $borderColor = '#cbd5e1';  // Gray
        ?>
        <div class="card" style="margin-bottom: 16px; border-left: 4px solid <?= $borderColor ?>;">
            <div style="margin-bottom: 12px;">
                <strong>Q<?= $qNum++ ?>.</strong> <?= nl2br(e($qa['question_text'])) ?>
            </div>

            <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 16px;">
                <?php foreach (['A', 'B', 'C', 'D'] as $opt): ?>
                    <?php
                    $optKey = 'option_' . strtolower($opt);
                    if (empty(trim($qa[$optKey] ?? '')))
                        continue;

                    $is_selected = ($selected === $opt);
                    $is_actual_correct = ($qa['correct_option'] === $opt);

                    // Styling the options
                    $bg = 'transparent';
                    $color = 'inherit';
                    $weight = 'normal';

                    if ($is_selected && $is_correct) {
                        $bg = '#dcfce7';
                        $color = '#16a34a';
                        $weight = 'bold';
                    } elseif ($is_selected && !$is_correct) {
                        $bg = '#fee2e2';
                        $color = '#dc2626';
                        $weight = 'bold';
                    } elseif ($is_actual_correct && !$is_correct) {
                        $bg = '#f0fdf4';
                        $color = '#15803d';
                        $weight = 'bold';  // Highlight correct answer if they missed it
                    }
                    ?>
                    <div style="padding: 8px 12px; border-radius: 6px; background: <?= $bg ?>; color: <?= $color ?>; font-weight: <?= $weight ?>; border: 1px solid <?= $is_selected || $is_actual_correct ? 'currentColor' : '#e2e8f0' ?>;">
                        <strong><?= $opt ?>)</strong> <?= e($qa[$optKey]) ?>
                        <?php if ($is_selected && $is_correct): ?> <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle; float: right;">check_circle</span> <?php endif; ?>
                        <?php if ($is_selected && !$is_correct): ?> <span class="material-symbols-outlined" style="font-size: 16px; vertical-align: middle; float: right;">cancel</span> <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!$selected): ?>
                <div style="color: #64748b; font-size: 14px; font-style: italic;">
                    <span class="material-symbols-outlined icon-sm" style="vertical-align: middle;">remove_circle_outline</span> Student skipped this question.
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

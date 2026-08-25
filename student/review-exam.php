<?php
require_once 'student-guard.php';
require_once '../config/database.php';

$student_id = $_SESSION['student_id'];
$student_name = $_SESSION['student_name'] ?? 'Student';
$dept = $_SESSION['department'];
$roll = $_SESSION['roll_number'];

if (!isset($_GET['attempt_id'])) {
    die('Invalid request. No exam attempt specified.');
}
$attempt_id = $_GET['attempt_id'];

try {
    $examStmt = $pdo->prepare("
        SELECT e.title, e.total_marks, ea.score, ea.total_questions, ea.submitted_at
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.id = ? AND ea.student_id = ? AND ea.status = 'completed'
    ");

    $examStmt->execute([$attempt_id, $student_id]);
    $examOverview = $examStmt->fetch(PDO::FETCH_ASSOC);

    if (!$examOverview) {
        die('Exam not found or you do not have permission to view this.');
    }

    $qStmt = $pdo->prepare('SELECT q.question_text, q.option_a, q.option_b, q.option_c,
    q.option_d, q.correct_option, sa.selected_option, sa.is_correct 
    FROM student_answers sa JOIN questions q ON sa.question_id = q.id WHERE sa.attempt_id = ?');

    $qStmt->execute([$attempt_id]);
    $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

    $page_title = 'Review exam . Examify';
    include __DIR__ . '/../components/header.php';
} catch (PDOException $e) {
    // die($e->getMessage());
    die('Registration failed. Please check your information.');
}
?>


<div class="container" style="max-width: 800px; margin: 0 auto; padding: 20px;">

    <!-- Exam Summary Header -->
    <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1 style="margin: 0 0 8px 0; color: #1e293b; font-size: 24px;"><?= htmlspecialchars($examOverview['title']) ?></h1>
            <p style="margin: 0; color: #64748b;">Submitted on: <?= date('d M Y, h:i A', strtotime($examOverview['submitted_at'])) ?></p>

            <p>Name: <strong> <?= htmlspecialchars($student_name) ?> </strong></p>
            <p>Roll: <?= htmlspecialchars($roll) ?></p>
            <p>Dpartment: <?= htmlspecialchars($dept) ?></p>
        </div>
        <div style="text-align: right;">
            <div style="font-size: 32px; font-weight: bold; color: #4f46e5;">
                <?= $examOverview['score'] ?> / <?= $examOverview['total_marks'] ?>
            </div>
            <p style="margin: 0; color: #64748b; font-weight: 500;">Total Score</p>
            <p><?= $marks_each = $examOverview['total_marks'] / $examOverview['total_questions'] ?> Marks for each</p>
            <p>Total quesions - <?= $examOverview['total_questions'] ?></p>
            <button onclick="window.print();" class="btn btn-primary">Download</button>
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
                    <?= htmlspecialchars($q['question_text']) ?>
                </h3>
            </div>

            <?php if (empty($q['selected_option'])): ?>
                <div style="background: #fef08a; color: #854d0e; padding: 8px 12px; border-radius: 6px; font-size: 14px; margin-bottom: 16px; font-weight: 500;">
                    You did not answer this question.
                </div>
            <?php endif; ?>

            <div style="display: flex; flex-direction: column; gap: 10px; padding-left: 50px;">
                <?php
                $options = [
                    'A' => $q['option_a'],
                    'B' => $q['option_b'],
                    'C' => $q['option_c'],
                    'D' => $q['option_d']
                ];

                foreach ($options as $letter => $text):
                    $bgColor = '#f8fafc';
                    $borderColor = '#e2e8f0';
                    $textColor = '#475569';
                    $icon = '';

                    if ($letter === $q['correct_option']) {
                        $bgColor = '#ecfdf5';
                        $borderColor = '#10b981';
                        $textColor = '#065f46';
                        $icon = '✓';
                    } elseif ($letter === $q['selected_option'] && $q['is_correct'] == 0) {
                        $bgColor = '#fef2f2';
                        $borderColor = '#ef4444';
                        $textColor = '#991b1b';
                        $icon = '✗';
                    }
                    ?>

                    <div style="padding: 12px 16px; border-radius: 8px; border: 1px solid <?= $borderColor ?>; background: <?= $bgColor ?>; color: <?= $textColor ?>; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong style="margin-right: 8px; opacity: 0.7;"><?= $letter ?>.</strong>
                            <?= htmlspecialchars($text) ?>
                        </div>
                        <?php if ($icon): ?>
                            <span style="font-weight: bold; font-size: 18px;"><?= $icon ?></span>
                        <?php endif; ?>
                    </div>

                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

    <div style="text-align: center; margin-top: 30px;">
        <a href="dashboard.php" class="btn btn-primary" style="background: #4f46e5; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; font-weight: 500;">Back to Dashboard</a>
    </div>

</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

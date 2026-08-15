<?php
require_once 'student-guard.php';
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: dashboard.php");
    exit();
}

if (!isset($_POST['exam_id']) || empty($_POST['exam_id'])) {
    die("Error: No exam ID provided.");
}

$exam_id = (int)$_POST['exam_id'];
$student_id = $_SESSION['student_id'];
$submitted_answers = $_SESSION['exam_answers'][$exam_id] ?? [];

$score = 0;
$total_marks = 0;
$already_submitted = false;

try {
    // Check if the student has already attempted this exam
    $checkSql = "SELECT id, score, status FROM exam_attempts WHERE student_id = :student_id AND exam_id = :exam_id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':student_id' => $student_id, ':exam_id' => $exam_id]);
    $attempt = $checkStmt->fetch();
    
    if (!$attempt) die("Error: No active attempt found for this exam.");
    
    $attempt_id = $attempt['id'];
    
    if ($attempt['status'] === 'completed') {
        $already_submitted = true;
        $score = $attempt['score'];
        
        $examStmt = $pdo->prepare("SELECT title, total_marks FROM exams WHERE id = :exam_id");
        $examStmt->execute([':exam_id' => $exam_id]);
        $exam = $examStmt->fetch();
        $total_marks = $exam['total_marks'];
        
    } else {
        // Process submission
        $examSql = "SELECT title, total_marks, total_questions_to_ask FROM exams WHERE id = :exam_id LIMIT 1";
        $examStmt = $pdo->prepare($examSql);
        $examStmt->execute([':exam_id' => $exam_id]);
        $exam = $examStmt->fetch();

        if (!$exam) die("Error: Invalid exam.");
        
        $total_marks = $exam['total_marks'];
        $points_per_question = ($exam['total_questions_to_ask'] > 0) ? ($exam['total_marks'] / $exam['total_questions_to_ask']) : 0;

        $qSql = "SELECT sa.id AS ans_id, q.id AS question_id, q.correct_option 
                 FROM student_answers sa 
                 JOIN questions q ON sa.question_id = q.id 
                 WHERE sa.attempt_id = :attempt_id";
        $qStmt = $pdo->prepare($qSql);
        $qStmt->execute([':attempt_id' => $attempt_id]);
        $assigned_questions = $qStmt->fetchAll();

        $pdo->beginTransaction();
        $updateAnsSql = "UPDATE student_answers SET selected_option = :selected_option, is_correct = :is_correct WHERE id = :ans_id";
        $updateAnsStmt = $pdo->prepare($updateAnsSql);

        foreach ($assigned_questions as $q) {
            $q_id = $q['question_id'];
            $ans_id = $q['ans_id'];
            
            $selected_option = isset($submitted_answers[$q_id]) ? trim(strip_tags($submitted_answers[$q_id])) : null;
            $is_correct = ($selected_option === $q['correct_option']) ? 1 : 0;
            
            if ($is_correct) $score += $points_per_question;

            $updateAnsStmt->execute([
                ':selected_option' => $selected_option,
                ':is_correct' => $is_correct,
                ':ans_id' => $ans_id
            ]);
        }

        $attemptUpdateSql = "UPDATE exam_attempts SET score = :score, status = 'completed', submitted_at = NOW() WHERE id = :attempt_id";
        $attemptUpdateStmt = $pdo->prepare($attemptUpdateSql);
        $attemptUpdateStmt->execute([':score' => $score, ':attempt_id' => $attempt_id]);
        
        $pdo->commit();
        unset($_SESSION['exam_answers'][$exam_id]);
        unset($_SESSION['exam_reviews'][$exam_id]);
    }

    // Fetch Correct, Wrong, and Skipped stats
    $statsStmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
            SUM(CASE WHEN is_correct = 0 AND selected_option IS NOT NULL AND selected_option != '' THEN 1 ELSE 0 END) as wrong_count,
            SUM(CASE WHEN selected_option IS NULL OR selected_option = '' THEN 1 ELSE 0 END) as skipped_count
        FROM student_answers
        WHERE attempt_id = :attempt_id
    ");
    $statsStmt->execute([':attempt_id' => $attempt_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $correct_count = (int)$stats['correct_count'];
    $wrong_count   = (int)$stats['wrong_count'];
    $skipped_count = (int)$stats['skipped_count'];

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    die("Database Error: " . $e->getMessage());
}

$percentage = ($total_marks > 0) ? round(($score / $total_marks) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Result • Examify</title>
    <style>
        :root {
            --primary: #2563eb; --primary-light: #eff6ff;
            --success: #16a34a; --success-light: #dcfce7;
            --danger: #dc2626;  --danger-light: #fee2e2;
            --gray: #64748b;    --light: #f8fafc;
            --dark: #0f172a;    --border: #e2e8f0;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: system-ui, sans-serif; }
        body { background: var(--light); color: var(--dark); display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        
        .card { background: white; width: 100%; max-width: 480px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); text-align: center; overflow: hidden; border: 1px solid var(--border); padding: 40px 30px; position: relative; }
        .card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px; background: var(--primary); }
        
        .icon { width: 70px; height: 70px; background: var(--success-light); color: var(--success); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 32px; margin: 0 auto 20px; }
        .icon.info { background: var(--primary-light); color: var(--primary); }
        
        h1 { font-size: 1.8rem; margin-bottom: 5px; }
        .subtitle { color: var(--gray); font-size: 1rem; margin-bottom: 30px; font-weight: 500; }
        
        .score-box { background: var(--light); border: 1px solid var(--border); border-radius: 16px; padding: 25px 20px; margin-bottom: 25px; }
        .score-label { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; color: var(--gray); font-weight: 600; margin-bottom: 10px; }
        .score-value { font-size: 3.5rem; font-weight: 800; color: var(--primary); line-height: 1; }
        .score-divider { font-size: 2rem; color: #cbd5e1; margin: 0 5px; }
        .score-total { font-size: 1.8rem; color: var(--gray); font-weight: 600; }
        .badge { display: inline-block; background: var(--primary-light); color: var(--primary); padding: 5px 15px; border-radius: 20px; font-weight: 700; font-size: 0.9rem; margin-top: 15px; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 30px; }
        .stat { padding: 15px 10px; border-radius: 12px; border: 1px solid transparent; }
        .stat.correct { background: var(--success-light); color: var(--success); border-color: #bbf7d0; }
        .stat.wrong { background: var(--danger-light); color: var(--danger); border-color: #fecaca; }
        .stat.skipped { background: var(--light); color: var(--gray); border-color: var(--border); }
        .stat-val { font-size: 1.4rem; font-weight: 800; line-height: 1; margin-bottom: 5px; }
        .stat-lbl { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.9; }
        
        .btn { display: block; width: 100%; background: var(--primary); color: white; padding: 14px; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 1rem; transition: 0.2s; }
        .btn:hover { background: #1d4ed8; transform: translateY(-2px); }
        
        .alert { background: #fef3c7; color: #92400e; padding: 12px; border-radius: 8px; font-size: 0.9rem; margin-bottom: 25px; border: 1px solid #fde68a; }
    </style>
</head>
<body>

    <div class="card">
        <?php if ($already_submitted): ?>
            <div class="alert">
                <strong>Notice:</strong> You have already submitted this exam. Your official score is below.
            </div>
            <h1>Submission Recorded</h1>
        <?php else: ?>
            <div class="icon">✓</div>
            <h1>Exam Completed!</h1>
        <?php endif; ?>

        <div class="subtitle"><?= htmlspecialchars($exam['title']); ?></div>
        
        <div class="score-box">
            <div class="score-label">Your Final Score</div>
            <div>
                <span class="score-value"><?= $score; ?></span>
                <span class="score-divider">/</span>
                <span class="score-total"><?= $total_marks; ?></span>
            </div>
            <div class="badge"><?= $percentage; ?>% Accuracy</div>
        </div>

        <div class="stats-grid">
            <div class="stat correct">
                <div class="stat-val"><?= $correct_count ?></div>
                <div class="stat-lbl">Correct</div>
            </div>
            <div class="stat wrong">
                <div class="stat-val"><?= $wrong_count ?></div>
                <div class="stat-lbl">Wrong</div>
            </div>
            <div class="stat skipped">
                <div class="stat-val"><?= $skipped_count ?></div>
                <div class="stat-lbl">Skipped</div>
            </div>
        </div>

        <a href="dashboard.php" class="btn">Return to Dashboard</a>
    </div>

</body>
</html>
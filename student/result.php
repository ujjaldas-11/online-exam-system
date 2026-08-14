<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

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
    // 1. Check if the student has already attempted this exam
    $checkSql = "SELECT id, score, status FROM exam_attempts WHERE student_id = :student_id AND exam_id = :exam_id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':student_id' => $student_id,
        ':exam_id' => $exam_id
    ]);
    
    $attempt = $checkStmt->fetch();
    
    if (!$attempt) {
        die("Error: No active attempt found for this exam.");
    }
    
    $attempt_id = $attempt['id'];
    
    if ($attempt['status'] === 'completed') {
        // Student already submitted the exam, fetch existing data
        $already_submitted = true;
        $score = $attempt['score'];
        
        $examStmt = $pdo->prepare("SELECT title, total_marks FROM exams WHERE id = :exam_id");
        $examStmt->execute([':exam_id' => $exam_id]);
        $exam = $examStmt->fetch();
        $total_marks = $exam['total_marks'];
        
    } else {
        // 2. Process Submission: Fetch exam details
        $examSql = "SELECT title, total_marks FROM exams WHERE id = :exam_id LIMIT 1";
        $examStmt = $pdo->prepare($examSql);
        $examStmt->execute([':exam_id' => $exam_id]);
        $exam = $examStmt->fetch();

        if (!$exam) {
            die("Error: Invalid exam.");
        }
        $total_marks = $exam['total_marks'];

        // 3. Fetch assigned questions for this attempt
        $qSql = "SELECT sa.id AS ans_id, q.id AS question_id, q.correct_option, q.marks 
                 FROM student_answers sa 
                 JOIN questions q ON sa.question_id = q.id 
                 WHERE sa.attempt_id = :attempt_id";
        $qStmt = $pdo->prepare($qSql);
        $qStmt->execute([':attempt_id' => $attempt_id]);
        $assigned_questions = $qStmt->fetchAll();

        // 4. Begin Database Transaction
        $pdo->beginTransaction();

        $updateAnsSql = "UPDATE student_answers SET selected_option = :selected_option, is_correct = :is_correct WHERE id = :ans_id";
        $updateAnsStmt = $pdo->prepare($updateAnsSql);

        foreach ($assigned_questions as $q) {
            $q_id = $q['question_id'];
            $ans_id = $q['ans_id'];
            $marks = (int)$q['marks'];
            $correct_ans = $q['correct_option'];
            
            $selected_option = isset($submitted_answers[$q_id]) ? trim(strip_tags($submitted_answers[$q_id])) : null;
            $is_correct = ($selected_option === $correct_ans) ? 1 : 0;
            
            if ($is_correct) {
                $score += $marks;
            }

            // Update individual answer row
            $updateAnsStmt->execute([
                ':selected_option' => $selected_option,
                ':is_correct' => $is_correct,
                ':ans_id' => $ans_id
            ]);
        }

        // 5. Update exam_attempts
        $attemptUpdateSql = "UPDATE exam_attempts 
                             SET score = :score, status = 'completed', submitted_at = NOW() 
                             WHERE id = :attempt_id";
        $attemptUpdateStmt = $pdo->prepare($attemptUpdateSql);
        $attemptUpdateStmt->execute([
            ':score' => $score,
            ':attempt_id' => $attempt_id
        ]);
        
        // 6. Commit the transaction
        $pdo->commit();
        
        // Clear session answers
        unset($_SESSION['exam_answers'][$exam_id]);
        unset($_SESSION['exam_reviews'][$exam_id]);
    }

} catch (PDOException $e) {
    // If anything fails, rollback the database changes so we don't get partial data
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Result - Examify</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; padding: 20px; text-align: center; }
        .result-container { max-width: 600px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-top: 0; }
        .score-box { background-color: #e9ecef; border-radius: 8px; padding: 30px; margin: 20px 0; }
        .score-text { font-size: 48px; font-weight: bold; color: #28a745; margin: 0; }
        .alert { background-color: #fff3cd; color: #856404; padding: 15px; border-radius: 4px; border: 1px solid #ffeeba; margin-bottom: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 20px; }
        .btn:hover { background-color: #0056b3; }
    </style>
</head>
<body>

    <div class="result-container">
        <?php if ($already_submitted): ?>
            <div class="alert">
                <strong>Notice:</strong> You have already submitted this exam. Your previous score is shown below.
            </div>
        <?php else: ?>
            <h1>Exam Completed!</h1>
            <p>You have successfully submitted the exam.</p>
        <?php endif; ?>

        <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
        
        <div class="score-box">
            <p style="margin: 0 0 10px 0; color: #666; font-size: 18px;">Your Final Score</p>
            <p class="score-text"><?php echo $score; ?> / <?php echo $total_marks; ?></p>
        </div>

        <a href="dashboard.php" class="btn">Return to Dashboard</a>
    </div>

</body>
</html> 
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

if (!isset($_POST['exam_id'])) {
    die("Error: No exam ID provided.");
}

$exam_id = (int)$_POST['exam_id'];
$student_id = $_SESSION['student_id'];
$submitted_answers = isset($_POST['answers']) ? $_POST['answers'] : [];

$score = 0;
$total_marks = 0;

try {
    $examSql = "SELECT title FROM exams WHERE id = :exam_id LIMIT 1";
    $examStmt = $pdo->prepare($examSql);
    $examStmt->execute([':exam_id' => $exam_id]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        die("Error: Invalid exam.");
    }

    $qSql = "SELECT id, correct_option, marks FROM questions WHERE exam_id = :exam_id";
    $qStmt = $pdo->prepare($qSql);
    $qStmt->execute([':exam_id' => $exam_id]);
    $questions = $qStmt->fetchAll();

    foreach ($questions as $q) {
        $q_id = $q['id'];
        $marks = (int)$q['marks'];
        $correct_ans = $q['correct_option'];
        
        $total_marks += $marks;

        if (isset($submitted_answers[$q_id]) && $submitted_answers[$q_id] === $correct_ans) {
            $score += $marks;
        }
    }

    // Optional: Save the result to a database table
    $saveSql = "INSERT INTO results (student_id, exam_id, score, total_marks, submitted_at) 
                VALUES (:student_id, :exam_id, :score, :total_marks, NOW())";
    $saveStmt = $pdo->prepare($saveSql);
    $saveStmt->execute([
        ':student_id' => $student_id,
        ':exam_id' => $exam_id,
        ':score' => $score,
        ':total_marks' => $total_marks
    ]);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Exam Result - Examify</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; padding: 20px; text-align: center; }
        .result-container { max-width: 600px; margin: 40px auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; margin-top: 0; }
        .score-box { background-color: #e9ecef; border-radius: 8px; padding: 30px; margin: 20px 0; }
        .score-text { font-size: 48px; font-weight: bold; color: #28a745; margin: 0; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 20px; }
        .btn:hover { background-color: #0056b3; }
    </style>
</head>
<body>

    <div class="result-container">
        <h1>Exam Completed!</h1>
        <p>You have successfully submitted the exam: <strong><?php echo htmlspecialchars($exam['title']); ?></strong></p>
        
        <div class="score-box">
            <p style="margin: 0 0 10px 0; color: #666; font-size: 18px;">Your Final Score</p>
            <p class="score-text"><?php echo $score; ?> / <?php echo $total_marks; ?></p>
        </div>

        <a href="dashboard.php" class="btn">Return to Dashboard</a>
    </div>

</body>
</html>
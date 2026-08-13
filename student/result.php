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
$submitted_answers = isset($_POST['answers']) ? $_POST['answers'] : [];

$score = 0;
$total_marks = 0;
$already_submitted = false;

try {
    // 1. Check if the student has already attempted this exam
    $checkSql = "SELECT id, score FROM exam_attempts WHERE student_id = :student_id AND exam_id = :exam_id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([
        ':student_id' => $student_id,
        ':exam_id' => $exam_id
    ]);
    
    if ($checkStmt->rowCount() > 0) {
        // Student already took the exam, fetch existing data
        $existingAttempt = $checkStmt->fetch();
        $already_submitted = true;
        $score = $existingAttempt['score'];
        
        $examStmt = $pdo->prepare("SELECT title, total_marks FROM exams WHERE id = :exam_id");
        $examStmt->execute([':exam_id' => $exam_id]);
        $exam = $examStmt->fetch();
        $total_marks = $exam['total_marks'];
        
    } else {
        // 2. New Attempt: Fetch exam details
        $examSql = "SELECT title, total_marks FROM exams WHERE id = :exam_id AND status = 'active' LIMIT 1";
        $examStmt = $pdo->prepare($examSql);
        $examStmt->execute([':exam_id' => $exam_id]);
        $exam = $examStmt->fetch();

        if (!$exam) {
            die("Error: Invalid or inactive exam.");
        }
        $total_marks = $exam['total_marks'];

        // 3. Fetch correct answers and marks for this exam
        $qSql = "SELECT id, correct_option, marks FROM questions WHERE exam_id = :exam_id";
        $qStmt = $pdo->prepare($qSql);
        $qStmt->execute([':exam_id' => $exam_id]);
        $questions = $qStmt->fetchAll();

        // 4. Begin Database Transaction
        $pdo->beginTransaction();

        // Array to hold data for student_answers table
        $answersData = [];

        foreach ($questions as $q) {
            $q_id = $q['id'];
            $marks = (int)$q['marks'];
            $correct_ans = $q['correct_option'];
            
            $selected_option = isset($submitted_answers[$q_id]) ? $submitted_answers[$q_id] : null;
            $is_correct = ($selected_option === $correct_ans) ? 1 : 0;
            
            if ($is_correct) {
                $score += $marks;
            }

            // Store for bulk insert later
            $answersData[] = [
                'question_id' => $q_id,
                'selected_option' => $selected_option,
                'is_correct' => $is_correct
            ];
        }

        // 5. Insert into exam_attempts
        // Note: Assuming your table has columns: student_id, exam_id, score
        $attemptSql = "INSERT INTO exam_attempts (student_id, exam_id, score) VALUES (:student_id, :exam_id, :score)";
        $attemptStmt = $pdo->prepare($attemptSql);
        $attemptStmt->execute([
            ':student_id' => $student_id,
            ':exam_id' => $exam_id,
            ':score' => $score
        ]);
        
        // Get the ID of the attempt we just inserted (useful if student_answers connects to attempt_id)
        $attempt_id = $pdo->lastInsertId();

        // 6. Insert individual answers into student_answers
        // Note: Assuming your table has columns: attempt_id, question_id, selected_option, is_correct
        $ansSql = "INSERT INTO student_answers (attempt_id, question_id, selected_option, is_correct) 
                   VALUES (:attempt_id, :question_id, :selected_option, :is_correct)";
        $ansStmt = $pdo->prepare($ansSql);

        foreach ($answersData as $data) {
            $ansStmt->execute([
                ':attempt_id' => $attempt_id,
                ':question_id' => $data['question_id'],
                ':selected_option' => $data['selected_option'],
                ':is_correct' => $data['is_correct']
            ]);
        }

        // 7. Commit the transaction
        $pdo->commit();
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
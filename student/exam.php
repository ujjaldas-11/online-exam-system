<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Error: No exam selected. Please return to the dashboard.");
}

$exam_id = (int)$_GET['id'];
$student_semester = $_SESSION['semester'];
$student_department = $_SESSION['department'];

try {
    $examSql = "SELECT id, title, duration_minutes 
            FROM exams 
            WHERE id = :id 
            AND semester = :semester 
            AND department = :department
            AND status = 'active' 
            LIMIT 1";
    
    $examStmt = $pdo->prepare($examSql);
    $examStmt->execute([
        ':id' => $exam_id,
        ':semester' => $student_semester,
        ':department' => $student_department
    ]);
    
    $exam = $examStmt->fetch();

    if (!$exam) {
        die("Error: Exam not found or you do not have permission to take this exam.");
    }

    $questionSql = "SELECT id, question_text, option_a, option_b, option_c, option_d, marks 
                    FROM questions 
                    WHERE exam_id = :exam_id 
                    ORDER BY id ASC";
                    
    $questionStmt = $pdo->prepare($questionSql);
    $questionStmt->execute([':exam_id' => $exam_id]);
    $questions = $questionStmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($exam['title']); ?> - Examify</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="exam-container">
        <header class="exam-header">
            <h1><?php echo htmlspecialchars($exam['title']); ?></h1>
            <div class="timer">Time Allowed: <?php echo htmlspecialchars($exam['duration_minutes']); ?> minutes</div>
        </header>

        <form action="result.php" method="POST" id="examForm">
            <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($exam['id']); ?>">

            <?php if (empty($questions)): ?>
                <p>No questions have been added to this exam yet.</p>
            <?php else: ?>
                
                <?php $questionNumber = 1; ?>
                <?php foreach ($questions as $q): ?>
                    <div class="question-block" style="margin-bottom: 30px; padding: 15px; border: 1px solid #ddd;">
                        <h3>
                            <?php echo $questionNumber . ". " . htmlspecialchars($q['question_text']); ?> 
                            <span style="font-size: 0.8em; color: #666;">[<?php echo htmlspecialchars($q['marks']); ?> Marks]</span>
                        </h3>
                        
                        <div class="options">
                            <label style="display: block; margin: 8px 0;">
                                <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="A" required>
                                A) <?php echo htmlspecialchars($q['option_a']); ?>
                            </label>
                            
                            <label style="display: block; margin: 8px 0;">
                                <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="B">
                                B) <?php echo htmlspecialchars($q['option_b']); ?>
                            </label>
                            
                            <label style="display: block; margin: 8px 0;">
                                <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="C">
                                C) <?php echo htmlspecialchars($q['option_c']); ?>
                            </label>
                            
                            <label style="display: block; margin: 8px 0;">
                                <input type="radio" name="answers[<?php echo $q['id']; ?>]" value="D">
                                D) <?php echo htmlspecialchars($q['option_d']); ?>
                            </label>
                        </div>
                    </div>
                    <?php $questionNumber++; ?>
                <?php endforeach; ?>

                <div class="form-actions">
                    <button type="submit" class="btn-submit" onclick="return confirm('Are you sure you want to submit your exam?');">
                        Submit Exam
                    </button>
                </div>

            <?php endif; ?>
        </form>
    </div>
</body>
</html>
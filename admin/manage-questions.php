<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

// Fetch only inactive exams (so you don't accidentally add questions to a running exam)
$exams = $pdo->query("SELECT id, title, semester FROM exams WHERE status = 'inactive' ORDER BY id DESC")->fetchAll();

// Handle Question Insertion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_question'])) {
    $exam_id = (int)$_POST['exam_id'];
    
    $sql = "INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks) 
            VALUES (:exam_id, :q_text, :opt_a, :opt_b, :opt_c, :opt_d, :correct, :marks)";
    $stmt = $pdo->prepare($sql);
    
    try {
        $stmt->execute([
            ':exam_id' => $exam_id,
            ':q_text'  => $_POST['question_text'],
            ':opt_a'   => $_POST['option_a'],
            ':opt_b'   => $_POST['option_b'],
            ':opt_c'   => $_POST['option_c'],
            ':opt_d'   => $_POST['option_d'],
            ':correct' => $_POST['correct_option'],
            ':marks'   => (int)$_POST['marks']
        ]);
        $message = "Question added successfully!";
    } catch (PDOException $e) {
        $message = "Error adding question: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Questions - Admin</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f4f7f6; }
        .card { max-width: 800px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        input[type="text"], input[type="number"], select, textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; font-size: 16px; border-radius: 4px; }
    </style>
</head>
<body>

    <div class="card">
        <h2>Insert New Question</h2>
        <?php if(isset($message)) echo "<p style='color:green;'><b>$message</b></p>"; ?>

        <form method="POST">
            <div class="form-group">
                <label>Select Exam (Only inactive exams shown):</label>
                <select name="exam_id" required>
                    <option value="">-- Choose Exam --</option>
                    <?php foreach ($exams as $ex): ?>
                        <option value="<?php echo $ex['id']; ?>">
                            <?php echo htmlspecialchars($ex['title']); ?> (Semester <?php echo $ex['semester']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Question Text:</label>
                <textarea name="question_text" rows="3" required></textarea>
            </div>

            <div class="form-group"><label>Option A:</label><input type="text" name="option_a" required></div>
            <div class="form-group"><label>Option B:</label><input type="text" name="option_b" required></div>
            <div class="form-group"><label>Option C:</label><input type="text" name="option_c" required></div>
            <div class="form-group"><label>Option D:</label><input type="text" name="option_d" required></div>

            <div class="form-group">
                <label>Correct Option:</label>
                <select name="correct_option" required>
                    <option value="A">A</option>
                    <option value="B">B</option>
                    <option value="C">C</option>
                    <option value="D">D</option>
                </select>
            </div>

            <div class="form-group">
                <label>Marks for this Question:</label>
                <input type="number" name="marks" required>
            </div>

            <button type="submit" name="add_question" class="btn">Add Question to Exam</button>
        </form>
    </div>

</body>
</html>
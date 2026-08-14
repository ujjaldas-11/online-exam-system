<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

$exams = $pdo->query("SELECT id, title, semester FROM exams WHERE status = 'inactive' ORDER BY id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bulk_questions'])) {
    $exam_id = (int)$_POST['exam_id'];
    $json_input = trim($_POST['json_data']);
    
    // Decode JSON string to PHP Array
    $questions = json_decode($json_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
        $error_message = "Invalid JSON format! Please check your syntax. Error: " . json_last_error_msg();
    } else {
        try {
            // Begin Transaction
            $pdo->beginTransaction();
            
            $sql = "INSERT INTO questions (exam_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks) 
                    VALUES (:exam_id, :q_text, :opt_a, :opt_b, :opt_c, :opt_d, :correct, :marks)";
            $stmt = $pdo->prepare($sql);
            
            $count = 0;
            foreach ($questions as $q) {
                // Ensure all required fields exist in the JSON object
                if (empty($q['question_text']) || empty($q['option_a']) || empty($q['option_b']) || empty($q['correct_option'])) {
                    throw new Exception("Missing required fields in one or more questions.");
                }

                $stmt->execute([
                    ':exam_id' => $exam_id,
                    ':q_text'  => trim(strip_tags($q['question_text'])),
                    ':opt_a'   => trim(strip_tags($q['option_a'])),
                    ':opt_b'   => trim(strip_tags($q['option_b'])),
                    ':opt_c'   => isset($q['option_c']) ? trim(strip_tags($q['option_c'])) : '',
                    ':opt_d'   => isset($q['option_d']) ? trim(strip_tags($q['option_d'])) : '',
                    ':correct' => strtoupper(trim(strip_tags($q['correct_option']))),
                    ':marks'   => isset($q['marks']) ? (int)$q['marks'] : 1
                ]);
                $count++;
            }
            
            // Commit Transaction if everything is successful
            $pdo->commit();
            $success_message = "$count Questions added successfully!";
            
        } catch (Exception $e) {
            // Rollback if any error occurs (prevents partial insertion)
            $pdo->rollBack();
            $error_message = "Error adding questions: " . $e->getMessage();
        }
    }
}

// Default JSON Template to guide the admin
$default_json = '[
  {
    "question_text": "What does DBMS stand for?",
    "option_a": "Database Management System",
    "option_b": "Data Basic Management System",
    "option_c": "Database Basic Management System",
    "option_d": "None of the above",
    "correct_option": "A",
    "marks": 1
  },
  {
    "question_text": "Which of the following is a NoSQL database?",
    "option_a": "MySQL",
    "option_b": "PostgreSQL",
    "option_c": "MongoDB",
    "option_d": "Oracle",
    "correct_option": "C",
    "marks": 2
  }
]';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Bulk Insert Questions - Admin</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f4f7f6; }
        .card { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { font-weight: bold; display: block; margin-bottom: 5px; }
        select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        textarea { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; font-family: monospace; font-size: 14px; background: #f8f9fa; }
        .btn { padding: 10px 20px; background: #28a745; color: white; border: none; cursor: pointer; font-size: 16px; border-radius: 4px; }
        .btn:hover { background: #218838; }
        .alert-success { color: #155724; background-color: #d4edda; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .alert-error { color: #721c24; background-color: #f8d7da; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
        .instructions { font-size: 13px; color: #555; background: #e9ecef; padding: 10px; border-radius: 4px; margin-bottom: 15px; }
    </style>
</head>
<body>

    <div class="card">
        <h2>Bulk Insert Questions (JSON)</h2>
        
        <?php if(isset($success_message)) echo "<div class='alert-success'><b>" . htmlspecialchars($success_message) . "</b></div>"; ?>
        <?php if(isset($error_message)) echo "<div class='alert-error'><b>" . htmlspecialchars($error_message) . "</b></div>"; ?>

        <div class="instructions">
            <strong>Instructions:</strong> Paste your questions in a valid JSON array format. 
            Ensure the keys are exactly: <code>question_text, option_a, option_b, option_c, option_d, correct_option, marks</code>. 
            The <code>correct_option</code> must be "A", "B", "C", or "D".
        </div>

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
                <label>JSON Data Array:</label>
                <textarea name="json_data" rows="20" required><?php echo htmlspecialchars($default_json); ?></textarea>
            </div>

            <button type="submit" name="add_bulk_questions" class="btn">Upload All Questions</button>
        </form>
    </div>

</body>
</html>
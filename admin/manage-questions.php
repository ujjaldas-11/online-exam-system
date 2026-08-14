<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

$subjects = $pdo->query("SELECT id, name, department, semester FROM subjects ORDER BY id DESC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bulk_questions'])) {
    $subject_id = (int)$_POST['subject_id'];
    $json_input = trim($_POST['json_data']);
    
    // Decode JSON string to PHP Array
    $questions = json_decode($json_input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
        $error_message = "Invalid JSON format! Please check your syntax. Error: " . json_last_error_msg();
    } else {
        try {
            // Begin Transaction
            $pdo->beginTransaction();
            
            $sql = "INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks) 
                    VALUES (:subject_id, :q_text, :opt_a, :opt_b, :opt_c, :opt_d, :correct, :marks)";
            $stmt = $pdo->prepare($sql);
            
            $count = 0;
            foreach ($questions as $q) {
                // Ensure all required fields exist in the JSON object
                if (empty($q['question_text']) || empty($q['option_a']) || empty($q['option_b']) || empty($q['correct_option'])) {
                    throw new Exception("Missing required fields in one or more questions.");
                }

                $stmt->execute([
                    ':subject_id' => $subject_id,
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
        .btn-blue { background: #007bff; font-size: 14px; padding: 6px 12px; margin-bottom: 10px; }
        .btn-blue:hover { background: #0056b3; }
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
                <label>Select Subject:</label>
                <select name="subject_id" id="subject_id" required>
                    <option value="">-- Choose Subject --</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?php echo $sub['id']; ?>">
                            <?php echo htmlspecialchars($sub['name']); ?> (<?php echo $sub['department']; ?>, Sem <?php echo $sub['semester']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label style="display: flex; justify-content: space-between; align-items: center;">
                    <span>JSON Data Array:</span>
                    <div>
                        <button type="button" class="btn btn-blue" id="copy-prompt-btn" disabled style="opacity: 0.6; cursor: not-allowed;">📋 Copy LLM Prompt</button>
                        <button type="button" class="btn btn-blue" id="paste-btn" style="background: #17a2b8;">📝 Paste JSON</button>
                    </div>
                </label>
                <textarea name="json_data" id="json_data" rows="20" required><?php echo htmlspecialchars($default_json); ?></textarea>
            </div>

            <button type="submit" name="add_bulk_questions" class="btn">Upload All Questions</button>
        </form>
    </div>

    <script>
        const subjectSelect = document.getElementById('subject_id');
        const copyPromptBtn = document.getElementById('copy-prompt-btn');

        subjectSelect.addEventListener('change', function() {
            if (this.value) {
                copyPromptBtn.disabled = false;
                copyPromptBtn.style.opacity = '1';
                copyPromptBtn.style.cursor = 'pointer';
            } else {
                copyPromptBtn.disabled = true;
                copyPromptBtn.style.opacity = '0.6';
                copyPromptBtn.style.cursor = 'not-allowed';
            }
        });

        copyPromptBtn.addEventListener('click', function() {
            if (subjectSelect.disabled || !subjectSelect.value) return;
            
            // Get selected subject text without the semester/department extra info if possible, or just use the whole text
            let subjectText = subjectSelect.options[subjectSelect.selectedIndex].text;
            // Extract just the name if it's formatted like "Name (Dept, Sem)"
            subjectText = subjectText.split('(')[0].trim();

            const prompt = `Please generate 10 multiple-choice questions about ${subjectText} suitable for university students. Return the output STRICTLY as a JSON array of objects with no markdown code blocks (\`\`\`) and no extra text. 

Each object must EXACTLY match this structure:
{
  "question_text": "Sample question?",
  "option_a": "Option 1",
  "option_b": "Option 2",
  "option_c": "Option 3",
  "option_d": "Option 4",
  "correct_option": "A",
  "marks": 1
}

Rules:
- "correct_option" must be exactly "A", "B", "C", or "D".
- "marks" must be an integer.
- Do NOT wrap the JSON in backticks or markdown formatting. Start directly with [ and end with ].`;

            navigator.clipboard.writeText(prompt).then(() => {
                const btn = document.getElementById('copy-prompt-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '✅ Copied!';
                btn.style.backgroundColor = '#28a745';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.backgroundColor = '';
                }, 2500);
            }).catch(err => {
                alert('Failed to copy prompt to clipboard. ' + err);
            });
        });

        document.getElementById('paste-btn').addEventListener('click', async function() {
            try {
                const text = await navigator.clipboard.readText();
                document.getElementById('json_data').value = text;
                const btn = document.getElementById('paste-btn');
                const originalText = btn.innerHTML;
                btn.innerHTML = '✅ Pasted!';
                btn.style.backgroundColor = '#28a745';
                setTimeout(() => {
                    btn.innerHTML = originalText;
                    btn.style.backgroundColor = '#17a2b8';
                }, 2000);
            } catch (err) {
                alert('Failed to read from clipboard. You may need to grant permission or paste manually. Error: ' + err);
            }
        });
    </script>
</body>
</html>
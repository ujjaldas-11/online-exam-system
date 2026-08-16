<?php
require_once 'admin-guard.php';
require_once '../config/database.php';

date_default_timezone_set('Asia/Kolkata');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    $title           = trim(strip_tags($_POST['title'] ?? ''));
    $subject_id      = (int)($_POST['subject_id'] ?? 0);
    $duration        = (int)($_POST['duration_minutes'] ?? 0);
    $total_marks     = (int)($_POST['total_marks'] ?? 0);
    $total_questions = (int)($_POST['total_questions_to_ask'] ?? 0);

    if (empty($title) || $subject_id <= 0 || $duration <= 0 || $total_marks <= 0 || $total_questions <= 0) {
        $message = "Please fill all fields correctly.";
        $message_type = 'error';
    } else {
        // Check available questions
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE subject_id = ?");
        $stmt->execute([$subject_id]);
        $available = (int)$stmt->fetchColumn();

        if ($available < $total_questions) {
            $message = "This subject only has $available questions. You cannot ask for $total_questions.";
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO exams 
                    (title, subject_id, duration_minutes, total_marks, total_questions_to_ask, status) 
                    VALUES (?, ?, ?, ?, ?, 'inactive')
                ");
                $stmt->execute([$title, $subject_id, $duration, $total_marks, $total_questions]);

                $message = "Exam created successfully! Go to 'Control Exams' to start it.";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = "Error: " . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Fetch subjects for the dropdown
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exam • Examify</title>
    <style>
        :root { --primary: #2563eb; --dark: #0f172a; --gray: #64748b; --light: #f8fafc; --border: #e2e8f0; --success: #16a34a; --error: #dc2626; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: var(--light); color: var(--dark); line-height: 1.5; }
        .container { max-width: 800px; margin: 0 auto; padding: 32px 20px; }
        h1 { font-size: 1.6rem; margin-bottom: 4px; }
        .subtitle { color: var(--gray); margin-bottom: 24px; }
        .card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-group { margin-bottom: 4px; }
        .form-group.full { grid-column: 1 / -1; }
        label { display: block; font-size: 0.9rem; font-weight: 500; margin-bottom: 5px; color: #334155; }
        input, select { width: 100%; padding: 10px 12px; border: 1px solid var(--border); border-radius: 8px; font-size: 0.95rem; background: white; }
        input:focus, select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1); }
        .btn { background: var(--primary); color: white; border: none; padding: 10px 18px; border-radius: 8px; font-weight: 600; font-size: 0.95rem; cursor: pointer; margin-top: 12px; }
        .btn:hover { background: #1d4ed8; }
        .alert { padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-weight: 500; }
        .alert.success { background: #dcfce7; color: var(--success); }
        .alert.error { background: #fee2e2; color: var(--error); }
        .btn-link { display: inline-block; background: #e2e8f0; color: #334155; text-decoration: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.9rem; margin-bottom: 20px;}
        .btn-link:hover { background: #cbd5e1; }
        @media (max-width: 700px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include 'admin-navbar.php'; ?>

<div class="container">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Exam Builder</h1>
            <p class="subtitle">Design and configure new exams</p>
        </div>
        <a href="control-exams.php" class="btn-link">⚙️ Go to Control Center</a>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $message_type ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2 style="margin-bottom: 16px;">Create New Exam</h2>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Exam Title</label>
                    <input type="text" name="title" required placeholder="e.g. Mid-Term Operating Systems">
                </div>

                <div class="form-group full">
                    <label>Subject</label>
                    <select name="subject_id" required>
                        <option value="">-- Choose Subject --</option>
                        <?php foreach ($subjects as $sub): ?>
                            <option value="<?= $sub['id'] ?>">
                                <?= htmlspecialchars($sub['name']) ?> 
                                (<?= htmlspecialchars($sub['department']) ?>, Sem <?= $sub['semester'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Duration (minutes)</label>
                    <input type="number" name="duration_minutes" min="1" required placeholder="e.g. 60">
                </div>

                <div class="form-group">
                    <label>Total Marks</label>
                    <input type="number" name="total_marks" id="calcTotalMarks">
                </div>
                
                <div class="form-group">
                    <label>Marks per Question</label>
                    <input type="number" id="calcMarksPerQ" min="1" value="1" required>
                </div>

                <div class="form-group">
                    <label>Questions to Ask (Auto-Calculated)</label>
                    <input type="number" name="total_questions_to_ask" id="calcQuestions" min="1" readonly  style="background-color: #f1f5f9;
                      font-weight: bold; color: var(--primary); cursor: not-allowed;">
                </div>

            </div>

            <button type="submit" name="create_exam" class="btn">Create Exam</button>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const questionsInput = document.getElementById('calcQuestions');
    const marksPerQInput = document.getElementById('calcMarksPerQ');
    const totalMarksInput = document.getElementById('calcTotalMarks');

    function claculateQuestionNumber(){
        const total_marks = parseInt(totalMarksInput.value) || 0;
        const marksQ = parseInt(marksPerQInput.value) || 0;

        questionsInput.value = total_marks / marksQ;
    }


    if (totalMarksInput && marksPerQInput) {
        totalMarksInput.addEventListener('input', claculateQuestionNumber);
        marksPerQInput.addEventListener('input', claculateQuestionNumber);
    }
});


</script>

</body>
</html>
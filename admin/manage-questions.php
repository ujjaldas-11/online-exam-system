<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

try {
    $subjects = $pdo->query('SELECT id, name, department, semester FROM subjects ORDER BY name ASC')->fetchAll();
} catch (PDOException $e) {
    log_error('Failed to fetch subjects in manage-questions', $e);
    $subjects = [];
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bulk_csv'])) {
    verify_csrf();

    $subject_id = int_param($_POST['subject_id'] ?? 0);
    $json_input = trim($_POST['json_data'] ?? '');
    $maxJsonSize = 2 * 1024 * 1024; // 2MB max payload

    if (empty($subject_id)) {
        $error_message = "Please select a subject.";
    } elseif (strlen($json_input) > $maxJsonSize) {
        $error_message = "JSON payload too large. Maximum 2MB allowed.";
    } else {
        $questions = json_decode($json_input, true, 10);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
            $error_message = "Invalid JSON format! Error: " . json_last_error_msg();
        } elseif (empty($questions)) {
            $error_message = "JSON array is empty. Please provide at least one question.";
        } elseif (count($questions) > 1000) {
            $error_message = "Too many questions. Maximum 1,000 questions per import.";
        } else {
            try {
                $pdo->beginTransaction();

                $sql = "INSERT INTO questions
                        (subject_id, question_text, option_a, option_b, option_c, option_d, correct_option)
                        VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);

                $count = 0;
                $allowedOptions = ['A', 'B', 'C', 'D'];

                foreach ($questions as $index => $q) {
                    if (!is_array($q)) {
                        throw new Exception("Invalid question entry at index " . ($index + 1) . ".");
                    }

                    $qText = clean_input($q['question_text'] ?? '');
                    $optA  = clean_input($q['option_a'] ?? '');
                    $optB  = clean_input($q['option_b'] ?? '');
                    $optC  = isset($q['option_c']) ? clean_input($q['option_c']) : '';
                    $optD  = isset($q['option_d']) ? clean_input($q['option_d']) : '';
                    $correctOpt = strtoupper(clean_input($q['correct_option'] ?? ''));

                    if ($qText === '' || $optA === '' || $optB === '' || $correctOpt === '') {
                        throw new Exception("Question #" . ($index + 1) . " is missing required fields (question_text, option_a, option_b, correct_option).");
                    }

                    if (!in_array($correctOpt, $allowedOptions, true)) {
                        throw new Exception("Question #" . ($index + 1) . " has invalid correct_option '$correctOpt'. Must be A, B, C, or D.");
                    }

                    $stmt->execute([
                        $subject_id,
                        $qText,
                        $optA,
                        $optB,
                        $optC,
                        $optD,
                        $correctOpt,
                    ]);
                    $count++;
                }

                $pdo->commit();
                $success_message = "$count questions added successfully!";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_message = "Error: " . $e->getMessage();
            }
    $csv_text = trim($_POST['csv_text'] ?? '');
    $has_file = isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK;

    if (empty($subject_id)) {
        $error_message = 'Please select a subject.';
    } elseif (!$has_file && empty($csv_text)) {
        $error_message = 'Please either upload a CSV file OR paste CSV content.';
    } else {
        try {
            $pdo->beginTransaction();

            $sql = 'INSERT INTO questions
                    (subject_id, question_text, unit_number, option_a, option_b, option_c, option_d, correct_option)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
            $stmt = $pdo->prepare($sql);


            if ($has_file) {
                $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
            } else {
                $handle = fopen('php://memory', 'r+');
                fwrite($handle, $csv_text);
                rewind($handle);
            }

            if ($handle !== FALSE) {
                $count = 0;
                $is_header = true;

                while (($data = fgetcsv($handle, 1000, ',')) !== FALSE) {
                    if (empty(array_filter($data)))
                        continue;

                    if ($is_header) {
                        if (strtolower(trim($data[0])) === 'question text' || !is_numeric(trim($data[1] ?? ''))) {
                            $is_header = false;
                            continue;
                        }
                        $is_header = false;  // It wasn't a header row, process it as data
                    }

                    $q_text = $data[0] ?? '';
                    $u_num = $data[1] ?? '';
                    $opt_a = $data[2] ?? '';
                    $opt_b = $data[3] ?? '';
                    $opt_c = $data[4] ?? '';
                    $opt_d = $data[5] ?? '';
                    $correct = $data[6] ?? '';

                    if (empty($q_text) || empty($u_num) || empty($opt_a) || empty($opt_b) || empty($correct)) {
                        throw new Exception('Row ' . ($count + 1) . ' is missing required fields. Transaction aborted.');
                    }

                    $stmt->execute([
                        $subject_id,
                        clean_input($q_text),
                        clean_input($u_num),
                        clean_input($opt_a),
                        clean_input($opt_b),
                        clean_input($opt_c),
                        clean_input($opt_d),
                        strtoupper(clean_input($correct))
                    ]);
                    $count++;
                }
                fclose($handle);

                if ($count === 0) {
                    throw new Exception('No valid questions found in the CSV data.');
                }

                $pdo->commit();
                $success_message = "$count questions imported successfully!";
            } else {
                throw new Exception('Failed to process the CSV data.');
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error_message = 'Error: ' . $e->getMessage();
        }
    }
}
$page_title = 'Manage Questions • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>Manage Questions</h1>
            <p>Bulk upload multiple-choice questions into the curriculum question bank</p>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?= e($success_message) ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-error"><?= e($error_message) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">Bulk Insert Questions (CSV)</div>

        <div class="alert alert-info" style="text-align: left; margin-bottom: 20px;">
            <strong>Instructions:</strong> Upload a CSV file OR paste comma-separated text.<br>
            • 7 columns required: <code>Question Text, Unit Number, Option A, Option B, Option C, Option D, Correct Option</code><br>
            • <code>Correct Option</code> must be A, B, C, or D.
        </div>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Select Subject <span style="color:red;">*</span></label>
                <select name="subject_id" id="subject_id" required>
                    <option value="">-- Choose Target Subject --</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>">
                            <?= e($sub['name']) ?> (<?= e($sub['department']) ?>, Sem <?= e((string) $sub['semester']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Option 1: Upload .CSV File</label>
                <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control">
            </div>

            <div style="text-align: center; margin: 15px 0; color: #64748b; font-weight: bold;">OR</div>

            <div class="form-group">
                <label>Option 2: Paste CSV Text</label>
                <textarea name="csv_text" id="csv_text" rows="8" class="form-control"
                placeholder='What is a CPU?,1,Central Processing Unit,Computer Power Unit,Core Process Utility,None,A&#10;Is HTML a programming language?,2,Yes,No,,,B'></textarea>
            </div>

            <button type="submit" name="add_bulk_csv" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; margin-top: 15px;">
                <span class="material-symbols-outlined icon-sm">upload</span> Upload Questions
            </button>
        </form>
    </div>

</div>

<script>
    const subjectSelect = document.getElementById('subject_id');
    const copyPromptBtn = document.getElementById('copy-prompt-btn');
    const pasteBtn = document.getElementById('paste-btn');
    const jsonTextarea = document.getElementById('json_data');

    subjectSelect.addEventListener('change', function () {
        copyPromptBtn.disabled = !this.value;
    });

    copyPromptBtn.addEventListener('click', function () {
        if (!subjectSelect.value) return;

        let subjectText = subjectSelect.options[subjectSelect.selectedIndex].text;
        subjectText = subjectText.split('(')[0].trim();

        const prompt = `Please generate 10 multiple-choice questions about ${subjectText} suitable for university students. Return the output STRICTLY as a JSON array of objects with no markdown code blocks and no extra text.

Each object must EXACTLY match this structure:
{
    "question_text": "Sample question?",
    "option_a": "Option 1",
    "option_b": "Option 2",
    "option_c": "Option 3",
    "option_d": "Option 4",
    "correct_option": "A"
}

Rules:
- "correct_option" must be exactly "A", "B", "C", or "D".
- Do NOT wrap the JSON in backticks or markdown. Start directly with [ and end with ].`;

        navigator.clipboard.writeText(prompt).then(() => {
            const original = copyPromptBtn.innerHTML;
            copyPromptBtn.innerHTML = '<span class="material-symbols-outlined icon-xs">check</span> Copied Prompt!';
            setTimeout(() => copyPromptBtn.innerHTML = original, 2000);
        });
    });

    pasteBtn.addEventListener('click', async function () {
        try {
            const text = await navigator.clipboard.readText();
            jsonTextarea.value = text;
            const original = pasteBtn.innerHTML;
            pasteBtn.innerHTML = '<span class="material-symbols-outlined icon-xs">check</span> Pasted!';
            setTimeout(() => pasteBtn.innerHTML = original, 2000);
        } catch (err) {
            alert('Could not read clipboard automatically. Please press Ctrl+V to paste manually.');
        }
    });
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>

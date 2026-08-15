<?php
require_once 'admin-guard.php';
require_once '../config/database.php';

$subjects = $pdo->query("SELECT id, name, department, semester FROM subjects ORDER BY name ASC")->fetchAll();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bulk_questions'])) {
    $subject_id = (int)$_POST['subject_id'];
    $json_input = trim($_POST['json_data']);

    $questions = json_decode($json_input, true);

    if (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
        $error_message = "Invalid JSON format! Error: " . json_last_error_msg();
    } else {
        try {
            $pdo->beginTransaction();

            $sql = "INSERT INTO questions 
                    (subject_id, question_text, option_a, option_b, option_c, option_d, correct_option) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);

            $count = 0;
            foreach ($questions as $q) {
                if (empty($q['question_text']) || empty($q['option_a']) || empty($q['option_b']) || empty($q['correct_option'])) {
                    throw new Exception("Missing required fields in one or more questions.");
                }

                $stmt->execute([
                    $subject_id,
                    trim(strip_tags($q['question_text'])),
                    trim(strip_tags($q['option_a'])),
                    trim(strip_tags($q['option_b'])),
                    isset($q['option_c']) ? trim(strip_tags($q['option_c'])) : '',
                    isset($q['option_d']) ? trim(strip_tags($q['option_d'])) : '',
                    strtoupper(trim(strip_tags($q['correct_option'])))
                ]);
                $count++;
            }

            $pdo->commit();
            $success_message = "$count questions added successfully!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = "Error: " . $e->getMessage();
        }
    }
}

$default_json = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Questions • Examify</title>
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --success: #16a34a;
            --error: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.5;
        }
        /* Layout */
        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 32px 20px;
        }
        h1 { font-size: 1.6rem; margin-bottom: 4px; }
        .subtitle { color: var(--gray); margin-bottom: 24px; }

        /* Alerts */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        .alert.success { background: #dcfce7; color: var(--success); }
        .alert.error { background: #fee2e2; color: var(--error); }

        /* Card */
        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
        }
        .card h2 {
            font-size: 1.15rem;
            margin-bottom: 16px;
        }

        /* Form */
        .form-group { margin-bottom: 18px; }
        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: #334155;
        }
        select, textarea {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
        }
        textarea {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.875rem;
            line-height: 1.5;
            resize: vertical;
            min-height: 320px;
        }
        select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn-row {
            display: flex;
            gap: 10px;
            margin-bottom: 8px;
            flex-wrap: wrap;
        }
        .btn-secondary {
            background: #e2e8f0;
            color: #334155;
            border: none;
            padding: 7px 14px;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
        }
        .btn-secondary:hover { background: #cbd5e1; }
        .btn-secondary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn {
            background: var(--primary);
            color: white;
            border: none;
            padding: 11px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn:hover { background: #1d4ed8; }

        .instructions {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 12px 14px;
            font-size: 0.875rem;
            color: #475569;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        .instructions code {
            background: #e2e8f0;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 0.8rem;
        }

    </style>
</head>
<body>

<?php include 'admin-navbar.php' ?>

<div class="container">
    <h1>Manage Questions</h1>
    <p class="subtitle">Bulk upload questions using JSON</p>

    <?php if ($success_message): ?>
        <div class="alert success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Bulk Insert Questions</h2>

        <div class="instructions">
            <strong>Instructions:</strong> Paste a valid JSON array.  
            Required keys: <code>question_text</code>, <code>option_a</code>, <code>option_b</code>, <code>correct_option</code>.  
            Optional: <code>option_c</code>, <code>option_d</code>.  
            <code>correct_option</code> must be <code>A</code>, <code>B</code>, <code>C</code> or <code>D</code>.
        </div>

        <form method="POST">
            <div class="form-group">
                <label>Select Subject</label>
                <select name="subject_id" id="subject_id" required>
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
                <label>JSON Data</label>
                <div class="btn-row">
                    <button type="button" class="btn-secondary" id="copy-prompt-btn" disabled>
                         Copy LLM Prompt
                    </button>
                    <button type="button" class="btn-secondary" id="paste-btn">
                        📝 Paste from Clipboard
                    </button>
                </div>
                <textarea name="json_data" id="json_data" required><?= htmlspecialchars($default_json) ?></textarea>
            </div>

            <button type="submit" name="add_bulk_questions" class="btn">Upload All Questions</button>
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
            copyPromptBtn.innerHTML = '✅ Copied!';
            setTimeout(() => copyPromptBtn.innerHTML = original, 2000);
        });
    });

    pasteBtn.addEventListener('click', async function () {
        try {
            const text = await navigator.clipboard.readText();
            jsonTextarea.value = text;
            const original = pasteBtn.innerHTML;
            pasteBtn.innerHTML = '✅ Pasted!';
            setTimeout(() => pasteBtn.innerHTML = original, 2000);
        } catch (err) {
            alert('Could not read clipboard. Please paste manually.');
        }
    });
</script>

</body>
</html>
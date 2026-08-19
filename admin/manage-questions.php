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
            --primary-soft: #eff6ff;
            --text: #0f172a;
            --text-secondary: #64748b;
            --border: #e2e8f0;
            --bg: #f8fafc;
            --card: #ffffff;
            --success: #16a34a;
            --error: #dc2626;
            --shadow: 0 1px 3px rgba(0,0,0,0.04), 0 1px 2px rgba(0,0,0,0.06);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background: var(--bg);
            color: var(--text);
            line-height: 1.5;
            -webkit-font-smoothing: antialiased;
        }

        .container {
            max-width: 920px;
            margin: 0 auto;
            padding: 36px 24px 60px;
        }

        /* Header */
        .page-header {
            margin-bottom: 32px;
        }
        .page-header h1 {
            font-size: 1.7rem;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .subtitle {
            color: var(--text-secondary);
            margin-top: 4px;
            font-size: 0.95rem;
        }

        /* Alerts */
        .alert {
            padding: 13px 16px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .alert.success {
            background: #dcfce7;
            color: var(--success);
            border: 1px solid #bbf7d0;
        }
        .alert.error {
            background: #fee2e2;
            color: var(--error);
            border: 1px solid #fecaca;
        }

        /* Card */
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 28px;
            box-shadow: var(--shadow);
        }
        .card h2 {
            font-size: 1.15rem;
            font-weight: 600;
            margin-bottom: 18px;
        }

        /* Instructions */
        .instructions {
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            font-size: 0.875rem;
            color: #475569;
            margin-bottom: 24px;
            line-height: 1.55;
        }
        .instructions strong {
            color: var(--text);
        }
        .instructions code {
            background: #e2e8f0;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
        }

        /* Form */
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            margin-bottom: 6px;
            color: #334155;
        }
        select, textarea {
            width: 100%;
            padding: 11px 13px;
            border: 1px solid var(--border);
            border-radius: 9px;
            font-size: 0.95rem;
            background: white;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }
        textarea {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.875rem;
            line-height: 1.55;
            resize: vertical;
            min-height: 340px;
        }

        /* Buttons row */
        .btn-row {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            flex-wrap: wrap;
        }

        .btn-secondary {
            background: white;
            color: #334155;
            border: 1px solid var(--border);
            padding: 8px 14px;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-secondary:hover:not(:disabled) {
            background: #f8fafc;
            border-color: #cbd5e1;
        }
        .btn-secondary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--primary);
            color: white;
            border: none;
            padding: 12px 22px;
            border-radius: 9px;
            font-weight: 600;
            font-size: 0.95rem;
            cursor: pointer;
            transition: background 0.15s;
            margin-top: 6px;
        }
        .btn:hover {
            background: #1d4ed8;
        }

        @media (max-width: 640px) {
            .container {
                padding: 24px 16px 40px;
            }
        }
    </style>
</head>
<body>

<?php include '../components/navbar.php'; ?>

<div class="container">

    <div class="page-header">
        <div>
            <h1>Manage Questions</h1>
            <p class="subtitle">Bulk upload questions using JSON</p>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert error"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="card">
        <h2>Bulk Insert Questions</h2>

        <div class="instructions">
            <strong>Instructions:</strong> Paste a valid JSON array.<br>
            Required keys: <code>question_text</code>, <code>option_a</code>, <code>option_b</code>, <code>correct_option</code>.<br>
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
                        📋 Copy LLM Prompt
                    </button>
                    <button type="button" class="btn-secondary" id="paste-btn">
                        📝 Paste from Clipboard
                    </button>
                </div>
                <textarea name="json_data" id="json_data" required placeholder='[{"question_text":"...","option_a":"...","option_b":"...","correct_option":"A"}]'><?= htmlspecialchars($default_json) ?></textarea>
            </div>

            <button type="submit" name="add_bulk_questions" class="btn">
                Upload All Questions
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
<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

try {
    $subjects = $pdo->query("SELECT id, name, department, semester FROM subjects ORDER BY name ASC")->fetchAll();

    //registration requests
    $pending_registration_requests_count = $pdo->query("SELECT COUNT(*) FROM registration_request WHERE status = 'pending'")->fetchColumn();

    //fetch notification count
    $pending_requests_count = $pdo->query("SELECT COUNT(*) FROM profile_requests WHERE status = 'pending'")->fetchColumn();
} catch (PDOException $e) {
    log_error("Failed to fetch subjects in manage-questions", $e);
    $subjects = [];
}

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_bulk_questions'])) {
    verify_csrf();

    $subject_id = int_param($_POST['subject_id'] ?? 0);
    $json_input = trim($_POST['json_data'] ?? '');

    $questions = json_decode($json_input, true);

    if (empty($subject_id)) {
        $error_message = "Please select a subject.";
    } elseif (json_last_error() !== JSON_ERROR_NONE || !is_array($questions)) {
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
                    clean_input($q['question_text']),
                    clean_input($q['option_a']),
                    clean_input($q['option_b']),
                    isset($q['option_c']) ? clean_input($q['option_c']) : '',
                    isset($q['option_d']) ? clean_input($q['option_d']) : '',
                    strtoupper(clean_input($q['correct_option'])),
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
        <div class="card-title">Bulk Insert Questions (JSON)</div>

        <div class="alert alert-info" style="text-align: left; margin-bottom: 20px;">
            <strong>Instructions:</strong> Paste a valid JSON array.<br>
            • Required keys: <code>question_text</code>, <code>option_a</code>, <code>option_b</code>, <code>correct_option</code>.<br>
            • Optional keys: <code>option_c</code>, <code>option_d</code>.<br>
            • <code>correct_option</code> must be <code>A</code>, <code>B</code>, <code>C</code>, or <code>D</code>.
        </div>

        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Select Subject</label>
                <select name="subject_id" id="subject_id" required>
                    <option value="">-- Choose Target Subject --</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>">
                            <?= e($sub['name']) ?> (<?= e($sub['department']) ?>, Sem <?= e((string)$sub['semester']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>JSON Data Array</label>
                <div style="display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" id="copy-prompt-btn" disabled style="display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined icon-xs">content_copy</span> Copy LLM Prompt
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="paste-btn" style="display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined icon-xs">content_paste</span> Paste from Clipboard
                    </button>
                </div>
                <textarea name="json_data" id="json_data" required rows="10"
                    placeholder='[{"question_text":"What is an operating system?","option_a":"System software","option_b":"Application","option_c":"Hardware","option_d":"Malware","correct_option":"A"}]'></textarea>
            </div>

            <button type="submit" name="add_bulk_questions" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">upload</span> Upload All Questions
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

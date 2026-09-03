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
    $csv_text = trim($_POST['csv_text'] ?? '');
    $maxFileSize = 5 * 1024 * 1024; // 5MB max
    $allowedExtensions = ['csv', 'txt'];
    $allowedMimes = [
        'text/plain',
        'text/csv',
        'application/csv',
        'text/x-csv',
        'application/vnd.ms-excel',
        'text/comma-separated-values',
        'application/octet-stream',
    ];

    $has_file = !empty($_FILES['csv_file']['name']);

    if (empty($subject_id)) {
        $error_message = 'Please select a subject.';
    } elseif (!$has_file && empty($csv_text)) {
        $error_message = 'Please either upload a CSV file OR paste CSV content.';
    } elseif ($has_file && $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'File upload error code: ' . $_FILES['csv_file']['error'];
    } elseif ($has_file && $_FILES['csv_file']['size'] > $maxFileSize) {
        $error_message = 'Uploaded file too large. Maximum size allowed is 5MB.';
    } elseif (!$has_file && strlen($csv_text) > $maxFileSize) {
        $error_message = 'Pasted CSV content too large. Maximum size allowed is 5MB.';
    } else {
        $handle = false;

        if ($has_file) {
            $tmpPath = $_FILES['csv_file']['tmp_name'];
            $fileExt = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));

            if (!in_array($fileExt, $allowedExtensions, true)) {
                $error_message = 'Invalid file extension. Only .csv and .txt files are allowed.';
            } elseif (!is_uploaded_file($tmpPath)) {
                $error_message = 'Uploaded file verification failed.';
            } else {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpPath);
                finfo_close($finfo);

                if (!in_array($mimeType, $allowedMimes, true)) {
                    $error_message = "Invalid file type ($mimeType). Only CSV files are allowed.";
                } else {
                    $handle = fopen($tmpPath, 'r');
                }
            }
        } else {
            $handle = fopen('php://memory', 'r+');
            fwrite($handle, $csv_text);
            rewind($handle);
        }

        if ($handle !== false) {
            try {
                $pdo->beginTransaction();

                $creator_id = $_SESSION['admin_id'] ?? null;
                $sql = 'INSERT INTO questions
                        (subject_id, question_text, unit_number, option_a, option_b, option_c, option_d, correct_option, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $stmt = $pdo->prepare($sql);

                $count = 0;
                $is_header = true;
                $allowedOptions = ['A', 'B', 'C', 'D'];

                while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                    if (empty(array_filter($data, fn($v) => trim((string)$v) !== ''))) {
                        continue;
                    }

                    if ($is_header) {
                        $col0 = strtolower(trim((string)($data[0] ?? '')));
                        $col1 = trim((string)($data[1] ?? ''));
                        if (str_contains($col0, 'question') || !is_numeric($col1)) {
                            $is_header = false;
                            continue;
                        }
                        $is_header = false;
                    }

                    if ($count >= 1000) {
                        throw new Exception('Too many questions. Maximum 1,000 questions per import.');
                    }

                    $q_text  = clean_input($data[0] ?? '');
                    $u_num   = clean_input($data[1] ?? '');
                    $opt_a   = clean_input($data[2] ?? '');
                    $opt_b   = clean_input($data[3] ?? '');
                    $opt_c   = isset($data[4]) ? clean_input($data[4]) : '';
                    $opt_d   = isset($data[5]) ? clean_input($data[5]) : '';
                    $correct = strtoupper(clean_input($data[6] ?? ''));

                    if (empty($q_text) || empty($u_num) || empty($opt_a) || empty($opt_b) || empty($correct)) {
                        throw new Exception('Row ' . ($count + 1) . ' is missing required fields (Question Text, Unit Number, Option A, Option B, Correct Option). Transaction aborted.');
                    }

                    if (!in_array($correct, $allowedOptions, true)) {
                        throw new Exception("Row " . ($count + 1) . " has invalid Correct Option '$correct'. Must be A, B, C, or D.");
                    }

                    $stmt->execute([
                        $subject_id,
                        $q_text,
                        $u_num,
                        $opt_a,
                        $opt_b,
                        $opt_c,
                        $opt_d,
                        $correct,
                        $creator_id
                    ]);
                    $count++;
                }

                fclose($handle);

                if ($count === 0) {
                    throw new Exception('No valid questions found in the CSV data.');
                }

                $pdo->commit();
                log_admin_action($pdo, 'import_questions', 'questions', $subject_id, "Imported $count questions into subject #$subject_id");
                $success_message = "$count questions imported successfully!";
            } catch (Exception $e) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error_message = 'Error: ' . $e->getMessage();
            }
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
                <div style="display: flex; gap: 8px; margin-bottom: 8px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-secondary btn-sm" id="copy-prompt-btn" disabled style="display: inline-flex; align-items: center; gap: 4px; cursor: not-allowed;">
                        <span class="material-symbols-outlined icon-xs">content_copy</span> Copy LLM Prompt
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="paste-btn" style="display: inline-flex; align-items: center; gap: 4px; cursor: not-allowed;">
                        <span class="material-symbols-outlined icon-xs">content_paste</span> Paste from Clipboard
                    </button>
                </div>
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
    const csvTextarea = document.getElementById('csv_text');

    if (subjectSelect && copyPromptBtn) {
        subjectSelect.addEventListener('change', function () {
            copyPromptBtn.disabled = !this.value;
        });

        copyPromptBtn.addEventListener('click', function () {
            if (!subjectSelect.value) return;

            let subjectText = subjectSelect.options[subjectSelect.selectedIndex].text;
            subjectText = subjectText.split('(')[0].trim();

            const prompt = `Please generate 10 multiple-choice questions about ${subjectText} suitable for university students. Return the output STRICTLY as CSV text with no markdown code blocks and no extra text.

Columns format:
Question Text,Unit Number,Option A,Option B,Option C,Option D,Correct Option

Example:
What is an operating system?,1,System software,Application,Hardware,Malware,A

Rules:
- "Unit Number" must be an integer (e.g. 1, 2, 3, 4).
- "Correct Option" must be exactly "A", "B", "C", or "D".
- Do NOT wrap the output in backticks or markdown code blocks.`;

            navigator.clipboard.writeText(prompt).then(() => {
                const original = copyPromptBtn.innerHTML;
                copyPromptBtn.innerHTML = '<span class="material-symbols-outlined icon-xs">check</span> Copied Prompt!';
                setTimeout(() => copyPromptBtn.innerHTML = original, 2000);
            });
        });
    }

    if (pasteBtn && csvTextarea) {
        pasteBtn.addEventListener('click', async function () {
            try {
                const text = await navigator.clipboard.readText();
                csvTextarea.value = text;
                const original = pasteBtn.innerHTML;
                pasteBtn.innerHTML = '<span class="material-symbols-outlined icon-xs">check</span> Pasted!';
                setTimeout(() => pasteBtn.innerHTML = original, 2000);
            } catch (err) {
                alert('Could not read clipboard automatically. Please press Ctrl+V to paste manually.');
            }
        });
    }
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>


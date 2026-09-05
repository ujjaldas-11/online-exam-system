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

                while (($data = fgetcsv($handle, 4000, ',')) !== false) {
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
                    $u_num   = (int) clean_input($data[1] ?? '1');
                    $opt_a   = trim($data[2] ?? '');
                    $opt_b   = trim($data[3] ?? '');
                    $opt_c   = isset($data[4]) ? trim($data[4]) : null;
                    $opt_d   = isset($data[5]) ? trim($data[5]) : null;
                    $correct = strtoupper(clean_input($data[6] ?? ''));

                    if (empty($q_text) || empty($opt_a) || empty($opt_b) || empty($correct)) {
                        throw new Exception('Row ' . ($count + 1) . ' is missing required fields (Question Text, Option A, Option B, Correct Option). Transaction aborted.');
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
                        $opt_c ?: null,
                        $opt_d ?: null,
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
        <a href="view-questions.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">visibility</span> View Question Bank
        </a>
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
                    <button type="button" class="btn btn-secondary btn-sm" id="copy-prompt-btn" style="display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined icon-xs">content_copy</span> Copy LLM Prompt
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" id="paste-btn" style="display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined icon-xs">content_paste</span> Paste from Clipboard
                    </button>
                </div>
                <textarea name="csv_text" id="csv_text" rows="8" class="form-control"
                placeholder='What is an operating system?,1,System software,Application software,Hardware component,Malware,A&#10;Is HTML a programming language?,2,Yes,No,,,B'></textarea>
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

    if (copyPromptBtn) {
        copyPromptBtn.addEventListener('click', function () {
            let subjectTitle = '[INSERT SUBJECT NAME, e.g. Operating Systems / Data Structures]';
            let targetAudience = 'Undergraduate university students';

            if (subjectSelect && subjectSelect.value) {
                const selectedText = subjectSelect.options[subjectSelect.selectedIndex].text.trim();
                const match = selectedText.match(/^(.*?)\s*\((.*?)\)$/);
                if (match) {
                    subjectTitle = match[1].trim();
                    targetAudience = match[2].trim();
                } else {
                    subjectTitle = selectedText;
                }
            }

            const prompt = `Act as an expert university professor and exam controller.
Generate 15 high-quality multiple-choice questions (MCQs) for the following course:
Course: ${subjectTitle}
Target Audience: ${targetAudience}

STRICT OUTPUT FORMAT RULES:
1. Return ONLY the raw CSV text. Do NOT wrap output in markdown code blocks (\`\`\`csv or \`\`\`). Zero commentary, notes, or conversational filler.
2. The very first line MUST be this exact header row:
Question Text,Unit Number,Option A,Option B,Option C,Option D,Correct Option

3. Field Constraints:
   - Question Text: Clear, unambiguous question testing conceptual depth and practical application.
   - Unit Number: An integer representing syllabus unit (1, 2, 3, 4, or 5). Distribute questions evenly across units.
   - Option A, Option B, Option C, Option D: Distinct, plausible options. Do NOT prefix with "A)", "B.", "1.", or labels.
   - Correct Option: Exactly one single uppercase letter: "A", "B", "C", or "D".

4. CRITICAL CSV ESCAPING RULES:
   - Any field that contains a comma (,), quotation mark ("), or semicolon MUST be enclosed inside standard double quotes (e.g. "Which of the following, if any, is...").
   - Any quotation mark within a field must be escaped as two double quotes (e.g. "Use the ""volatile"" keyword").
   - Each question must occupy exactly one single line.

VALID OUTPUT EXAMPLE:
Question Text,Unit Number,Option A,Option B,Option C,Option D,Correct Option
"Which data structure operates on a Last-In, First-Out (LIFO) basis?",1,Queue,Stack,Array,Binary Tree,B
"What is the average time complexity of searching in a balanced Binary Search Tree?",2,O(1),O(n),O(log n),O(n^2),C
"In relational databases, which SQL clause is used to filter aggregated group results?",3,WHERE,HAVING,ORDER BY,GROUP BY,B`;

            const copySuccess = () => {
                const originalHtml = copyPromptBtn.innerHTML;
                copyPromptBtn.innerHTML = '<span class="material-symbols-outlined icon-xs">check</span> Copied Prompt!';
                setTimeout(() => { copyPromptBtn.innerHTML = originalHtml; }, 2500);
            };

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(prompt).then(copySuccess).catch(() => fallbackCopy(prompt));
            } else {
                fallbackCopy(prompt);
            }

            function fallbackCopy(text) {
                const ta = document.createElement('textarea');
                ta.value = text;
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.focus();
                ta.select();
                try {
                    document.execCommand('copy');
                    copySuccess();
                } catch (e) {
                    alert('Could not copy prompt to clipboard. Please select and copy manually.');
                }
                document.body.removeChild(ta);
            }
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

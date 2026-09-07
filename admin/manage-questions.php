<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/CsvService.php';

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
    $has_file = !empty($_FILES['csv_file']['name']);

    if (empty($subject_id)) {
        $error_message = 'Please select a subject.';
    } elseif (!can_admin_manage_subject($pdo, $subject_id)) {
        $error_message = 'Access Denied: You do not have permission to manage questions for this subject.';
    } elseif (!$has_file && empty($csv_text)) {
        $error_message = 'Please either upload a CSV file OR paste CSV content.';
    } elseif (!$has_file && strlen($csv_text) > $maxFileSize) {
        $error_message = 'Pasted CSV content too large. Maximum size allowed is 5MB.';
    } else {
        $handle = false;

        if ($has_file) {
            $fileErr = CsvService::validateUploadedCsv($_FILES['csv_file'], $maxFileSize);
            if ($fileErr !== null) {
                $error_message = $fileErr;
            } else {
                $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
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
                        (subject_id, question_text, unit_number, question_type, option_a, option_b, option_c, option_d, correct_option, created_by)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
                $stmt = $pdo->prepare($sql);

                $count = 0;
                $is_header = true;
                $allowedOptions = ['A', 'B', 'C', 'D'];
                $validTypes = ['single', 'multiple', 'case_study', 'assertion_reason', 'matching'];

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

                    // Auto-heal and normalize raw CSV data (e.g. unquoted commas in assertion-reason/matching options)
                    $data = CsvService::normalizeQuestionRow($data);

                    $q_text  = clean_input($data[0] ?? '');
                    // Normalize literal \n or \r\n to real newlines so questions render with proper line breaks
                    $q_text  = str_replace(['\r\n', '\r', '\n'], "\n", $q_text);
                    $u_num   = (int) clean_input($data[1] ?? '1');
                    $opt_a   = trim($data[2] ?? '');
                    $opt_b   = trim($data[3] ?? '');
                    $opt_c   = isset($data[4]) ? trim($data[4]) : null;
                    $opt_d   = isset($data[5]) ? trim($data[5]) : null;
                    $rawCorrect = strtoupper(clean_input($data[6] ?? ''));

                    if (empty($q_text) || empty($opt_a) || empty($opt_b) || empty($rawCorrect)) {
                        throw new Exception('Row ' . ($count + 1) . ' is missing required fields (Question Text, Option A, Option B, Correct Option). Transaction aborted.');
                    }

                    // Parse correct options (single e.g. "A", or multi e.g. "A,C,D" or "ACD")
                    $letters = [];
                    if (str_contains($rawCorrect, ',')) {
                        $tokens = array_filter(array_map('trim', explode(',', $rawCorrect)));
                        foreach ($tokens as $tok) {
                            if (in_array($tok, $allowedOptions, true)) {
                                if (!in_array($tok, $letters, true)) {
                                    $letters[] = $tok;
                                }
                            } else {
                                throw new Exception("Row " . ($count + 1) . " has invalid Correct Option '$rawCorrect'. Must be A, B, C, or D.");
                            }
                        }
                    } else {
                        $len = strlen($rawCorrect);
                        for ($i = 0; $i < $len; $i++) {
                            $ch = $rawCorrect[$i];
                            if (in_array($ch, $allowedOptions, true)) {
                                if (!in_array($ch, $letters, true)) {
                                    $letters[] = $ch;
                                }
                            } elseif ($ch !== ' ' && $ch !== ';') {
                                throw new Exception("Row " . ($count + 1) . " has invalid Correct Option '$rawCorrect'. Must be A, B, C, or D.");
                            }
                        }
                    }

                    if (empty($letters)) {
                        throw new Exception("Row " . ($count + 1) . " has invalid Correct Option '$rawCorrect'. Must be A, B, C, or D.");
                    }
                    sort($letters);
                    $correct = implode(',', $letters);

                    // Parse optional 8th column: question_type
                    $rawType = strtolower(trim((string)($data[7] ?? '')));
                    if (!empty($rawType) && in_array($rawType, $validTypes, true)) {
                        $q_type = $rawType;
                    } else {
                        $q_type = (count($letters) > 1) ? 'multiple' : 'single';
                    }

                    $stmt->execute([
                        $subject_id,
                        $q_text,
                        $u_num,
                        $q_type,
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
            • <code>Correct Option</code> must be A, B, C, or D. For <em>Multiple-Answer</em> questions, list multiple letters separated by commas (e.g. <code>A,C,D</code> or <code>ACD</code>).<br>
            • <em>Optional 8th Column:</em> <code>Question Type</code> (<code>single</code>, <code>multiple</code>, <code>case_study</code>, <code>assertion_reason</code>, <code>matching</code>). Auto-detected as <code>multiple</code> if multiple correct options are provided.
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
                placeholder='What is an operating system?,1,System software,Application software,Hardware component,Malware,A,single&#10;Which of the following are valid IPC mechanisms?,2,Pipes,Shared Memory,Queues,Registers,"A,B,C",multiple&#10;"Assertion (A): Paging eliminates external fragmentation.&#10;Reason (R): Frames are fixed size.",3,"Both A and R are true, and R is the correct explanation of A","Both A and R are true, but R is NOT the correct explanation of A","A is true, but R is false","A is false, but R is true",A,assertion_reason'></textarea>
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
Generate 15 high-quality, academically rigorous questions for the following course:
Course: ${subjectTitle}
Target Audience: ${targetAudience}

SUPPORTED QUESTION TYPES (Multi-Type MCQ Architecture):
1. single           - Standard single-choice question with exactly one correct option (A, B, C, or D).
2. multiple         - Multiple-Answer question where multiple options are correct (e.g. "Select all that apply"). Correct Option MUST be comma-separated letters inside quotes (e.g. "A,C,D" or "A,B").
3. case_study       - Scenario-based / problem-solving question presenting a realistic technical scenario followed by a focused diagnostic or architectural decision question.
4. assertion_reason - "Assertion (A): [statement]\\nReason (R): [statement]" with standard options (MUST be enclosed in double quotes):
                      A) "Both A and R are true, and R is the correct explanation of A"
                      B) "Both A and R are true, but R is NOT the correct explanation of A"
                      C) "A is true, but R is false"
                      D) "A is false, but R is true"
5. matching         - Column matching question (e.g. "Match List-I with List-II: 1-..., 2-...") with options formatted as combinations enclosed in double quotes (e.g. "1-P, 2-Q, 3-R").

STRICT OUTPUT FORMAT RULES:
1. Return ONLY the raw CSV text. Do NOT wrap output in markdown code blocks (\`\`\`csv or \`\`\`). Zero commentary, notes, or conversational filler.
2. The very first line MUST be this exact 8-column header row:
Question Text,Unit Number,Option A,Option B,Option C,Option D,Correct Option,Question Type

3. Field Constraints:
   - Question Text: Clear, rigorous academic question testing conceptual mastery and practical problem solving.
   - Unit Number: An integer representing syllabus unit (1, 2, 3, 4, or 5). Distribute questions evenly across units.
   - Option A, Option B, Option C, Option D: Distinct, plausible options. Do NOT prefix with "A)", "B.", "1.", or labels.
   - Correct Option:
     * For single, case_study, assertion_reason, matching: exactly one uppercase letter ("A", "B", "C", or "D").
     * For multiple: comma-separated uppercase letters enclosed in quotes (e.g. "A,C" or "A,B,D").
   - Question Type: Must be exactly one of: single, multiple, case_study, assertion_reason, matching.

4. CRITICAL CSV ESCAPING RULES:
   - Any field that contains a comma (,), quotation mark ("), or semicolon MUST be enclosed inside standard double quotes.
   - For assertion_reason and matching questions, options contain commas and MUST be enclosed in double quotes (e.g. "Both A and R are true, and R is the correct explanation of A" or "1-Q, 2-P, 3-R").
   - Any quotation mark within a field must be escaped as two double quotes (e.g. "Use the ""volatile"" keyword").
   - Each question must occupy exactly one single line. Use \\n for internal line breaks inside quoted question text.

5. DISTRIBUTION REQUIREMENT:
   - Distribute questions evenly across units 1 to 5.
   - Include diverse question types: ~8 single, ~3 multiple, ~2 case_study, ~1 assertion_reason, ~1 matching.

VALID OUTPUT EXAMPLE:
Question Text,Unit Number,Option A,Option B,Option C,Option D,Correct Option,Question Type
"Which data structure operates on a Last-In, First-Out (LIFO) basis?",1,Queue,Stack,Array,Binary Tree,B,single
"Which of the following are standard inter-process communication (IPC) mechanisms in UNIX-like systems? (Select all that apply)",1,Message Queues,Shared Memory,Pipes,Floating-Point Registers,"A,B,C",multiple
"Scenario: A high-frequency trading server experiences severe throughput degradation due to lock contention on shared queues. Which architectural pattern should the engineers evaluate?",2,Lock-free ring buffers with atomic CAS,Coarse-grained recursive mutexes,Single-threaded synchronous I/O,Global Interpreter Lock,A,case_study
"Assertion (A): Virtual memory paging completely eliminates external fragmentation.\\nReason (R): In paging, physical memory is partitioned into uniform, fixed-size page frames.",3,"Both A and R are true, and R is the correct explanation of A","Both A and R are true, but R is NOT the correct explanation of A","A is true, but R is false","A is false, but R is true",A,assertion_reason
"Match the disk scheduling algorithms with their operational behaviors:\\n1. FCFS - P. Services nearest request\\n2. SSTF - Q. Strict arrival order\\n3. SCAN - R. Elevates in one direction then reverses",4,"1-Q, 2-P, 3-R","1-P, 2-Q, 3-R","1-R, 2-P, 3-Q","1-Q, 2-R, 3-P",A,matching`;

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

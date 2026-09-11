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

if (isset($_GET['action']) && $_GET['action'] === 'download_template') {
    $format = (($_GET['format'] ?? 'csv') === 'xlsx') ? 'xlsx' : 'csv';
    CsvService::downloadSampleQuestionsTemplate($format);
    exit;
}

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
            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 280px;">
                    <strong>Instructions:</strong> Upload a CSV file OR paste comma-separated text.<br>
                    • 7 columns required: <code>Question Text, Unit Number, Option A, Option B, Option C, Option D, Correct Option</code><br>
                    • <code>Correct Option</code> must be A, B, C, or D. For <em>Multiple-Answer</em> questions, list multiple letters separated by commas (e.g. <code>A,C,D</code> or <code>ACD</code>).<br>
                    • <em>Optional 8th Column:</em> <code>Question Type</code> (<code>single</code>, <code>multiple</code>, <code>case_study</code>, <code>assertion_reason</code>, <code>matching</code>). Auto-detected as <code>multiple</code> if multiple correct options are provided.
                </div>
                <div style="display: flex; flex-direction: column; gap: 6px; align-items: flex-start;">
                    <span style="font-size: 0.8rem; font-weight: 700; color: #1e3a8a; text-transform: uppercase; letter-spacing: 0.5px;">Sample Templates</span>
                    <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                        <a href="manage-questions.php?action=download_template&format=csv" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;" title="Download sample questions CSV template">
                            <span class="material-symbols-outlined icon-xs">download</span> CSV (.csv)
                        </a>
                        <a href="manage-questions.php?action=download_template&format=xlsx" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;" title="Download sample questions Excel XLSX template">
                            <span class="material-symbols-outlined icon-xs">table_view</span> Excel (.xlsx)
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" enctype="multipart/form-data" id="upload-questions-form">
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
                <input type="file" name="csv_file" id="csv_file" accept=".csv,text/csv" class="form-control">
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
                    <button type="button" class="btn btn-secondary btn-sm" id="preview-btn" style="display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined icon-xs">preview</span> Preview Questions
                    </button>
                </div>
                <textarea name="csv_text" id="csv_text" rows="8" class="form-control"
                placeholder='What is an operating system?,1,System software,Application software,Hardware component,Malware,A,single&#10;Which of the following are valid IPC mechanisms?,2,Pipes,Shared Memory,Queues,Registers,"A,B,C",multiple&#10;"Assertion (A): Paging eliminates external fragmentation.&#10;Reason (R): Frames are fixed size.",3,"Both A and R are true, and R is the correct explanation of A","Both A and R are true, but R is NOT the correct explanation of A","A is true, but R is false","A is false, but R is true",A,assertion_reason'></textarea>
            </div>

            <button type="submit" name="add_bulk_csv" id="submit-upload-btn" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; margin-top: 15px;">
                <span class="material-symbols-outlined icon-sm">upload</span> Upload Questions
            </button>
        </form>
    </div>

    <!-- In-DOM Accessible Question Preview Modal -->
    <div id="previewQuestionsModal" class="admin-modal-overlay" style="display: none;">
        <div class="admin-modal-card admin-modal-card-wide" style="max-width: 1100px; max-height: 90vh; display: flex; flex-direction: column;">
            <div class="admin-modal-header" style="flex-shrink: 0;">
                <div>
                    <h3 style="display: flex; align-items: center; gap: 8px; margin: 0; font-size: 1.15rem; color: var(--color-dark);">
                        <span class="material-symbols-outlined" style="color: var(--color-primary);">preview</span>
                        <span>Questions Import Preview</span>
                    </h3>
                    <p id="preview-summary-subtext" style="margin: 3px 0 0; font-size: 0.85rem; color: var(--color-text-secondary);">
                        Review parsed questions and verified question types before committing
                    </p>
                </div>
                <button type="button" class="admin-modal-close" id="closePreviewModalBtn">&times;</button>
            </div>

            <div id="preview-summary-badges" style="padding: 10px 20px; background: var(--bg-body, #f8fafc); border-bottom: 1px solid var(--border-color, #e2e8f0); display: flex; gap: 8px; flex-wrap: wrap; align-items: center; font-size: 0.85rem;">
                <!-- Badges will be dynamically injected here -->
            </div>

            <div class="admin-modal-body" style="padding: 15px 20px; overflow-y: auto; flex: 1;">
                <div class="table-wrap">
                    <table style="width: 100%; font-size: 0.85rem;" id="previewTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;">#</th>
                                <th style="min-width: 220px;">Question Text</th>
                                <th style="width: 55px; text-align: center;">Unit</th>
                                <th style="width: 115px; text-align: center;">Type</th>
                                <th>Option A</th>
                                <th>Option B</th>
                                <th>Option C</th>
                                <th>Option D</th>
                                <th style="width: 80px; text-align: center;">Correct</th>
                                <th style="width: 75px; text-align: center;">Status</th>
                            </tr>
                        </thead>
                        <tbody id="previewTableBody">
                            <!-- Injected rows -->
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="admin-modal-footer" style="padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0;">
                <span id="previewValidationError" style="font-size: 0.85rem; color: var(--color-danger, #ef4444); font-weight: 600;"></span>
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-secondary" id="dismissPreviewModalBtn">Close</button>
                    <button type="button" class="btn btn-primary" id="confirmUploadFromModalBtn" style="display: inline-flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-outlined icon-sm">upload</span> Proceed to Upload
                    </button>
                </div>
            </div>
        </div>
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

    // --- In-DOM Questions Preview System ---
    const previewBtn = document.getElementById('preview-btn');
    const previewModal = document.getElementById('previewQuestionsModal');
    const closePreviewBtn = document.getElementById('closePreviewModalBtn');
    const dismissPreviewBtn = document.getElementById('dismissPreviewModalBtn');
    const confirmUploadBtn = document.getElementById('confirmUploadFromModalBtn');
    const previewTableBody = document.getElementById('previewTableBody');
    const previewSummaryBadges = document.getElementById('preview-summary-badges');
    const previewSummarySubtext = document.getElementById('preview-summary-subtext');
    const previewValidationError = document.getElementById('previewValidationError');
    const csvFileInput = document.getElementById('csv_file');
    const uploadForm = document.getElementById('upload-questions-form');

    function closePreview() {
        if (previewModal) previewModal.style.display = 'none';
    }

    if (closePreviewBtn) closePreviewBtn.addEventListener('click', closePreview);
    if (dismissPreviewBtn) dismissPreviewBtn.addEventListener('click', closePreview);
    if (previewModal) {
        previewModal.addEventListener('click', function (e) {
            if (e.target === previewModal) closePreview();
        });
    }

    if (confirmUploadBtn) {
        confirmUploadBtn.addEventListener('click', function () {
            if (subjectSelect && !subjectSelect.value) {
                alert('Please select a Target Subject before proceeding with the upload.');
                closePreview();
                subjectSelect.focus();
                return;
            }
            closePreview();
            // Submit form with original submit button name
            const submitHidden = document.createElement('input');
            submitHidden.type = 'hidden';
            submitHidden.name = 'add_bulk_csv';
            submitHidden.value = '1';
            uploadForm.appendChild(submitHidden);
            uploadForm.submit();
        });
    }

    // Robust CSV parsing helper supporting quoted fields, inner commas, and escaped quotes
    function parseCsvString(text) {
        const rows = [];
        let currentRow = [];
        let currentCell = '';
        let insideQuotes = false;

        for (let i = 0; i < text.length; i++) {
            const char = text[i];
            const nextChar = text[i + 1];

            if (char === '"') {
                if (insideQuotes && nextChar === '"') {
                    currentCell += '"';
                    i++;
                } else {
                    insideQuotes = !insideQuotes;
                }
            } else if (char === ',' && !insideQuotes) {
                currentRow.push(currentCell);
                currentCell = '';
            } else if ((char === '\r' || char === '\n') && !insideQuotes) {
                if (char === '\r' && nextChar === '\n') {
                    i++;
                }
                currentRow.push(currentCell);
                if (currentRow.some(c => c.trim() !== '')) {
                    rows.push(currentRow);
                }
                currentRow = [];
                currentCell = '';
            } else {
                currentCell += char;
            }
        }
        if (currentCell.length > 0 || currentRow.length > 0) {
            currentRow.push(currentCell);
            if (currentRow.some(c => c.trim() !== '')) {
                rows.push(currentRow);
            }
        }
        return rows;
    }

    // Client-side mirror of CsvService::normalizeQuestionRow
    function normalizeClientRow(raw) {
        if (!Array.isArray(raw) || raw.length <= 6) return raw;

        let data = [...raw];

        // 1. Repair unquoted question text if unit is displaced
        if (isNaN(parseInt(data[1], 10))) {
            let uIdx = -1;
            for (let i = 1; i < data.length - 5; i++) {
                const val = (data[i] || '').trim();
                const num = parseInt(val, 10);
                if (!isNaN(num) && num >= 1 && num <= 20) {
                    uIdx = i;
                    break;
                }
            }
            if (uIdx > 1) {
                const qText = data.slice(0, uIdx).map(s => s.trim()).join(', ');
                const uNum = data[uIdx].trim();
                data = [qText, uNum, ...data.slice(uIdx + 1)];
            }
        }

        if (data.length === 7 || data.length === 8) {
            return data;
        }

        const validTypes = ['single', 'multiple', 'case_study', 'assertion_reason', 'matching'];
        let detectedType = null;
        const lastVal = (data[data.length - 1] || '').trim().toLowerCase();
        if (validTypes.includes(lastVal)) {
            detectedType = lastVal;
            data.pop();
        }

        const allowedLetters = ['A', 'B', 'C', 'D'];
        const correctLetters = [];

        while (data.length > 6) {
            const candidate = (data[data.length - 1] || '').trim().toUpperCase();
            if (allowedLetters.includes(candidate)) {
                correctLetters.unshift(data.pop());
                if (detectedType && detectedType !== 'multiple') break;
            } else if (/^[A-D](\s*,\s*[A-D])+$/.test(candidate)) {
                const toks = candidate.split(',').map(s => s.trim());
                for (const t of toks) {
                    if (allowedLetters.includes(t) && !correctLetters.includes(t)) {
                        correctLetters.push(t);
                    }
                }
                data.pop();
                break;
            } else {
                break;
            }
        }

        let rawCorrect = correctLetters.length > 0 ? correctLetters.join(',') : (data.pop() || '').trim().toUpperCase();
        const qText = data[0] || '';
        const uNum = data[1] || '1';
        const middle = data.slice(2);

        let opts = [];
        if (middle.length === 4) {
            opts = middle;
        } else if (middle.length > 4) {
            const isAssertion = (detectedType === 'assertion_reason' || /assertion/i.test(qText));
            if (isAssertion) {
                const starts = [];
                middle.forEach((frag, idx) => {
                    const trimmed = frag.trim();
                    if (idx === 0) starts[0] = idx;
                    else if (starts.length === 1 && /^both\b/i.test(trimmed)) starts[1] = idx;
                    else if (starts.length === 2 && /^(\(?a\)?|assertion)\s+is\s+true\b/i.test(trimmed)) starts[2] = idx;
                    else if (starts.length === 3 && /^(\(?a\)?|assertion)\s+is\s+false\b/i.test(trimmed)) starts[3] = idx;
                });
                if (starts.length === 4) {
                    for (let i = 0; i < 4; i++) {
                        const from = starts[i];
                        const to = (i < 3) ? starts[i + 1] : middle.length;
                        opts.push(middle.slice(from, to).map(s => s.trim()).join(', '));
                    }
                    if (!detectedType) detectedType = 'assertion_reason';
                }
            }

            if (opts.length === 0 && middle.length % 4 === 0) {
                const chunkSize = middle.length / 4;
                for (let i = 0; i < 4; i++) {
                    opts.push(middle.slice(i * chunkSize, (i + 1) * chunkSize).map(s => s.trim()).join(', '));
                }
            }

            if (opts.length === 0) {
                opts = [middle[0] || '', middle[1] || '', middle[2] || '', middle.slice(3).map(s => s.trim()).join(', ')];
            }
        } else {
            opts = middle;
            while (opts.length < 4) opts.push('');
        }

        const res = [qText, uNum, opts[0] || '', opts[1] || '', opts[2] || '', opts[3] || '', rawCorrect];
        if (detectedType) res.push(detectedType);
        return res;
    }

    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    async function handlePreviewClick() {
        let rawContent = '';

        if (csvFileInput && csvFileInput.files && csvFileInput.files.length > 0) {
            const file = csvFileInput.files[0];
            try {
                rawContent = await file.text();
            } catch (err) {
                alert('Could not read the selected CSV file: ' + err.message);
                return;
            }
        } else if (csvTextarea && csvTextarea.value.trim() !== '') {
            rawContent = csvTextarea.value.trim();
        } else {
            alert('Please select a .CSV file or paste CSV text before opening the preview.');
            if (csvTextarea) csvTextarea.focus();
            return;
        }

        const rawRows = parseCsvString(rawContent);
        if (rawRows.length === 0) {
            alert('No valid CSV rows could be extracted from the input.');
            return;
        }

        const parsedQuestions = [];
        let isHeader = true;
        const validTypes = ['single', 'multiple', 'case_study', 'assertion_reason', 'matching'];
        const allowedOptions = ['A', 'B', 'C', 'D'];

        const typeCounts = {
            single: 0,
            multiple: 0,
            case_study: 0,
            assertion_reason: 0,
            matching: 0
        };
        let errorCount = 0;

        for (let i = 0; i < rawRows.length; i++) {
            let row = rawRows[i];
            if (row.every(cell => (cell || '').trim() === '')) continue;

            if (isHeader) {
                const col0 = (row[0] || '').toLowerCase().trim();
                const col1 = (row[1] || '').trim();
                if (col0.includes('question') || isNaN(parseInt(col1, 10))) {
                    isHeader = false;
                    continue;
                }
                isHeader = false;
            }

            row = normalizeClientRow(row);

            let qText = (row[0] || '').trim().replace(/\\r\\n|\\r|\\n/g, '\n');
            let uNum = parseInt((row[1] || '1').trim(), 10) || 1;
            let optA = (row[2] || '').trim();
            let optB = (row[3] || '').trim();
            let optC = (row[4] || '').trim();
            let optD = (row[5] || '').trim();
            let rawCorrect = (row[6] || '').trim().toUpperCase();
            let rawType = (row[7] || '').trim().toLowerCase();

            // Validate correct letters
            const letters = [];
            const tokens = rawCorrect.split(/[\s,;]+/).filter(Boolean);
            let hasInvalidToken = false;
            for (const tok of tokens) {
                for (let ch of tok) {
                    if (allowedOptions.includes(ch)) {
                        if (!letters.includes(ch)) letters.push(ch);
                    } else {
                        hasInvalidToken = true;
                    }
                }
            }
            letters.sort();
            const correctClean = letters.join(',');

            let qType = 'single';
            if (validTypes.includes(rawType)) {
                qType = rawType;
            } else {
                qType = (letters.length > 1) ? 'multiple' : 'single';
            }

            let error = null;
            if (!qText) error = 'Missing Question Text';
            else if (!optA || !optB) error = 'Missing Option A or Option B';
            else if (letters.length === 0 || hasInvalidToken) error = 'Invalid Correct Option (' + rawCorrect + ')';

            if (error) {
                errorCount++;
            } else if (typeCounts[qType] !== undefined) {
                typeCounts[qType]++;
            }

            parsedQuestions.push({
                index: parsedQuestions.length + 1,
                text: qText,
                unit: uNum,
                type: qType,
                optA: optA,
                optB: optB,
                optC: optC,
                optD: optD,
                correct: correctClean || rawCorrect,
                error: error
            });
        }

        if (parsedQuestions.length === 0) {
            alert('No questions were found after filtering empty rows or headers.');
            return;
        }

        // Render summary badges
        let badgeHtml = `
            <span class="badge badge-active" style="font-weight: 700;">${parsedQuestions.length} Total Questions</span>
            ${typeCounts.single > 0 ? `<span class="badge badge-info">${typeCounts.single} Single</span>` : ''}
            ${typeCounts.multiple > 0 ? `<span class="badge badge-warning">${typeCounts.multiple} Multiple</span>` : ''}
            ${typeCounts.case_study > 0 ? `<span class="badge badge-primary">${typeCounts.case_study} Case Study</span>` : ''}
            ${typeCounts.assertion_reason > 0 ? `<span class="badge badge-secondary">${typeCounts.assertion_reason} Assertion-Reason</span>` : ''}
            ${typeCounts.matching > 0 ? `<span class="badge badge-outline">${typeCounts.matching} Matching</span>` : ''}
            ${errorCount > 0 ? `<span class="badge badge-rejected" style="font-weight: bold;"><span class="material-symbols-outlined icon-xs">warning</span> ${errorCount} Invalid Row(s)</span>` : `<span class="badge badge-active"><span class="material-symbols-outlined icon-xs">check_circle</span> 100% Valid</span>`}
        `;
        if (previewSummaryBadges) previewSummaryBadges.innerHTML = badgeHtml;

        if (previewSummarySubtext) {
            previewSummarySubtext.textContent = `Found ${parsedQuestions.length} questions across ${typeCounts.single + typeCounts.multiple + typeCounts.case_study + typeCounts.assertion_reason + typeCounts.matching} categorized items (${errorCount} errors detected).`;
        }

        // Render table body
        let tableRowsHtml = '';
        parsedQuestions.forEach(q => {
            const trClass = q.error ? 'style="background-color: rgba(239, 68, 68, 0.06);"' : '';
            const typeBadgeStyle = q.type === 'multiple' ? 'badge-warning'
                : q.type === 'case_study' ? 'badge-primary'
                : q.type === 'assertion_reason' ? 'badge-secondary'
                : q.type === 'matching' ? 'badge-outline'
                : 'badge-info';

            const statusBadge = q.error
                ? `<span class="badge badge-rejected" title="${escapeHtml(q.error)}">Error</span>`
                : `<span class="badge badge-active">Ready</span>`;

            tableRowsHtml += `
                <tr ${trClass}>
                    <td style="font-weight: bold; text-align: center;">${q.index}</td>
                    <td style="white-space: pre-line; word-break: break-word; font-weight: 500;">${escapeHtml(q.text)}</td>
                    <td style="text-align: center;"><span class="badge" style="background: #f1f5f9; color: #334155;">Unit ${q.unit}</span></td>
                    <td style="text-align: center;"><span class="badge ${typeBadgeStyle}">${escapeHtml(q.type)}</span></td>
                    <td style="max-width: 140px; word-break: break-word;">${escapeHtml(q.optA)}</td>
                    <td style="max-width: 140px; word-break: break-word;">${escapeHtml(q.optB)}</td>
                    <td style="max-width: 140px; word-break: break-word;">${escapeHtml(q.optC || '—')}</td>
                    <td style="max-width: 140px; word-break: break-word;">${escapeHtml(q.optD || '—')}</td>
                    <td style="text-align: center; font-weight: 700; color: var(--color-primary);">${escapeHtml(q.correct)}</td>
                    <td style="text-align: center;">${statusBadge}</td>
                </tr>
            `;
        });

        if (previewTableBody) previewTableBody.innerHTML = tableRowsHtml;

        if (previewValidationError) {
            previewValidationError.textContent = errorCount > 0 ? `⚠️ ${errorCount} row(s) contain validation errors. Hover over 'Error' badges to see details.` : '';
        }

        if (previewModal) previewModal.style.display = 'flex';
    }

    if (previewBtn) {
        previewBtn.addEventListener('click', handlePreviewClick);
    }
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>

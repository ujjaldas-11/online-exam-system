<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/CsvService.php';

$subjects = $pdo->query("SELECT id, name, department, semester FROM subjects ORDER BY name ASC")->fetchAll();

$subject_id = int_param($_GET['subject_id'] ?? ($subjects[0]['id'] ?? 0));
$message = '';
$message_type = '';

if (isset($_GET['action']) && $_GET['action'] === 'export_questions' && $subject_id > 0) {
    if (!can_admin_manage_subject($pdo, $subject_id)) {
        $message = "Unauthorized: You can only export questions for subjects you have access to.";
        $message_type = 'error';
    } else {
        $format = (($_GET['format'] ?? 'csv') === 'xlsx') ? 'xlsx' : 'csv';
        $stmt = $pdo->prepare("SELECT question_text, unit_number, option_a, option_b, option_c, option_d, correct_option, question_type FROM questions WHERE subject_id = ? ORDER BY unit_number ASC, id ASC");
        $stmt->execute([$subject_id]);
        $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $subjName = 'subject_' . $subject_id;
        foreach ($subjects as $s) {
            if ($s['id'] == $subject_id) {
                $subjName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $s['name']);
                break;
            }
        }
        log_admin_action($pdo, 'export_questions', 'subject', $subject_id, "Exported " . count($questions) . " questions for subject #$subject_id as $format");
        CsvService::exportQuestions("questions_{$subjName}", $questions, $format);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $subject_id > 0) {
    verify_csrf();

    if (isset($_POST['delete_all'])) {
        $isOwnerOrSuper = can_admin_manage_subject($pdo, $subject_id);

        if (!$isOwnerOrSuper) {
            $message = "Unauthorized: You can only manage questions for subjects you have created.";
            $message_type = 'error';
        } else {
            try {
                $delStmt = $pdo->prepare("DELETE FROM questions WHERE subject_id = ?");
                $delStmt->execute([$subject_id]);
                log_admin_action($pdo, 'delete_questions', 'subject', $subject_id, "Deleted all questions for subject ID #$subject_id");
                $message = "All questions for this subject have been deleted.";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to delete questions.");
                $message_type = 'error';
            }
        }
    } elseif (isset($_POST['delete_question'])) {
        $q_id = int_param($_POST['question_id'] ?? 0);
        if ($q_id > 0) {
            $canDelete = can_admin_manage_question($pdo, $q_id);

            if (!$canDelete) {
                $message = "Unauthorized: You can only delete questions you authored or in subjects you created.";
                $message_type = 'error';
            } else {
                try {
                    $del = $pdo->prepare("DELETE FROM questions WHERE id = ?");
                    $del->execute([$q_id]);
                    log_admin_action($pdo, 'delete_question', 'question', $q_id, "Deleted question #$q_id from subject #$subject_id");
                    $message = "Question deleted successfully.";
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = safe_db_error($e, "Failed to delete question.");
                    $message_type = 'error';
                }
            }
        }
    } elseif (isset($_POST['edit_question'])) {
        $q_id = int_param($_POST['question_id'] ?? 0);
        $q_text = clean_input($_POST['question_text'] ?? '');
        $opt_a = clean_input($_POST['option_a'] ?? '');
        $opt_b = clean_input($_POST['option_b'] ?? '');
        $opt_c = clean_input($_POST['option_c'] ?? '');
        $opt_d = clean_input($_POST['option_d'] ?? '');
        $q_type = clean_input($_POST['question_type'] ?? 'single');
        $validTypes = ['single', 'multiple', 'case_study', 'assertion_reason', 'matching'];
        if (!in_array($q_type, $validTypes, true)) {
            $q_type = 'single';
        }

        $rawCorrect = '';
        if (isset($_POST['correct_options']) && is_array($_POST['correct_options'])) {
            $rawCorrect = implode(',', $_POST['correct_options']);
        } elseif (isset($_POST['correct_option'])) {
            $rawCorrect = (string)$_POST['correct_option'];
        }
        $rawCorrect = strtoupper(trim($rawCorrect));
        $allowedOptions = ['A', 'B', 'C', 'D'];
        $cleanTokens = [];
        foreach (array_filter(array_map('trim', explode(',', $rawCorrect))) as $tok) {
            if (in_array($tok, $allowedOptions, true) && !in_array($tok, $cleanTokens, true)) {
                $cleanTokens[] = $tok;
            }
        }
        sort($cleanTokens);
        $correct_opt = implode(',', $cleanTokens);
        $unit_num = int_param($_POST['unit_number'] ?? 1);

        if (empty($q_text) || empty($opt_a) || empty($opt_b) || empty($correct_opt)) {
            $message = "Please provide question text, at least Options A & B, and valid Correct Option(s).";
            $message_type = 'error';
        } else {
            $canEdit = can_admin_manage_question($pdo, $q_id);

            if (!$canEdit) {
                $message = "Unauthorized: You can only edit questions you authored or in subjects you created.";
                $message_type = 'error';
            } else {
                try {
                    $up = $pdo->prepare("
                        UPDATE questions
                        SET question_text = ?, question_type = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, unit_number = ?
                        WHERE id = ?
                    ");
                    $up->execute([$q_text, $q_type, $opt_a, $opt_b, $opt_c ?: null, $opt_d ?: null, $correct_opt, $unit_num, $q_id]);
                    log_admin_action($pdo, 'edit_question', 'question', $q_id, "Updated question #$q_id in subject #$subject_id");
                    $message = "Question updated successfully.";
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = safe_db_error($e, "Failed to update question.");
                    $message_type = 'error';
                }
            }
        }
    }
}

$subject = null;
$all_questions = [];

if ($subject_id > 0) {
    try {
        $subjectStmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
        $subjectStmt->execute([$subject_id]);
        $subject = $subjectStmt->fetch();

        if ($subject) {
            $resultsSql = "
                SELECT q.id, q.unit_number, q.question_type, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d, q.correct_option,
                       a.name as creator_name, a.status as creator_status
                FROM questions q
                LEFT JOIN admins a ON q.created_by = a.id
                WHERE q.subject_id = :subject_id
                ORDER BY q.id ASC
            ";
            $resultsStmt = $pdo->prepare($resultsSql);
            $resultsStmt->execute([':subject_id' => $subject_id]);
            $all_questions = $resultsStmt->fetchAll();
        }
    } catch (PDOException $e) {
        log_error("Failed to fetch subject questions", $e);
    }
}

$page_title = 'Question Bank • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div style="margin-bottom: 16px;">
        <a href="manage-subjects.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span class="material-symbols-outlined icon-sm">arrow_back</span> Back to All Subjects
        </a>
    </div>

    <div class="page-header">
        <div>
            <h1><?= $subject ? e($subject['name']) : 'Curriculum Question Bank' ?></h1>
            <p>
                <?php if ($subject): ?>
                    Department: <strong><?= e($subject['department']) ?></strong> • Semester: <strong><?= e((string)$subject['semester']) ?></strong>
                <?php else: ?>
                    Select a subject to view its questions
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <!-- Subject Switcher Dropdown -->
            <form method="GET" style="display: inline-flex; align-items: center; gap: 6px;">
                <select name="subject_id" onchange="this.form.submit()" class="form-control" style="padding: 6px 12px; font-size: 0.9rem;">
                    <?php foreach ($subjects as $s): ?>
                        <option value="<?= $s['id'] ?>" <?= $s['id'] == $subject_id ? 'selected' : '' ?>>
                            <?= e($s['name']) ?> (<?= e($s['department']) ?> Sem <?= e((string)$s['semester']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <a href="manage-questions.php" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                <span class="material-symbols-outlined icon-sm">upload</span> Upload Questions
            </a>
            <?php if (!empty($all_questions)): ?>
                <a href="view-questions.php?subject_id=<?= $subject_id ?>&action=export_questions&format=csv" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;" title="Export questions as CSV">
                    <span class="material-symbols-outlined icon-sm">download</span> Export CSV
                </a>
                <a href="view-questions.php?subject_id=<?= $subject_id ?>&action=export_questions&format=xlsx" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;" title="Export questions as Excel (.xlsx)">
                    <span class="material-symbols-outlined icon-sm">table_view</span> Export XLSX
                </a>
                <form method="POST" style="display: inline;" data-confirm="Are you sure you want to delete ALL questions for this subject? This action CANNOT be undone!" data-confirm-title="Delete All Questions" data-confirm-btn="Delete All">
                    <?= csrf_field() ?>
                    <button type="submit" name="delete_all" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined icon-sm">delete_forever</span> Delete All
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">Question Bank (<?= count($all_questions) ?> Questions)</div>

        <div style="margin-bottom: 10px;">
            <?php include '../components/searchbar.php' ?>
        </div>

        <?php if (empty($all_questions)): ?>
            <p style="color: var(--color-text-secondary); padding: 24px 0; text-align: center;">No questions have been added to this subject yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Question Text</th>
                            <th style="width: 130px; text-align: center;">Type</th>
                            <th style="width: 140px; text-align: center;">Correct Option</th>
                            <th style="width: 90px; text-align: center;">Unit</th>
                            <th style="width: 140px; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $counter = 1;
                        $typeBadges = [
                            'single' => ['label' => 'Single Select', 'class' => 'badge-active'],
                            'multiple' => ['label' => 'Multiple Answer', 'class' => 'badge-warning'],
                            'case_study' => ['label' => 'Case Study', 'class' => 'badge-info'],
                            'assertion_reason' => ['label' => 'Assertion-Reason', 'class' => 'badge-secondary'],
                            'matching' => ['label' => 'Matching', 'class' => 'badge-outline'],
                        ];
                        ?>
                        <?php foreach ($all_questions as $row): ?>
                            <?php
                            $qType = $row['question_type'] ?? 'single';
                            $badgeInfo = $typeBadges[$qType] ?? ['label' => ucfirst($qType), 'class' => 'badge-inactive'];
                            $isMulti = ($qType === 'multiple');
                            ?>
                            <tr>
                                <td><strong><?= $counter++ ?></strong></td>
                                <td>
                                    <div style="font-weight: 600; margin-bottom: 6px;"><?= nl2br(e($row['question_text'])) ?></div>
                                    <div style="font-size: 0.85rem; color: var(--color-text-secondary); display: grid; grid-template-columns: 1fr 1fr; gap: 4px;">
                                        <div><strong>A:</strong> <?= e($row['option_a']) ?></div>
                                        <div><strong>B:</strong> <?= e($row['option_b']) ?></div>
                                        <?php if (!empty($row['option_c'])): ?>
                                            <div><strong>C:</strong> <?= e($row['option_c']) ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($row['option_d'])): ?>
                                            <div><strong>D:</strong> <?= e($row['option_d']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($row['creator_name'])): ?>
                                        <div style="font-size: 0.74rem; color: var(--color-text-secondary); margin-top: 6px;">
                                            Added by: <strong><?= e($row['creator_name']) ?></strong>
                                            <?php if (($row['creator_status'] ?? '') === 'retired'): ?>
                                                <span class="badge badge-warning" style="font-size: 0.62rem; padding: 1px 3px;">Retired</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge <?= $badgeInfo['class'] ?>" style="font-size: 0.75rem; white-space: nowrap;"><?= e($badgeInfo['label']) ?></span>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($isMulti): ?>
                                        <span class="badge badge-warning" style="font-size: 0.85rem;">Options <?= e($row['correct_option']) ?></span>
                                    <?php else: ?>
                                        <span class="badge badge-active" style="font-size: 0.9rem;">Option <?= e($row['correct_option']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-inactive">Unit <?= e((string)$row['unit_number']) ?></span>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <button type="button" class="btn btn-secondary btn-sm btn-edit-question"
                                        data-id="<?= (int)$row['id'] ?>"
                                        data-type="<?= e($qType) ?>"
                                        data-text="<?= e($row['question_text']) ?>"
                                        data-a="<?= e($row['option_a']) ?>"
                                        data-b="<?= e($row['option_b']) ?>"
                                        data-c="<?= e($row['option_c'] ?? '') ?>"
                                        data-d="<?= e($row['option_d'] ?? '') ?>"
                                        data-correct="<?= e($row['correct_option']) ?>"
                                        data-unit="<?= e((string)$row['unit_number']) ?>"
                                        style="display: inline-flex; align-items: center; gap: 4px;">
                                        <span class="material-symbols-outlined icon-xs">edit</span> Edit
                                    </button>
                                    <form method="POST" style="display: inline;" data-confirm="Are you sure you want to delete this question?" data-confirm-title="Delete Question" data-confirm-btn="Delete Question">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="question_id" value="<?= (int)$row['id'] ?>">
                                        <button type="submit" name="delete_question" class="btn btn-danger btn-sm" title="Delete Question" style="display: inline-flex; align-items: center;">
                                            <span class="material-symbols-outlined icon-xs">delete</span>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Question Modal -->
<div id="editQuestionModal" class="admin-modal-overlay">
    <div class="admin-modal-card admin-modal-card-wide">
        <div class="admin-modal-header">
            <h3><span class="material-symbols-outlined">edit</span> Edit Question</h3>
            <button type="button" class="admin-modal-close" id="closeEditModal">&times;</button>
        </div>
        <form method="POST" id="editQuestionForm">
            <?= csrf_field() ?>
            <input type="hidden" name="question_id" id="modal_q_id" value="">

            <div class="admin-modal-body">
                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;">Question Text</label>
                    <textarea name="question_text" id="modal_q_text" rows="3" required class="form-control" style="width: 100%;"></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Option A</label>
                        <input type="text" name="option_a" id="modal_opt_a" required class="form-control" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Option B</label>
                        <input type="text" name="option_b" id="modal_opt_b" required class="form-control" style="width: 100%;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Option C (Optional)</label>
                        <input type="text" name="option_c" id="modal_opt_c" class="form-control" style="width: 100%;">
                    </div>
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Option D (Optional)</label>
                        <input type="text" name="option_d" id="modal_opt_d" class="form-control" style="width: 100%;">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Question Type</label>
                        <select name="question_type" id="modal_q_type" class="form-control" style="width: 100%;">
                            <option value="single">Standard Single-Select</option>
                            <option value="multiple">Multiple-Answer (Select all that apply)</option>
                            <option value="case_study">Case-Study / Scenario-Based</option>
                            <option value="assertion_reason">Assertion-Reason</option>
                            <option value="matching">Matching Columns</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-weight: 600; display: block; margin-bottom: 4px;">Unit Number</label>
                        <input type="number" name="unit_number" id="modal_unit_num" min="1" max="20" required class="form-control" style="width: 100%;">
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 12px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;">Correct Option(s)</label>
                    <div id="modal_single_correct_container">
                        <select name="correct_option" id="modal_correct_opt" class="form-control" style="width: 100%;">
                            <option value="A">Option A</option>
                            <option value="B">Option B</option>
                            <option value="C">Option C</option>
                            <option value="D">Option D</option>
                        </select>
                    </div>
                    <div id="modal_multi_correct_container" style="display: none; padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-surface);">
                        <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                            <?php foreach (['A', 'B', 'C', 'D'] as $letter): ?>
                                <label style="display: inline-flex; align-items: center; gap: 6px; font-weight: 500; cursor: pointer;">
                                    <input type="checkbox" name="correct_options[]" id="modal_multi_opt_<?= $letter ?>" value="<?= $letter ?>">
                                    Option <?= $letter ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="admin-modal-footer">
                <button type="button" id="cancelEditBtn" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="edit_question" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('editQuestionModal');
    const closeBtn = document.getElementById('closeEditModal');
    const cancelBtn = document.getElementById('cancelEditBtn');
    const qTypeSelect = document.getElementById('modal_q_type');
    const singleContainer = document.getElementById('modal_single_correct_container');
    const multiContainer = document.getElementById('modal_multi_correct_container');
    const singleSelect = document.getElementById('modal_correct_opt');
    const multiCheckboxes = multiContainer.querySelectorAll('input[type="checkbox"]');

    function syncTypeUI(isMulti) {
        if (isMulti) {
            singleContainer.style.display = 'none';
            singleSelect.disabled = true;
            multiContainer.style.display = 'block';
            multiCheckboxes.forEach(cb => cb.disabled = false);
        } else {
            multiContainer.style.display = 'none';
            multiCheckboxes.forEach(cb => cb.disabled = true);
            singleContainer.style.display = 'block';
            singleSelect.disabled = false;
        }
    }

    if (qTypeSelect) {
        qTypeSelect.addEventListener('change', () => {
            syncTypeUI(qTypeSelect.value === 'multiple');
        });
    }

    document.querySelectorAll('.btn-edit-question').forEach(btn => {
        btn.addEventListener('click', () => {
            const qType = btn.dataset.type || 'single';
            const correctVal = btn.dataset.correct || 'A';
            const isMulti = (qType === 'multiple');

            document.getElementById('modal_q_id').value = btn.dataset.id;
            document.getElementById('modal_q_text').value = btn.dataset.text;
            document.getElementById('modal_opt_a').value = btn.dataset.a;
            document.getElementById('modal_opt_b').value = btn.dataset.b;
            document.getElementById('modal_opt_c').value = btn.dataset.c;
            document.getElementById('modal_opt_d').value = btn.dataset.d;
            document.getElementById('modal_unit_num').value = btn.dataset.unit;
            document.getElementById('modal_q_type').value = qType;

            const correctTokens = correctVal.split(',').map(s => s.trim().toUpperCase());
            multiCheckboxes.forEach(cb => {
                cb.checked = correctTokens.includes(cb.value);
            });
            singleSelect.value = correctTokens[0] || 'A';

            syncTypeUI(isMulti);

            modal.style.display = 'flex';
        });
    });

    const hideModal = () => { if (modal) modal.style.display = 'none'; };
    if (closeBtn) closeBtn.onclick = hideModal;
    if (cancelBtn) cancelBtn.onclick = hideModal;
    if (modal) {
        modal.onclick = (e) => { if (e.target === modal) hideModal(); };
    }
});
</script>

<?php
include __DIR__ . '/../components/confirm-modal.php';
include __DIR__ . '/../components/footer.php';
?>

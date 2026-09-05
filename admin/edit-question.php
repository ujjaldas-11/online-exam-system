<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$questionId = int_param($_GET['id'] ?? $_POST['question_id'] ?? 0);

if ($questionId <= 0) {
    redirect('view-questions.php');
}

// Fetch question and check ownership
try {
    $stmt = $pdo->prepare("
        SELECT q.*, s.name as subject_name, s.department, s.semester, s.created_by as subject_creator
        FROM questions q
        JOIN subjects s ON q.subject_id = s.id
        WHERE q.id = ?
    ");
    $stmt->execute([$questionId]);
    $question = $stmt->fetch();

    if (!$question) {
        die("Question not found.");
    }

    $canEdit = false;
    $adminId = (int)($_SESSION['admin_id'] ?? 0);
    if (is_superadmin() || (int)$question['created_by'] === $adminId || (int)$question['subject_creator'] === $adminId) {
        $canEdit = true;
    }

    if (!$canEdit) {
        http_response_code(403);
        die("Forbidden: You do not have permission to edit this question.");
    }
} catch (PDOException $e) {
    log_error("Failed loading question $questionId for edit", $e);
    die("Database error.");
}

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $q_text = clean_input($_POST['question_text'] ?? '');
    $opt_a = clean_input($_POST['option_a'] ?? '');
    $opt_b = clean_input($_POST['option_b'] ?? '');
    $opt_c = clean_input($_POST['option_c'] ?? '');
    $opt_d = clean_input($_POST['option_d'] ?? '');
    $correct_opt = strtoupper(clean_input($_POST['correct_option'] ?? ''));
    $unit_num = int_param($_POST['unit_number'] ?? 1);

    if (empty($q_text) || empty($opt_a) || empty($opt_b) || !in_array($correct_opt, ['A', 'B', 'C', 'D'], true)) {
        $message = "Please provide question text, Options A & B, and a valid Correct Option (A, B, C, or D).";
        $message_type = 'error';
    } else {
        try {
            $up = $pdo->prepare("
                UPDATE questions
                SET question_text = ?, option_a = ?, option_b = ?, option_c = ?, option_d = ?, correct_option = ?, unit_number = ?
                WHERE id = ?
            ");
            $up->execute([$q_text, $opt_a, $opt_b, $opt_c ?: null, $opt_d ?: null, $correct_opt, $unit_num, $questionId]);
            log_admin_action($pdo, 'edit_question', 'question', $questionId, "Updated question #$questionId");

            redirect("view-questions.php?subject_id={$question['subject_id']}");
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to update question.");
            $message_type = 'error';
        }
    }
}

$page_title = 'Edit Question • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div style="margin-bottom: 16px;">
        <a href="view-questions.php?subject_id=<?= (int)$question['subject_id'] ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span class="material-symbols-outlined icon-sm">arrow_back</span> Back to Question Bank
        </a>
    </div>

    <div class="page-header">
        <div>
            <h1>Edit Question #<?= (int)$question['id'] ?></h1>
            <p>Subject: <strong><?= e($question['subject_name']) ?></strong> (<?= e($question['department']) ?> Sem <?= e((string)$question['semester']) ?>)</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="card" style="max-width: 750px;">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="question_id" value="<?= (int)$question['id'] ?>">

            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 600; display: block; margin-bottom: 6px;">Question Text</label>
                <textarea name="question_text" rows="4" required class="form-control" style="width: 100%;"><?= e($question['question_text']) ?></textarea>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">Option A</label>
                    <input type="text" name="option_a" required value="<?= e($question['option_a']) ?>" class="form-control" style="width: 100%;">
                </div>
                <div class="form-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">Option B</label>
                    <input type="text" name="option_b" required value="<?= e($question['option_b']) ?>" class="form-control" style="width: 100%;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                <div class="form-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">Option C (Optional)</label>
                    <input type="text" name="option_c" value="<?= e($question['option_c'] ?? '') ?>" class="form-control" style="width: 100%;">
                </div>
                <div class="form-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">Option D (Optional)</label>
                    <input type="text" name="option_d" value="<?= e($question['option_d'] ?? '') ?>" class="form-control" style="width: 100%;">
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                <div class="form-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">Correct Option</label>
                    <select name="correct_option" required class="form-control" style="width: 100%;">
                        <option value="A" <?= $question['correct_option'] === 'A' ? 'selected' : '' ?>>Option A</option>
                        <option value="B" <?= $question['correct_option'] === 'B' ? 'selected' : '' ?>>Option B</option>
                        <option value="C" <?= $question['correct_option'] === 'C' ? 'selected' : '' ?>>Option C</option>
                        <option value="D" <?= $question['correct_option'] === 'D' ? 'selected' : '' ?>>Option D</option>
                    </select>
                </div>
                <div class="form-group">
                    <label style="font-weight: 600; display: block; margin-bottom: 6px;">Unit Number</label>
                    <input type="number" name="unit_number" min="1" max="20" required value="<?= (int)$question['unit_number'] ?>" class="form-control" style="width: 100%;">
                </div>
            </div>

            <div style="display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">save</span> Update Question
                </button>
                <a href="view-questions.php?subject_id=<?= (int)$question['subject_id'] ?>" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

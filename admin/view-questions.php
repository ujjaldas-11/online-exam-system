<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

if (empty($_GET['subject_id'])) {
    die("No subject selected.");
}

$subject_id = int_param($_GET['subject_id']);
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Delete all questions for this subject
    if (isset($_POST['delete_all'])) {
        try {
            $delStmt = $pdo->prepare("DELETE FROM questions WHERE subject_id = ?");
            $delStmt->execute([$subject_id]);
            $message = "All questions for this subject have been deleted.";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to delete questions.");
            $message_type = 'error';
        }
    }
}

try {
    $subjectStmt = $pdo->prepare("SELECT * FROM subjects WHERE id = ?");
    $subjectStmt->execute([$subject_id]);
    $subject = $subjectStmt->fetch();

    if (!$subject) {
        die("Subject not found.");
    }

    // Fetch All Questions for this specific subject
    $resultsSql = "SELECT id, question_text, option_a, option_b, option_c, option_d, correct_option FROM questions WHERE subject_id = :subject_id ORDER BY id ASC";
    $resultsStmt = $pdo->prepare($resultsSql);
    $resultsStmt->execute([':subject_id' => $subject_id]);
    $all_questions = $resultsStmt->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to fetch subject questions", $e);
    die("Database error. Please try again.");
}

$page_title = 'Questions: ' . e($subject['name']) . ' • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/navbar.php';
?>

<div class="container">
    <div style="margin-bottom: 16px;">
        <a href="manage-subjects.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span class="material-symbols-outlined icon-sm">arrow_back</span> Back to All Subjects
        </a>
    </div>

    <div class="page-header">
        <div>
            <h1><?= e($subject['name']) ?></h1>
            <p>Department: <strong><?= e($subject['department']) ?></strong> • Semester: <strong><?= e((string)$subject['semester']) ?></strong></p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="manage-questions.php" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                <span class="material-symbols-outlined icon-sm">upload</span> Upload Questions
            </a>
            <?php if (!empty($all_questions)): ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete ALL questions for this subject? This action CANNOT be undone!');">
                    <?= csrf_field() ?>
                    <button type="submit" name="delete_all" class="btn btn-danger btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined icon-sm">delete</span> Delete All Questions
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

        <?php if (empty($all_questions)): ?>
            <p style="color: var(--color-text-secondary); padding: 24px 0; text-align: center;">No questions have been added to this subject yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Question Text</th>
                            <th style="width: 140px; text-align: center;">Correct Option</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $counter = 1; ?>
                        <?php foreach ($all_questions as $row): ?>
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
                                </td>
                                <td style="text-align: center;">
                                    <span class="badge badge-active" style="font-size: 0.9rem;">Option <?= e($row['correct_option']) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

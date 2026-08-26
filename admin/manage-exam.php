<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

date_default_timezone_set('Asia/Kolkata');

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    verify_csrf();

    $title = clean_input($_POST['title'] ?? '');
    $subject_id = int_param($_POST['subject_id'] ?? 0);
    $duration = int_param($_POST['duration_minutes'] ?? 0);
    $total_marks = int_param($_POST['total_marks'] ?? 0);
    $total_questions = int_param($_POST['total_questions_to_ask'] ?? 0);
    $access_pin = clean_input($_POST['access_pin'] ?? '');

    if (empty($title) || $subject_id <= 0 || $duration <= 0 || $total_marks <= 0 || $total_questions <= 0) {
        $message = "Please fill all required fields correctly.";
        $message_type = 'error';
    } else {
        // Check available questions in question bank
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE subject_id = ?");
        $stmt->execute([$subject_id]);
        $available = (int) $stmt->fetchColumn();

        if ($available < $total_questions) {
            $message = "This subject only has $available questions. You cannot configure an exam for $total_questions questions.";
            $message_type = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO exams
                    (title, subject_id, duration_minutes, total_marks, total_questions_to_ask, access_pin, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'inactive')
                ");
                $stmt->execute([$title, $subject_id, $duration, $total_marks, $total_questions, $access_pin ?: null]);

                $message = "Exam created successfully! Navigate to 'Control Exams' to start and monitor it.";
                $message_type = 'success';
            } catch (Exception $e) {
                $message = safe_db_error($e, "Failed to create examination.");
                $message_type = 'error';
            }
        }
    }
}

try {
    $subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to fetch subjects in manage-exam", $e);
    $subjects = [];
}



$page_title = 'Create Exam • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content" style="max-width: 750px;">
    <div class="page-header">
        <div>
            <h1>Create Examination</h1>
            <p>Configure exam parameters, duration, question pool, and classroom PIN</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Exam Title</label>
                <input type="text" name="title" required placeholder="e.g. Mid-Term Surprise Quiz on DBMS" value="<?= e($_POST['title'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Subject</label>
                <select name="subject_id" required>
                    <option value="">-- Choose Subject --</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= (($_POST['subject_id'] ?? '') == $sub['id']) ? 'selected' : '' ?>>
                            <?= e($sub['name']) ?> (<?= e($sub['department']) ?>, Sem <?= e((string)$sub['semester']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Duration (Minutes)</label>
                    <input type="number" name="duration_minutes" required min="1" max="300" placeholder="e.g. 30" value="<?= e($_POST['duration_minutes'] ?? '30') ?>">
                </div>

                <div class="form-group">
                    <label>Total Marks</label>
                    <input type="number" name="total_marks" required min="1" placeholder="e.g. 50" value="<?= e($_POST['total_marks'] ?? '50') ?>">
                </div>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Questions per Student</label>
                    <input type="number" name="total_questions_to_ask" required min="1" placeholder="e.g. 20" value="<?= e($_POST['total_questions_to_ask'] ?? '20') ?>">
                    <small style="color: var(--color-text-secondary);">Randomly picked per student from subject pool</small>
                </div>

                <div class="form-group">
                    <label>Classroom PIN (Optional)</label>
                    <input type="text" name="access_pin" maxlength="10" placeholder="e.g. 4821" value="<?= e($_POST['access_pin'] ?? '') ?>">
                    <small style="color: var(--color-text-secondary);">Students must enter this to start surprise test</small>
                </div>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px; flex-wrap: wrap;">
                <button type="submit" name="create_exam" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">add_circle</span> Create Examination
                </button>
                <a href="control-exams.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">tune</span> Go to Exam Controls
                </a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

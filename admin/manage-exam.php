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
    $target_units = clean_input($_POST['target_units'] ?? '');

    $allowed_units = ['all', '1', '2', '3', '4', '5', '6'];

    if (empty($title) || $subject_id <= 0 || $duration <= 0 || $duration > 1440 || $total_marks <= 0 || $total_marks > 10000 || $total_questions <= 0 || $total_questions > 1000 || empty($target_units)) {
        $message = 'Please fill all required fields with valid values.';
        $message_type = 'error';
    } elseif (strlen($title) > 200) {
        $message = 'Exam title cannot exceed 200 characters.';
        $message_type = 'error';
    } elseif (strlen($access_pin) > 10) {
        $message = 'Access PIN cannot exceed 10 characters.';
        $message_type = 'error';
    } elseif (!in_array($target_units, $allowed_units, true)) {
        $message = 'Invalid target unit selection.';
        $message_type = 'error';
    } else {
        // Check available questions in question bank
        if ($target_units === 'all') {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE subject_id = ?');
            $stmt->execute([$subject_id]);
        } else {
            $stmt = $pdo->prepare('SELECT COUNT(*) FROM questions WHERE subject_id = ? AND unit_number = ?');
            $stmt->execute([$subject_id, $target_units]);
        }

        $available = (int) $stmt->fetchColumn();

        if ($available < $total_questions) {
            $unit_text = ($target_units === 'all') ? "This subject" : "Unit $target_units";
            $message = "$unit_text only has $available questions. You cannot configure an exam for $total_questions questions.";
            $message_type = 'error';
        } else {
            try {
                $creator_id = $_SESSION['admin_id'] ?? null;
                $stmt = $pdo->prepare("
                    INSERT INTO exams
                    (title, subject_id, duration_minutes, total_marks, total_questions_to_ask, access_pin, target_units, status, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'inactive', ?)
                ");
                $stmt->execute([$title, $subject_id, $duration, $total_marks, $total_questions, $access_pin ?: null, $target_units, $creator_id]);
                $newExamId = (int) $pdo->lastInsertId();

                log_admin_action($pdo, 'create_exam', 'exam', $newExamId, "Created exam: $title (Duration: {$duration}m, Marks: $total_marks, Qs: $total_questions)");

                $message = "Exam created successfully! Navigate to 'Control Exams' to start and monitor it.";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = safe_db_error($e, 'Failed to create examination.');
                $message_type = 'error';
            }
        }
    }
}

try {
    $subjects = $pdo->query('SELECT * FROM subjects ORDER BY name ASC')->fetchAll();
} catch (PDOException $e) {
    log_error('Failed to fetch subjects in manage-exam', $e);
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
                <select name="subject_id" id="subject_dropdown" required>
                    <option value="">-- Choose Subject --</option>
                    <?php foreach ($subjects as $sub): ?>
                        <option value="<?= $sub['id'] ?>" <?= (($_POST['subject_id'] ?? '') == $sub['id']) ? 'selected' : '' ?>>
                            <?= e($sub['name']) ?> (<?= e($sub['department']) ?>, Sem <?= e((string) $sub['semester']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Target Unit</label>
                <select name="target_units" required class="form-control">
                    <option value="">Select Target Unit</option>
                    <option value="all" <?= (($_POST['target_units'] ?? '') === 'all') ? 'selected' : '' ?>>All Units (Combined Exam)</option>
                    <option value="1" <?= (($_POST['target_units'] ?? '') == '1') ? 'selected' : '' ?>>Unit 1</option>
                    <option value="2" <?= (($_POST['target_units'] ?? '') == '2') ? 'selected' : '' ?>>Unit 2</option>
                    <option value="3" <?= (($_POST['target_units'] ?? '') == '3') ? 'selected' : '' ?>>Unit 3</option>
                    <option value="4" <?= (($_POST['target_units'] ?? '') == '4') ? 'selected' : '' ?>>Unit 4</option>
                    <option value="5" <?= (($_POST['target_units'] ?? '') == '5') ? 'selected' : '' ?>>Unit 5</option>
                    <option value="6" <?= (($_POST['target_units'] ?? '') == '6') ? 'selected' : '' ?>>Unit 6</option>
                </select>
                <small style="color: var(--color-text-secondary);">If the selected unit lacks enough questions, the system will notify you.</small>
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
                    <small style="color: var(--color-text-secondary);">Randomly picked per student from question pool</small>
                </div>

                <div class="form-group">
                    <label>Classroom PIN (Optional)</label>
                    <input type="text" name="access_pin" maxlength="10" placeholder="e.g. 4821" value="<?= e($_POST['access_pin'] ?? '') ?>">
                    <small style="color: var(--color-text-secondary);">Students must enter this PIN to start surprise test</small>
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

<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_subject'])) {
    verify_csrf();

    $name = clean_input($_POST['name'] ?? '');
    $department = clean_input($_POST['department'] ?? '');
    $semester = int_param($_POST['semester'] ?? 0);

    if (empty($name) || empty($department) || $semester < 1 || $semester > 8) {
        $message = "Please fill all fields correctly.";
        $message_type = 'error';
    } elseif (strlen($name) > 200) {
        $message = "Subject name cannot exceed 200 characters.";
        $message_type = 'error';
    } elseif (!in_array($department, ['BCA', 'BBA'], true)) {
        $message = "Invalid department selected.";
        $message_type = 'error';
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO subjects (name, department, semester, created_by) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $department, $semester, $_SESSION['admin_id'] ?? null]);
            $newSubId = (int) $pdo->lastInsertId();

            log_admin_action($pdo, 'create_subject', 'subject', $newSubId, "Created subject $name ($department, Sem $semester)");

            $message = "Subject created successfully!";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to create subject. Please check inputs.");
            $message_type = 'error';
        }
    }
}

try {
    $subjects = $pdo->query("
        SELECT
            s.*,
            a.name as creator_name,
            a.role as creator_role,
            a.status as creator_status,
            (SELECT COUNT(*) FROM questions WHERE subject_id = s.id) as question_count
        FROM subjects s
        LEFT JOIN admins a ON s.created_by = a.id
        ORDER BY s.id DESC
    ")->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to fetch subjects", $e);
    $subjects = [];
}

$page_title = 'Manage Subjects • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>Manage Curriculum Subjects</h1>
            <p>Add department subjects and configure question banks</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 24px; align-items: start;">
        <!-- Create Subject Form -->
        <div class="card">
            <div class="card-title">Add New Subject</div>
            <form method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label>Subject Name</label>
                    <input type="text" name="name" required placeholder="e.g. Cloud Computing" value="<?= e($_POST['name'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label>Department</label>
                    <select name="department" required>
                        <option value="">Select Department</option>
                        <option value="BCA" <?= (($_POST['department'] ?? '') === 'BCA') ? 'selected' : '' ?>>BCA</option>
                        <option value="BBA" <?= (($_POST['department'] ?? '') === 'BBA') ? 'selected' : '' ?>>BBA</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" required>
                        <option value="">Select Semester</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= (($_POST['semester'] ?? '') == $i) ? 'selected' : '' ?>>
                                Semester <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button type="submit" name="create_subject" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">add_circle</span> Add Subject
                </button>
            </form>
        </div>

        <!-- Subjects List Table -->
        <div class="card">
            <div class="card-title">Curriculum Subjects (<?= count($subjects) ?>)</div>

            <div style="margin-bottom: 10px;">
                <?php include '../components/searchbar.php' ?>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Department</th>
                            <th>Semester</th>
                            <th>Questions</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($subjects)): ?>
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--color-text-secondary); padding: 32px;">No subjects created yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($subjects as $sub): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($sub['name']) ?></strong>
                                        <div style="font-size: 0.74rem; color: var(--color-text-secondary); margin-top: 2px;">
                                            By: <strong><?= e($sub['creator_name'] ?? 'System') ?></strong>
                                            <?php if (($sub['creator_status'] ?? '') === 'retired'): ?>
                                                <span class="badge badge-warning" style="font-size: 0.62rem; padding: 1px 3px;">Retired</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><span class="badge badge-inactive"><?= e($sub['department']) ?></span></td>
                                    <td>Sem <?= e((string)$sub['semester']) ?></td>
                                    <td>
                                        <span class="badge badge-active"><?= (int)$sub['question_count'] ?> Qs</span>
                                    </td>
                                    <td style="text-align: right;">
                                        <a href="view-questions.php?subject_id=<?= $sub['id'] ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="material-symbols-outlined icon-xs">visibility</span> View Qs
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

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
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO subjects (name, department, semester) VALUES (?, ?, ?)");
            $stmt->execute([$name, $department, $semester]);
            $message = "Subject created successfully!";
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = safe_db_error($e, "Failed to create subject. Please check inputs.");
            $message_type = 'error';
        }
    }
}

try {
    $subjects = $pdo->query("SELECT * FROM subjects ORDER BY id DESC")->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to fetch subjects", $e);
    $subjects = [];
}

$page_title = 'Manage Subjects • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/navbar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>Manage Subjects</h1>
            <p>Create and organize curriculum subjects by department & semester</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- Create Subject Form -->
    <div class="card">
        <div class="card-title">Create New Subject</div>
        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Subject Name</label>
                <input type="text" name="name" required placeholder="e.g. Operating Systems" value="<?= e($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" required>
                        <option value="">-- Select Department --</option>
                        <option value="BCA" <?= (($_POST['department'] ?? '') === 'BCA') ? 'selected' : '' ?>>BCA</option>
                        <option value="BBA" <?= (($_POST['department'] ?? '') === 'BBA') ? 'selected' : '' ?>>BBA</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" required>
                        <option value="">-- Select Semester --</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= (($_POST['semester'] ?? '') == $i) ? 'selected' : '' ?>>Semester <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top: 16px;">
                <button type="submit" name="create_subject" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">add</span> Create Subject
                </button>
            </div>
        </form>
    </div>

    <!-- Existing Subjects Table -->
    <div class="card">
        <div class="card-title">Curriculum Subjects (<?= count($subjects) ?>)</div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width: 60px;">ID</th>
                        <th>Subject Name</th>
                        <th>Department</th>
                        <th>Semester</th>
                        <th>Date Created</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--color-text-secondary); padding: 32px;">No subjects created yet.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($subjects as $sub): ?>
                            <tr>
                                <td>#<?= e((string)$sub['id']) ?></td>
                                <td><strong><?= e($sub['name']) ?></strong></td>
                                <td><span class="badge badge-inactive"><?= e($sub['department']) ?></span></td>
                                <td>Sem <?= e((string)$sub['semester']) ?></td>
                                <td><?= date('d M Y', strtotime($sub['created_at'])) ?></td>
                                <td style="text-align: right;">
                                    <a href="view-questions.php?subject_id=<?= $sub['id'] ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                        <span class="material-symbols-outlined icon-xs">quiz</span> View Questions
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

<?php include __DIR__ . '/../components/footer.php'; ?>

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
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_subject'])) {
    verify_csrf();
    $subId = int_param($_POST['subject_id'] ?? 0);
    $name = clean_input($_POST['name'] ?? '');
    $department = clean_input($_POST['department'] ?? '');
    $semester = int_param($_POST['semester'] ?? 0);

    if ($subId <= 0 || empty($name) || empty($department) || $semester < 1 || $semester > 8) {
        $message = "Please fill all fields correctly.";
        $message_type = 'error';
    } elseif (strlen($name) > 200) {
        $message = "Subject name cannot exceed 200 characters.";
        $message_type = 'error';
    } elseif (!in_array($department, ['BCA', 'BBA'], true)) {
        $message = "Invalid department selected.";
        $message_type = 'error';
    } else {
        // Verify ownership
        $chk = $pdo->prepare("SELECT created_by FROM subjects WHERE id = ?");
        $chk->execute([$subId]);
        $creator = $chk->fetchColumn();
        $adminId = (int)($_SESSION['admin_id'] ?? 0);

        if (!is_superadmin() && (int)$creator !== $adminId) {
            $message = "Unauthorized: You can only edit subjects you created.";
            $message_type = 'error';
        } else {
            try {
                $up = $pdo->prepare("UPDATE subjects SET name = ?, department = ?, semester = ? WHERE id = ?");
                $up->execute([$name, $department, $semester, $subId]);
                log_admin_action($pdo, 'edit_subject', 'subject', $subId, "Updated subject $name ($department, Sem $semester)");
                $message = "Subject updated successfully!";
                $message_type = 'success';
            } catch (PDOException $e) {
                $message = safe_db_error($e, "Failed to update subject.");
                $message_type = 'error';
            }
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_subject'])) {
    verify_csrf();
    $subId = int_param($_POST['subject_id'] ?? 0);
    if ($subId > 0) {
        // Verify ownership
        $chk = $pdo->prepare("SELECT name, created_by FROM subjects WHERE id = ?");
        $chk->execute([$subId]);
        $sub = $chk->fetch();
        $adminId = (int)($_SESSION['admin_id'] ?? 0);

        if (!$sub) {
            $message = "Subject not found.";
            $message_type = 'error';
        } elseif (!is_superadmin() && (int)$sub['created_by'] !== $adminId) {
            $message = "Unauthorized: You can only delete subjects you created.";
            $message_type = 'error';
        } else {
            // Constraint check: check questions and exams attached
            $qCountStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE subject_id = ?");
            $qCountStmt->execute([$subId]);
            $qCount = (int)$qCountStmt->fetchColumn();

            $eCountStmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE subject_id = ?");
            $eCountStmt->execute([$subId]);
            $eCount = (int)$eCountStmt->fetchColumn();

            if ($qCount > 0 || $eCount > 0) {
                $message = "Cannot delete subject '{$sub['name']}': It has $qCount question(s) and $eCount examination(s) linked to it. Delete or reassign them first.";
                $message_type = 'error';
            } else {
                try {
                    $del = $pdo->prepare("DELETE FROM subjects WHERE id = ?");
                    $del->execute([$subId]);
                    log_admin_action($pdo, 'delete_subject', 'subject', $subId, "Deleted subject {$sub['name']} (#$subId)");
                    $message = "Subject deleted successfully.";
                    $message_type = 'success';
                } catch (PDOException $e) {
                    $message = safe_db_error($e, "Failed to delete subject.");
                    $message_type = 'error';
                }
            }
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
                                    <td style="text-align: right; white-space: nowrap;">
                                        <a href="view-questions.php?subject_id=<?= (int)$sub['id'] ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="material-symbols-outlined icon-xs">visibility</span> View Qs
                                        </a>
                                        <button type="button" class="btn btn-outline btn-sm btn-edit-subject"
                                            data-id="<?= (int)$sub['id'] ?>"
                                            data-name="<?= e($sub['name']) ?>"
                                            data-department="<?= e($sub['department']) ?>"
                                            data-semester="<?= (int)$sub['semester'] ?>"
                                            style="display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="material-symbols-outlined icon-xs">edit</span> Edit
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete subject \'<?= e(addslashes($sub['name'])) ?>\'?');">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="subject_id" value="<?= (int)$sub['id'] ?>">
                                            <button type="submit" name="delete_subject" class="btn btn-danger btn-sm" title="Delete Subject" style="display: inline-flex; align-items: center;">
                                                <span class="material-symbols-outlined icon-xs">delete</span>
                                            </button>
                                        </form>
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

<!-- Edit Subject Modal -->
<div id="editSubjectModal" class="modal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 9999; align-items: center; justify-content: center; padding: 20px;">
    <div class="card" style="max-width: 500px; width: 100%; margin: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h3 style="margin: 0;">Edit Subject</h3>
            <button type="button" id="closeSubModal" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="subject_id" id="modal_sub_id" value="">

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Subject Name</label>
                <input type="text" name="name" id="modal_sub_name" required class="form-control" style="width: 100%;">
            </div>

            <div class="form-group" style="margin-bottom: 14px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Department</label>
                <select name="department" id="modal_sub_dept" required class="form-control" style="width: 100%;">
                    <option value="BCA">BCA</option>
                    <option value="BBA">BBA</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: 600; display: block; margin-bottom: 4px;">Semester</label>
                <select name="semester" id="modal_sub_sem" required class="form-control" style="width: 100%;">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>">Semester <?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" id="cancelSubBtn" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="update_subject" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('editSubjectModal');
    const closeBtn = document.getElementById('closeSubModal');
    const cancelBtn = document.getElementById('cancelSubBtn');

    document.querySelectorAll('.btn-edit-subject').forEach(btn => {
        btn.addEventListener('click', () => {
            document.getElementById('modal_sub_id').value = btn.dataset.id;
            document.getElementById('modal_sub_name').value = btn.dataset.name;
            document.getElementById('modal_sub_dept').value = btn.dataset.department;
            document.getElementById('modal_sub_sem').value = btn.dataset.semester;

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

<?php include __DIR__ . '/../components/footer.php'; ?>

<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/CurriculumService.php';

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
    } elseif (!CurriculumService::isValidDepartment($pdo, $department)) {
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
    } elseif (!CurriculumService::isValidDepartment($pdo, $department)) {
        $message = "Invalid department selected.";
        $message_type = 'error';
    } else {
        if (!can_admin_manage_subject($pdo, $subId)) {
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
        $chk = $pdo->prepare("SELECT name FROM subjects WHERE id = ?");
        $chk->execute([$subId]);
        $sub = $chk->fetch();

        if (!$sub) {
            $message = "Subject not found.";
            $message_type = 'error';
        } elseif (!can_admin_manage_subject($pdo, $subId)) {
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

$totalSubjects = count($subjects);
$totalQuestionsCount = 0;
$departmentsList = [];
foreach ($subjects as $s) {
    $totalQuestionsCount += (int)($s['question_count'] ?? 0);
    if (!empty($s['department'])) {
        $departmentsList[$s['department']] = true;
    }
}
$totalDepartmentsCount = count($departmentsList);

$page_title = 'Manage Subjects • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<!-- <style>
.subjects-layout-grid {
    display: grid;
    grid-template-columns: 360px 1fr;
    gap: 24px;
    align-items: start;
}

.subjects-table {
    width: 100%;
    min-width: 580px;
}

.subject-actions {
    display: inline-flex;
    align-items: center;
    justify-content: flex-end;
    gap: 6px;
    flex-wrap: nowrap;
}

.card-header-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 16px;
}

@media (max-width: 1024px) {
    .subjects-layout-grid {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .mobile-add-btn {
        display: inline-flex !important;
    }
}

@media (max-width: 640px) {
    .page-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }

    .page-header h1 {
        font-size: 1.45rem;
    }

    .action-btn-label {
        display: none;
    }

    .subject-actions .btn-sm {
        padding: 6px 8px;
    }

    .subjects-table {
        min-width: 480px;
    }

    .table-wrap th,
    .table-wrap td {
        padding: 10px 12px;
    }

    .admin-modal-card {
        margin: 12px;
        max-width: calc(100% - 24px);
    }
}
</style> -->

<div class="container main-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; flex-wrap: wrap;">
        <div>
            <h1>Manage Curriculum Subjects</h1>
            <p>Add department subjects and configure question banks</p>
        </div>
        <!-- CHANGED: now a button that opens the Create Subject modal -->
        <button type="button" id="openCreateSubjectBtn" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-xs">add_circle</span> Create Subject
        </button>
    </div>

    <!-- Curriculum Stats Overview -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-num"><?= $totalSubjects ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">auto_stories</span> Total Subjects
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-primary, #33422e);">
            <div class="stat-num"><?= $totalQuestionsCount ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">quiz</span> Total Questions
            </div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-warning, #d97706);">
            <div class="stat-num"><?= $totalDepartmentsCount ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">domain</span> Active Departments
            </div>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <div class="subjects-layout-grid">
        <!-- CHANGED: the inline "Add New Subject" card was removed; the form now lives in the Create Subject modal below -->

        <!-- Subjects List Table -->
        <div class="card">
            <div class="card-header-bar">
                <div class="card-title" style="margin-bottom: 0; display: flex; align-items: center; gap: 8px;">
                    <span class="material-symbols-outlined icon-sm" style="color: var(--color-primary);">menu_book</span>
                    <span>Curriculum Subjects (<?= count($subjects) ?>)</span>
                </div>
            </div>

            <div style="margin-bottom: 14px;">
                <?php include '../components/searchbar.php' ?>
            </div>

            <div class="table-wrap">
                <table class="subjects-table">
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
                                        <div class="subject-actions">
                                            <a href="view-questions.php?subject_id=<?= (int)$sub['id'] ?>" class="btn btn-secondary btn-sm" title="View Questions" style="display: inline-flex; align-items: center; gap: 4px;">
                                                <span class="material-symbols-outlined icon-xs">visibility</span> <span class="action-btn-label">View Qs</span>
                                            </a>
                                            <button type="button" class="btn btn-outline btn-sm btn-edit-subject"
                                                data-id="<?= (int)$sub['id'] ?>"
                                                data-name="<?= e($sub['name']) ?>"
                                                data-department="<?= e($sub['department']) ?>"
                                                data-semester="<?= (int)$sub['semester'] ?>"
                                                title="Edit Subject"
                                                style="display: inline-flex; align-items: center; gap: 4px;">
                                                <span class="material-symbols-outlined icon-xs">edit</span> <span class="action-btn-label">Edit</span>
                                            </button>
                                            <!-- <form method="POST" style="display: inline;" data-confirm="Are you sure you want to delete subject '<?= e($sub['name']) ?>' and all associated questions?" data-confirm-title="Delete Subject" data-confirm-btn="Delete Subject">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="subject_id" value="<?= (int)$sub['id'] ?>">
                                                <button type="submit" name="delete_subject" class="btn btn-danger btn-sm" title="Delete Subject" style="display: inline-flex; align-items: center;">
                                                    <span class="material-symbols-outlined icon-xs">delete</span>
                                                </button>
                                            </form> -->
                                        </div>
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

<!-- CHANGED: Create Subject Modal (same structure/classes as the Edit Subject modal) -->
<div id="createSubjectModal" class="admin-modal-overlay">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><span class="material-symbols-outlined">add_circle</span> Add Subject</h3>
            <button type="button" class="admin-modal-close" id="closeCreateSubModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>

            <div class="admin-modal-body">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label for="create_sub_name" style="font-weight: 600; display: block; margin-bottom: 4px;">Subject Name</label>
                    <input type="text" name="name" id="create_sub_name" required placeholder="e.g. Cloud Computing" value="<?= e($_POST['name'] ?? '') ?>" class="form-control" style="width: 100%;">
                </div>

                <div class="form-group">
                    <label for="new_sub_dept">Department</label>
                    <select id="new_sub_dept" name="department" required class="form-control" style="width: 100%; box-sizing: border-box;">
                        <option value="">Select Department</option>
                        <?php foreach (CurriculumService::getDepartments($pdo) as $d): ?>
                            <option value="<?= e($d) ?>" <?= (($_POST['department'] ?? '') === $d) ? 'selected' : '' ?>><?= e($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label for="create_sub_sem" style="font-weight: 600; display: block; margin-bottom: 4px;">Semester</label>
                    <select name="semester" id="create_sub_sem" required class="form-control" style="width: 100%;">
                        <option value="">Select Semester</option>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= (($_POST['semester'] ?? '') == $i) ? 'selected' : '' ?>>Semester <?= $i ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div class="admin-modal-footer">
                <button type="button" id="cancelCreateSubBtn" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="create_subject" class="btn btn-primary">Create</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Subject Modal (unchanged) -->
<div id="editSubjectModal" class="admin-modal-overlay">
    <div class="admin-modal-card">
        <div class="admin-modal-header">
            <h3><span class="material-symbols-outlined">edit</span> Edit Subject</h3>
            <button type="button" class="admin-modal-close" id="closeSubModal">&times;</button>
        </div>
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="subject_id" id="modal_sub_id" value="">

            <div class="admin-modal-body">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;">Subject Name</label>
                    <input type="text" name="name" id="modal_sub_name" required class="form-control" style="width: 100%;">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label style="font-weight: 600; display: block; margin-bottom: 4px;">Department</label>
                    <select name="department" id="modal_sub_dept" required class="form-control" style="width: 100%;">
                        <?php foreach (CurriculumService::getDepartments($pdo) as $d): ?>
                            <option value="<?= e($d) ?>"><?= e($d) ?></option>
                        <?php endforeach; ?>
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
            </div>

            <div class="admin-modal-footer">
                <button type="button" id="cancelSubBtn" class="btn btn-secondary">Cancel</button>
                <button type="submit" name="update_subject" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    // ---------- Edit Subject modal (unchanged) ----------
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

    // ---------- CHANGED: Create Subject modal (same pattern as edit) ----------
    const createModal = document.getElementById('createSubjectModal');
    const openCreateBtn = document.getElementById('openCreateSubjectBtn');
    const closeCreateBtn = document.getElementById('closeCreateSubModal');
    const cancelCreateBtn = document.getElementById('cancelCreateSubBtn');

    const showCreateModal = () => { if (createModal) createModal.style.display = 'flex'; };
    const hideCreateModal = () => { if (createModal) createModal.style.display = 'none'; };

    if (openCreateBtn) openCreateBtn.onclick = showCreateModal;
    if (closeCreateBtn) closeCreateBtn.onclick = hideCreateModal;
    if (cancelCreateBtn) cancelCreateBtn.onclick = hideCreateModal;
    if (createModal) {
        createModal.onclick = (e) => { if (e.target === createModal) hideCreateModal(); };
    }

    // Reopen the create modal if the server re-rendered the page after a failed create
    <?php if (!empty($_POST['create_subject']) && (($message_type ?? '') !== 'success')): ?>
    showCreateModal();
    <?php endif; ?>
});
</script>

<?php
include __DIR__ . '/../components/confirm-modal.php';
include __DIR__ . '/../components/footer.php';
?>

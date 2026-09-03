<?php
require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/csrf.php';

$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_update_semester'])) {
    verify_csrf();
    
    $student_ids = $_POST['student_ids'] ?? [];
    $new_semester = (int)($_POST['new_semester'] ?? 0);

    if (empty($student_ids) || !is_array($student_ids)) {
        $message = "Please select at least one student.";
        $message_type = "error";
    } elseif ($new_semester <= 0) {
        $message = "Please enter a valid semester number.";
        $message_type = "error";
    } else {
        try {
            $placeholders = implode(',', array_fill(0, count($student_ids), '?'));

            $sql = "UPDATE students SET semester = ? WHERE id IN ($placeholders)";
            $stmt = $pdo->prepare($sql);

            $params = array_merge([$new_semester], $student_ids);
            $stmt->execute($params);

            $updated_count = $stmt->rowCount();
            $message = "$updated_count student(s) successfully updated to Semester $new_semester.";
            $message_type = "success";
        } catch (PDOException $e) {
            $message = "Database Error: Failed to update students.";
            $message_type = "error";
        }
    }
}

$filter_dept = $_GET['department'] ?? '';
$filter_sem = $_GET['semester'] ?? '';

try {
    $deptStmt = $pdo->query("SELECT DISTINCT department FROM students WHERE department IS NOT NULL AND department != '' ORDER BY department ASC");
    $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

    $semStmt = $pdo->query("SELECT DISTINCT semester FROM students WHERE semester IS NOT NULL AND semester != '' ORDER BY semester ASC");
    $semesters = $semStmt->fetchAll(PDO::FETCH_COLUMN);

    $query = "SELECT id, name, email, department, semester, created_at FROM students WHERE 1=1";
    $params = [];

    if (!empty($filter_dept)) {
        $query .= " AND department = ?";
        $params[] = $filter_dept;
    }

    if (!empty($filter_sem)) {
        $query .= " AND semester = ?";
        $params[] = $filter_sem;
    }

    $query .= " ORDER BY name ASC";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}

$page_title = 'Manage Students • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>Manage Students</h1>
            <p>View, filter, and bulk-update student semesters.</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?= $message_type === 'success' ? 'success' : 'error' ?>">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <!-- Filter Form (GET) -->
    <div class="card" style="margin-bottom: 24px;">
        <form method="GET" action="manage-students.php" style="display: flex; gap: 16px; align-items: flex-end; flex-wrap: wrap;">
            
            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                <label>Filter by Department</label>
                <select name="department" class="form-control">
                    <option value="">-- All Departments --</option>
                    <?php foreach ($departments as $dept): ?>
                        <option value="<?= e($dept) ?>" <?= ($filter_dept === $dept) ? 'selected' : '' ?>>
                            <?= e($dept) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 0; flex: 1; min-width: 200px;">
                <label>Filter by Semester</label>
                <select name="semester" class="form-control">
                    <option value="">-- All Semesters --</option>
                    <?php foreach ($semesters as $sem): ?>
                        <option value="<?= e($sem) ?>" <?= ($filter_sem == $sem) ? 'selected' : '' ?>>
                            Semester <?= e($sem) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">filter_list</span> Apply
                </button>
                <a href="manage-students.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">restart_alt</span> Reset
                </a>
            </div>
        </form>
    </div>

    <!-- Bulk Update Form (POST) wrapping the Table -->
    <div class="card">
        <form method="POST" action="manage-students.php?department=<?= urlencode($filter_dept) ?>&semester=<?= urlencode($filter_sem) ?>">
            <?= csrf_field() ?>
            
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left;">
                    <thead>
                        <tr style="border-bottom: 2px solid var(--color-border); background: #f8fafc;">
                            <th style="padding: 12px; width: 40px;">
                                <input type="checkbox" id="selectAll">
                            </th>
                            <th style="padding: 12px;">ID</th>
                            <th style="padding: 12px;">Name</th>
                            <th style="padding: 12px;">Email</th>
                            <th style="padding: 12px;">Department</th>
                            <th style="padding: 12px;">Semester</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                            <tr>
                                <td colspan="6" style="padding: 20px; text-align: center; color: var(--color-text-secondary);">
                                    No students found matching your filters.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($students as $student): ?>
                                <tr style="border-bottom: 1px solid var(--color-border);">
                                    <td style="padding: 12px;">
                                        <input type="checkbox" name="student_ids[]" value="<?= e($student['id']) ?>" class="student-cb">
                                    </td>
                                    <td style="padding: 12px;">#<?= e($student['id']) ?></td>
                                    <td style="padding: 12px; font-weight: bold;"><?= e($student['name']) ?></td>
                                    <td style="padding: 12px;"><?= e($student['email']) ?></td>
                                    <td style="padding: 12px;"><?= e($student['department']) ?></td>
                                    <td style="padding: 12px;">Sem <?= e($student['semester']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Bulk Action Footer -->
            <?php if (!empty($students)): ?>
                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                    <div style="font-size: 0.9em; color: var(--color-text-secondary);">
                        Showing <?= count($students) ?> student(s). Select students to update.
                    </div>

                    <div style="display: flex; gap: 12px; align-items: center;">
                        <label style="font-weight: bold;">Update to Semester:</label>
                        <input type="number" name="new_semester" min="1" max="8" required class="form-control" style="width: 100px; margin-bottom: 0;" placeholder="e.g. 3">
                        <button type="submit" name="bulk_update_semester" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                            <span class="material-symbols-outlined icon-sm">upgrade</span> Update Semester
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const selectAllCb = document.getElementById('selectAll');
    const studentCbs = document.querySelectorAll('.student-cb');

    if (selectAllCb) {
        selectAllCb.addEventListener('change', function() {
            studentCbs.forEach(cb => {
                cb.checked = selectAllCb.checked;
            });
        });
    }
});
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>

<?php
require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';

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
    
    // 4. Execute the query
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
            <p>View and filter enrolled students by department and semester.</p>
        </div>
    </div>

    <!-- Filter Form -->
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

    <!-- Students Data Table -->
    <div class="card">
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 2px solid var(--color-border); background: #f8fafc;">
                        <th style="padding: 12px;">ID</th>
                        <th style="padding: 12px;">Name</th>
                        <th style="padding: 12px;">Email</th>
                        <th style="padding: 12px;">Department</th>
                        <th style="padding: 12px;">Semester</th>
                        <th style="padding: 12px;">Joined</th>
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
                                <td style="padding: 12px;">#<?= e($student['id']) ?></td>
                                <td style="padding: 12px; font-weight: bold;"><?= e($student['name']) ?></td>
                                <td style="padding: 12px;"><?= e($student['email']) ?></td>
                                <td style="padding: 12px;"><?= e($student['department']) ?></td>
                                <td style="padding: 12px;">Sem <?= e($student['semester']) ?></td>
                                <td style="padding: 12px; font-size: 0.9em; color: var(--color-text-secondary);">
                                    <?= date('M j, Y', strtotime($student['created_at'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 16px; font-size: 0.9em; color: var(--color-text-secondary);">
            Showing <?= count($students) ?> student(s).
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$selected_dept = clean_input($_GET['department'] ?? 'All');

try {
    $deptStmt = $pdo->query("SELECT DISTINCT department FROM subjects ORDER BY department");
    $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

    $params = [];
    $sql = "SELECT e.id, e.title, e.total_marks, s.department, s.semester,
            (SELECT COUNT(*) FROM exam_attempts
            WHERE exam_id = e.id AND status = 'completed') AS total_attempts
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id";

    if ($selected_dept !== 'All') {
        $sql .= " WHERE s.department = :dept";
        $params[':dept'] = $selected_dept;
    }

    $sql .= " ORDER BY e.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $exams = $stmt->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to fetch exam results list", $e);
    $departments = [];
    $exams = [];
}

$page_title = 'Results Dashboard • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/navbar.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1>Results & Performance Dashboard</h1>
            <p>View graded submissions, score distributions, and class performance</p>
        </div>
    </div>

    <div class="card">
        <!-- Department Filters -->
        <div class="filters">
            <a href="results.php?department=All" class="filter <?= $selected_dept === 'All' ? 'active' : '' ?>">
                All Departments
            </a>
            <?php foreach ($departments as $dept): ?>
                <a href="results.php?department=<?= urlencode($dept) ?>" class="filter <?= $selected_dept === $dept ? 'active' : '' ?>">
                    <?= e($dept) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Exam Title</th>
                        <th>Department</th>
                        <th>Semester</th>
                        <th>Max Marks</th>
                        <th>Submissions</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: var(--color-text-secondary); padding: 32px;">
                                No exams found for <strong><?= e($selected_dept) ?></strong>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exams as $exam): ?>
                            <tr>
                                <td><strong><?= e($exam['title']) ?></strong></td>
                                <td><span class="badge badge-inactive"><?= e($exam['department']) ?></span></td>
                                <td>Sem <?= e((string)$exam['semester']) ?></td>
                                <td><?= e((string)$exam['total_marks']) ?> marks</td>
                                <td>
                                    <span class="badge badge-active">
                                        <?= e((string)$exam['total_attempts']) ?> submissions
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="view-results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-primary btn-sm">
                                        View Results
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

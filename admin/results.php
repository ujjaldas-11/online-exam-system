<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$selected_dept = clean_input($_GET['department'] ?? 'All');

try {
    $deptStmt = $pdo->query('SELECT DISTINCT department FROM subjects ORDER BY department');
    $departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

    $params = [];
    $sql = "SELECT e.id, e.title, e.total_marks, e.results_published, s.department, s.semester,
            a.name as creator_name, a.status as creator_status,
            (SELECT COUNT(*) FROM exam_attempts
            WHERE exam_id = e.id AND status = 'completed') AS total_attempts
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN admins a ON e.created_by = a.id";

    if ($selected_dept !== 'All') {
        $sql .= ' WHERE s.department = :dept';
        $params[':dept'] = $selected_dept;
    }

    $sql .= ' ORDER BY e.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $exams = $stmt->fetchAll();
} catch (PDOException $e) {
    log_error('Failed to fetch exam results list', $e);
    $departments = [];
    $exams = [];
}

$page_title = 'Results Dashboard • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>Results & Performance Dashboard</h1>
            <p>View graded submissions, score distributions, and class performance</p>
        </div>
    </div>

    <div class="card">
        <div style="margin-bottom: 10px;">
            <?php include '../components/searchbar.php' ?>
        </div>
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
                        <th>Results Status</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; color: var(--color-text-secondary); padding: 32px;">
                                No exams found for <strong><?= e($selected_dept) ?></strong>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exams as $exam): ?>
                            <tr>
                                <td>
                                    <strong><?= e($exam['title']) ?></strong>
                                    <div style="font-size: 0.78rem; color: var(--color-text-secondary); margin-top: 2px;">
                                        Author: <strong><?= e($exam['creator_name'] ?? 'System') ?></strong>
                                        <?php if (($exam['creator_status'] ?? '') === 'retired'): ?>
                                            <span class="badge badge-warning" style="font-size: 0.65rem; padding: 1px 4px;">Retired</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><span class="badge badge-inactive"><?= e($exam['department']) ?></span></td>
                                <td>Sem <?= e((string) $exam['semester']) ?></td>
                                <td><?= e((string) $exam['total_marks']) ?> marks</td>
                                <td>
                                    <span class="badge badge-active">
                                        <?= e((string) $exam['total_attempts']) ?> submissions
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($exam['results_published'])): ?>
                                        <span class="badge badge-active" style="display: inline-flex; align-items: center; gap: 4px;" title="Scores and answer reviews are visible to students">
                                            <span class="material-symbols-outlined icon-xs">visibility</span> Published
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-warning" style="display: inline-flex; align-items: center; gap: 4px;" title="Scores and answer reviews are hidden from students">
                                            <span class="material-symbols-outlined icon-xs">visibility_off</span> Unpublished
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                        <a href="view-results.php?exam_id=<?= $exam['id'] ?>" class="btn btn-primary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="material-symbols-outlined icon-xs">leaderboard</span> View Results
                                        </a>
                                        <!-- <a href="export-pdf.php?exam_id=<?= $exam['id'] ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;" title="Download Results PDF">
                                            <span class="material-symbols-outlined icon-xs">picture_as_pdf</span> PDF
                                        </a> -->
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

<?php include __DIR__ . '/../components/footer.php'; ?>

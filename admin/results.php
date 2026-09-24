<?php
require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/CurriculumService.php';

// --- Data Fetching & Filter Query ---
$form_action = 'results.php';
$show_dept = true;
$show_sem = true;
$show_author = true;
$search_placeholder = "Search by exam name";


$departments = CurriculumService::getDepartments($pdo);

try {
    $authors_list = $pdo->query("SELECT id, name FROM admins ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $authors_list = [];
}


$filterQ = clean_input($_GET['q'] ?? '');
$filterDept = clean_input($_GET['department'] ?? '');
$filterSem = isset($_GET['semester']) ? (int)$_GET['semester'] : 0;
$filterAuthor = isset($_GET['author']) ? (int)$_GET['author'] : 0;

$queryWhere = ["e.status='ended'"];
$queryParams = [];

if ($filterQ !== '') {
    $queryWhere[] = "(e.title LIKE ?)"; 
    $queryParams[] = "%$filterQ%";
}
if ($filterDept !== '') {
    $queryWhere[] = "s.department = ?";
    $queryParams[] = $filterDept;
}
if ($filterSem > 0 && $filterSem <= 8) {
    $queryWhere[] = "s.semester = ?";
    $queryParams[] = $filterSem;
}

if ($filterAuthor > 0) {
    $queryWhere[] = "e.created_by = ?";
    $queryParams[] = $filterAuthor;
}


$whereClause = "WHERE " . implode(" AND ", $queryWhere);

// Setup Pagination Variables
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10; // Number of exams per page
$total_result_count = 0;
$exams = [];

try {

    $totalCount = (int) $pdo->query("SELECT COUNT(*) FROM exams WHERE status='ended'")->fetchColumn();

    // Total count for current filter
    $countSql = "SELECT COUNT(*) FROM exams e 
                 JOIN subjects s ON e.subject_id = s.id 
                 LEFT JOIN admins a ON e.created_by = a.id $whereClause";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($queryParams);
    $total_result_count = (int) $countStmt->fetchColumn();

    $offset = ($current_page - 1) * $per_page;

    $sql = "SELECT e.id, e.title, e.total_marks, e.results_published, s.department, s.semester,
            a.name as creator_name, a.status as creator_status,
            (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND status = 'completed') AS total_attempts
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN admins a ON e.created_by = a.id 
            $whereClause
            ORDER BY e.created_at DESC 
            LIMIT $per_page
            OFFSET $offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($queryParams);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    log_error('Failed to fetch exam results list', $e);
}

$page_title = 'Results Dashboard • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>
    <div class="page-header">
        <div>
            <h1>Results & Performance Dashboard</h1>
            <p>View graded submissions, score distributions, and class performance</p>
        </div>
    </div>

    <?php include __DIR__ . '/../components/filter-bar.php' ?>
    <div class="card">

        <div class="card-title">Total Results(<?= $total_result_count ?>)</div>

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
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        
        <?php
        // Include the pagination UI component
        $total_items = $total_result_count;
        include __DIR__ . '/../components/pagination.php';
        ?>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

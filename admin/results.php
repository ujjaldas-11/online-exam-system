<?php
require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/CurriculumService.php';

$selected_dept = clean_input($_GET['department'] ?? 'All');

// Setup Pagination Variables
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10; // Number of exams per page
$total_result_count = 0;
$exams = [];
$departments = [];

try {
    $departments = CurriculumService::getDepartments($pdo);

    // Build the dynamic WHERE clause for filters
    $whereClause = "";
    if ($selected_dept !== 'All') {
        $whereClause = " WHERE s.department = :dept";
    }

    // Get Total Count for Pagination
    $countSql = "SELECT COUNT(*) FROM exams e JOIN subjects s ON e.subject_id = s.id" . $whereClause;
    $countStmt = $pdo->prepare($countSql);
    if ($selected_dept !== 'All') {
        $countStmt->bindValue(':dept', $selected_dept, PDO::PARAM_STR);
    }
    $countStmt->execute();
    $total_result_count = (int)$countStmt->fetchColumn();

    // Fetch the specific page of data using LIMIT and OFFSET
    $offset = ($current_page - 1) * $per_page;

    $sql = "SELECT e.id, e.title, e.total_marks, e.results_published, s.department, s.semester,
            a.name as creator_name, a.status as creator_status,
            (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND status = 'completed') AS total_attempts
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN admins a ON e.created_by = a.id 
            $whereClause
            ORDER BY e.created_at DESC 
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    
    // Securely bind all parameters (PDO requires LIMIT/OFFSET to be strictly integers)
    if ($selected_dept !== 'All') {
        $stmt->bindValue(':dept', $selected_dept, PDO::PARAM_STR);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
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

    <div class="card">
        <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
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
            <div style="width: 30%;">
                <?php include '../components/searchbar.php' ?>
            </div>
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

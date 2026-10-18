<?php
require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$student_id = (int) $_SESSION['student_id'];

// ---  Filter & Pagination Setup ---
$form_action = 'exam-history.php';
$search_placeholder = 'Search by exam or subject...';
$show_status = false;
$show_dept = false;
$show_sem = true; // Enables the semester dropdown from filter-bar.php
$show_author = false;

$filterQ = trim(clean_input($_GET['q'] ?? ''));
$filterSem = isset($_GET['semester']) ? (int) $_GET['semester'] : 0;
$current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;

$queryWhere = ["ea.student_id = ?", "ea.status IN ('completed', 'disqualified')"];
$queryParams = [$student_id];

// Search both Exam Title and Subject Name
if ($filterQ !== '') {
    $queryWhere[] = "(e.title LIKE ? OR s.name LIKE ?)";
    $queryParams[] = "%$filterQ%";
    $queryParams[] = "%$filterQ%";
}

// Apply Semester Filter
if ($filterSem > 0) {
    $queryWhere[] = "s.semester = ?";
    $queryParams[] = $filterSem;
}

$whereClause = "WHERE " . implode(" AND ", $queryWhere);

try {
    // Get total count for pagination (Added JOIN subjects)
    $countSql = "
        SELECT COUNT(*) 
        FROM exam_attempts ea 
        JOIN exams e ON ea.exam_id = e.id 
        JOIN subjects s ON e.subject_id = s.id 
        $whereClause
    ";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($queryParams);
    $total_items = (int) $countStmt->fetchColumn();

    $offset = ($current_page - 1) * $per_page;

    // Fetch paginated results (Added JOIN subjects & historical semester)
    $resultStmt = $pdo->prepare("
        SELECT 
            e.title, e.total_marks, 
            ea.id AS attempt_id, ea.score, ea.total_questions, ea.submitted_at,
            s.name AS subject_name, s.semester AS exam_semester
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN subjects s ON e.subject_id = s.id
        $whereClause
        ORDER BY ea.submitted_at DESC
        LIMIT $per_page OFFSET $offset
    ");
    $resultStmt->execute($queryParams);
    $past_results = $resultStmt->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to load exam history for student $student_id", $e);
    die('Database Error. Please try again later.');
}

$page_title = 'Exam History • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<div class="container history-page">
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <div class="page-header history-header">
        <div>
            <h1 class="page-title">Exam History</h1>
            <p class="page-subtitle">
                You have successfully completed
                <?= e((string) $total_items) ?> 
                <?= $total_items === 1 ? 'exam' : 'exams' ?> 
            </p>
        </div>
        <a href="profile.php" class="btn btn-secondary">
            <span class="material-symbols-outlined icon-sm">arrow_back</span>
            Back to Profile
        </a>
    </div>

    <!-- Global Filter Bar -->
    <?php include __DIR__ . '/../components/filter-bar.php'; ?>

    <div class="card">
        <div class="card-body" style="padding: 0;">
            <?php if (empty($past_results)): ?>
                <div class="empty-state">
                    <span class="material-symbols-outlined empty-icon">assignment</span>
                    <p>No examinations found matching your criteria.</p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="table" style="margin: 0;">
                        <thead>
                            <tr>
                                <th>Exam Details</th>
                                <th>Semester</th>
                                <th>Score</th>
                                <th>Submitted On</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past_results as $result): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($result['title']) ?></strong>
                                        <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 2px;">
                                            <?= e($result['subject_name']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-info">Sem <?= e((string) $result['exam_semester']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge badge-success">
                                            <?= sprintf('%.2f', (float) $result['score']) ?>
                                            <span class="score-divider">/</span>
                                            <?= e((string) $result['total_marks']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <?= $result['submitted_at']
                                            ? date('d M Y, h:i A', strtotime($result['submitted_at']))
                                            : '—' ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="download-card.php?attempt_id=<?= (int) $result['attempt_id'] ?>"
                                               class="btn btn-secondary btn-sm"
                                               title="Download Score Card">
                                                <span class="material-symbols-outlined icon-xs">picture_as_pdf</span>
                                                Score Card
                                            </a>
                                            <a href="review-exam.php?attempt_id=<?= (int) $result['attempt_id'] ?>"
                                               class="btn btn-primary btn-sm">
                                                Review Exam
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Global Pagination -->
        <?php if (!empty($past_results)): ?>
            <?php include __DIR__ . '/../components/pagination.php'; ?>
        <?php endif; ?>
    </div>
</div>

<style>
    /* Layout only — colors come from the existing theme */
    .history-page {
        padding: 30px;
    }

    .history-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 24px;
    }

    .empty-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 10px;
        text-align: center;
        min-height: 200px;
        padding: 28px;
    }

    .empty-icon {
        font-size: 38px;
        color: var(--color-text-secondary);
    }

    .action-buttons {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 8px;
        flex-wrap: wrap;
    }

    .score-divider {
        opacity: 0.7;
        margin: 0 4px;
    }

    @media (max-width: 560px) {
        .history-page {
            padding: 18px;
        }
        .history-header {
            flex-direction: column;
        }
        .action-buttons {
            justify-content: flex-start;
        }
    }
</style>

<?php include __DIR__ . '/../components/footer.php'; ?>

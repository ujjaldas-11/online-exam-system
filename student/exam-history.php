<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$student_id = (int) $_SESSION['student_id'];

try {
    $resultStmt = $pdo->prepare("
        SELECT e.title, e.total_marks, ea.id AS attempt_id, ea.score, ea.total_questions, ea.submitted_at
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.student_id = ? AND ea.status = 'completed'
        ORDER BY ea.submitted_at DESC
    ");
    $resultStmt->execute([$student_id]);
    $past_results = $resultStmt->fetchAll();
} catch (PDOException $e) {
    log_error("Failed to load exam history for student $student_id", $e);
    die('Database Error. Please try again later.');
}

$total_attempts = count($past_results);

$page_title = 'Exam History • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<div class="history-page">
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <div class="page-header history-header">
        <div>
            <h1 class="page-title">Exam History</h1>
            <p class="page-subtitle">
                <?= e((string) $total_attempts) ?> completed
                <?= $total_attempts === 1 ? 'exam' : 'exams' ?>
            </p>
        </div>
        <a href="profile.php" class="btn btn-secondary">
            <span class="material-symbols-outlined icon-sm">arrow_back</span>
            Back to Profile
        </a>
    </div>

    <div class="card">
        <div class="card-body">

            <?php if (empty($past_results)): ?>
                <div class="empty-state">
                    <span class="material-symbols-outlined empty-icon">assignment</span>
                    <p>You haven't completed any examinations yet.</p>
                </div>
            <?php else: ?>

                <div class="table-toolbar">
                    <?php include '../components/searchbar.php'; ?>
                </div>

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Exam Title</th>
                                <th>Score</th>
                                <th>Submitted On</th>
                                <th class="text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($past_results as $result): ?>
                                <tr>
                                    <td><strong><?= e($result['title']) ?></strong></td>
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
                                    <td class="text-right">
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

    .table-toolbar {
        margin-bottom: 16px;
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
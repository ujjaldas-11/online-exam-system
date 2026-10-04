<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$student_id = (int) $_SESSION['student_id'];

try {
    $stmt = $pdo->prepare('SELECT name, email, roll_number, department, semester FROM students WHERE id = ?');
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        die('Student record not found.');
    }

    $resultStmt = $pdo->prepare("
        SELECT e.title, e.total_marks, ea.id AS attempt_id, ea.score, ea.total_questions, ea.submitted_at
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.student_id = ? AND ea.status = 'completed'
        ORDER BY ea.submitted_at DESC
    ");
    $resultStmt->execute([$student_id]);
    $past_results = $resultStmt->fetchAll();

    $completed_exams = count($past_results);
    $average_score = 0.0;
    $best_score = 0.0;
    $last_submission = null;

    if ($completed_exams > 0) {
        $scores = array_map(static fn($result) => (float) $result['score'], $past_results);
        $average_score = count($scores) > 0 ? array_sum($scores) / count($scores) : 0.0;
        $best_score = max($scores);
        $last_submission = $past_results[0]['submitted_at'] ?? null;
    }

} catch (PDOException $e) {
    log_error("Failed to load profile for student $student_id", $e);
    die('Database Error. Please try again later.');
}

$page_title = 'My Profile • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<div class="container profile-page">
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <div class="page-header profile-header">
        <div>
            <span class="eyebrow">Student Portfolio</span>
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle">Track your academic profile and examination progress</p>
        </div>
        <a href="edit-profile.php" class="btn btn-primary">
            <span class="material-symbols-outlined icon-sm">edit</span>
            Edit Profile
        </a>
    </div>

    <div class="card profile-hero">
        <div class="profile-hero__main">
            <div class="profile-avatar" aria-label="Student profile avatar">
                <?= e(strtoupper(substr($student['name'], 0, 1))) ?>
            </div>
            <div class="profile-identity">
                <span class="profile-role">Student</span>
                <h2><?= e($student['name']) ?></h2>
                <p><?= e($student['department']) ?> • Semester <?= e((string)$student['semester']) ?></p>
            </div>
        </div>

        <div class="profile-hero__meta">
            <div class="meta-chip">
                <span class="material-symbols-outlined icon-sm">badge</span>
                <?= e($student['roll_number']) ?>
            </div>
            <div class="meta-chip">
                <span class="material-symbols-outlined icon-sm">mail</span>
                <?= e($student['email']) ?>
            </div>
        </div>
    </div>

    <div class="stats profile-stats">
        <div class="stat-card">
            <div class="stat-num"><?= e((string) $completed_exams) ?></div>
            <div class="stat-label">Completed Exams</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= sprintf('%.1f', $average_score) ?></div>
            <div class="stat-label">Average Score</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= sprintf('%.1f', $best_score) ?></div>
            <div class="stat-label">Best Score</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= $last_submission ? date('d M', strtotime($last_submission)) : '—' ?></div>
            <div class="stat-label">Last Submission</div>
        </div>
    </div>

    <div class="card profile-history">
        <div class="card-header">
            <h2 class="card-title">
                <span class="material-symbols-outlined">history_edu</span>
                Exam History
            </h2>
        </div>

        <div class="card-body">
            <div class="table-toolbar">
                <?php include '../components/searchbar.php'; ?>
            </div>

            <?php if (empty($past_results)): ?>
                <div class="empty-state">
                    <span class="material-symbols-outlined empty-icon">assignment</span>
                    <p>You haven't completed any examinations yet.</p>
                </div>
            <?php else: ?>
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
                                            <?= sprintf('%.2f', (float)$result['score']) ?>
                                            <span class="score-divider">/</span>
                                            <?= e((string)$result['total_marks']) ?>
                                        </span>
                                    </td>
                                    <td class="text-muted">
                                        <?= $result['submitted_at']
                                            ? date('d M Y, h:i A', strtotime($result['submitted_at']))
                                            : '—' ?>
                                    </td>
                                    <td class="text-right">
                                        <div class="action-buttons">
                                            <a href="download-card.php?attempt_id=<?= $result['attempt_id'] ?>"
                                               class="btn btn-secondary btn-sm"
                                               title="Download Score Card">
                                                <span class="material-symbols-outlined icon-xs">picture_as_pdf</span>
                                                Score Card
                                            </a>
                                            <a href="review-exam.php?attempt_id=<?= $result['attempt_id'] ?>"
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
    .profile-page {
        height: calc(100vh - var(--nav-h, 54px));
        height: calc(100dvh - var(--nav-h, 54px));
        min-height: 0;
        padding: 16px 20px 18px;
        display: flex;
        flex-direction: column;
        gap: 12px;
        overflow: hidden;
    }

    .profile-header {
        flex-shrink: 0;
        margin-bottom: 0;
        gap: 12px;
    }

    .profile-header .page-title {
        font-size: 1.55rem;
        line-height: 1.2;
    }

    .profile-header .page-subtitle {
        margin-top: 4px;
        font-size: 0.88rem;
    }

    .profile-page > .card {
        margin: 0;
    }

    .eyebrow {
        display: inline-block;
        margin-bottom: 4px;
        font-size: 0.68rem;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        font-weight: 700;
        color: var(--color-primary);
    }

    .profile-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-shrink: 0;
        padding: 14px 18px;
        background: #eff6ff;
        border: 1px solid rgba(15, 23, 42, 0.12);
    }

    .profile-hero__main {
        display: flex;
        align-items: center;
        gap: 14px;
        min-width: 0;
    }

    .profile-avatar {
        width: 58px;
        height: 58px;
        flex-shrink: 0;
        border-radius: 17px;
        background: var(--color-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.55rem;
    }

    .profile-role {
        display: inline-block;
        margin-bottom: 2px;
        font-size: 0.66rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--color-primary-light);
    }

    .profile-identity h2 {
        margin: 0;
        font-size: 1.45rem;
        color: var(--color-dark);
        line-height: 1.2;
        overflow-wrap: anywhere;
    }

    .profile-identity p {
        margin-top: 3px;
        color: var(--color-text-secondary);
        font-size: 0.85rem;
    }

    .profile-hero__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        justify-content: flex-end;
    }

    .meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 12px;
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 999px;
        color: var(--color-text);
        font-size: 0.8rem;
        font-weight: 600;
        overflow-wrap: anywhere;
    }

    .meta-chip .material-symbols-outlined {
        flex-shrink: 0;
        color: var(--color-primary);
        font-size: 18px;
    }

    .profile-stats {
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 10px;
        flex-shrink: 0;
        margin: 0;
    }

    .profile-stats .stat-card {
        min-width: 0;
        padding: 8px 12px;
    }

    .profile-stats .stat-num {
        font-size: 1.45rem;
        line-height: 1.15;
    }

    .profile-stats .stat-label {
        margin-top: 3px;
        font-size: 0.68rem;
        line-height: 1.25;
    }

    .profile-history {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
        margin: 0;
        padding: 16px 20px;
    }

    .profile-history .card-header {
        flex-shrink: 0;
        padding-bottom: 10px;
        border-bottom: 1px solid var(--color-border);
        margin-bottom: 10px;
    }

    .profile-history .card-title {
        display: flex;
        align-items: center;
        gap: 8px;
        margin: 0;
        font-size: 1.1rem;
    }

    .profile-history .card-body {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        min-height: 0;
    }

    .table-toolbar {
        flex-shrink: 0;
        margin-bottom: 10px;
    }

    .profile-history .search-wrapper {
        margin: 0;
    }

    .profile-history .search-input {
        padding-top: 8px;
        padding-bottom: 8px;
    }

    .profile-history .table-wrap {
        flex: 1 1 auto;
        min-height: 0;
        overflow: auto;
    }

    .profile-history .empty-state {
        display: flex;
        flex: 1 1 auto;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 8px;
        min-height: 125px;
        border: 1px dashed rgba(148, 163, 184, 0.9);
        border-radius: var(--radius-lg);
        background: #f8fafc;
        color: var(--color-text-secondary);
        text-align: center;
        padding: 16px;
    }

    .empty-state p {
        margin: 0;
    }

    .empty-icon {
        font-size: 32px;
        color: var(--color-primary-light);
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

    @media (max-width: 900px), (max-height: 700px) {
        .profile-page {
            height: auto;
            min-height: 0;
            overflow: visible;
        }

        .profile-history {
            flex: 0 0 auto;
        }

        .profile-history .table-wrap {
            max-height: 55vh;
        }
    }

    @media (max-width: 640px) {
        .profile-page {
            padding: 14px 16px 24px;
            gap: 12px;
        }

        .profile-header {
            align-items: flex-start;
        }

        .profile-header .btn {
            flex-shrink: 0;
            padding: 8px 10px;
            font-size: 0.82rem;
        }

        .profile-hero {
            align-items: flex-start;
            flex-direction: column;
            padding: 14px;
        }

        .profile-hero__meta {
            justify-content: flex-start;
        }

        .profile-stats {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .profile-history {
            padding: 14px;
        }

        .action-buttons {
            justify-content: flex-start;
        }
    }
</style>

<?php include __DIR__ . '/../components/footer.php'; ?>
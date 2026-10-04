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

    <div class="profile-layout">
        <div class="card profile-card profile-card--details">
            <div class="card-header">
                <h2 class="card-title">
                    <span class="material-symbols-outlined">person</span>
                    Academic Details
                </h2>
            </div>
            <div class="card-body">
                <div class="profile-grid">
                    <div class="profile-item">
                        <span class="profile-label">Full Name</span>
                        <span class="profile-value"><?= e($student['name']) ?></span>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Email Address</span>
                        <span class="profile-value"><?= e($student['email']) ?></span>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Roll Number / Student ID</span>
                        <span class="profile-value"><?= e($student['roll_number']) ?></span>
                    </div>
                    <div class="profile-item">
                        <span class="profile-label">Department & Semester</span>
                        <span class="profile-value">
                            <?= e($student['department']) ?> • Semester <?= e((string)$student['semester']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card profile-card profile-card--summary">
            <div class="card-header">
                <h2 class="card-title">
                    <span class="material-symbols-outlined">insights</span>
                    Performance Snapshot
                </h2>
            </div>
            <div class="card-body">
                <div class="summary-list">
                    <div class="summary-item">
                        <span class="summary-icon material-symbols-outlined">task_alt</span>
                        <div>
                            <strong><?= e((string) $completed_exams) ?></strong>
                            <small>Exams submitted</small>
                        </div>
                    </div>
                    <div class="summary-item">
                        <span class="summary-icon material-symbols-outlined">trending_up</span>
                        <div>
                            <strong><?= sprintf('%.1f', $average_score) ?></strong>
                            <small>Average marks</small>
                        </div>
                    </div>
                    <div class="summary-item">
                        <span class="summary-icon material-symbols-outlined">emoji_events</span>
                        <div>
                            <strong><?= sprintf('%.1f', $best_score) ?></strong>
                            <small>Top performance</small>
                        </div>
                    </div>
                </div>
            </div>
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
                                    <td>
                                        <strong><?= e($result['title']) ?></strong>
                                    </td>
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
        padding-top: 18px;
    }

    .profile-header {
        margin-bottom: 20px;
    }

    .eyebrow {
        display: inline-block;
        margin-bottom: 8px;
        font-size: 0.72rem;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        font-weight: 700;
        color: var(--color-primary);
    }

    .profile-hero {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        padding: 28px 30px;
        background: #eff6ff;
        border: 1px solid rgba(15, 23, 42, 0.12);
    }

    .profile-hero__main {
        display: flex;
        align-items: center;
        gap: 18px;
        min-width: 0;
    }

    .profile-avatar {
        width: 76px;
        height: 76px;
        border-radius: 22px;
        background: var(--color-primary);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 800;
        font-size: 1.9rem;
        box-shadow: 0 12px 28px rgba(15, 23, 42, 0.18);
    }

    .profile-role {
        display: inline-block;
        margin-bottom: 6px;
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--color-primary-light);
    }

    .profile-identity h2 {
        margin: 0;
        font-size: clamp(1.5rem, 2vw, 2rem);
        color: var(--color-dark);
        line-height: 1.2;
    }

    .profile-identity p {
        margin-top: 4px;
        color: var(--color-text-secondary);
        font-size: 0.95rem;
    }

    .profile-hero__meta {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }

    .meta-chip {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 14px;
        background: rgba(255, 255, 255, 0.7);
        border: 1px solid rgba(148, 163, 184, 0.35);
        border-radius: 999px;
        color: var(--color-text);
        font-size: 0.85rem;
        font-weight: 600;
    }

    .meta-chip .material-symbols-outlined {
        color: var(--color-primary);
        font-size: 18px;
    }

    .profile-stats {
        margin-top: 22px;
        margin-bottom: 24px;
    }

    .profile-layout {
        display: grid;
        grid-template-columns: 1.5fr 1fr;
        gap: 24px;
        margin-bottom: 24px;
    }

    .profile-card {
        height: 100%;
    }

    .profile-card--details .card-header,
    .profile-card--summary .card-header,
    .profile-history .card-header {
        padding-bottom: 14px;
        border-bottom: 1px solid var(--color-border);
        margin-bottom: 18px;
    }

    .profile-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .profile-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
        padding: 18px 16px;
        background: #ffffff;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-lg);
    }

    .profile-label {
        font-size: 0.75rem;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--color-text-secondary);
        font-weight: 700;
    }

    .profile-value {
        font-size: 1rem;
        font-weight: 600;
        color: var(--color-dark);
        line-height: 1.5;
    }

    .summary-list {
        display: flex;
        flex-direction: column;
        gap: 14px;
    }

    .summary-item {
        display: flex;
        align-items: center;
        gap: 14px;
        padding: 16px;
        border-radius: var(--radius-lg);
        background: #f8fafc;
        border: 1px solid var(--color-border);
    }

    .summary-icon {
        width: 44px;
        height: 44px;
        border-radius: 12px;
        background: rgba(15, 23, 42, 0.07);
        color: var(--color-primary);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
    }

    .summary-item strong {
        display: block;
        font-size: 1.5rem;
        color: var(--color-dark);
        line-height: 1.1;
    }

    .summary-item small {
        color: var(--color-text-secondary);
        font-size: 0.8rem;
    }

    .profile-history {
        margin-bottom: 0;
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
        border: 1px dashed rgba(148, 163, 184, 0.9);
        border-radius: var(--radius-lg);
        background: #f8fafc;
        color: var(--color-text-secondary);
        padding: 28px;
    }

    .empty-icon {
        font-size: 38px;
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

    @media (max-width: 900px) {
        .profile-hero,
        .profile-layout {
            grid-template-columns: 1fr;
            display: grid;
        }

        .profile-hero__meta {
            justify-content: flex-start;
        }
    }

    @media (max-width: 640px) {
        .profile-grid {
            grid-template-columns: 1fr;
        }

        .profile-hero {
            padding: 22px 18px;
        }

        .profile-hero__main {
            align-items: flex-start;
        }

        .profile-avatar {
            width: 60px;
            height: 60px;
            font-size: 1.5rem;
        }

        .action-buttons {
            justify-content: flex-start;
        }
    }
</style>

<?php include __DIR__ . '/../components/footer.php'; ?>
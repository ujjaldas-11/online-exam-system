<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/logger.php';
require_once '../utils/sanitize.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    $total_exams = (int) $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
    $total_subjects = (int) $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    $active_exams = (int) $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'active'")->fetchColumn();
    $total_questions = (int) $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    $total_students = (int) $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $total_attempts = (int) $pdo->query("SELECT COUNT(*) FROM exam_attempts")->fetchColumn();
} catch (PDOException $e) {
    log_error("Admin dashboard database error", $e);
    die("Database Error. Please try again later.");
}

$page_title = 'Admin Dashboard • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <!-- Header -->
    <div class="page-header">
        <div>
            <h1>Admin & Instructor Dashboard</h1>
            <p>System overview, live exams, and classroom controls</p>
        </div>
        <div class="badge badge-active" style="padding: 8px 14px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">calendar_today</span> <?= date('l, d M Y') ?>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-num"><?= $total_subjects ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm">menu_book</span> Subjects</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= $total_exams ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm">assignment</span> Total Exams</div>
        </div>
        <div class="stat-card" style="border-left: 4px solid var(--color-success);">
            <div class="stat-num" style="color: var(--color-success);"><?= $active_exams ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm" style="color: var(--color-success);">sensors</span> Live Exams</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= $total_questions ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm">quiz</span> Question Bank</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= $total_students ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm">school</span> Enrolled Students</div>
        </div>
        <div class="stat-card">
            <div class="stat-num"><?= $total_attempts ?></div>
            <div class="stat-label" style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm">analytics</span> Exam Attempts</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card">
        <div class="card-title">Quick Actions</div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
            <a href="control-exams.php" class="btn btn-primary" style="justify-content: flex-start; padding: 16px; gap: 12px;">
                <span class="material-symbols-outlined icon-xl">tune</span>
                <div style="text-align: left;">
                    <div><strong>Control & Proctor Exams</strong></div>
                    <small style="opacity: 0.85; font-weight: normal;">Start exams & set PIN</small>
                </div>
            </a>

            <a href="manage-exam.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 16px; gap: 12px;">
                <span class="material-symbols-outlined icon-xl">add_circle</span>
                <div style="text-align: left;">
                    <div><strong>Create New Exam</strong></div>
                    <small style="color: var(--color-text-secondary); font-weight: normal;">Configure time & questions</small>
                </div>
            </a>

            <a href="manage-questions.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 16px; gap: 12px;">
                <span class="material-symbols-outlined icon-xl">help</span>
                <div style="text-align: left;">
                    <div><strong>Add Questions</strong></div>
                    <small style="color: var(--color-text-secondary); font-weight: normal;">Expand question bank</small>
                </div>
            </a>

            <a href="results.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 16px; gap: 12px;">
                <span class="material-symbols-outlined icon-xl">leaderboard</span>
                <div style="text-align: left;">
                    <div><strong>View Results & Reports</strong></div>
                    <small style="color: var(--color-text-secondary); font-weight: normal;">Student scores & analytics</small>
                </div>
            </a>

            <a href="import-students.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 16px; gap: 12px;">
                <span class="material-symbols-outlined icon-xl">upload_file</span>
                <div style="text-align: left;">
                    <div><strong>Import Students (CSV)</strong></div>
                    <small style="color: var(--color-text-secondary); font-weight: normal;">Bulk roster creation</small>
                </div>
            </a>

            <a href="manage-requests.php" class="btn btn-secondary" style="justify-content: flex-start; padding: 16px; gap: 12px;">
                <span class="material-symbols-outlined icon-xl">manage_accounts</span>
                <div style="text-align: left;">
                    <div><strong>Manage Requests</strong></div>
                    <small style="color: var(--color-text-secondary); font-weight: normal;">Profile edits & password resets</small>
                </div>
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

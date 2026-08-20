<?php
require_once 'admin-guard.php';
require_once '../config/database.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';

try {
    $total_exams     = $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
    $total_subjects  = $pdo->query("SELECT COUNT(*) FROM subjects")->fetchColumn();
    $active_exams    = $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'active'")->fetchColumn();
    $total_questions = $pdo->query("SELECT COUNT(*) FROM questions")->fetchColumn();
    $total_students  = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
    $total_attempts  = $pdo->query("SELECT COUNT(*) FROM exam_attempts")->fetchColumn();
} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard • Examify</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<?php include '../components/navbar.php'; ?>

<div class="container">

    <!-- Header -->
    <div class="page-header">
        <div>
            <!-- <h1>Admin Dashboard</h1> -->
            <p class="subtitle">System overview & quick actions</p>
        </div>
        <div class="date-badge"><?= date('l, d M Y') ?></div>
    </div>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📚</div>
            <div>
                <div class="stat-label">Subjects</div>
                <div class="stat-value"><?= $total_subjects ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📝</div>
            <div>
                <div class="stat-label">Total Exams</div>
                <div class="stat-value"><?= $total_exams ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">🟢</div>
            <div>
                <div class="stat-label">Active Exams</div>
                <div class="stat-value"><?= $active_exams ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">❓</div>
            <div>
                <div class="stat-label">Questions</div>
                <div class="stat-value"><?= $total_questions ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">👨‍🎓</div>
            <div>
                <div class="stat-label">Students</div>
                <div class="stat-value"><?= $total_students ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div>
                <div class="stat-label">Attempts</div>
                <div class="stat-value"><?= $total_attempts ?></div>
            </div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="section-title">
        <h2>Quick Actions</h2>
    </div>

    <div class="actions-grid">
        <a href="manage-subjects.php" class="action-card">
            <div class="action-icon">📚</div>
            <div class="action-content">
                <strong>Manage Subjects</strong>
                <span>Add or edit subjects</span>
            </div>
        </a>

        <a href="manage-exam.php" class="action-card">
            <div class="action-icon">➕</div>
            <div class="action-content">
                <strong>Create Exam</strong>
                <span>Build a new exam</span>
            </div>
        </a>

        <a href="manage-questions.php" class="action-card">
            <div class="action-icon">❓</div>
            <div class="action-content">
                <strong>Add Questions</strong>
                <span>Grow the question bank</span>
            </div>
        </a>

        <a href="results.php" class="action-card">
            <div class="action-icon">📈</div>
            <div class="action-content">
                <strong>View Results</strong>
                <span>Scores & reports</span>
            </div>
        </a>

        <a href="control-exam.php" class="action-card">
            <div class="action-icon">⚙️</div>
            <div class="action-content">
                <strong>Control Exams</strong>
                <span>Start & monitor exams</span>
            </div>
        </a>
    </div>

</div>

</body>
</html>

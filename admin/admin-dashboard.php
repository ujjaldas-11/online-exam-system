<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];

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
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.5;
        }

        /* Navbar */
        nav {
            background: var(--dark);
            color: white;
            position: sticky;
            top: 0;
            z-index: 50;
        }
        .nav-inner {
            max-width: 1100px;
            margin: 0 auto;
            padding: 0 20px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .logo { font-weight: 700; font-size: 1.25rem; }
        .nav-links { display: flex; gap: 6px; }
        .nav-links a {
            color: #cbd5e1;
            text-decoration: none;
            padding: 7px 12px;
            border-radius: 6px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        .nav-links a:hover { background: #1e293b; color: white; }
        .logout { background: #dc2626 !important; color: white !important; }
        .menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Layout */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 32px 20px;
        }
        h1 { font-size: 1.6rem; margin-bottom: 4px; }
        .subtitle { color: var(--gray); margin-bottom: 28px; }

        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 36px;
        }
        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
        }
        .card span {
            display: block;
            font-size: 0.8rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.4px;
            margin-bottom: 6px;
        }
        .card strong {
            font-size: 1.8rem;
            font-weight: 700;
        }

        /* Actions */
        h2 { font-size: 1.15rem; margin-bottom: 14px; }
        .actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        .actions a {
            background: white;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
            text-align: center;
            text-decoration: none;
            color: var(--dark);
            font-weight: 600;
            font-size: 0.95rem;
            transition: 0.2s;
        }
        .actions a:hover {
            border-color: var(--primary);
            color: var(--primary);
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.1);
        }

        /* Mobile */
        @media (max-width: 768px) {
            .menu-btn { display: block; }
            .nav-links {
                display: none;
                position: absolute;
                top: 60px;
                left: 0;
                right: 0;
                background: var(--dark);
                flex-direction: column;
                padding: 12px;
                gap: 4px;
            }
            .nav-links.show { display: flex; }
            .nav-links a { padding: 12px; text-align: center; }
            .stats { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 480px) {
            .stats { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<nav>
    <div class="nav-inner">
        <div class="logo">Examify Admin</div>
        <button class="menu-btn" onclick="document.querySelector('.nav-links').classList.toggle('show')">☰</button>
        <div class="nav-links">
            <a href="admin-dashboard.php">Dashboard</a>
            <a href="manage-subjects.php">Subjects</a>
            <a href="manage-exam.php">Exams</a>
            <a href="manage-questions.php">Questions</a>
            <a href="results.php">Results</a>
            <a href="admin-logout.php" class="logout">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <h1>Welcome back, <?= htmlspecialchars($admin_name) ?></h1>
    <p class="subtitle">System overview</p>

    <div class="stats">
        <div class="card"><span>Subjects</span><strong><?= $total_subjects ?></strong></div>
        <div class="card"><span>Exams</span><strong><?= $total_exams ?></strong></div>
        <div class="card"><span>Active Exams</span><strong><?= $active_exams ?></strong></div>
        <div class="card"><span>Questions</span><strong><?= $total_questions ?></strong></div>
        <div class="card"><span>Students</span><strong><?= $total_students ?></strong></div>
        <div class="card"><span>Attempts</span><strong><?= $total_attempts ?></strong></div>
    </div>

    <h2>Quick Actions</h2>
    <div class="actions">
        <a href="manage-subjects.php">Manage Subjects</a>
        <a href="manage-exam.php">Create Exam</a>
        <a href="manage-questions.php">Add Questions</a>
        <a href="results.php">View Results</a>
    </div>
</div>

</body>
</html>
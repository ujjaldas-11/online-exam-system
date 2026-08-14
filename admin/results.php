<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

// Fetch all attempts with student & exam details
$attempts = $pdo->query("
    SELECT 
        ea.id,
        ea.score,
        ea.total_questions,
        ea.status,
        ea.started_at,
        ea.submitted_at,
        s.name AS student_name,
        s.roll_number,
        s.department,
        e.title AS exam_title,
        sub.name AS subject_name
    FROM exam_attempts ea
    JOIN students s ON ea.student_id = s.id
    JOIN exams e ON ea.exam_id = e.id
    JOIN subjects sub ON e.subject_id = sub.id
    ORDER BY ea.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results • Examify</title>
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --success: #16a34a;
            --error: #dc2626;
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
        .nav-links a:hover,
        .nav-links a.active { background: #1e293b; color: white; }
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
        .subtitle { color: var(--gray); margin-bottom: 24px; }

        /* Card */
        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
        }
        .card h2 {
            font-size: 1.15rem;
            margin-bottom: 16px;
        }

        /* Table */
        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        th, td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: #f1f5f9;
            font-weight: 600;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        tr:hover td { background: #f8fafc; }

        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .badge.completed { background: #dcfce7; color: var(--success); }
        .badge.in_progress { background: #fef3c7; color: #d97706; }

        .score {
            font-weight: 700;
            color: var(--primary);
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
            <a href="results.php" class="active">Results</a>
            <a href="admin-logout.php" class="logout">Logout</a>
        </div>
    </div>
</nav>

<div class="container">
    <h1>Exam Results</h1>
    <p class="subtitle">All student attempts and scores</p>

    <div class="card">
        <h2>All Attempts (<?= count($attempts) ?>)</h2>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Student</th>
                        <th>Exam</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th>Submitted</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($attempts)): ?>
                        <tr>
                            <td colspan="6" style="text-align:center; color:var(--gray); padding: 30px;">
                                No exam attempts yet
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($attempts as $row): ?>
                            <?php
                                $percentage = $row['total_questions'] > 0 
                                    ? round(($row['score'] / $row['total_questions']) * 100) 
                                    : 0;
                            ?>
                            <tr>
                                <td><?= $row['id'] ?></td>
                                <td>
                                    <?= htmlspecialchars($row['student_name']) ?><br>
                                    <small style="color:var(--gray)">
                                        <?= htmlspecialchars($row['roll_number']) ?> • <?= htmlspecialchars($row['department']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?= htmlspecialchars($row['exam_title']) ?><br>
                                    <small style="color:var(--gray)"><?= htmlspecialchars($row['subject_name']) ?></small>
                                </td>
                                <td>
                                    <span class="score"><?= $row['score'] ?>/<?= $row['total_questions'] ?></span>
                                    <small style="color:var(--gray)">(<?= $percentage ?>%)</small>
                                </td>
                                <td>
                                    <span class="badge <?= $row['status'] ?>">
                                        <?= strtoupper(str_replace('_', ' ', $row['status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($row['submitted_at']): ?>
                                        <?= date('d M Y, h:i A', strtotime($row['submitted_at'])) ?>
                                    <?php else: ?>
                                        <span style="color:var(--gray)">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
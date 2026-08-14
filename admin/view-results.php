<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

if (!isset($_GET['exam_id'])) {
    die("No exam selected.");
}

$exam_id = (int)$_GET['exam_id'];

// Fetch Exam Details
$examStmt = $pdo->prepare("SELECT title, total_marks FROM exams WHERE id = ?");
$examStmt->execute([$exam_id]);
$exam = $examStmt->fetch();

if (!$exam) die("Exam not found.");

// Fetch All Completed Attempts (Ordered by Score)
$resultsSql = "SELECT s.name, s.roll_number, s.department, s.semester, ea.score, ea.total_questions, ea.submitted_at 
               FROM exam_attempts ea
               JOIN students s ON ea.student_id = s.id
               WHERE ea.exam_id = :exam_id AND ea.status = 'completed'
               ORDER BY ea.score DESC, ea.submitted_at ASC"; 
$resultsStmt = $pdo->prepare($resultsSql);
$resultsStmt->execute([':exam_id' => $exam_id]);
$all_results = $resultsStmt->fetchAll();

// Top 3 Scorers
$top_scorers = array_slice($all_results, 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results: <?= htmlspecialchars($exam['title']) ?></title>
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --gold: #fef08a; --gold-border: #facc15;
            --silver: #f1f5f9; --silver-border: #cbd5e1;
            --bronze: #ffedd5; --bronze-border: #fdba74;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, sans-serif; background: var(--light); color: var(--dark); line-height: 1.5; }
        
        .container { max-width: 1100px; margin: 0 auto; padding: 32px 20px; }
        .header-flex { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; }
        h1 { font-size: 1.6rem; margin-bottom: 4px; }
        .subtitle { color: var(--gray); }

        .card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 24px; margin-bottom: 24px; }
        .card h2 { font-size: 1.15rem; margin-bottom: 16px; border-bottom: 1px solid var(--border); padding-bottom: 10px; }

        .btn { padding: 8px 16px; border-radius: 6px; font-weight: 500; cursor: pointer; text-decoration: none; border: none; font-size: 0.95rem; display: inline-block; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-outline { background: white; border: 1px solid var(--border); color: var(--dark); margin-bottom: 20px; }

        /* Podium Styles */
        .podium { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px; }
        .medal-card { padding: 20px; border-radius: 10px; text-align: center; border: 1px solid var(--border); }
        .rank-1 { background: var(--gold); border-color: var(--gold-border); }
        .rank-2 { background: var(--silver); border-color: var(--silver-border); }
        .rank-3 { background: var(--bronze); border-color: var(--bronze-border); }
        .medal-icon { font-size: 2rem; margin-bottom: 8px; }
        .medal-score { font-size: 1.5rem; font-weight: 700; color: var(--dark); margin-top: 8px; }

        /* Table */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: #f1f5f9; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px; }
        tr:hover td { background: #f8fafc; }
        .score { font-weight: 700; color: var(--primary); }

        /* Print Settings */
        @media print {
            body { background: white; padding: 0; }
            .no-print { display: none !important; }
            .container { padding: 0; max-width: 100%; }
            .card { border: none; padding: 0; box-shadow: none; margin-bottom: 20px; }
            .podium { page-break-inside: avoid; }
            th { background-color: #f1f5f9 !important; -webkit-print-color-adjust: exact; }
            .medal-card { -webkit-print-color-adjust: exact; }
        }
    </style>
</head>
<body>

<div class="no-print">
    <?php include 'admin-navbar.php' ?>
</div>

<div class="container">
    <a href="results.php" class="btn btn-outline no-print">← Back to All Exams</a>

    <div class="header-flex">
        <div>
            <h1><?= htmlspecialchars($exam['title']) ?></h1>
            <p class="subtitle">Total Marks: <?= $exam['total_marks'] ?></p>
        </div>
        <button onclick="window.print()" class="btn btn-primary no-print">
            Download PDF
        </button>
    </div>

    <?php if (empty($all_results)): ?>
        <div class="card">
            <p style="color:var(--gray); text-align:center;">No students have completed this exam yet.</p>
        </div>
    <?php else: ?>

        <!-- Podium Section -->
        <div class="card">
            <h2> Top Performers</h2>
            <div class="podium">
                <?php foreach ($top_scorers as $index => $student): ?>
                    <?php 
                        $rankClass = 'rank-' . ($index + 1); 
                        $medals = ['1st', '2nd', '3rd'];
                    ?>
                    <div class="medal-card <?= $rankClass ?>">
                        <div class="medal-icon"><?= $medals[$index] ?></div>
                        <h3 style="margin-bottom: 4px;"><?= htmlspecialchars($student['name']) ?></h3>
                        <small style="color: var(--gray);">Roll: <?= htmlspecialchars($student['roll_number']) ?></small>
                        <div class="medal-score"><?= $student['score'] ?> / <?= $exam['total_marks'] ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Full Table Section -->
        <div class="card">
            <h2> All Submissions (<?= count($all_results) ?>)</h2>
             <?php 
                $search_placeholder = "Search exams, departments, or marks..."; 
                include 'searchbar.php'; 
            ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Rank</th>
                            <th>Student</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Submitted</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $rank = 1;
                        foreach ($all_results as $row): 
                            $percentage = $exam['total_marks'] > 0 
                                ? round(($row['score'] / $exam['total_marks']) * 100) 
                                : 0;
                        ?>
                        <tr>
                            <td><strong>#<?= $rank++ ?></strong></td>
                            <td>
                                <?= htmlspecialchars($row['name']) ?><br>
                                <small style="color:var(--gray)">
                                    <?= htmlspecialchars($row['roll_number']) ?> • <?= htmlspecialchars($row['department']) ?>
                                </small>
                            </td>
                            <td>
                                <span class="score"><?= $row['score'] ?> / <?= $exam['total_marks'] ?></span>
                            </td>
                            <td><?= $percentage ?>%</td>
                            <td><?= date('d M Y, h:i A', strtotime($row['submitted_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php endif; ?>
</div>

</body>
</html>
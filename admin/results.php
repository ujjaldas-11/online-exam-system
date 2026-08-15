<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

$selected_dept = $_GET['department'] ?? 'All';

// Fetch distinct departments to generate the filter buttons automatically
$deptStmt = $pdo->query("SELECT DISTINCT department FROM subjects ORDER BY department");
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

$params = [];
$sql = "SELECT e.id, e.title, e.total_marks, s.department, s.semester, 
               (SELECT COUNT(*) FROM exam_attempts WHERE exam_id = e.id AND status = 'completed') as total_attempts
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id";

// If a specific department is selected, add a WHERE clause
if ($selected_dept !== 'All') {
    $sql .= " WHERE s.department = :dept";
    $params[':dept'] = $selected_dept;
}

$sql .= " ORDER BY e.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$exams = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Results Dashboard • Examify</title>
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
        body { font-family: system-ui, -apple-system, sans-serif; background: var(--light); color: var(--dark); line-height: 1.5; }
        
        .container { max-width: 1100px; margin: 0 auto; padding: 32px 20px; }
        h1 { font-size: 1.6rem; margin-bottom: 4px; }
        .subtitle { color: var(--gray); margin-bottom: 24px; }

        .card { background: white; border: 1px solid var(--border); border-radius: 12px; padding: 24px; }
        
        /* Filter Button Styles */
        .filter-group { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .filter-btn {
            padding: 6px 16px;
            border: 1px solid var(--border);
            border-radius: 20px;
            text-decoration: none;
            color: var(--gray);
            font-size: 0.9rem;
            font-weight: 500;
            background: white;
            transition: 0.2s;
        }
        .filter-btn:hover { border-color: var(--primary); color: var(--primary); }
        .filter-btn.active { background: var(--primary); color: white; border-color: var(--primary); }

        /* Table Styles */
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; font-size: 0.95rem; }
        th, td { padding: 12px 14px; text-align: left; border-bottom: 1px solid var(--border); }
        th { background: #f1f5f9; font-weight: 600; color: #475569; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.3px; }
        tr:hover td { background: #f8fafc; }

        .badge { display: inline-block; padding: 4px 10px; border-radius: 20px; font-size: 0.8rem; font-weight: 600; }
        .badge.info { background: #e0f2fe; color: #0369a1; }

        .btn-view { background: var(--primary); color: white; padding: 6px 14px; text-decoration: none; border-radius: 6px; font-size: 0.9rem; font-weight: 500; display: inline-block; transition: 0.2s; }
        .btn-view:hover { opacity: 0.9; }
    </style>
</head>
<body>

<?php include 'admin-navbar.php' ?>

<div class="container">
    <h1> Results Dashboard</h1>
    <p class="subtitle">Select an exam to view detailed student scores and leaderboards</p>

    <div class="card">
        
        <!--  Department Filter Buttons -->
        <div class="filter-group no-print">
            <a href="results.php" class="filter-btn <?= $selected_dept === 'All' ? 'active' : '' ?>">
                All Departments
            </a>
            
            <?php foreach ($departments as $dept): ?>
                <a href="results.php?department=<?= urlencode($dept) ?>" class="filter-btn <?= $selected_dept === $dept ? 'active' : '' ?>">
                    <?= htmlspecialchars($dept) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php 
        $search_placeholder = "Search exams, departments, or marks..."; 
        include 'searchbar.php'; 
        ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Exam Title</th>
                        <th>Department</th>
                        <th>Semester</th>
                        <th>Total Marks</th>
                        <th>Submissions</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; color:var(--gray); padding: 30px;">
                                No exams found for <?= htmlspecialchars($selected_dept) ?>.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exams as $exam): ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($exam['title']) ?></td>
                            <td><?= htmlspecialchars($exam['department']) ?> </td>
                            <td>Sem <?= $exam['semester'] ?></td>
                            <td><?= $exam['total_marks'] ?> marks</td>
                            <td><span class="badge info"><?= $exam['total_attempts'] ?> students</span></td>
                            <td>
                                <a href="view-results.php?exam_id=<?= $exam['id'] ?>" class="btn-view">View Results</a>
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
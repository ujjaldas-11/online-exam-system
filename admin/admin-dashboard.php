<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

$admin_name = $_SESSION['admin_name'];

// 2. Fetch Dashboard Statistics
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM exams");
    $total_exams = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'active'");
    $active_exams = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM questions");
    $total_questions = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM students");
    $total_students = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM exam_attempts");
    $total_attempts = $stmt->fetchColumn();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Examify</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .navbar { background-color: #343a40; padding: 15px 20px; display: flex; justify-content: space-between; align-items: center; color: white; }
        .navbar a { color: white; text-decoration: none; margin-left: 20px; font-weight: bold; padding: 8px 12px; border-radius: 4px; transition: background 0.3s; }
        .navbar a:hover { background-color: #495057; }
        .navbar .logout-btn { background-color: #dc3545; }
        .navbar .logout-btn:hover { background-color: #c82333; }
        
        .container { max-width: 1000px; margin: 40px auto; padding: 0 20px; }
        h1 { color: #333; }
        
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-top: 30px; }
        .stat-card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; border-top: 4px solid #007bff; }
        .stat-card.green { border-top-color: #28a745; }
        .stat-card.orange { border-top-color: #fd7e14; }
        .stat-card.purple { border-top-color: #6f42c1; }
        
        .stat-card h3 { margin: 0 0 10px 0; color: #666; font-size: 16px; text-transform: uppercase; }
        .stat-card .number { font-size: 36px; font-weight: bold; color: #333; margin: 0; }

        .quick-links { margin-top: 40px; display: flex; gap: 15px; }
        .quick-links a { flex: 1; text-align: center; padding: 20px; background: white; text-decoration: none; color: #333; font-weight: bold; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); border: 1px solid #e0e0e0; transition: transform 0.2s, box-shadow 0.2s; }
        .quick-links a:hover { transform: translateY(-3px); box-shadow: 0 4px 8px rgba(0,0,0,0.15); border-color: #007bff; color: #007bff; }
    </style>
</head>
<body>

    <nav class="navbar">
        <div class="logo">
            <h2 style="margin: 0;">Examify Admin</h2>
        </div>
        <div class="nav-links">
            <a href="admin-dashboard.php">Dashboard</a>
            <a href="manage-exam.php">Manage Exams</a>
            <a href="manage-questions.php">Manage Questions</a>
            <a href="results.php">View Results</a>
            <a href="admin-logout.php" class="logout-btn">Logout</a>
        </div>
    </nav>

    <div class="container">
        <h1>Welcome back, <?php echo htmlspecialchars($admin_name); ?>!</h1>
        <p>Here is an overview of the system today.</p>

        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Exams</h3>
                <p class="number"><?php echo $total_exams; ?></p>
            </div>
            
            <div class="stat-card green">
                <h3>Active Exams</h3>
                <p class="number"><?php echo $active_exams; ?></p>
            </div>
            
            <div class="stat-card orange">
                <h3>Total Questions</h3>
                <p class="number"><?php echo $total_questions; ?></p>
            </div>
            
            <div class="stat-card purple">
                <h3>Total Students</h3>
                <p class="number"><?php echo $total_students; ?></p>
            </div>
        </div>

        <h2 style="margin-top: 40px; color: #333;">Quick Actions</h2>
        <div class="quick-links">
            <a href="manage-exam.php">➕ Create New Exam</a>
            <a href="manage-questions.php">📝 Add Questions</a>
            <a href="results.php">📊 Review Submissions (<?php echo $total_attempts; ?>)</a>
        </div>
    </div>

</body>
</html>
<?php
// student/dashboard.php
session_start();
require_once '../config/database.php';

// 1. Check if the student is logged in
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_name = $_SESSION['student_name'];
$semester     = $_SESSION['semester'];
$department   = $_SESSION['department'];

// 2. Fetch active exams for the student's specific semester
try {
    $sql = "SELECT id, title, description, duration_minutes, total_marks 
            FROM exams 
            WHERE department = :department 
            AND semester = :semester 
            AND status = 'active'
            ORDER BY id DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
    $stmt->bindParam(':department', $department, PDO::PARAM_STR);   
    $stmt->execute();
    
    $available_exams = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - Examify</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 10px; margin-bottom: 20px; }
        .exam-card { border: 1px solid #e0e0e0; padding: 20px; margin-bottom: 15px; border-radius: 6px; background-color: #fafafa; }
        .exam-card h3 { margin-top: 0; color: #333; }
        .exam-meta { font-size: 0.9em; color: #555; margin: 15px 0; }
        .btn { display: inline-block; padding: 10px 20px; background-color: #28a745; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .btn:hover { background-color: #218838; }
        .logout-btn { color: #dc3545; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>

    <div class="container">
        <div class="header">
            <div>
                <h1 style="margin: 0;"> <?php echo htmlspecialchars($student_name); ?></h1>
                <p style="margin: 5px 0 0 0; color: #666;">Semester: <?php echo htmlspecialchars($semester); ?></p>
                <p style="margin: 5px 0 0 0; color: #666;">department: <?php echo htmlspecialchars($department); ?></p>
            </div>
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>

        <h2>Available Exams</h2>

        <?php if (empty($available_exams)): ?>
            <p>No active exams are available for your semester at this time.</p>
        <?php else: ?>
            
            <?php foreach ($available_exams as $exam): ?>
                <div class="exam-card">
                    <h3><?php echo htmlspecialchars($exam['title']); ?></h3>
                    <p><?php echo htmlspecialchars($exam['description']); ?></p>
                    
                    <div class="exam-meta">
                        <span>⏱Duration: <strong><?php echo htmlspecialchars($exam['duration_minutes']); ?> mins</strong></span> &nbsp; | &nbsp;
                        <span>Total Marks: <strong><?php echo htmlspecialchars($exam['total_marks']); ?></strong></span>
                    </div>
                    
                    <!-- Notice how we use 'id' here to pass the exam ID securely -->
                    <a href="exam.php?id=<?php echo urlencode($exam['id']); ?>" class="btn">Start Exam</a>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</body>
</html>
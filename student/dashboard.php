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

// 2. Fetch active exams and their attempt status for the student
try {
    $sql = "SELECT e.id, e.title, e.description, e.duration_minutes, e.total_marks, e.total_questions_to_ask,
                   s.name AS subject_name, ea.id AS attempt_id, ea.score
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.student_id = :student_id
            WHERE s.department = :department 
            AND s.semester = :semester 
            AND e.status = 'active'
            ORDER BY e.id DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':semester', $semester, PDO::PARAM_INT);
    $stmt->bindParam(':department', $department, PDO::PARAM_STR);   
    $stmt->bindParam(':student_id', $_SESSION['student_id'], PDO::PARAM_INT);
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
                <p style="margin: 5px 0 0 0; color: #666;">Department: <?php echo htmlspecialchars($department); ?></p>
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
                        <span>📚 Subject: <strong><?php echo htmlspecialchars($exam['subject_name']); ?></strong></span> &nbsp; | &nbsp;
                        <span>⏱ Duration: <strong><?php echo htmlspecialchars($exam['duration_minutes']); ?> mins</strong></span> &nbsp; | &nbsp;
                        <span>Questions: <strong><?php echo htmlspecialchars($exam['total_questions_to_ask']); ?></strong></span> &nbsp; | &nbsp;
                        <span>Total Marks: <strong><?php echo htmlspecialchars($exam['total_marks']); ?></strong></span>
                    </div>
                    
                    <?php 
                        $is_completed = !empty($exam['attempt_id']);
                        $is_ongoing = isset($_SESSION['exam_answers'][$exam['id']]);
                    ?>
                    
                    <div style="margin-top: 15px;">
                        <?php if ($is_completed): ?>
                            <span style="display:inline-block; padding:8px 15px; background:#e9ecef; color:#28a745; font-weight:bold; border-radius:4px; border:1px solid #c3e6cb;">
                                ✅ Completed (Score: <?php echo htmlspecialchars($exam['score']); ?> / <?php echo htmlspecialchars($exam['total_marks']); ?>)
                            </span>
                        <?php elseif ($is_ongoing): ?>
                            <a href="exam.php?id=<?php echo urlencode($exam['id']); ?>" class="btn" style="background-color:#fd7e14;">Resume Exam</a>
                        <?php else: ?>
                            <a href="exam.php?id=<?php echo urlencode($exam['id']); ?>" class="btn">Start Exam</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</body>
</html>
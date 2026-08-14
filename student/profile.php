<?php
session_start();
require_once '../config/database.php';

// Security Check
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

try {
    // Fetch Current Student Details
    $stmt = $pdo->prepare("SELECT name, email, roll_number, department, semester FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        die("Student record not found.");
    }

    // Fetch Exam History
    $resultSql = "SELECT e.title, ea.score, e.total_marks, ea.submitted_at 
                  FROM exam_attempts ea
                  JOIN exams e ON ea.exam_id = e.id
                  WHERE ea.student_id = :student_id AND ea.status = 'completed'
                  ORDER BY ea.submitted_at DESC";
    $resultStmt = $pdo->prepare($resultSql);
    $resultStmt->execute([':student_id' => $student_id]);
    $past_results = $resultStmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile - Examify</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 30px; }
        .profile-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px; margin-bottom: 20px; }
        .profile-header h2 { margin: 0; color: #333; }
        .btn-edit { background: #007bff; color: white; padding: 8px 15px; text-decoration: none; border-radius: 4px; font-weight: bold; }
        .btn-edit:hover { background: #0056b3; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .info-box { background: #f8f9fa; padding: 15px; border-radius: 6px; border: 1px solid #e9ecef; }
        .info-label { color: #666; font-size: 14px; margin-bottom: 5px; }
        .info-value { font-size: 18px; font-weight: bold; color: #333; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 12px; border: 1px solid #ddd; text-align: left; }
        th { background-color: #f8f9fa; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container">
        
        <!-- Student Details Card -->
        <div class="card">
            <div class="profile-header">
                <h2>My Profile</h2>
                <a href="edit-profile.php" class="btn-edit">✎ Edit Profile</a>
            </div>
            
            <div class="info-grid">
                <div class="info-box">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['name']); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Email Address</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['email']); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Roll Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['roll_number']); ?></div>
                </div>
                <div class="info-box">
                    <div class="info-label">Program</div>
                    <div class="info-value"><?php echo htmlspecialchars($student['department']); ?> - Semester <?php echo htmlspecialchars($student['semester']); ?></div>
                </div>
            </div>
        </div>

        <!-- Exam History Card -->
        <div class="card">
            <h2 style="margin-top: 0; color: #333; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">My Exam History</h2>
            <?php if (empty($past_results)): ?>
                <p style="color: #666;">You haven't completed any exams yet.</p>
            <?php else: ?>
                <table>
                    <tr>
                        <th>Exam Title</th>
                        <th>Score</th>
                        <th>Submitted On</th>
                    </tr>
                    <?php foreach ($past_results as $result): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['title']); ?></td>
                            <td>
                                <strong><?php echo $result['score']; ?> / <?php echo $result['total_marks']; ?></strong>
                            </td>
                            <td><?php echo date('M d, Y h:i A', strtotime($result['submitted_at'])); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>
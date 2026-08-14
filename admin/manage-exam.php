<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    $title = trim(strip_tags($_POST['title'] ?? ''));
    $department = trim(strip_tags($_POST['department'] ?? ''));
    $semester = (int)($_POST['semester'] ?? 0);
    $duration = (int)($_POST['duration_minutes'] ?? 0);
    $total_marks = (int)($_POST['total_marks'] ?? 0);

    if (empty($title) || empty($department) || $semester <= 0 || $duration <= 0 || $total_marks <= 0) {
        $message = "Invalid input! Please fill out all fields correctly.";
    } else {
        $sql = "INSERT INTO exams (title, department, semester, duration_minutes, total_marks, status) 
                VALUES (:title, :department, :semester, :duration, :total_marks, 'inactive')";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title' => $title,
            ':department' => $department,
            ':semester' => $semester,
            ':duration' => $duration,
            ':total_marks' => $total_marks
        ]);
        $message = "Exam created successfully! It is currently inactive.";
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'start' && isset($_GET['id'])) {
    $exam_id = (int)$_GET['id'];
    $startSql = "UPDATE exams SET status = 'active', start_time = NOW() WHERE id = :id";
    $pdo->prepare($startSql)->execute([':id' => $exam_id]);
    $message = "Exam timer has been STARTED. Students can now join.";
}

// Fetch all exams
$exams = $pdo->query("SELECT * FROM exams ORDER BY id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Exams - Admin</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f4f7f6; }
        .card { background: white; padding: 20px; margin-bottom: 20px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .btn { padding: 8px 15px; color: white; text-decoration: none; border-radius: 3px; border: none; cursor: pointer; }
        .btn-green { background: #28a745; } .btn-blue { background: #007bff; }
        input, select { padding: 8px; margin: 5px 0; width: 100%; box-sizing: border-box; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
    </style>
</head>
<body>
    <h1>Admin Panel - Manage Exams</h1>
    <?php 
    if(isset($message)) { 
        $color = strpos($message, 'Invalid') !== false ? 'red' : 'green';
        echo "<p style='color:$color;'><b>" . htmlspecialchars($message) . "</b></p>"; 
    } 
    ?>

    <div class="card">
        <h3>Create New Examination</h3>
        <form method="POST">
            <label>Exam Title:</label>
            <input type="text" name="title" required>
            
            <label>Target Department:</label>
            <select name="department" required>
                <option value="">-- Choose Department --</option>
                <option value="BCA">BCA</option>
                <option value="BBA">BBA</option>
            </select>
            
            <label>Target Semester:</label>
            <input type="number" name="semester" min="1" max="8" required>
            
            <label>Duration (in minutes):</label>
            <input type="number" name="duration_minutes" required>
            
            <label>Total Marks:</label>
            <input type="number" name="total_marks" required>
            
            <button type="submit" name="create_exam" class="btn btn-blue">Create Exam</button>
        </form>
    </div>

    <div class="card">
        <h3>Exam Control Center</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Semester</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php foreach ($exams as $exam): ?>
            <tr>
                <td><?php echo $exam['id']; ?></td>
                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                <td>Sem <?php echo $exam['semester']; ?></td>
                <td><?php echo $exam['duration_minutes']; ?> mins</td>
                <td>
                    <b style="color: <?php echo $exam['status'] == 'active' ? 'green' : 'red'; ?>">
                        <?php echo strtoupper($exam['status']); ?>
                    </b>
                </td>
                <td>
                    <?php if ($exam['status'] == 'inactive'): ?>
                        <a href="?action=start&id=<?php echo $exam['id']; ?>" class="btn btn-green" onclick="return confirm('Are you sure? This will start the timer globally for all students!');">▶ START EXAM</a>
                    <?php else: ?>
                        Started at: <?php echo $exam['start_time']; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>
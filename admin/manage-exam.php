<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_exam'])) {
    $title = trim(strip_tags($_POST['title'] ?? ''));
    $subject_id = (int)($_POST['subject_id'] ?? 0);
    $duration = (int)($_POST['duration_minutes'] ?? 0);
    $total_marks = (int)($_POST['total_marks'] ?? 0);
    $total_questions = (int)($_POST['total_questions_to_ask'] ?? 0);

    if (empty($title) || $subject_id <= 0 || $duration <= 0 || $total_marks <= 0 || $total_questions <= 0) {
        $message = "Invalid input! Please fill out all fields correctly.";
    } else {
        // Validate if subject has enough questions in the pool
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE subject_id = :subject_id");
        $stmt->execute([':subject_id' => $subject_id]);
        $available_questions = $stmt->fetchColumn();
        
        if ($available_questions < $total_questions) {
            $message = "Error: The selected subject only has $available_questions questions in its pool. Cannot ask for $total_questions.";
        } else {
            $sql = "INSERT INTO exams (title, subject_id, duration_minutes, total_marks, total_questions_to_ask, status) 
                    VALUES (:title, :subject_id, :duration, :total_marks, :total_questions, 'inactive')";
                    
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':title' => $title,
                ':subject_id' => $subject_id,
                ':duration' => $duration,
                ':total_marks' => $total_marks,
                ':total_questions' => $total_questions
            ]);
            $message = "Exam created successfully! It is currently inactive.";
        }
    }
}

if (isset($_GET['action']) && $_GET['action'] === 'start' && isset($_GET['id'])) {
    $exam_id = (int)$_GET['id'];
    
    // Check if it's already active to avoid resetting the timer on reload
    $checkSql = "SELECT status FROM exams WHERE id = :id";
    $stmt = $pdo->prepare($checkSql);
    $stmt->execute([':id' => $exam_id]);
    $examStatus = $stmt->fetchColumn();
    
    if ($examStatus !== 'active') {
        $startSql = "UPDATE exams SET status = 'active', start_time = NOW() WHERE id = :id";
        $pdo->prepare($startSql)->execute([':id' => $exam_id]);
        $message = "Exam timer has been STARTED. Students can now join.";
    } else {
        $message = "Exam is already active.";
    }
}

// Fetch all exams with subject info
$exams = $pdo->query("SELECT e.*, s.name as subject_name, s.department, s.semester 
                      FROM exams e 
                      JOIN subjects s ON e.subject_id = s.id 
                      ORDER BY e.id DESC")->fetchAll();
                      
// Fetch subjects for form
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY name ASC")->fetchAll();
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
            
            <label>Subject:</label>
            <select name="subject_id" required>
                <option value="">-- Choose Subject --</option>
                <?php foreach ($subjects as $sub): ?>
                    <option value="<?php echo $sub['id']; ?>">
                        <?php echo htmlspecialchars($sub['name']); ?> (<?php echo htmlspecialchars($sub['department']); ?>, Sem <?php echo $sub['semester']; ?>)
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label>Total Questions to Ask per Student:</label>
            <input type="number" name="total_questions_to_ask" min="1" required placeholder="e.g. 10">
            
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
                <th>Subject</th>
                <th>Questions to Ask</th>
                <th>Duration</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
            <?php foreach ($exams as $exam): ?>
            <tr>
                <td><?php echo $exam['id']; ?></td>
                <td><?php echo htmlspecialchars($exam['title']); ?></td>
                <td><?php echo htmlspecialchars($exam['subject_name']); ?> <br><small>(<?php echo htmlspecialchars($exam['department']); ?>, Sem <?php echo $exam['semester']; ?>)</small></td>
                <td><?php echo $exam['total_questions_to_ask']; ?></td>
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
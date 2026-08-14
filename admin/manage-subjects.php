<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_subject'])) {
    $name = trim(strip_tags($_POST['name'] ?? ''));
    $department = trim(strip_tags($_POST['department'] ?? ''));
    $semester = (int)($_POST['semester'] ?? 0);

    if (empty($name) || empty($department) || $semester <= 0) {
        $message = "Invalid input! Please fill out all fields.";
    } else {
        $sql = "INSERT INTO subjects (name, department, semester) VALUES (:name, :department, :semester)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':name' => $name,
            ':department' => $department,
            ':semester' => $semester
        ]);
        $message = "Subject created successfully!";
    }
}

// Fetch all subjects
$subjects = $pdo->query("SELECT * FROM subjects ORDER BY id DESC")->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <title>Manage Subjects - Admin</title>
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
    <h1>Admin Panel - Manage Subjects</h1>
    <a href="admin-dashboard.php" class="btn btn-blue" style="margin-bottom:20px; display:inline-block;">&larr; Back to Dashboard</a>
    
    <?php 
    if(isset($message)) { 
        $color = strpos($message, 'Invalid') !== false ? 'red' : 'green';
        echo "<p style='color:$color;'><b>" . htmlspecialchars($message) . "</b></p>"; 
    } 
    ?>

    <div class="card">
        <h3>Create New Subject</h3>
        <form method="POST">
            <label>Subject Name:</label>
            <input type="text" name="name" required placeholder="e.g. Operating Systems">
            
            <label>Department:</label>
            <select name="department" required>
                <option value="">-- Choose Department --</option>
                <option value="BCA">BCA</option>
                <option value="BBA">BBA</option>
            </select>
            
            <label>Semester:</label>
            <input type="number" name="semester" min="1" max="8" required>
            
            <button type="submit" name="create_subject" class="btn btn-blue">Create Subject</button>
        </form>
    </div>

    <div class="card">
        <h3>Existing Subjects</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Department</th>
                <th>Semester</th>
                <th>Created At</th>
            </tr>
            <?php foreach ($subjects as $sub): ?>
            <tr>
                <td><?php echo $sub['id']; ?></td>
                <td><?php echo htmlspecialchars($sub['name']); ?></td>
                <td><?php echo htmlspecialchars($sub['department']); ?></td>
                <td>Sem <?php echo $sub['semester']; ?></td>
                <td><?php echo $sub['created_at']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>

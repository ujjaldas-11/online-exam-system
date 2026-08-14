<?php
session_start();
require_once '../config/database.php';

// Security Check
if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$message = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name']);
    $roll_number = trim($_POST['roll_number']);
    $department = trim($_POST['department']);
    $semester = (int)$_POST['semester'];

    if (empty($name) || empty($roll_number) || empty($department) || empty($semester)) {
        $error = "All fields are required.";
    } else {
        // Check if another student is already using this roll number
        $checkStmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ? AND id != ?");
        $checkStmt->execute([$roll_number, $student_id]);
        
        if ($checkStmt->rowCount() > 0) {
            $error = "This Roll Number is already registered to another student.";
        } else {
            // Update the database (Email cannot be changed here for security)
            $updateSql = "UPDATE students SET name = ?, roll_number = ?, department = ?, semester = ? WHERE id = ?";
            $updateStmt = $pdo->prepare($updateSql);
            
            if ($updateStmt->execute([$name, $roll_number, $department, $semester, $student_id])) {
                $message = "Profile updated successfully!";
                
                // CRITICAL: Update the active session variables so the dashboard updates instantly
                $_SESSION['student_name'] = $name;
                $_SESSION['department'] = $department;
                $_SESSION['semester'] = $semester;
            } else {
                $error = "Failed to update profile. Please try again.";
            }
        }
    }
}

// Fetch Current Details to pre-fill the form
$stmt = $pdo->prepare("SELECT name, email, roll_number, department, semester FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Profile - Examify</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7f6; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 40px auto; padding: 0 20px; }
        .card { background: white; padding: 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: bold; margin-bottom: 5px; color: #333;}
        input[type="text"], input[type="email"], select { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        .btn-update { background: #007bff; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; margin-bottom: 10px; }
        .btn-update:hover { background: #0056b3; }
        .btn-back { display: block; text-align: center; background: #6c757d; color: white; padding: 10px 15px; text-decoration: none; border-radius: 4px; }
        .btn-back:hover { background: #5a6268; }
        .alert { padding: 10px; margin-bottom: 15px; border-radius: 4px; }
        .alert-success { background-color: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .readonly-field { background-color: #e9ecef; cursor: not-allowed; }
    </style>
</head>
<body>

    <?php include 'navbar.php'; ?>

    <div class="container">
        <div class="card">
            <h2 style="margin-top: 0; border-bottom: 2px solid #f0f0f0; padding-bottom: 15px;">Edit Profile</h2>
            
            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <!-- Email is read-only because it acts as their unique login ID -->
                <div class="form-group">
                    <label>Email Address (Cannot be changed):</label>
                    <input type="email" value="<?php echo htmlspecialchars($student['email']); ?>" class="readonly-field" readonly>
                </div>

                <div class="form-group">
                    <label>Full Name:</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($student['name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Roll Number:</label>
                    <input type="text" name="roll_number" value="<?php echo htmlspecialchars($student['roll_number']); ?>" required>
                </div>

                <div class="form-group">
                    <label>Department:</label>
                    <select name="department" required>
                        <option value="BCA" <?php echo ($student['department'] == 'BCA') ? 'selected' : ''; ?>>BCA</option>
                        <option value="BBA" <?php echo ($student['department'] == 'BBA') ? 'selected' : ''; ?>>BBA</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Current Semester:</label>
                    <select name="semester" required>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?php echo $i; ?>" <?php echo ($student['semester'] == $i) ? 'selected' : ''; ?>>
                                Semester <?php echo $i; ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <button type="submit" name="update_profile" class="btn-update">Save Changes</button>
                <a href="profile.php" class="btn-back">Cancel & Go Back</a>
            </form>
        </div>
    </div>

</body>
</html>
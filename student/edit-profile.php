<?php
require_once 'student-guard.php';
require_once '../config/database.php';


$student_id = $_SESSION['student_id'];
$message = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name        = trim(strip_tags($_POST['name'] ?? ''));
    $roll_number = trim(strip_tags($_POST['roll_number'] ?? ''));
    $department  = trim(strip_tags($_POST['department'] ?? ''));
    $semester    = (int)($_POST['semester'] ?? 0);

    if (empty($name) || empty($roll_number) || empty($department) || $semester < 1 || $semester > 8) {
        $error = "All fields are required and must be valid.";
    } else {
        // Check if roll number is already used by another student
        $checkStmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ? AND id != ?");
        $checkStmt->execute([$roll_number, $student_id]);

        if ($checkStmt->rowCount() > 0) {
            $error = "This Roll Number is already registered to another student.";
        } else {
            $updateStmt = $pdo->prepare("
                UPDATE students 
                SET name = ?, roll_number = ?, department = ?, semester = ? 
                WHERE id = ?
            ");

            if ($updateStmt->execute([$name, $roll_number, $department, $semester, $student_id])) {
                $message = "Profile updated successfully!";

                // Update session so dashboard reflects changes immediately
                $_SESSION['student_name'] = $name;
                $_SESSION['department']   = $department;
                $_SESSION['semester']     = $semester;
            } else {
                $error = "Failed to update profile. Please try again.";
            }
        }
    }
}

// Fetch current details
$stmt = $pdo->prepare("SELECT name, email, roll_number, department, semester FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student record not found.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile • Examify</title>
    <link rel="stylesheet" href="../assets/css/student.css">
     <!-- <style>
        
     </style> -->
    
</head>
<body>
    
<?php include '../components/navbar.php'; ?>

<div class="container">
    <h1>Edit Profile</h1>
    <p class="subtitle">Update your personal information</p>

    <div class="card">
        <?php if ($message): ?>
            <div class="alert success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address (cannot be changed)</label>
                <input type="email" value="<?= htmlspecialchars($student['email']) ?>" readonly>
            </div>

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required 
                       value="<?= htmlspecialchars($student['name']) ?>">
            </div>

            <div class="form-group">
                <label>Roll Number</label>
                <input type="text" name="roll_number" required 
                       value="<?= htmlspecialchars($student['roll_number']) ?>">
            </div>

            <div class="form-group">
                <label>Department</label>
                <select name="department" required>
                    <option value="BCA" <?= $student['department'] === 'BCA' ? 'selected' : '' ?>>BCA</option>
                    <option value="BBA" <?= $student['department'] === 'BBA' ? 'selected' : '' ?>>BBA</option>
                </select>
            </div>

            <div class="form-group">
                <label>Current Semester</label>
                <select name="semester" required>
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>" <?= $student['semester'] == $i ? 'selected' : '' ?>>
                            Semester <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <button type="submit" name="update_profile" class="btn">Save Changes</button>
            <a href="profile.php" class="btn-secondary">Cancel & Go Back</a>
        </form>
    </div>
</div>

</body>
</html>
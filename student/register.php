<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim(strip_tags($_POST['name'] ?? ''));
    $email    = trim($_POST['email'] ?? '');
    $roll     = trim(strip_tags($_POST['roll_number'] ?? ''));
    $dept     = trim(strip_tags($_POST['department'] ?? ''));
    $pass     = $_POST['password'] ?? '';
    $cpass    = $_POST['confirm_password'] ?? '';
    $sem      = (int)($_POST['semester'] ?? 0);

    if (!$name || !$email || !$pass || !$roll || !$sem || !$dept) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif ($pass !== $cpass) {
        $error = "Passwords do not match.";
    } elseif (strlen($pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif ($sem < 1 || $sem > 8) {
        $error = "Semester must be between 1 and 8.";
    } else {
        try {
            // Check email
            $stmt = $pdo->prepare("SELECT id FROM students WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $error = "Email is already registered.";
            } else {
                // Check roll number
                $stmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ?");
                $stmt->execute([$roll]);
                if ($stmt->rowCount() > 0) {
                    $error = "Roll number is already registered.";
                } else {
                    $hashed = password_hash($pass, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("INSERT INTO students (name, email, password, roll_number, department, semester) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$name, $email, $hashed, $roll, $dept, $sem]);
                    $success = "Registration successful! You can now login.";
                }
            }
        } catch (PDOException $e) {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration • Examify</title>
    <link rel="stylesheet" href="../assets/css/auth.css">

</head>
<body>
    <div class="card">
        <h1>Create Account</h1>
        <p class="subtitle">Register as a student</p>

        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required value="<?= htmlspecialchars($_POST['name'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Roll Number</label>
                <input type="text" name="roll_number" required value="<?= htmlspecialchars($_POST['roll_number'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Department</label>
                <select name="department" required>
                    <option value="">Select Department</option>
                    <option value="BCA" <?= (($_POST['department'] ?? '') === 'BCA') ? 'selected' : '' ?>>BCA</option>
                    <option value="BBA" <?= (($_POST['department'] ?? '') === 'BBA') ? 'selected' : '' ?>>BBA</option>
                </select>
            </div>

            <div class="form-group">
                <label>Semester</label>
                <select name="semester" required>
                    <option value="">Select Semester</option>
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>" <?= (($_POST['semester'] ?? '') == $i) ? 'selected' : '' ?>>
                            Semester <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn">Register</button>
        </form>

        <p class="footer">
            Already have an account? <a href="login.php">Login here</a>
        </p>
    </div>
</body>
</html>

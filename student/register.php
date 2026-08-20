<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/sanitize.php';

init_secure_session();

if (is_student_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = clean_input($_POST['name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $roll = clean_input($_POST['roll_number'] ?? '');
    $dept = clean_input($_POST['department'] ?? '');
    $pass = $_POST['password'] ?? '';
    $cpass = $_POST['confirm_password'] ?? '';
    $sem = int_param($_POST['semester'] ?? 0);

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
            $error = safe_db_error($e, "Registration failed. Please check your information.");
        }
    }
}

$page_title = 'Student Registration • Examify';
$body_class = 'auth-body';
include __DIR__ . '/../components/header.php';
?>

<div class="auth-card" style="max-width: 480px;">
    <h1>Create Account</h1>
    <p class="subtitle">Register as a student for classroom examinations</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Full Name</label>
            <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Alex Johnson">
        </div>

        <div class="form-group">
            <label>College Email</label>
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="student@college.edu">
        </div>

        <div class="form-group">
            <label>Roll Number / Student ID</label>
            <input type="text" name="roll_number" required value="<?= e($_POST['roll_number'] ?? '') ?>" placeholder="e.g. BCA2401">
        </div>

        <div class="form-grid">
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
        </div>

        <div class="form-grid">
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required placeholder="Min 6 characters">
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Re-enter password">
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="margin-top: 8px; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">person_add</span> Register
        </button>
    </form>

    <p class="footer">
        Already have an account? <a href="login.php">Login here</a>
    </p>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

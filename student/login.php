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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
            $stmt->execute([$email]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password'])) {
                if (isset($student['status']) && $student['status'] === 'blocked') {
                    $error = "Your account is blocked. Please contact your instructor.";
                } else {
                    // Prevent session fixation
                    session_regenerate_id(true);

                    $_SESSION['student_id'] = $student['id'];
                    $_SESSION['student_name'] = $student['name'];
                    $_SESSION['roll_number'] = $student['roll_number'];
                    $_SESSION['semester'] = $student['semester'];
                    $_SESSION['department'] = $student['department'];

                    // Optional: Record active session token
                    $sessionToken = session_id();
                    try {
                        $upStmt = $pdo->prepare("UPDATE students SET active_session_id = ? WHERE id = ?");
                        $upStmt->execute([$sessionToken, $student['id']]);
                    } catch (PDOException) {
                        // Ignore if column doesn't exist yet
                    }

                    redirect('dashboard.php');
                }
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = safe_db_error($e, "Login service unavailable. Please try again.");
        }
    }
}

$page_title = 'Student Login • Examify';
$body_class = 'auth-body';
include __DIR__ . '/../components/header.php';
?>

<div class="auth-card">
        <img src="../assets/images/examify_logo.png" alt=".." height="50" width="50" style="background-color: black; border: none; border-radius: 8px;">
        <h1 style="text-align:left;">Student Login</h1>
        <p class="subtitle" style="text-align:left;">Sign in to start your examination</p>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Email Address</label>
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="student@college.edu">
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">login</span> Login
        </button>
    </form>

    <p class="footer">
        Don't have an account? <a href="register.php">Register here</a>
    </p>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

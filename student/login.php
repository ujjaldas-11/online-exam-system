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
if (!empty($_GET['error'])) {
    if ($_GET['error'] === 'concurrent_session') {
        $error = "You have been logged out because your account was accessed from another device or browser.";
    } elseif ($_GET['error'] === 'blocked') {
        $error = "Your account has been blocked. Please contact your instructor.";
    } elseif ($_GET['error'] === 'expired') {
        $error = "Your session has expired due to inactivity. Please log in again.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, email, password, roll_number, department, semester, status FROM students WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password'])) {
                $status = $student['status'] ?? 'active';

                if ($status === 'pending') {
                    $error = "Your account registration is currently pending instructor approval. Please contact your classroom proctor.";
                } elseif ($status === 'rejected') {
                    $error = "Your registration request was not approved by the administrator.";
                } elseif ($status === 'blocked') {
                    $error = "Your account has been blocked. Please contact your instructor.";
                } else {
                    // Prevent session fixation
                    session_regenerate_id(true);

                    $_SESSION['student_id'] = (int) $student['id'];
                    $_SESSION['student_name'] = $student['name'];
                    $_SESSION['roll_number'] = $student['roll_number'];
                    $_SESSION['semester'] = (int) $student['semester'];
                    $_SESSION['department'] = $student['department'];

                    // Enforce singleton active session
                    bind_active_session($pdo, 'student', (int) $student['id']);

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
    <div class="auth-header">
        <img src="../assets/images/examify_logo.png" alt="Examify Logo" class="auth-logo">
        <div class="auth-header-text">
            <h1>Student Login</h1>
            <p class="subtitle">Sign in to start your examination</p>
        </div>
    </div>

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
            <div class="password-wrapper">
                <input type="password" name="password" required placeholder="••••••••">
                <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password">
                    <span class="material-symbols-outlined">visibility</span>
                </button>
            </div>
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

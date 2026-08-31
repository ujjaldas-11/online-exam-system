<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/sanitize.php';

init_secure_session();

if (is_admin_logged_in()) {
    redirect('admin-dashboard.php');
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
            $stmt = $pdo->prepare("SELECT id, name, password FROM admins WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Prevent session fixation
                session_regenerate_id(true);

                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['role'] = 'admin';

                redirect('admin-dashboard.php');
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = safe_db_error($e, "Admin login unavailable.");
        }
    }
}

$page_title = 'Admin Login • Examify';
$body_class = 'auth-body';
include __DIR__ . '/../components/header.php';
?>

<div class="auth-card">
    <div class="auth-header">
        <img src="../assets/images/examify_logo.png" alt="Examify Logo" class="auth-logo">
        <div class="auth-header-text">
            <h2>Admin Login</h2>
            <p class="subtitle">Instructor & Examination Control</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Instructor Email</label>
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="admin@college.edu">
        </div>

        <div class="form-group">
            <label>Password</label>
            <input type="password" name="password" required placeholder="••••••••">
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">lock_open</span> Login as Admin
        </button>
    </form>

    <p class="footer">
        Authorized college staff only
    </p>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

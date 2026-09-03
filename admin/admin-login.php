<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/sanitize.php';

init_secure_session();

if (!is_system_initialized($pdo)) {
    redirect('setup.php');
}

if (is_admin_logged_in()) {
    redirect('admin-dashboard.php');
}

$error = '';
$success = '';

if (isset($_GET['error'])) {
    if ($_GET['error'] === 'retired') {
        $error = "This instructor account has been marked as retired/deactivated. Access is disabled.";
    } elseif ($_GET['error'] === 'concurrent_session') {
        $error = "You have been logged out because your administrator account was accessed from another device or browser.";
    } elseif ($_GET['error'] === 'expired') {
        $error = "Your session has expired due to inactivity. Please log in again.";
    }
} elseif (isset($_GET['msg']) && $_GET['msg'] === 'setup_complete') {
    $success = "Superadmin setup completed successfully! Please log in.";
}

if (has_flash('error')) {
    $error = get_flash('error');
}
if (has_flash('success')) {
    $success = get_flash('success');
}

require_once __DIR__ . '/../utils/rate-limiter.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Check rate limit before verifying credentials
    $rateCheck = RateLimiter::checkLogin($pdo, 'admin', $email, 5);
    if (!$rateCheck['allowed']) {
        http_response_code(429);
        $cooldownMin = ceil($rateCheck['retry_after'] / 60);
        $error = "Too many failed login attempts. For security reasons, access is temporarily locked. Please try again in {$cooldownMin} minute" . ($cooldownMin > 1 ? 's' : '') . " (or {$rateCheck['retry_after']}s).";
    } elseif (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, name, password, role, status, department FROM admins WHERE email = :email LIMIT 1");
            $stmt->execute([':email' => $email]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                if (($admin['status'] ?? 'active') === 'retired') {
                    $error = "This instructor account has been marked as retired/deactivated. Access is disabled.";
                } else {
                    // Reset failed login attempts upon successful authentication
                    RateLimiter::clearLogin($pdo, 'admin', $email);

                    // Prevent session fixation
                    session_regenerate_id(true);

                    $_SESSION['admin_id'] = (int) $admin['id'];
                    $_SESSION['admin_name'] = $admin['name'];
                    $_SESSION['admin_role'] = $admin['role'] ?? 'teacher';
                    $_SESSION['role'] = $admin['role'] ?? 'teacher';
                    $_SESSION['admin_dept'] = $admin['department'] ?? '';

                    // Enforce singleton active session
                    bind_active_session($pdo, 'admin', (int) $admin['id']);

                    log_admin_action($pdo, 'login', 'admin', $admin['id'], "Admin/Teacher logged in: {$admin['name']}");

                    redirect('admin-dashboard.php');
                }
            } else {
                $failInfo = RateLimiter::recordFailedLogin($pdo, 'admin', $email, 300, 5);
                $remaining = $failInfo['remaining'];
                if ($remaining > 0) {
                    $error = "Invalid email or password. {$remaining} attempt" . ($remaining === 1 ? '' : 's') . " remaining before temporary lockout.";
                } else {
                    http_response_code(429);
                    $error = "Invalid email or password. Maximum attempts exceeded; please wait 5 minutes before trying again.";
                }
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

    <?php if ($success): ?>
        <div class="alert alert-success"><?= e($success) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Instructor Email</label>
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="admin@college.edu">
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
            <span class="material-symbols-outlined icon-sm">lock_open</span> Login as Admin
        </button>
    </form>

    <p class="footer">
        Authorized college staff only
    </p>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

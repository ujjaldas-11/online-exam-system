<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/sanitize.php';

init_secure_session();

// If system is already initialized with a superadmin, lock this page permanently
if (is_system_initialized($pdo)) {
    redirect('admin-login.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = clean_input($_POST['name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please provide a valid email address.";
    } elseif (strlen($password) < 8) {
        $error = "Superadmin password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } else {
        try {
            // Concurrency guard: double-check if another session initialized
            $checkStmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'superadmin'");
            if ((int)$checkStmt->fetchColumn() > 0) {
                redirect('admin-login.php');
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            // Check if this email already exists in admins table (e.g. from prior test)
            $existing = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
            $existing->execute([$email]);
            $existingId = $existing->fetchColumn();

            if ($existingId) {
                // Elevate to superadmin and update credentials
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, password = ?, role = 'superadmin', status = 'active' WHERE id = ?");
                $stmt->execute([$name, $hashed, $existingId]);
                $superadminId = (int) $existingId;
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO admins (name, email, password, role, status, department)
                    VALUES (?, ?, ?, 'superadmin', 'active', 'Administration')
                ");
                $stmt->execute([$name, $email, $hashed]);
                $superadminId = (int) $pdo->lastInsertId();
            }

            // Record initial audit trail event
            log_admin_action(
                $pdo,
                'system_initialized',
                'system',
                $superadminId,
                "Master Superadmin initialized the system ($name / $email).",
                $superadminId,
                $name,
                'superadmin'
            );

            // Establish authenticated Superadmin session immediately
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $superadminId;
            $_SESSION['admin_name'] = $name;
            $_SESSION['admin_role'] = 'superadmin';
            $_SESSION['role'] = 'superadmin';

            set_flash('success', 'System initialized successfully! Welcome, ' . $name . '.');
            redirect('admin-dashboard.php');
        } catch (PDOException $e) {
            $error = safe_db_error($e, "System initialization failed. Please verify database connection.");
        }
    }
}

$page_title = 'Initial System Setup • Examify';
$body_class = 'auth-body';
include __DIR__ . '/../components/header.php';
?>

<div class="auth-card" style="max-width: 500px;">
    <div class="auth-header">
        <img src="../assets/images/examify_logo.png" alt="Examify Logo" class="auth-logo">
        <div class="auth-header-text">
            <h2>System Initialization</h2>
            <p class="subtitle">Master Superadmin One-Time Configuration</p>
        </div>
    </div>

    <div class="alert alert-info" style="font-size: 0.88rem; line-height: 1.5; margin-bottom: 20px;">
        <span class="material-symbols-outlined icon-sm" style="vertical-align: text-bottom;">info</span>
        <strong>Welcome to Examify!</strong> The system is initializing for the first time. Set your primary <strong>Superadmin password</strong> below. Once configured, this setup locks permanently, and you can create separate accounts for teachers and instructors.
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Superadmin Full Name</label>
            <input type="text" name="name" required value="<?= e($_POST['name'] ?? 'System Administrator') ?>" placeholder="e.g. Dr. Sarah Admin">
        </div>

        <div class="form-group">
            <label>Superadmin Email Address</label>
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? 'admin@college.edu') ?>" placeholder="admin@college.edu">
        </div>

        <div class="form-group">
            <label>Master Superadmin Password</label>
            <input type="password" name="password" required placeholder="Minimum 8 characters" minlength="8" autocomplete="new-password">
            <small style="color: var(--color-text-secondary); font-size: 0.8rem; display: block; margin-top: 4px;">
                Choose a strong master password (letters, numbers & symbols recommended).
            </small>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" required placeholder="Re-enter password" minlength="8" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; margin-top: 10px; padding: 12px;">
            <span class="material-symbols-outlined icon-sm">verified_user</span> Initialize System &amp; Proceed
        </button>
    </form>

    <p class="footer" style="margin-top: 20px;">
        Protected setup protocol • Executed once on deployment
    </p>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

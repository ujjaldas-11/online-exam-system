<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/sanitize.php';

init_secure_session();

// If system is already initialized with a superadmin, lock this page
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
            $checkStmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'superadmin'");
            if ((int)$checkStmt->fetchColumn() > 0) {
                redirect('admin-login.php');
            }

            $hashed = password_hash($password, PASSWORD_DEFAULT);

            $existing = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
            $existing->execute([$email]);
            $existingId = $existing->fetchColumn();

            if ($existingId) {
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

            log_admin_action(
                $pdo,
                'system_setup',
                'admin',
                $superadminId,
                "Initial Superadmin ($name) setup completed via setup.php",
                $superadminId,
                $name,
                'superadmin'
            );

            redirect('admin-login.php?msg=setup_complete');
        } catch (PDOException $e) {
            $error = safe_db_error($e, "System setup failed. Please check your database connection.");
        }
    }
}

$page_title = 'First-Time Setup • Examify';
$body_class = 'auth-body';
include __DIR__ . '/../components/header.php';
?>

<div class="auth-card">
    <div class="auth-header">
        <img src="../assets/images/examify_logo.png" alt="Examify Logo" class="auth-logo">
        <div class="auth-header-text">
            <h1>First-Time Setup</h1>
            <p class="subtitle">Create Root Superadmin Account</p>
        </div>
    </div>

    <div class="alert alert-warning" style="margin-bottom: 20px; font-size: 0.85rem;">
        <strong>Initial System Provisioning:</strong> No administrator account was detected in the database. Please configure your master administrative credentials below.
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>

        <div class="form-group">
            <label>Superadmin Full Name</label>
            <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Dr. Administrator">
        </div>

        <div class="form-group">
            <label>Master Email Address</label>
            <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="admin@college.edu">
        </div>

        <div class="form-group">
            <label>Password (Min 8 characters)</label>
            <div class="password-wrapper">
                <input type="password" name="password" required minlength="8" placeholder="••••••••••••">
                <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password">
                    <span class="material-symbols-outlined">visibility</span>
                </button>
            </div>
        </div>

        <div class="form-group">
            <label>Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" required minlength="8" placeholder="••••••••••••">
                <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password">
                    <span class="material-symbols-outlined">visibility</span>
                </button>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">admin_panel_settings</span> Initialize System
        </button>
    </form>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

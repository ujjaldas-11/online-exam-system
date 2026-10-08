<?php
require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/sanitize.php';

init_secure_session();

// Ensure the user is an authenticated admin
if (!is_admin_logged_in()) {
    redirect('admin-login.php');
}

$admin_id = (int) $_SESSION['admin_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error = "New password must be at least 8 characters long.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $hash = $stmt->fetchColumn();

            if ($hash && password_verify($current_password, $hash)) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $updateStmt->execute([$new_hash, $admin_id]);
                
                $success = "Password updated successfully.";
                log_admin_action($pdo, 'password_change', 'admin', $admin_id, "Admin updated their password.");
            } else {
                $error = "Incorrect current password.";
            }
        } catch (PDOException $e) {
            log_error("Password change failed for admin $admin_id", $e);
            $error = "Failed to update password. Please try again later.";
        }
    }
}

$page_title = 'Reset Password • Examify Admin';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php'; 
?>

<!-- Wrap in admin-main if your layout requires it for sidebar offset -->
<main class="admin-main" style="padding: 24px;">
    <div class="container" style="max-width: 550px; margin: 0 auto; padding-top: 40px; padding-bottom: 60px;">
        
        <div class="page-header" style="margin-bottom: 24px;">
            <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--color-dark); margin: 0;">Reset Password</h1>
            <p style="color: var(--color-text-secondary); margin: 4px 0 0;">Update your administrator account password securely.</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error" style="margin-bottom: 24px;"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success" style="margin-bottom: 24px;"><?= e($success) ?></div>
        <?php endif; ?>

        <div class="card" style="background: #fff; border-radius: 12px; border: 1px solid var(--color-border); box-shadow: 0 4px 6px rgba(0,0,0,0.02); padding: 32px;">
            <form method="POST" action="">
                <?= csrf_field() ?>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: var(--color-dark);">Current Password</label>
                    <div class="password-wrapper" style="position: relative;">
                        <input type="password" name="current_password" required style="width: 100%; padding: 12px 40px 12px 16px; border: 1px solid var(--color-border); border-radius: 8px; font-size: 0.95rem;">
                        <button type="button" class="password-toggle-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--color-text-secondary);">
                            <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
                        </button>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: var(--color-dark);">New Password</label>
                    <div class="password-wrapper" style="position: relative;">
                        <input type="password" name="new_password" required minlength="8" style="width: 100%; padding: 12px 40px 12px 16px; border: 1px solid var(--color-border); border-radius: 8px; font-size: 0.95rem;">
                        <button type="button" class="password-toggle-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--color-text-secondary);">
                            <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
                        </button>
                    </div>
                    <small style="color: var(--color-text-secondary); font-size: 0.8rem; margin-top: 6px; display: block;">Must be at least 8 characters long.</small>
                </div>

                <div class="form-group" style="margin-bottom: 32px;">
                    <label style="display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.9rem; color: var(--color-dark);">Confirm New Password</label>
                    <div class="password-wrapper" style="position: relative;">
                        <input type="password" name="confirm_password" required minlength="8" style="width: 100%; padding: 12px 40px 12px 16px; border: 1px solid var(--color-border); border-radius: 8px; font-size: 0.95rem;">
                        <button type="button" class="password-toggle-btn" style="position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: var(--color-text-secondary);">
                            <span class="material-symbols-outlined" style="font-size: 20px;">visibility</span>
                        </button>
                    </div>
                </div>

                <div style="display: flex; gap: 12px;">
                    <button type="submit" class="btn btn-primary" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; flex: 1; padding: 12px;">
                        <span class="material-symbols-outlined icon-sm">save</span> Update Password
                    </button>
                    <a href="admin-dashboard.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; justify-content: center; flex: 1; padding: 12px;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../components/footer.php'; ?>

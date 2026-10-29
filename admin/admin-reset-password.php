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
        set_flash('error',"All password fields are required.");
    } elseif ($new_password !== $confirm_password) {
        set_flash('error', "New passwords do not match.");
    } elseif (strlen($new_password) < 8) {
        set_flash('error',"New password must be at least 8 characters long.");
    } else {
        try {
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $hash = $stmt->fetchColumn();

            if ($hash && password_verify($current_password, $hash)) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $updateStmt->execute([$new_hash, $admin_id]);
                
                set_flash('success',"Password updated successfully.");
                log_admin_action($pdo, 'password_change', 'admin', $admin_id, "Admin updated their password.");
                redirect('admin-dashboard.php');
            } else {
                set_flash('error',"Incorrect current password.");
                redirect('admin-reset-password.php');
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

        <?php include __DIR__ . '/../components/flash-messages.php'; ?>
        <div class="card" style="background: #fff; border-radius: 12px; border: 1px solid var(--color-border); box-shadow: 0 4px 6px rgba(0,0,0,0.02); padding: 32px;">
            <form method="POST" action="" class="password-form">
                <?= csrf_field() ?>

                <div class="form-group float-field">
                <div class="password-wrapper">
                    <input type="password" name="current_password" id="current_password" required placeholder=" ">
                    <label for="current_password">Current Password</label>
                    <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password">
                    <span class="material-symbols-outlined">visibility</span>
                    </button>
                </div>
                </div>

                <div class="form-group float-field">
                <div class="password-wrapper">
                    <input type="password" name="new_password" id="new_password" required minlength="8" placeholder=" ">
                    <label for="new_password">New Password</label>
                    <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password">
                    <span class="material-symbols-outlined">visibility</span>
                    </button>
                </div>
                <small class="field-hint">Must be at least 8 characters long.</small>
                </div>

                <div class="form-group float-field form-group--last">
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" id="confirm_password" required minlength="8" placeholder=" ">
                    <label for="confirm_password">Confirm New Password</label>
                    <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password">
                    <span class="material-symbols-outlined">visibility</span>
                    </button>
                </div>
                </div>

                <div class="form-actions">
                <button type="submit" class="btn btn-primary">
                    <span class="material-symbols-outlined icon-sm">save</span> Update Password
                </button>
                <a href="admin-dashboard.php" class="btn btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../components/footer.php'; ?>

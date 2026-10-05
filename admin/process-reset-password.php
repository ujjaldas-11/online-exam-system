<?php
ob_start();
require_once '../utils/session.php';
require_once '../config/database.php';
require_once '../utils/logger.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: admin-login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $admin_id = (int) $_SESSION['admin_id'];
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Hardcoded redirect to ensure it always goes to the dashboard
    $redirect_url = 'admin-dashboard.php';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        set_flash('error', "All fields are required.");
        header("Location: " . $redirect_url);
        exit;
    }

    if ($new_password !== $confirm_password) {
        set_flash('error', "New passwords do not match.");
        header("Location: " . $redirect_url);
        exit;
    }

    if (strlen($new_password) < 6) {
        set_flash('error', "New password must be at least 6 characters long.");
        header("Location: " . $redirect_url);
        exit;
    }

    try {
        $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
        $stmt->execute([$admin_id]);
        $admin = $stmt->fetch();

        if (!$admin || !password_verify($current_password, $admin['password'])) {
            set_flash('error', "Incorrect current password.");
            header("Location: " . $redirect_url);
            exit;
        }

        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $updateStmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
        $updateStmt->execute([$hashed_password, $admin_id]);

        set_flash('success', "Password updated successfully!");
        header("Location: " . $redirect_url);
        exit;

    } catch (PDOException $e) {
        log_error("Admin password reset failed", $e);
        set_flash('error', "Database error occurred.");
        header("Location: " . $redirect_url);
        exit;
    }
}
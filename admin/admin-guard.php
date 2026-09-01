<?php

require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../config/database.php';

require_admin();

// Verify admin account is still active and update session role
if (isset($pdo) && !empty($_SESSION['admin_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT status, role, name FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $currAdmin = $stmt->fetch();

        if (!$currAdmin || ($currAdmin['status'] ?? 'active') === 'retired') {
            destroy_user_session('admin-login.php?error=retired');
            exit;
        }

        $_SESSION['admin_name'] = $currAdmin['name'];
        $_SESSION['admin_role'] = $currAdmin['role'];
        $_SESSION['role'] = $currAdmin['role'];
    } catch (PDOException) {}
}

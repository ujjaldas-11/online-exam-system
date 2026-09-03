<?php

require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/session.php';

require_admin();

// Verify admin account is still active, update session role, and enforce singleton login
if (isset($pdo) && !empty($_SESSION['admin_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT status, role, name, active_session_id FROM admins WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['admin_id']]);
        $currAdmin = $stmt->fetch();

        if (!$currAdmin || ($currAdmin['status'] ?? 'active') === 'retired') {
            destroy_user_session('admin-login.php?error=retired');
            exit;
        }

        // Enforce singleton active session: terminate older session if logged in on another device
        $currentSessionId = session_id();
        if (!empty($currAdmin['active_session_id']) && $currAdmin['active_session_id'] !== $currentSessionId) {
            destroy_user_session('admin-login.php?error=concurrent_session');
            exit;
        }

        $_SESSION['admin_name'] = $currAdmin['name'];
        $_SESSION['admin_role'] = $currAdmin['role'];
        $_SESSION['role'] = $currAdmin['role'];
    } catch (PDOException) {}
}

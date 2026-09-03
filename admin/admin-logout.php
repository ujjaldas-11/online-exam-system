<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/auth.php';

init_secure_session();

if (isset($pdo) && !empty($_SESSION['admin_id'])) {
    try {
        clear_active_session($pdo, 'admin', (int) $_SESSION['admin_id']);
    } catch (Throwable) {}
}

destroy_user_session('admin-login.php');

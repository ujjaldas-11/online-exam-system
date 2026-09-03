<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/auth.php';

init_secure_session();

if (isset($pdo) && !empty($_SESSION['student_id'])) {
    try {
        clear_active_session($pdo, 'student', (int) $_SESSION['student_id']);
    } catch (Throwable) {}
}

destroy_user_session('login.php');

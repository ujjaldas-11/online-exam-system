<?php

/**
 * Student Authentication & Singleton Session Guard
 */

require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/session.php';

require_student();

// Enforce singleton login: ensure this session is the only active session for this student
if (isset($pdo) && !empty($_SESSION['student_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT status, active_session_id FROM students WHERE id = ? LIMIT 1");
        $stmt->execute([$_SESSION['student_id']]);
        $currStudent = $stmt->fetch();

        if (!$currStudent || $currStudent['status'] === 'blocked') {
            destroy_user_session('login.php?error=blocked');
            exit;
        }

        $currentSessionId = session_id();
        if (!empty($currStudent['active_session_id']) && $currStudent['active_session_id'] !== $currentSessionId) {
            destroy_user_session('login.php?error=concurrent_session');
            exit;
        }
    } catch (PDOException) {}
}

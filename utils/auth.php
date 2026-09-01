<?php

/**
 * Authentication Helpers
 */

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/response.php';

function is_admin_logged_in(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    return !empty($_SESSION['admin_id']);
}

function is_superadmin(): bool
{
    if (!is_admin_logged_in()) {
        return false;
    }
    $role = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? '';
    return $role === 'superadmin';
}

function is_teacher(): bool
{
    if (!is_admin_logged_in()) {
        return false;
    }
    $role = $_SESSION['admin_role'] ?? $_SESSION['role'] ?? '';
    return $role === 'teacher';
}

function get_admin_role(): string
{
    if (!is_admin_logged_in()) {
        return '';
    }
    return $_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'teacher';
}

function is_student_logged_in(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    return !empty($_SESSION['student_id']);
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        redirect('admin-login.php');
    }
}

function require_superadmin(): void
{
    require_admin();
    if (!is_superadmin()) {
        http_response_code(403);
        set_flash('error', 'Access denied: Superadmin privileges required.');
        redirect('admin-dashboard.php');
    }
}

function require_student(): void
{
    if (!is_student_logged_in()) {
        redirect('login.php');
    }
}

function is_system_initialized(PDO $pdo): bool
{
    try {
        $check = $pdo->query("SHOW TABLES LIKE 'admins'")->fetchColumn();
        if (!$check) {
            return false;
        }
        $stmt = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'superadmin'");
        return ((int) $stmt->fetchColumn()) > 0;
    } catch (Throwable) {
        return false;
    }
}

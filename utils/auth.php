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

function require_student(): void
{
    if (!is_student_logged_in()) {
        redirect('login.php');
    }
}

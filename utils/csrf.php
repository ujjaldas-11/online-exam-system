<?php

/**
 * Cross-Site Request Forgery (CSRF) Protection Utility
 */

require_once __DIR__ . '/session.php';

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

function verify_csrf(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }

    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
        http_response_code(403);
        die("Security Error: Invalid or expired CSRF token. Please refresh and try again.");
    }
}

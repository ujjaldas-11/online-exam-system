<?php

/**
 * Cross-Site Request Forgery (CSRF) Protection Utility
 * Supports HTML forms and AJAX JSON requests with headers or body tokens.
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

function is_csrf_valid(?string $tokenToCheck = null): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (empty($sessionToken)) {
        return false;
    }

    $token = $tokenToCheck;
    if ($token === null) {
        $token = $_POST['csrf_token']
            ?? $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_XSRF_TOKEN']
            ?? '';
    }

    if (empty($token)) {
        return false;
    }

    return hash_equals($sessionToken, (string) $token);
}

function verify_csrf(?string $tokenToCheck = null): void
{
    if (!is_csrf_valid($tokenToCheck)) {
        http_response_code(403);
        if (
            (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
            (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'application/json'))
        ) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['error' => 'Security Error: Invalid or expired CSRF token. Please reload.']);
            exit;
        }
        die("Security Error: Invalid or expired CSRF token. Please refresh and try again.");
    }
}

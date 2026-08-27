<?php

/**
 * Session Security & Management Helper
 */

function init_secure_session(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            $isSecure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
                || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'domain' => '',
                'secure' => $isSecure,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        session_start();
    }

    // Check for idle session timeout (30 minutes)
    $timeout = 1800; // 30 mins
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $timeout)) {
        session_unset();
        session_destroy();
        session_start();
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Flash Messaging Helpers
 */
function set_flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    $_SESSION['flash'][$type] = $message;
}

function get_flash(string $type): ?string
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    if (isset($_SESSION['flash'][$type])) {
        $message = $_SESSION['flash'][$type];
        unset($_SESSION['flash'][$type]);
        return $message;
    }
    return null;
}

function has_flash(string $type): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    return !empty($_SESSION['flash'][$type]);
}

/**
 * Terminate current session securely and redirect
 */
function destroy_user_session(string $redirectUrl): void
{
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }

    $_SESSION = [];

    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params["path"],
            $params["domain"],
            $params["secure"],
            $params["httponly"]
        );
    }

    session_destroy();

    header("Location: $redirectUrl");
    exit;
}

<?php

/**
 * Environment & Security Header Helper
 */

function load_env(?string $path = null): void
{
    static $isLoaded = false;
    if ($isLoaded && $path === null) {
        return;
    }

    if ($path === null) {
        $path = __DIR__ . '/../.env';
        if (!file_exists($path)) {
            $path = __DIR__ . '/../../.env';
        }
    }

    if (!file_exists($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        if (str_contains($line, '=')) {
            list($name, $value) = explode('=', $line, 2);

            $name = trim($name);
            $value = trim($value);

            // Strip surrounding quotes
            $value = trim($value, '"\'');

            $_ENV[$name] = $value;
            putenv("$name=$value");
        }
    }

    $isLoaded = true;
}

function get_env(string $key, mixed $default = null): mixed
{
    load_env();

    if (isset($_ENV[$key])) {
        return $_ENV[$key];
    }

    $val = getenv($key);
    if ($val !== false) {
        return $val;
    }

    return $default;
}

function is_development(): bool
{
    $env = strtolower((string) get_env('APP_ENV', 'development'));
    return in_array($env, ['development', 'dev', 'local', 'debug'], true);
}

function is_production(): bool
{
    return !is_development();
}

function asset_version(): string
{
    return is_development() ? (string) time() : '2.0.0';
}

function asset_url(string $path): string
{
    $version = asset_version();
    $separator = (str_contains($path, '?')) ? '&' : '?';
    return $path . $separator . 'v=' . $version;
}

function send_cache_control_headers(): void
{
    if (php_sapi_name() === 'cli' || headers_sent()) {
        return;
    }

    if (is_development()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        header('Expires: 0');
    }
}

function is_ssl(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') {
        return true;
    }
    return false;
}

function enforce_ssl_in_production(): void
{
    if (php_sapi_name() === 'cli' || headers_sent()) {
        return;
    }

    if (is_production()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

        if (!is_ssl()) {
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $uri = $_SERVER['REQUEST_URI'] ?? '/';
            header('Location: https://' . $host . $uri, true, 301);
            exit;
        }
    }
}

function send_security_headers(): void
{
    if (php_sapi_name() === 'cli' || headers_sent()) {
        return;
    }

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('X-XSS-Protection: 1; mode=block');
}

load_env();
date_default_timezone_set((string) get_env('APP_TIMEZONE', 'Asia/Kolkata'));
send_security_headers();
enforce_ssl_in_production();
send_cache_control_headers();


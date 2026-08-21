<?php

/**
 * Environment & Caching Helper
 *
 * Handles .env parsing and environment-aware caching controls.
 */

/**
 * Load environment variables from .env file into $_ENV and putenv().
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

        if ($line === '' || strpos($line, '#') === 0) {
            continue;
        }

        if (strpos($line, '=') !== false) {
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

/**
 * Get an environment variable with optional fallback.
 */
function get_env(string $key, $default = null)
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

/**
 * Check if the application is running in development mode.
 */
function is_development(): bool
{
    $env = strtolower((string) get_env('APP_ENV', 'development'));
    return in_array($env, ['development', 'dev', 'local', 'debug'], true);
}

/**
 * Check if the application is running in production mode.
 */
function is_production(): bool
{
    return !is_development();
}

/**
 * Get the cache-busting asset version string.
 * In development: dynamic timestamp to guarantee fresh assets on reload.
 * In production: static version string for efficient browser caching.
 */
function asset_version(): string
{
    return is_development() ? (string) time() : '1.0.0';
}

/**
 * Generate an asset URL with cache-busting query parameter in development.
 */
function asset_url(string $path): string
{
    $version = asset_version();
    $separator = (strpos($path, '?') === false) ? '?' : '&';
    return $path . $separator . 'v=' . $version;
}

/**
 * Send HTTP headers to prevent browser caching when in development mode.
 */
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

// Auto-load .env and apply cache control headers on include
load_env();
send_cache_control_headers();

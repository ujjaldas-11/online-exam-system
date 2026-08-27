<?php

/**
 * Input Sanitization & Output Escaping Helpers
 */

function e(?string $string): string
{
    return htmlspecialchars((string) $string, ENT_QUOTES, 'UTF-8');
}

function clean_input(?string $input): string
{
    if ($input === null) {
        return '';
    }
    return trim(strip_tags($input));
}

function int_param(mixed $value, int $default = 0): int
{
    if ($value === null || $value === '') {
        return $default;
    }
    return filter_var($value, FILTER_VALIDATE_INT) !== false ? (int) $value : $default;
}

/**
 * Sanitize CSV cell values to prevent Formula Injection (CSV / DDE Injection).
 * Prepend a single quote if the first character is an executable spreadsheet formula trigger.
 */
function sanitize_csv_value(?string $value): string
{
    if ($value === null) {
        return '';
    }
    $trimmed = trim(strip_tags($value));
    if ($trimmed !== '') {
        $firstChar = $trimmed[0];
        if (in_array($firstChar, ['=', '+', '-', '@', '%'], true)) {
            return "'" . $trimmed;
        }
    }
    return $trimmed;
}

/**
 * Validate and sanitize asset filenames/paths to prevent Local File Inclusion (LFI)
 * and Path Traversal attacks.
 */
function sanitize_asset_name(?string $path, string $allowedExt): ?string
{
    if (empty($path)) {
        return null;
    }

    $path = trim($path);

    // Reject null bytes, directory traversal sequences, windows backslashes, protocol wrappers
    if (strpos($path, "\0") !== false ||
        strpos($path, '..') !== false ||
        strpos($path, '\\') !== false ||
        strpos($path, './') !== false ||
        preg_match('#^(https?|ftp|file|php|data|javascript):#i', $path) ||
        strpos($path, '//') === 0
    ) {
        return null;
    }

    // Strip leading slashes to prevent root-relative or absolute system paths
    $path = ltrim($path, '/');

    // Only allow alphanumeric characters, underscores, dashes, dots, and forward slashes
    if (!preg_match('#^[a-zA-Z0-9_\-\./]+$#', $path)) {
        return null;
    }

    // Verify extension strictly matches allowed extension
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== strtolower($allowedExt)) {
        return null;
    }

    return $path;
}


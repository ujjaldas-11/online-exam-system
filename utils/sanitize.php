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

function sanitize_asset_name(?string $path, string $allowedExt): ?string
{
    if (empty($path)) {
        return null;
    }

    $path = trim($path);

    if (str_contains($path, "\0") ||
        str_contains($path, '..') ||
        str_contains($path, '\\') ||
        str_contains($path, './') ||
        preg_match('#^(https?|ftp|file|php|data|javascript):#i', $path) ||
        str_starts_with($path, '//')
    ) {
        return null;
    }

    $path = ltrim($path, '/');

    if (!preg_match('#^[a-zA-Z0-9_\-\./]+$#', $path)) {
        return null;
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    if ($ext !== strtolower($allowedExt)) {
        return null;
    }

    return $path;
}

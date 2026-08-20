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

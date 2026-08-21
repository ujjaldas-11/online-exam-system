<?php

/**
 * HTTP Response and Redirect Helpers
 */

require_once __DIR__ . '/env.php';

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    send_cache_control_headers();
    echo json_encode($data);
    exit;
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

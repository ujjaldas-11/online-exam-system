<?php

/**
 * HTTP Response and Redirect Helpers
 */

function json_response(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

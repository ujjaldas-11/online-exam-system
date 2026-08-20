<?php

/**
 * Application Logger
 */

function log_error(string $message, ?Throwable $exception = null): void
{
    $logDir = __DIR__ . '/../logs';
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $logFile = $logDir . '/app_errors.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $logMessage = "[$timestamp] [IP: $ip] $message";
    if ($exception !== null) {
        $logMessage .= " | Exception: " . $exception->getMessage() . " in " . $exception->getFile() . ":" . $exception->getLine();
    }
    $logMessage .= PHP_EOL;

    @file_put_contents($logFile, $logMessage, FILE_APPEND | LOCK_EX);
}

function safe_db_error(PDOException $e, string $userMessage = "A database error occurred. Please try again."): string
{
    log_error("Database Error", $e);
    return $userMessage;
}

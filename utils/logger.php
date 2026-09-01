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

/**
 * Immutable Admin & Teacher Activity Tracking
 */
function log_admin_action(
    PDO $pdo,
    string $action,
    ?string $entityType = null,
    ?int $entityId = null,
    ?string $details = null,
    ?int $adminIdOverride = null,
    ?string $adminNameOverride = null,
    ?string $adminRoleOverride = null
): bool {
    if (session_status() === PHP_SESSION_NONE && function_exists('init_secure_session')) {
        init_secure_session();
    }
    $adminId = $adminIdOverride ?? ($_SESSION['admin_id'] ?? null);
    $adminName = $adminNameOverride ?? ($_SESSION['admin_name'] ?? 'System');
    $adminRole = $adminRoleOverride ?? ($_SESSION['admin_role'] ?? $_SESSION['role'] ?? 'system');
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_logs (admin_id, admin_name, admin_role, action, entity_type, entity_id, details, ip_address)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        return $stmt->execute([$adminId, $adminName, $adminRole, $action, $entityType, $entityId, $details, $ip]);
    } catch (Throwable $e) {
        log_error("Failed writing admin audit log: " . $e->getMessage(), $e);
        return false;
    }
}


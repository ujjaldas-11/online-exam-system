<?php

/**
 * Examify Rate Limiter
 * Production-grade, zero-dependency rate limiting utility backed by MySQL/MariaDB.
 * Supports IP-based and account-based throttling, atomic token updates, and automatic expiration.
 */

declare(strict_types=1);

require_once __DIR__ . '/logger.php';

class RateLimiter
{
    private static bool $tableChecked = false;

    /**
     * Ensure the rate_limits database table exists.
     */
    public static function ensureTable(PDO $pdo): void
    {
        if (self::$tableChecked) {
            return;
        }

        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS `rate_limits` (
                    `rate_key` VARCHAR(128) NOT NULL,
                    `hits` INT NOT NULL DEFAULT 1,
                    `expires_at` DATETIME NOT NULL,
                    PRIMARY KEY (`rate_key`),
                    KEY `idx_expires_at` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            ");
            self::$tableChecked = true;

            // Opportunistic cleanup (1% probability per request)
            if (random_int(1, 100) === 1) {
                $pdo->exec("DELETE FROM `rate_limits` WHERE `expires_at` < NOW()");
            }
        } catch (PDOException $e) {
            log_error("RateLimiter failed to ensure database table", $e);
        }
    }

    /**
     * Extract client IP address reliably.
     */
    public static function getClientIp(): string
    {
        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '127.0.0.1';
    }

    /**
     * Check if a key is currently rate limited without incrementing its counter.
     *
     * @return array{allowed: bool, hits: int, remaining: int, retry_after: int, reset_at: int}
     */
    public static function check(PDO $pdo, string $key, int $maxAttempts): array
    {
        self::ensureTable($pdo);

        try {
            $stmt = $pdo->prepare("
                SELECT hits,
                       TIMESTAMPDIFF(SECOND, NOW(), expires_at) AS retry_after,
                       UNIX_TIMESTAMP(expires_at) AS reset_at
                FROM rate_limits
                WHERE rate_key = ? AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                return [
                    'allowed' => true,
                    'hits' => 0,
                    'remaining' => $maxAttempts,
                    'retry_after' => 0,
                    'reset_at' => 0
                ];
            }

            $hits = (int) $row['hits'];
            $retryAfter = max(1, (int) $row['retry_after']);
            $resetAt = (int) $row['reset_at'];
            $allowed = ($hits < $maxAttempts);
            $remaining = max(0, $maxAttempts - $hits);

            return [
                'allowed' => $allowed,
                'hits' => $hits,
                'remaining' => $remaining,
                'retry_after' => $allowed ? 0 : $retryAfter,
                'reset_at' => $resetAt
            ];
        } catch (PDOException $e) {
            log_error("RateLimiter check error for key $key", $e);
            return [
                'allowed' => true,
                'hits' => 0,
                'remaining' => $maxAttempts,
                'retry_after' => 0,
                'reset_at' => 0
            ];
        }
    }

    /**
     * Record a hit/attempt for a key. Atomically increments existing active key or creates a new window.
     *
     * @return array{allowed: bool, hits: int, remaining: int, retry_after: int, reset_at: int}
     */
    public static function hit(PDO $pdo, string $key, int $windowSeconds, int $maxAttempts = 1000): array
    {
        self::ensureTable($pdo);

        try {
            $stmt = $pdo->prepare("
                INSERT INTO rate_limits (rate_key, hits, expires_at)
                VALUES (:key, 1, DATE_ADD(NOW(), INTERVAL :win1 SECOND))
                ON DUPLICATE KEY UPDATE
                    hits = IF(expires_at < NOW(), 1, hits + 1),
                    expires_at = IF(expires_at < NOW(), DATE_ADD(NOW(), INTERVAL :win2 SECOND), expires_at)
            ");
            $stmt->execute([
                ':key' => $key,
                ':win1' => $windowSeconds,
                ':win2' => $windowSeconds
            ]);

            return self::check($pdo, $key, $maxAttempts);
        } catch (PDOException $e) {
            log_error("RateLimiter hit error for key $key", $e);
            return [
                'allowed' => true,
                'hits' => 1,
                'remaining' => max(0, $maxAttempts - 1),
                'retry_after' => 0,
                'reset_at' => time() + $windowSeconds
            ];
        }
    }

    /**
     * Clear all recorded attempts for a specific key.
     */
    public static function clear(PDO $pdo, string $key): void
    {
        self::ensureTable($pdo);

        try {
            $stmt = $pdo->prepare("DELETE FROM rate_limits WHERE rate_key = ?");
            $stmt->execute([$key]);
        } catch (PDOException $e) {
            log_error("RateLimiter clear error for key $key", $e);
        }
    }

    /**
     * Check composite login rate limits (both IP and Account identifier).
     *
     * @return array{allowed: bool, hits: int, remaining: int, retry_after: int, reset_at: int, blocked_by: string|null}
     */
    public static function checkLogin(PDO $pdo, string $scope, string $identifier, int $maxAttempts = 5): array
    {
        $ip = self::getClientIp();
        $ipKey = "login:{$scope}:ip:{$ip}";
        $accountKey = "login:{$scope}:act:" . strtolower(trim($identifier));

        $ipCheck = self::check($pdo, $ipKey, $maxAttempts);
        if (!$ipCheck['allowed']) {
            $ipCheck['blocked_by'] = 'ip';
            return $ipCheck;
        }

        if (!empty($identifier)) {
            $accountCheck = self::check($pdo, $accountKey, $maxAttempts);
            if (!$accountCheck['allowed']) {
                $accountCheck['blocked_by'] = 'account';
                return $accountCheck;
            }
        }

        $ipCheck['blocked_by'] = null;
        return $ipCheck;
    }

    /**
     * Record a failed login attempt for both IP and Account identifier.
     *
     * @return array{hits: int, remaining: int, retry_after: int}
     */
    public static function recordFailedLogin(PDO $pdo, string $scope, string $identifier, int $windowSeconds = 300, int $maxAttempts = 5): array
    {
        $ip = self::getClientIp();
        $ipKey = "login:{$scope}:ip:{$ip}";
        $accountKey = "login:{$scope}:act:" . strtolower(trim($identifier));

        $ipResult = self::hit($pdo, $ipKey, $windowSeconds, $maxAttempts);
        $actResult = !empty($identifier) ? self::hit($pdo, $accountKey, $windowSeconds, $maxAttempts) : $ipResult;

        $maxHits = max($ipResult['hits'], $actResult['hits']);
        $retryAfter = max($ipResult['retry_after'], $actResult['retry_after']);

        return [
            'hits' => $maxHits,
            'remaining' => max(0, $maxAttempts - $maxHits),
            'retry_after' => $retryAfter
        ];
    }

    /**
     * Clear failed login counters for both IP and Account identifier upon successful login.
     */
    public static function clearLogin(PDO $pdo, string $scope, string $identifier): void
    {
        $ip = self::getClientIp();
        self::clear($pdo, "login:{$scope}:ip:{$ip}");
        if (!empty($identifier)) {
            self::clear($pdo, "login:{$scope}:act:" . strtolower(trim($identifier)));
        }
    }
}

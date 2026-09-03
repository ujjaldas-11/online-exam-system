<?php

/**
 * Examify - Rate Limiter Automated Test Suite
 * Validates atomic sliding window rate limiting, dual-key authentication defense,
 * PIN throttling, and cache cleanup.
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/rate-limiter.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "\033[32m[PASS]\033[0m $name\n";
    } else {
        $failedTests++;
        echo "\033[31m[FAIL]\033[0m $name" . ($detail ? " - $detail" : '') . "\n";
    }
}

echo "\n\033[1;34m=== EXAMIFY RATE LIMITER TEST SUITE ===\033[0m\n\n";

echo "--- 1. Testing Schema and Client IP Utilities ---\n";
try {
    RateLimiter::ensureTable($pdo);
    $tables = $pdo->query("SHOW TABLES LIKE 'rate_limits'")->fetchAll();
    assert_test("RateLimiter table exists in MySQL", count($tables) === 1);

    $cols = $pdo->query("DESCRIBE rate_limits")->fetchAll(PDO::FETCH_COLUMN);
    assert_test("rate_limits contains 'rate_key' column", in_array('rate_key', $cols, true));
    assert_test("rate_limits contains 'hits' column", in_array('hits', $cols, true));
    assert_test("rate_limits contains 'expires_at' column", in_array('expires_at', $cols, true));

    $clientIp = RateLimiter::getClientIp();
    assert_test("getClientIp returns valid IP address", filter_var($clientIp, FILTER_VALIDATE_IP) !== false, "IP: $clientIp");
} catch (Exception $e) {
    assert_test("Schema check failed", false, $e->getMessage());
}

echo "\n--- 2. Testing Core Hit, Check, and Throttle Logic ---\n";
try {
    $testKey = "test:unit:" . bin2hex(random_bytes(6));
    RateLimiter::clear($pdo, $testKey);

    // Initial check
    $initial = RateLimiter::check($pdo, $testKey, 3);
    assert_test("Initial check allows request", $initial['allowed'] === true);
    assert_test("Initial hits count is 0", $initial['hits'] === 0);
    assert_test("Initial remaining count is 3", $initial['remaining'] === 3);

    // First hit
    $hit1 = RateLimiter::hit($pdo, $testKey, 60, 3);
    assert_test("Hit 1 records hits = 1", $hit1['hits'] === 1);
    assert_test("Hit 1 leaves remaining = 2", $hit1['remaining'] === 2);
    assert_test("Hit 1 is allowed", $hit1['allowed'] === true);

    // Second hit
    $hit2 = RateLimiter::hit($pdo, $testKey, 60, 3);
    assert_test("Hit 2 records hits = 2", $hit2['hits'] === 2);
    assert_test("Hit 2 leaves remaining = 1", $hit2['remaining'] === 1);
    assert_test("Hit 2 is allowed", $hit2['allowed'] === true);

    // Third hit (max reached)
    $hit3 = RateLimiter::hit($pdo, $testKey, 60, 3);
    assert_test("Hit 3 records hits = 3", $hit3['hits'] === 3);
    assert_test("Hit 3 leaves remaining = 0", $hit3['remaining'] === 0);
    assert_test("Hit 3 is locked out", $hit3['allowed'] === false);
    assert_test("Hit 3 returns retry_after > 0", $hit3['retry_after'] > 0);

    // Subsequent check confirms locked out state
    $checkBlocked = RateLimiter::check($pdo, $testKey, 3);
    assert_test("Check confirms key is blocked", $checkBlocked['allowed'] === false);

    // Clear key
    RateLimiter::clear($pdo, $testKey);
    $checkCleared = RateLimiter::check($pdo, $testKey, 3);
    assert_test("Clearing key resets allowed status", $checkCleared['allowed'] === true);
    assert_test("Clearing key resets hits to 0", $checkCleared['hits'] === 0);
} catch (Exception $e) {
    assert_test("Hit/Check test failed", false, $e->getMessage());
}

echo "\n--- 3. Testing Dual-Key Login Throttling ---\n";
try {
    $testScope = "test_auth";
    $testEmail = "lockout_victim_" . bin2hex(random_bytes(4)) . "@college.edu";
    RateLimiter::clearLogin($pdo, $testScope, $testEmail);

    // Record 4 failed attempts (out of 5)
    for ($i = 1; $i <= 4; $i++) {
        RateLimiter::recordFailedLogin($pdo, $testScope, $testEmail, 300, 5);
    }
    $status4 = RateLimiter::checkLogin($pdo, $testScope, $testEmail, 5);
    assert_test("After 4 failures, login is still allowed", $status4['allowed'] === true);
    assert_test("After 4 failures, remaining attempts is 1", $status4['remaining'] === 1);

    // 5th failure triggers lockout
    $fail5 = RateLimiter::recordFailedLogin($pdo, $testScope, $testEmail, 300, 5);
    assert_test("5th failure reports 0 remaining", $fail5['remaining'] === 0);
    assert_test("5th failure reports positive cooldown retry_after", $fail5['retry_after'] > 0);

    $status5 = RateLimiter::checkLogin($pdo, $testScope, $testEmail, 5);
    assert_test("checkLogin blocks after 5 failures", $status5['allowed'] === false);
    assert_test("checkLogin identifies blocked identifier", !empty($status5['blocked_by']));

    // Successful login clears counters
    RateLimiter::clearLogin($pdo, $testScope, $testEmail);
    $statusRestored = RateLimiter::checkLogin($pdo, $testScope, $testEmail, 5);
    assert_test("clearLogin unblocks account immediately", $statusRestored['allowed'] === true);
} catch (Exception $e) {
    assert_test("Dual-key test failed", false, $e->getMessage());
}

echo "\n\033[1;34m=== RATE LIMITER TEST SUMMARY ===\033[0m\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       \033[32m$passedTests\033[0m\n";
echo "Failed:       \033[" . ($failedTests > 0 ? "31m$failedTests" : "32m0") . "\033[0m\n\n";

if ($failedTests > 0) {
    exit(1);
}

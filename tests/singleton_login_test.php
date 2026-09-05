<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

/**
 * Examify - Singleton Login & Single-Device Session Automated Test Suite
 */
ob_start();
ini_set('session.use_cookies', '0');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/session.php';

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

function switch_cli_session(string $newSessionId): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    session_id($newSessionId);
    session_start();
}

echo "\n\033[1;34m=== EXAMIFY SINGLETON LOGIN TEST SUITE ===\033[0m\n\n";

// 1. Schema Validation
echo "--- 1. Testing Database Schema for Active Session Tracking ---\n";
$adminCols = $pdo->query("DESCRIBE admins")->fetchAll(PDO::FETCH_COLUMN);
assert_test("admins table contains 'active_session_id' column", in_array('active_session_id', $adminCols, true));

$studentCols = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_COLUMN);
assert_test("students table contains 'active_session_id' column", in_array('active_session_id', $studentCols, true));

// 2. Student Singleton Session Simulation
echo "\n--- 2. Testing Student Singleton Login Flow ---\n";

// Fetch an active student
$student = $pdo->query("SELECT id FROM students WHERE status = 'active' LIMIT 1")->fetch();
$studentId = (int) $student['id'];

// Device 1 logs in
switch_cli_session('studentdev1token12345');
bind_active_session($pdo, 'student', $studentId);

// Verify Device 1 is active
assert_test("Device 1 session binds successfully", verify_active_session($pdo, 'student', $studentId) === true);

// Check database value
$dbSession = $pdo->query("SELECT active_session_id FROM students WHERE id = $studentId")->fetchColumn();
assert_test("Database holds Device 1 session token", $dbSession === 'studentdev1token12345');

// Device 2 logs in with the same account
switch_cli_session('studentdev2token67890');
bind_active_session($pdo, 'student', $studentId);

// Verify Device 2 is now the active session
assert_test("Device 2 is recognized as the active session", verify_active_session($pdo, 'student', $studentId) === true);

// Device 1 attempts to make a request with its older session
switch_cli_session('studentdev1token12345');
assert_test("Device 1 session is superseded and rejected (returns false)", verify_active_session($pdo, 'student', $studentId) === false);

// Test Student Logout
clear_active_session($pdo, 'student', $studentId);
$clearedSession = $pdo->query("SELECT active_session_id FROM students WHERE id = $studentId")->fetchColumn();
assert_test("Logout clears active_session_id in database", empty($clearedSession));

// 3. Admin / Teacher Singleton Session Simulation
echo "\n--- 3. Testing Admin / Teacher Singleton Login Flow ---\n";

// Fetch an admin
$admin = $pdo->query("SELECT id FROM admins WHERE status = 'active' LIMIT 1")->fetch();
$adminId = (int) $admin['id'];

// Admin Device 1 logs in
switch_cli_session('admindev1tokenaaa111');
bind_active_session($pdo, 'admin', $adminId);
assert_test("Admin Device 1 binds successfully", verify_active_session($pdo, 'admin', $adminId) === true);

// Admin Device 2 logs in
switch_cli_session('admindev2tokenbbb222');
bind_active_session($pdo, 'admin', $adminId);
assert_test("Admin Device 2 is recognized as active", verify_active_session($pdo, 'admin', $adminId) === true);

// Admin Device 1 tries to access
switch_cli_session('admindev1tokenaaa111');
assert_test("Admin Device 1 session is superseded and rejected", verify_active_session($pdo, 'admin', $adminId) === false);

// Admin Logout
clear_active_session($pdo, 'admin', $adminId);
$clearedAdminSession = $pdo->query("SELECT active_session_id FROM admins WHERE id = $adminId")->fetchColumn();
assert_test("Admin logout clears active_session_id", empty($clearedAdminSession));

// 4. Guard & Controller Verification
echo "\n--- 4. Testing Guard Enforcement Code ---\n";
$studentGuard = file_get_contents(__DIR__ . '/../student/student-guard.php');
assert_test("student-guard.php enforces concurrent_session check", str_contains($studentGuard, 'active_session_id') && str_contains($studentGuard, 'destroy_user_session'));
assert_test("student-guard.php redirects to login.php?error=concurrent_session", str_contains($studentGuard, 'login.php?error=concurrent_session'));

$adminGuard = file_get_contents(__DIR__ . '/../admin/admin-guard.php');
assert_test("admin-guard.php enforces concurrent_session check", str_contains($adminGuard, 'active_session_id') && str_contains($adminGuard, 'destroy_user_session'));
assert_test("admin-guard.php redirects to admin-login.php?error=concurrent_session", str_contains($adminGuard, 'admin-login.php?error=concurrent_session'));

$questionApi = file_get_contents(__DIR__ . '/../student/question.php');
assert_test("question.php API validates verify_active_session", str_contains($questionApi, 'verify_active_session'));

// Summary
echo "\n\033[1;34m=== SINGLETON LOGIN TEST RESULTS ===\033[0m\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       \033[32m$passedTests\033[0m\n";
echo "Failed:       \033[" . ($failedTests > 0 ? "31m$failedTests" : "32m0") . "\033[0m\n\n";

ob_end_flush();

if ($failedTests > 0) {
    exit(1);
}

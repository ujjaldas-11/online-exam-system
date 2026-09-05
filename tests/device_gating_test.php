<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

/**
 * Examify - Device & Touchscreen Gating Automated Test Suite
 */
require_once __DIR__ . '/../utils/device.php';

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

echo "\n\033[1;34m=== EXAMIFY DEVICE & TOUCHSCREEN GATING TEST SUITE ===\033[0m\n\n";

// 1. Test Mobile and Tablet User-Agents
echo "--- 1. Testing Mobile & Tablet Detection (is_mobile_or_tablet) ---\n";

$mobileUAs = [
    'iPhone 15 Mobile Safari' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
    'Samsung Galaxy S22 Chrome' => 'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Mobile Safari/537.36',
    'Google Pixel Android Firefox' => 'Mozilla/5.0 (Android 14; Mobile; rv:120.0) Gecko/120.0 Firefox/120.0',
    'iPad Safari (Tablet)' => 'Mozilla/5.0 (iPad; CPU OS 16_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.5 Mobile/15E148 Safari/604.1',
    'Android Tablet Chrome' => 'Mozilla/5.0 (Linux; Android 12; SM-T870) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/112.0.0.0 Safari/537.36 Tablet',
    'BlackBerry Device' => 'Mozilla/5.0 (BlackBerry; U; BlackBerry 9800; en) AppleWebKit/534.1+ (KHTML, like Gecko) Version/6.0.0.141 Mobile Safari/534.1+',
    'Windows Phone' => 'Mozilla/5.0 (compatible; MSIE 10.0; Windows Phone 8.0; Trident/6.0; IEMobile/10.0; ARM; Touch; NOKIA; Lumia 920)'
];

foreach ($mobileUAs as $device => $ua) {
    $_SERVER['HTTP_USER_AGENT'] = $ua;
    unset($_SERVER['HTTP_SEC_CH_UA_MOBILE']);
    assert_test("Detects $device as mobile/tablet", is_mobile_or_tablet() === true);
}

// Test Sec-CH-UA-Mobile header
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
$_SERVER['HTTP_SEC_CH_UA_MOBILE'] = '?1';
assert_test("Detects mobile via Sec-CH-UA-Mobile: ?1 client hint", is_mobile_or_tablet() === true);
unset($_SERVER['HTTP_SEC_CH_UA_MOBILE']);

// 2. Test Desktop User-Agents (Must NOT be detected as mobile)
echo "\n--- 2. Testing Desktop & Laptop Detection ---\n";

$desktopUAs = [
    'Windows 11 Chrome Desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Windows 10 Edge Desktop' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36 Edg/120.0.0.0',
    'macOS Safari Desktop' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Safari/605.1.15',
    'macOS Chrome Desktop' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
    'Linux Firefox Desktop' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/119.0',
    'Touchscreen Laptop (Lenovo Yoga Windows Chrome)' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/121.0.0.0 Safari/537.36'
];

foreach ($desktopUAs as $device => $ua) {
    $_SERVER['HTTP_USER_AGENT'] = $ua;
    unset($_SERVER['HTTP_SEC_CH_UA_MOBILE']);
    assert_test("Allows $device (not blocked by server UA check)", is_mobile_or_tablet() === false);
}

// 3. Route Access Verification
echo "\n--- 3. Testing Route Permission Separation ---\n";

// 3.1 Admin guard check: admin-guard.php does NOT require desktop
$adminGuardCode = file_get_contents(__DIR__ . '/../admin/admin-guard.php');
assert_test("Admin guard permits mobile access (require_desktop not invoked)", !str_contains($adminGuardCode, 'require_desktop_for_exam'));

// 3.2 Student dashboard check: dashboard.php permits mobile access
$studentDashboardCode = file_get_contents(__DIR__ . '/../student/dashboard.php');
assert_test("Student dashboard permits mobile access", !str_contains($studentDashboardCode, 'require_desktop_for_exam'));

// 3.3 Student results check: result.php permits mobile access
$studentResultCode = file_get_contents(__DIR__ . '/../student/result.php');
assert_test("Student results permit mobile access", !str_contains($studentResultCode, 'require_desktop_for_exam'));

// 3.4 Student exam route check: exam.php strictly enforces desktop
$studentExamCode = file_get_contents(__DIR__ . '/../student/exam.php');
assert_test("student/exam.php enforces require_desktop_for_exam", str_contains($studentExamCode, 'require_desktop_for_exam()'));

// 3.5 Student question API check: question.php checks is_mobile_or_tablet
$studentQuestionCode = file_get_contents(__DIR__ . '/../student/question.php');
assert_test("student/question.php rejects mobile/tablet devices with 403", str_contains($studentQuestionCode, 'is_mobile_or_tablet()'));

// 3.6 Anti-Cheat Touchscreen and Touchpad Suppression
$antiCheatJs = file_get_contents(__DIR__ . '/../utils/anti-cheat.js');
assert_test("anti-cheat.js includes checkDeviceCompliance", str_contains($antiCheatJs, 'checkDeviceCompliance'));
assert_test("anti-cheat.js includes enableTouchscreenSuppression", str_contains($antiCheatJs, 'enableTouchscreenSuppression'));
assert_test("anti-cheat.js handles touchscreen laptop warning element", str_contains($antiCheatJs, 'touchscreen-laptop-warning'));
assert_test("anti-cheat.js displays touchpad/mouse toast on touch attempts", str_contains($antiCheatJs, 'Touchscreen input disabled. Please strictly use your touchpad or mouse.'));

// 4. Test Lockout Screen Template
echo "\n--- 4. Testing Lockout Screen Partial ---\n";
$lockoutCode = file_get_contents(__DIR__ . '/../components/desktop-required.php');
assert_test("desktop-required.php exists and contains official warning", str_contains($lockoutCode, 'Desktop Workstation Required'));
assert_test("desktop-required.php mentions touchscreen laptop guidance", str_contains($lockoutCode, 'Using a Touchscreen Laptop?'));

// Summary
echo "\n\033[1;34m=== DEVICE GATING TEST RESULTS ===\033[0m\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       \033[32m$passedTests\033[0m\n";
echo "Failed:       \033[" . ($failedTests > 0 ? "31m$failedTests" : "32m0") . "\033[0m\n\n";

if ($failedTests > 0) {
    exit(1);
}

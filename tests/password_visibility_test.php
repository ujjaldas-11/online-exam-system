<?php

/**
 * Examify - Password View Eye Button Verification Test Suite
 */

declare(strict_types=1);

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

echo "\n\033[1;34m=== EXAMIFY PASSWORD VISIBILITY TOGGLE TEST SUITE ===\033[0m\n\n";

// 1. CSS Verification
echo "--- 1. Testing CSS Component Definitions ---\n";
$css = file_get_contents(__DIR__ . '/../assets/css/components.css');
assert_test("components.css defines .password-wrapper", str_contains($css, '.password-wrapper'));
assert_test("components.css defines .password-toggle-btn", str_contains($css, '.password-toggle-btn'));
assert_test("components.css defines right padding offset for input", str_contains($css, 'padding-right: 38px !important'));

// 2. Global Script Verification
echo "\n--- 2. Testing Global Footer Handler ---\n";
$footer = file_get_contents(__DIR__ . '/../components/footer.php');
assert_test("footer.php contains password-toggle-btn listener", str_contains($footer, 'password-toggle-btn'));
assert_test("footer.php toggles input type between password and text", str_contains($footer, "input.type = 'text'") && str_contains($footer, "input.type = 'password'"));
assert_test("footer.php toggles icon between visibility and visibility_off", str_contains($footer, 'visibility_off') && str_contains($footer, 'visibility'));

// 3. Password Input Fields Verification
echo "\n--- 3. Testing Password Input Fields in Templates ---\n";

$templates = [
    'student/login.php' => 1,
    'student/register.php' => 2,
    'admin/admin-login.php' => 1,
    'admin/setup.php' => 2,
    'admin/manage-teachers.php' => 1,
    'admin/manage-students.php' => 2
];

foreach ($templates as $file => $expectedCount) {
    $content = file_get_contents(__DIR__ . '/../' . $file);
    
    // Count password inputs
    preg_match_all('/type="password"/', $content, $passMatches);
    $passCount = count($passMatches[0]);
    
    // Count password wrappers
    preg_match_all('/class="password-wrapper"/', $content, $wrapperMatches);
    $wrapperCount = count($wrapperMatches[0]);

    // Count toggle buttons
    preg_match_all('/class="password-toggle-btn"/', $content, $btnMatches);
    $btnCount = count($btnMatches[0]);

    assert_test("$file contains $expectedCount password field(s)", $passCount === $expectedCount, "Found $passCount");
    assert_test("$file wraps all $expectedCount password field(s) in .password-wrapper", $wrapperCount === $expectedCount, "Found $wrapperCount");
    assert_test("$file equips all $expectedCount password field(s) with .password-toggle-btn", $btnCount === $expectedCount, "Found $btnCount");
}

echo "\n\033[1;34m=== TEST RESULTS SUMMARY ===\033[0m\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       \033[32m$passedTests\033[0m\n";
echo "Failed:       \033[" . ($failedTests > 0 ? "31m$failedTests" : "32m0") . "\033[0m\n\n";

if ($failedTests > 0) {
    exit(1);
}

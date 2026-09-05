<?php

/**
 * Examify - Offline Zero-CDN Verification Test Suite
 * Ensures all assets, fonts, icons, styles, and scripts are 100% self-hosted
 * for air-gapped college LAN exam environments with no internet access.
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

echo "\n\033[1;34m=== EXAMIFY OFFLINE ZERO-CDN TEST SUITE ===\033[0m\n\n";

// 1. Font & Icon Self-Hosting Verification
echo "--- 1. Testing Self-Hosted Web Fonts and Icons ---\n";
$fontFile = __DIR__ . '/../assets/fonts/material-symbols-outlined.woff2';
assert_test("Material Symbols WOFF2 font exists locally", file_exists($fontFile));
assert_test("Material Symbols WOFF2 font is valid size (>1MB)", file_exists($fontFile) && filesize($fontFile) > 1000000);

$fontCss = file_get_contents(__DIR__ . '/../assets/css/material-symbols.css');
assert_test("material-symbols.css references local woff2 file", str_contains($fontCss, "url('../fonts/material-symbols-outlined.woff2')"));
assert_test("material-symbols.css contains zero external URLs", !preg_match('/https?:\/\//i', $fontCss));

// 2. Header and Layout Offline Safety
echo "\n--- 2. Testing Shared Layouts for Zero External Requests ---\n";
$header = file_get_contents(__DIR__ . '/../components/header.php');
assert_test("header.php has NO preconnect to Google Fonts", !str_contains($header, 'fonts.googleapis.com') && !str_contains($header, 'fonts.gstatic.com'));
assert_test("header.php loads local material-symbols.css", str_contains($header, 'material-symbols.css'));
assert_test("header.php loads local app.css", str_contains($header, 'app.css'));

$footer = file_get_contents(__DIR__ . '/../components/footer.php');
assert_test("footer.php contains zero external script sources", !preg_match('/src=[\'"]https?:\/\//i', $footer));

// 3. System Typography Fallbacks
echo "\n--- 3. Testing Native System Typography Stack ---\n";
$variablesCss = file_get_contents(__DIR__ . '/../assets/css/variables.css');
assert_test("variables.css uses system-ui font stack", str_contains($variablesCss, 'system-ui, -apple-system'));
assert_test("variables.css uses ui-monospace font stack", str_contains($variablesCss, 'ui-monospace, SFMono-Regular'));

// 4. Security & Air-Gap Headers
echo "\n--- 4. Testing Apache .htaccess Air-Gap Security Headers ---\n";
$htaccess = file_get_contents(__DIR__ . '/../.htaccess');
assert_test(".htaccess defines Content-Security-Policy", str_contains($htaccess, 'Content-Security-Policy'));
assert_test(".htaccess CSP specifies default-src 'self'", str_contains($htaccess, "default-src 'self'"));
assert_test(".htaccess specifies caching for offline LAN performance", str_contains($htaccess, 'ExpiresActive On'));

// 5. Codebase Scan for Third-Party CDN Endpoints
echo "\n--- 5. Scanning Core Application for Third-Party CDN Inclusions ---\n";
$cdnSignatures = [
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'cdnjs.cloudflare.com',
    'cdn.jsdelivr.net',
    'unpkg.com',
    'ajax.googleapis.com',
    'code.jquery.com',
    'stackpath.bootstrapcdn.com',
    'cdn.tailwindcss.com',
];

$scanDirs = ['student', 'admin', 'components', 'assets/css', 'assets/js'];
$foundCdnViolations = [];

foreach ($scanDirs as $dir) {
    $fullDir = realpath(__DIR__ . '/../' . $dir);
    if (!$fullDir || !is_dir($fullDir)) continue;

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fullDir));
    foreach ($iterator as $file) {
        if ($file->isDir()) continue;
        $content = file_get_contents($file->getPathname());
        foreach ($cdnSignatures as $cdn) {
            if (stripos($content, $cdn) !== false) {
                $foundCdnViolations[] = $file->getFilename() . " contains $cdn";
            }
        }
    }
}

assert_test("Application code contains ZERO CDN domain references", empty($foundCdnViolations), implode(', ', $foundCdnViolations));

// 6. Developer & Tester Cards (Online GitHub with Offline Monogram Fallback)
echo "\n--- 6. Testing Developer & Tester Cards (Graceful Fallback Exception) ---\n";
$devPage = file_get_contents(__DIR__ . '/../developers.php');
assert_test("developers.php provides instant offline fallback monograms", str_contains($devPage, 'dev-fallback'));
assert_test("developers.php equips images with onerror hiding handler", str_contains($devPage, "onerror=\"this.style.display='none';\""));
assert_test("developers.php supports optional local avatar override", str_contains($devPage, 'localDevAvatar'));

// 7. Proctoring WebSocket LAN Resilience
echo "\n--- 7. Testing Real-Time Proctoring LAN Architecture ---\n";
$proctorJs = file_get_contents(__DIR__ . '/../assets/js/proctor-socket.js');
assert_test("proctor-socket.js uses window.location.hostname for dynamic LAN IP", str_contains($proctorJs, 'window.location.hostname'));
assert_test("proctor-socket.js includes HTTP polling fallback for offline resilience", str_contains($proctorJs, 'pollIntervalMs'));

echo "\n=== OFFLINE ZERO-CDN TEST SUMMARY ===\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       $passedTests\n";
echo "Failed:       $failedTests\n\n";

if ($failedTests > 0) {
    exit(1);
}

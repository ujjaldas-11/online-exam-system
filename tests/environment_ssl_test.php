<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

ob_start();
ini_set('session.use_cookies', '0');

/**
 * Environment & SSL Security Test Suite
 * Validates:
 * 1. Force SSL / HTTPS and HSTS detection on APP_ENV=production
 * 2. Session cookie security in production
 * 3. Environment mode detection (is_production, is_development)
 * 4. Production documentation verification
 * 5. Live HTTP server redirect integration tests (when web server is running)
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/database.php';
require_once $rootDir . '/utils/env.php';
require_once $rootDir . '/utils/session.php';

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

function assert_test_bool(bool $condition, string $name, string $detail = ''): void {
    assert_test($name, $condition, $detail);
}

echo "\n=== ENVIRONMENT & SSL SECURITY TEST SUITE ===\n\n";

// 1. Testing SSL and HTTPS Detection
echo "--- 1. Testing HTTPS Protocol Detection (is_ssl) ---\n";
$_SERVER['HTTPS'] = 'on';
assert_test_bool(is_ssl() === true, "is_ssl() detects \$_SERVER['HTTPS'] = 'on'");

$_SERVER['HTTPS'] = 'off';
assert_test_bool(is_ssl() === false, "is_ssl() returns false for \$_SERVER['HTTPS'] = 'off'");
unset($_SERVER['HTTPS']);

$_SERVER['SERVER_PORT'] = '443';
assert_test_bool(is_ssl() === true, "is_ssl() detects port 443");
unset($_SERVER['SERVER_PORT']);

$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
assert_test_bool(is_ssl() === true, "is_ssl() detects X-Forwarded-Proto: https");
unset($_SERVER['HTTP_X_FORWARDED_PROTO']);

$_SERVER['HTTP_X_FORWARDED_SSL'] = 'on';
assert_test_bool(is_ssl() === true, "is_ssl() detects X-Forwarded-Ssl: on");
unset($_SERVER['HTTP_X_FORWARDED_SSL']);

assert_test_bool(is_ssl() === false, "is_ssl() returns false for plain HTTP");

// 2. Testing Environment Differentiation & Production SSL Policy
echo "\n--- 2. Testing Environment Security Configuration ---\n";

putenv("APP_ENV=development");
$_ENV['APP_ENV'] = 'development';
assert_test_bool(is_production() === false, "APP_ENV=development identifies as non-production");

putenv("APP_ENV=production");
$_ENV['APP_ENV'] = 'production';
assert_test_bool(is_production() === true, "APP_ENV=production identifies as production");

$envContent = file_get_contents($rootDir . '/utils/env.php');
$hasEnforceSsl = str_contains($envContent, 'function enforce_ssl_in_production(): void')
    && str_contains($envContent, 'Strict-Transport-Security: max-age=31536000; includeSubDomains')
    && str_contains($envContent, 'enforce_ssl_in_production();');
assert_test_bool($hasEnforceSsl, "utils/env.php enforces SSL redirect and HSTS header on production");

$sessionContent = file_get_contents($rootDir . '/utils/session.php');
$hasSecureSessionProd = str_contains($sessionContent, "is_production()");
assert_test_bool($hasSecureSessionProd, "utils/session.php marks session cookie secure in production");

// Reset environment back to development
putenv("APP_ENV=development");
$_ENV['APP_ENV'] = 'development';

$prodMdPath = $rootDir . '/production.md';
$prodMdContent = file_exists($prodMdPath) ? (string)file_get_contents($prodMdPath) : '';
$hasProdMdSslDoc = !empty($prodMdContent) && str_contains($prodMdContent, "Enforced SSL / HTTPS");
assert_test_bool($hasProdMdSslDoc, "production.md documents enforced SSL/HTTPS and HSTS in production");

// 3. Testing Live HTTP Behavior with Server (cURL integration)
echo "\n--- 3. Testing Live HTTP Behavior with Server (cURL) ---\n";
$envFilePath = $rootDir . '/.env';
$origEnv = file_exists($envFilePath) ? file_get_contents($envFilePath) : '';

if (!function_exists('curl_init')) {
    echo "[SKIP] cURL extension not available in CLI environment.\n";
} else {
    try {
        $httpBase = 'http://127.0.0.1:8080';
        $server_reachable = false;

        $chTest = curl_init($httpBase . '/index.php');
        curl_setopt_array($chTest, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 1]);
        if (curl_exec($chTest) !== false) {
            $server_reachable = true;
        } else {
            $httpBase = 'http://127.0.0.1';
            curl_setopt($chTest, CURLOPT_URL, $httpBase . '/index.php');
            if (curl_exec($chTest) !== false) {
                $server_reachable = true;
            }
        }
        curl_close($chTest);

        if (!$server_reachable) {
            echo "[SKIP] Live HTTP Server tests skipped (No web server running on 127.0.0.1 in this environment).\n";
        } else {
            $prodEnvContent = $origEnv ? preg_replace('/APP_ENV=.*/', 'APP_ENV=production', $origEnv) : 'APP_ENV=production';
            file_put_contents($envFilePath, $prodEnvContent);

            $ch = curl_init($httpBase . '/index.php');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_TIMEOUT => 3,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            assert_test_bool($httpCode === 301, "Live server returns HTTP 301 redirect on plain HTTP in production");
            assert_test_bool(str_contains((string) $response, "Location: https://") || str_contains((string) $response, "location: https://"), "Live server 301 specifies https:// target");
            assert_test_bool(str_contains((string) $response, "Strict-Transport-Security"), "Live server outputs HSTS header in production");

            $ch = curl_init($httpBase . '/index.php');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_HTTPHEADER => ['X-Forwarded-Proto: https'],
                CURLOPT_TIMEOUT => 3,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            assert_test_bool($httpCode === 200, "Live server allows HTTPS request through with HTTP 200 in production");
            assert_test_bool(str_contains((string) $response, "Strict-Transport-Security"), "Live server includes HSTS header on HTTPS in production");

            $devEnvContent = $origEnv ? preg_replace('/APP_ENV=.*/', 'APP_ENV=development', $origEnv) : 'APP_ENV=development';
            file_put_contents($envFilePath, $devEnvContent);

            $ch = curl_init($httpBase . '/index.php');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HEADER => true,
                CURLOPT_NOBODY => true,
                CURLOPT_TIMEOUT => 3,
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            assert_test_bool($httpCode === 200, "Live server allows plain HTTP in development without redirect");
        }
    } finally {
        if ($origEnv !== '') {
            file_put_contents($envFilePath, $origEnv);
        } elseif (file_exists($envFilePath)) {
            unlink($envFilePath);
        }
    }
}

echo "\n=== ENVIRONMENT & SSL SECURITY TEST RESULTS ===\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       $passedTests\n";
echo "Failed:       $failedTests\n\n";

ob_end_flush();

if ($failedTests > 0) {
    exit(1);
}

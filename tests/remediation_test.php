// --- 9. Live HTTP Server Test of Force SSL on APP_ENV=production ---
echo "\n--- 9. Testing Live HTTP Behavior with Server (cURL) ---\n";
$envFilePath = $rootDir . '/.env';
$origEnv = file_exists($envFilePath) ? file_get_contents($envFilePath) : '';

try {
    $httpBase = 'http://127.0.0.1:8080';
    $server_reachable = false;
    
    // Check if server is running on 8080
    $chTest = curl_init($httpBase . '/index.php');
    curl_setopt_array($chTest, [CURLOPT_RETURNTRANSFER => true, CURLOPT_NOBODY => true, CURLOPT_TIMEOUT => 1]);
    if (curl_exec($chTest) !== false) {
        $server_reachable = true;
    } else {
        // Fallback to port 80
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
        // 6A. Test APP_ENV=production forces 301 redirect to https://
        $prodEnvContent = $origEnv ? preg_replace('/APP_ENV=.*/', 'APP_ENV=production', $origEnv) : 'APP_ENV=production';
        file_put_contents($envFilePath, $prodEnvContent);

        // Call plain HTTP endpoint
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

        // 6B. Test simulated HTTPS request with X-Forwarded-Proto
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

        // 6C. Test restore to APP_ENV=development allows plain HTTP
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
    // Always restore the original .env file to not break the CI environment
    if ($origEnv !== '') {
        file_put_contents($envFilePath, $origEnv);
    } elseif (file_exists($envFilePath)) {
        unlink($envFilePath);
    }
}

echo "\n=== REMEDIATION VERIFICATION TEST RESULTS ===\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       $passedTests\n";
echo "Failed:       $failedTests\n\n";

ob_end_flush();

if ($failedTests > 0) {
    exit(1);
}

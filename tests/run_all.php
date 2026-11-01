<?php
declare(strict_types=1);

/**
 * Examify — Unified Automated Test Runner
 *
 * Runs the full verification suite across all 17 automated test suites.
 * Usage:
 *   php tests/run_all.php
 */

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

echo "\n=======================================================\n";
echo "🏁 EXAMIFY UNIFIED AUTOMATED TEST RUNNER\n";
echo "=======================================================\n\n";

$tests = [
    'tests/bulk_promote_test.php'        => 'Bulk Student Cohort Promotion',
    'tests/concurrency_test.php'         => '40-Student Concurrency Benchmark',
    'tests/device_gating_test.php'       => 'Device & Hardware Touchscreen Gating',
    'tests/disqualified_student_test.php'=> 'Disqualified Student State Enforcement',
    'tests/doc_access_test.php'          => 'Role-Based Documentation Access Control',
    'tests/mcq_types_test.php'           => 'Multi-Type MCQ Grading & Export',
    'tests/offline_zero_cdn_test.php'    => '100% Air-Gapped Zero-CDN Architecture',
    'tests/password_visibility_test.php' => 'Universal Password Visibility Toggles',
    'tests/exam_management_test.php'     => 'Exam Scheduling & Question Bank Operations',
    'tests/rate_limiter_test.php'        => 'High-Throughput Security Rate Limiting',
    'tests/environment_ssl_test.php'     => 'Environment, Force SSL & Production HSTS',
    'tests/scheduled_exam_test.php'      => 'Scheduled Exam Lifecycle & Expiration',
    'tests/security_unit_test.php'       => 'Database Schema, RBAC & Unit Verification',
    'tests/singleton_login_test.php'     => 'Single-Device Concurrent Session Gating',
    'tests/websocket_unit_test.php'      => 'Real-Time WebSocket Protocol Framing & IPC',
    'tests/websocket_e2e_test.php'       => 'End-to-End WebSocket Broadcast & Channels',
    'tests/browser_e2e_test.php'         => 'Browser End-to-End Automation Suite'
];

$passedCount = 0;
$failedCount = 0;
$failedTests = [];
$startTime = microtime(true);

foreach ($tests as $file => $description) {
    echo ">> Running [{$file}] ({$description})...\n";
    $output = [];
    $exitCode = 0;
    exec("php " . escapeshellarg($file) . " 2>&1", $output, $exitCode);

    if ($exitCode === 0) {
        $passedCount++;
        echo "   \033[32m[PASS]\033[0m {$file}\n\n";
    } else {
        $failedCount++;
        $failedTests[] = $file;
        echo "   \033[31m[FAIL]\033[0m {$file} (Exit code: {$exitCode})\n";
        $lastLines = array_slice($output, -3);
        echo "   " . implode("\n   ", $lastLines) . "\n\n";
    }
}

$elapsed = round(microtime(true) - $startTime, 2);

echo "=======================================================\n";
echo "📊 TEST SUITE EXECUTION SUMMARY\n";
echo "=======================================================\n";
echo "Total Suites: " . count($tests) . "\n";
echo "Passed:       \033[32m{$passedCount}\033[0m\n";
echo "Failed:       " . ($failedCount > 0 ? "\033[31m{$failedCount}\033[0m" : "\033[32m0\033[0m") . "\n";
echo "Duration:     {$elapsed}s\n";
echo "=======================================================\n";

if ($failedCount > 0) {
    echo "\nFailed Suites:\n";
    foreach ($failedTests as $f) {
        echo " - {$f}\n";
    }
    exit(1);
}

echo "\n\033[32mALL 17 TEST SUITES PASSED!\033[0m\n\n";
exit(0);

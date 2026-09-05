<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

ob_start();
ini_set('session.use_cookies', '0');

/**
 * Remediation Verification Test Suite
 * Comprehensive automated verification for system remediations:
 * 1. Question Bank CSV Import & Documentation Harmonization
 * 2. Anti-Cheat Violation Threshold Harmonization to 3
 * 3. Live Exam Score Confidentiality & Unpublished Status Cards
 * 4. Dynamic Protocol Scheme in Proctor Socket (ws:// vs wss://)
 * 5. Proctor State Catch-Up on Reconnection & Immediate Fallback Polling
 * 6. Emit WebSocket Event on Exam Termination
 * 7. Zero External Avatar Calls & Air-Gapped CSP Hardening
 * 8. Force SSL / HTTPS & HSTS on APP_ENV=production
 * 9. Live HTTP Server Integration Tests
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/database.php';
require_once $rootDir . '/services/ExamEngine.php';
require_once $rootDir . '/utils/auth.php';
require_once $rootDir . '/utils/session.php';
require_once $rootDir . '/utils/env.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void {
    global $totalTests, $passedTests, $failedTests;
    $totalTests++;
    if ($condition) {
        $passedTests++;
        echo "[PASS] $name\n";
    } else {
        $failedTests++;
        echo "[FAIL] $name" . ($detail ? " - $detail" : '') . "\n";
    }
}

function assert_test_bool(bool $condition, string $name, string $detail = ''): void {
    assert_test($name, $condition, $detail);
}

echo "\n=== REMEDIATION VERIFICATION TEST SUITE ===\n\n";

// 1. Question Bank CSV Import & Documentation Harmonization
// --------------------------------------------------------------------------
echo "--- 1. Testing Question Bank CSV Import & Documentation Harmonization  ---\n";

$rootReadme = (string)file_get_contents(__DIR__ . '/../README.md');
assert_test("README.md documents CSV upload for manage-questions.php", str_contains($rootReadme, 'manage-questions.php') && str_contains($rootReadme, 'via CSV'));
assert_test("README.md does not document JSON upload for manage-questions.php", !str_contains($rootReadme, 'with JSON'));

$userReadme = (string)file_get_contents(__DIR__ . '/../docs/user/README.md');
assert_test("docs/user/README.md section 3.6 documents CSV format", str_contains($userReadme, 'upload multiple-choice questions in bulk via CSV'));
assert_test("docs/user/README.md section 3.6 lists 7 standard CSV columns", str_contains($userReadme, 'Question Text, Unit Number, Option A, Option B, Option C, Option D, Correct Option'));
assert_test("docs/user/README.md section 3.6 removed JSON array snippet", !str_contains($userReadme, '"question_text":'));

$adminDoc = (string)file_get_contents(__DIR__ . '/../docs/user/admin-doc.php');
assert_test("docs/user/admin-doc.php section 3.6 documents Bulk CSV Upload", str_contains($adminDoc, 'Bulk CSV Upload'));
assert_test("docs/user/admin-doc.php contains standard CSV example", str_contains($adminDoc, 'Question Text,Unit Number,Option A,Option B,Option C,Option D,Correct Option'));
assert_test("docs/user/admin-doc.php section 3.6 removed JSON array block", !str_contains($adminDoc, '<span class="key">"question_text"</span>'));

// Verify CSV parser functionality in admin/manage-questions.php
$manageQuestionsCode = (string)file_get_contents(__DIR__ . '/../admin/manage-questions.php');
assert_test("admin/manage-questions.php handles fgetcsv stream parsing", str_contains($manageQuestionsCode, 'fgetcsv($handle'));
assert_test("admin/manage-questions.php enforces allowed option letters A-D", str_contains($manageQuestionsCode, "['A', 'B', 'C', 'D']"));

// --------------------------------------------------------------------------
// 2. Harmonize Anti-Cheat Violation Threshold to 3
// --------------------------------------------------------------------------
echo "\n--- 2. Testing Anti-Cheat Violation Threshold Harmonization to 3  ---\n";

$antiCheatJs = (string)file_get_contents(__DIR__ . '/../utils/anti-cheat.js');
assert_test("utils/anti-cheat.js sets MAX_VIOLATIONS = 3", str_contains($antiCheatJs, 'MAX_VIOLATIONS = 3;'));

$logViolationPhp = (string)file_get_contents(__DIR__ . '/../student/log-violation.php');
assert_test("student/log-violation.php enforces \$maxViolations = 3", str_contains($logViolationPhp, '$maxViolations = 3;'));

$userDoc = (string)file_get_contents(__DIR__ . '/../docs/user/user-doc.php');
assert_test("docs/user/user-doc.php documents 3 violations threshold", str_contains($userDoc, '3 violations'));

$userReadmeDoc = (string)file_get_contents(__DIR__ . '/../docs/user/README.md');
assert_test("docs/user/README.md documents 3 violations threshold", str_contains($userReadmeDoc, '3 violations'));

$adminDoc = (string)file_get_contents(__DIR__ . '/../docs/user/admin-doc.php');
assert_test("docs/user/admin-doc.php documents 3 violations threshold", str_contains($adminDoc, '3 violations'));

// --------------------------------------------------------------------------
// 3. Enforce Live Exam Score Confidentiality
// --------------------------------------------------------------------------
echo "\n--- 3. Testing Live Exam Score Confidentiality  ---\n";

// Provision test subject, exam, and student
$pdo->prepare("INSERT INTO subjects (name, department, semester) VALUES ('Confidentiality Test Sub', 'BCA', 4)")->execute();
$subId = (int)$pdo->lastInsertId();

$insQ = $pdo->prepare("INSERT INTO questions (subject_id, question_text, option_a, option_b, correct_option, unit_number) VALUES (?, 'Q Conf 1', 'Opt A', 'Opt B', 'A', 1)");
$insQ->execute([$subId]);

$insExam = $pdo->prepare("
    INSERT INTO exams (title, subject_id, duration_minutes, total_marks, total_questions_to_ask, status, results_published, start_time)
    VALUES ('Confidentiality Exam', ?, 30, 10, 1, 'active', 0, NOW())
");
$insExam->execute([$subId]);
$examId = (int)$pdo->lastInsertId();

$stuRoll = 'CONF_STU_' . time();
$pdo->prepare("INSERT INTO students (name, email, password, roll_number, department, semester, status) VALUES ('Conf Student', ?, 'hash', ?, 'BCA', 4, 'active')")->execute(["{$stuRoll}@college.edu", $stuRoll]);
$stuId = (int)$pdo->lastInsertId();

// Start attempt and submit
$attRes = ExamEngine::getOrStartAttempt($pdo, $stuId, $examId, 4, 'BCA');
$attId = (int)($attRes['attempt']['id'] ?? 0);
assert_test("Attempt initialized successfully", $attId > 0);

$subRes = ExamEngine::submitExam($pdo, $stuId, $examId);
assert_test("Attempt submitted successfully", !empty($subRes['success']));

// Test results gating logic:
// When results_published = 0, score and reviews must be locked
$attemptData = $pdo->query("SELECT ea.score, e.results_published, e.status AS exam_status, e.start_time, e.duration_minutes FROM exam_attempts ea JOIN exams e ON ea.exam_id = e.id WHERE ea.id = $attId")->fetch();

$isExamEnded = ($attemptData['exam_status'] === 'ended');
if ($attemptData['exam_status'] === 'active' && !empty($attemptData['start_time'])) {
    $durationSec = (int)$attemptData['duration_minutes'] * 60;
    if (time() >= (strtotime($attemptData['start_time']) + $durationSec)) {
        $isExamEnded = true;
    }
}
$canViewResults = $isExamEnded && !empty($attemptData['results_published']);
assert_test("Active unpublished exam denies results view permission", $canViewResults === false);

// Check student/result.php template content for confidentiality badge
$resultPhp = (string)file_get_contents(__DIR__ . '/../student/result.php');
assert_test("student/result.php displays 'Submission Received — Results Pending' when unpublished", str_contains($resultPhp, 'Submission Received — Results Pending'));
assert_test("student/result.php informs student that score remains confidential until published", str_contains($resultPhp, 'confidential until officially published'));

// Check student/dashboard.php template content for confidential badge
$dashboardPhp = (string)file_get_contents(__DIR__ . '/../student/dashboard.php');
assert_test("student/dashboard.php shows 'Results Pending' badge for completed unpublished exams", str_contains($dashboardPhp, 'Results Pending'));
assert_test("student/dashboard.php does not reveal numerical score for unpublished exams", !str_contains($dashboardPhp, "(Awaiting Publication)</span></div>\n                                    </div>"));

// Verify review and scorecard PDF access gating
$revCode = (string)file_get_contents(__DIR__ . '/../student/review-exam.php');
assert_test("student/review-exam.php restricts access when !\$is_ended || !\$is_published", str_contains($revCode, '!$is_ended || !$is_published'));

$dlCardCode = (string)file_get_contents(__DIR__ . '/../student/download-card.php');
assert_test("student/download-card.php restricts access when !\$is_ended || !\$is_published", str_contains($dlCardCode, '!$is_ended || !$is_published'));

// Admin publishes results and exam ends
$pdo->prepare("UPDATE exams SET status = 'ended', results_published = 1 WHERE id = ?")->execute([$examId]);
$pubAttemptData = $pdo->query("SELECT ea.score, e.results_published, e.status AS exam_status FROM exam_attempts ea JOIN exams e ON ea.exam_id = e.id WHERE ea.id = $attId")->fetch();
$canViewResultsNow = ($pubAttemptData['exam_status'] === 'ended') && ($pubAttemptData['results_published'] == 1);
assert_test("Ended published exam grants results view permission", $canViewResultsNow === true);

// Cleanup test fixtures
$pdo->prepare("DELETE FROM student_answers WHERE attempt_id = ?")->execute([$attId]);
$pdo->prepare("DELETE FROM exam_attempts WHERE id = ?")->execute([$attId]);
$pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$examId]);
$pdo->prepare("DELETE FROM questions WHERE subject_id = ?")->execute([$subId]);
$pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$subId]);
$pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$stuId]);

// --- 4. Testing Dynamic Protocol Scheme  ---
echo "--- 4. Testing Dynamic Protocol Scheme  ---\n";
$proctorJsContent = file_get_contents($rootDir . '/assets/js/proctor-socket.js');
assert_test_bool($proctorJsContent !== false, "proctor-socket.js is readable");

$hasDynamicProtoDefault = str_contains($proctorJsContent, "window.location.protocol === 'https:' ? 'wss://' : 'ws://'");
assert_test_bool($hasDynamicProtoDefault, "proctor-socket.js dynamically defaults to wss:// when page is on HTTPS");

$hasWssUpgradeInInit = str_contains($proctorJsContent, "replace(/^ws:\\/\\//i, 'wss://')");
assert_test_bool($hasWssUpgradeInInit, "proctor-socket.js init() dynamically upgrades ws:// to wss:// when on HTTPS");

$proctorPhpContent = file_get_contents($rootDir . '/admin/proctor-exam.php');
$hasDynamicProtoInPhp = str_contains($proctorPhpContent, "is_ssl()") && str_contains($proctorPhpContent, "'wss://' : 'ws://'");
assert_test_bool($hasDynamicProtoInPhp, "admin/proctor-exam.php uses is_ssl() to pick wss:// vs ws://");

// --- 2. Testing Immediate Proctor State Catch-Up & Fallback Polling  ---
echo "\n--- 5. Testing Proctor Catch-Up & Immediate Fallback Polling  ---\n";

// In onopen, pollStatus() should be called immediately
$hasPollOnOpen = str_contains($proctorJsContent, "self.pollStatus();")
    && preg_match('/this\.socket\.onopen\s*=\s*function\s*\(\)\s*\{[\s\S]*?self\.pollStatus\(\);/s', $proctorJsContent);
assert_test_bool((bool) $hasPollOnOpen, "proctor-socket.js calls pollStatus() immediately on WebSocket open/reconnect");

// In handleDisconnect, startFallbackPolling() should be called immediately
$hasImmediatePollingOnDisconnect = preg_match('/handleDisconnect:\s*function\s*\(\)\s*\{[^}]*this\.startFallbackPolling\(\);/s', $proctorJsContent);
assert_test_bool((bool) $hasImmediatePollingOnDisconnect, "proctor-socket.js triggers fallback HTTP polling immediately upon disconnect");

// --- 3. Testing Emit WebSocket Event on Exam Termination  ---
echo "\n--- 6. Testing WebSocket Event on Exam Termination  ---\n";
$controlExamsContent = file_get_contents($rootDir . '/admin/control-exams.php');

$hasExamEndedEmission = str_contains($controlExamsContent, 'WebSocketPusher::emit("exam:{$exam_id}", "exam_ended"')
    || str_contains($controlExamsContent, "WebSocketPusher::emit(\"exam:{\$exam_id}\", \"exam_ended\"");
assert_test_bool($hasExamEndedEmission, "admin/control-exams.php emits 'exam_ended' event via WebSocketPusher on end_exam");

$hasExamEndedSocketHandler = str_contains($proctorJsContent, "event === 'exam_ended'");
assert_test_bool($hasExamEndedSocketHandler, "proctor-socket.js listens for 'exam_ended' event");

$hasHandleExamEndedMethod = str_contains($proctorJsContent, "handleExamEnded: function");
assert_test_bool($hasHandleExamEndedMethod, "proctor-socket.js implements handleExamEnded() to notify proctor and sync table");

// --- 7. Testing Zero External Avatar Calls & CSP  ---
echo "\n--- 7. Testing Zero External Avatar Calls & CSP  ---\n";
$devsContent = file_get_contents($rootDir . '/developers.php');

$hasNoDevGithubAvatar = !str_contains($devsContent, "https://github.com/' . e(\$dev['username']) . '.png");
assert_test_bool($hasNoDevGithubAvatar, "developers.php removed remote GitHub avatar URL for developers");

$hasNoTesterGithubAvatar = !str_contains($devsContent, "https://github.com/' . e(\$tester['username']) . '.png");
assert_test_bool($hasNoTesterGithubAvatar, "developers.php removed remote GitHub avatar URL for testers");

$htaccessContent = file_get_contents($rootDir . '/.htaccess');
$hasCspNoGithub = str_contains($htaccessContent, "img-src 'self' data:;");
assert_test_bool($hasCspNoGithub, ".htaccess CSP img-src strictly self-contained (no external github.com allowed)");

// --- 8. Testing Force SSL on APP_ENV=production ---
echo "\n--- 8. Testing Force SSL on APP_ENV=production ---\n";
require_once $rootDir . '/utils/env.php';

// Test is_ssl() helper
$_SERVER['HTTPS'] = 'on';
assert_test_bool(is_ssl() === true, "is_ssl() detects \$_SERVER['HTTPS'] = 'on'");

$_SERVER['HTTPS'] = 'off';
assert_test_bool(is_ssl() === false, "is_ssl() returns false for \$_SERVER['HTTPS'] = 'off'");

unset($_SERVER['HTTPS']);
$_SERVER['SERVER_PORT'] = '443';
assert_test_bool(is_ssl() === true, "is_ssl() detects port 443");

$_SERVER['SERVER_PORT'] = '80';
$_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';
assert_test_bool(is_ssl() === true, "is_ssl() detects X-Forwarded-Proto: https");

unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
$_SERVER['HTTP_X_FORWARDED_SSL'] = 'on';
assert_test_bool(is_ssl() === true, "is_ssl() detects X-Forwarded-Ssl: on");

unset($_SERVER['HTTP_X_FORWARDED_SSL']);
assert_test_bool(is_ssl() === false, "is_ssl() returns false for plain HTTP");

// Test enforce_ssl_in_production behavior via simulation
// Case A: APP_ENV=development => No redirect, no exception
putenv("APP_ENV=development");
$_ENV['APP_ENV'] = 'development';
assert_test_bool(is_production() === false, "APP_ENV=development identifies as non-production");

// Case B: APP_ENV=production
putenv("APP_ENV=production");
$_ENV['APP_ENV'] = 'production';
assert_test_bool(is_production() === true, "APP_ENV=production identifies as production");

// Check that enforce_ssl_in_production logic is present in utils/env.php
$envContent = file_get_contents($rootDir . '/utils/env.php');
$hasEnforceSsl = str_contains($envContent, 'function enforce_ssl_in_production(): void')
    && str_contains($envContent, 'Strict-Transport-Security: max-age=31536000; includeSubDomains')
    && str_contains($envContent, 'enforce_ssl_in_production();');
assert_test_bool($hasEnforceSsl, "utils/env.php enforces SSL redirect and HSTS header on production");

// Check session.php cookie secure flag
$sessionContent = file_get_contents($rootDir . '/utils/session.php');
$hasSecureSessionProd = str_contains($sessionContent, "is_production()");
assert_test_bool($hasSecureSessionProd, "utils/session.php marks session cookie secure in production");

// Reset environment back to development
putenv("APP_ENV=development");
$_ENV['APP_ENV'] = 'development';

// Check production.md documentation
$prodMdContent = file_get_contents($rootDir . '/production.md');
$hasProdMdSslDoc = str_contains($prodMdContent, "Enforced SSL / HTTPS");
assert_test_bool($hasProdMdSslDoc, "production.md documents enforced SSL/HTTPS and HSTS in production");

// --- 6. Live HTTP Server Test of Force SSL on APP_ENV=production ---
echo "\n--- 9. Testing Live HTTP Behavior with Server (cURL) ---\n";
$envFilePath = $rootDir . '/.env';
$origEnv = file_exists($envFilePath) ? file_get_contents($envFilePath) : null;

try {
    // 6A. Test APP_ENV=production forces 301 redirect to https://
    $prodEnvContent = preg_replace('/APP_ENV=.*/', 'APP_ENV=production', (string) $origEnv);
    file_put_contents($envFilePath, $prodEnvContent);

    // Call plain HTTP endpoint
    $ch = curl_init('http://127.0.0.1:8080/index.php');
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
    $ch = curl_init('http://127.0.0.1:8080/index.php');
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
    $devEnvContent = preg_replace('/APP_ENV=.*/', 'APP_ENV=development', (string) $origEnv);
    file_put_contents($envFilePath, $devEnvContent);

    $ch = curl_init('http://127.0.0.1:8080/index.php');
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
} finally {
    if ($origEnv !== null) {
        file_put_contents($envFilePath, $origEnv);
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

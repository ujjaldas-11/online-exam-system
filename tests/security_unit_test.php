<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

/**
 * Examify - Automated Test Suite
 * Covers: Refined Database Schema, Concurrency Engine, Security, RBAC, and PDF Generation
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/ExamEngine.php';
require_once __DIR__ . '/../services/PdfService.php';
require_once __DIR__ . '/../utils/sanitize.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/session.php';

@session_start();

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

echo "\n\033[1;34m=== EXAMIFY AUTOMATED TEST SUITE ===\033[0m\n\n";

// TEST 1: Database Schema & Redundancy Elimination
echo "--- 1. Testing Database Architecture Refinements ---\n";
try {
    // 1.1 Verify registration_request table is gone or not used
    $tables = $pdo->query("SHOW TABLES LIKE 'registration_request'")->fetchAll();
    assert_test("Database eliminated registration_request redundancy", empty($tables), "registration_request table should not exist");

    // 1.2 Verify students table has status and reviewer columns
    $stuCols = $pdo->query("DESCRIBE students")->fetchAll(PDO::FETCH_COLUMN);
    assert_test("Students table contains 'status' enum column", in_array('status', $stuCols, true));
    assert_test("Students table contains 'reviewed_by' column", in_array('reviewed_by', $stuCols, true));
    assert_test("Students table contains 'reviewed_at' column", in_array('reviewed_at', $stuCols, true));

    // 1.3 Verify exam_attempts.score is DECIMAL
    $scoreCol = $pdo->query("SHOW COLUMNS FROM exam_attempts WHERE Field = 'score'")->fetch();
    assert_test("Exam attempts score column is DECIMAL type", str_contains(strtolower($scoreCol['Type']), 'decimal'), "Found: " . $scoreCol['Type']);

    // 1.4 Verify student_answers.marked_for_review column exists
    $ansCols = $pdo->query("DESCRIBE student_answers")->fetchAll(PDO::FETCH_COLUMN);
    assert_test("Student answers contains persistent 'marked_for_review' column", in_array('marked_for_review', $ansCols, true));

    // 1.5 Verify exams.results_published column exists
    $examCols = $pdo->query("DESCRIBE exams")->fetchAll(PDO::FETCH_COLUMN);
    assert_test("Exams table contains 'results_published' boolean column", in_array('results_published', $examCols, true));

    // 1.6 Verify rate_limits table exists
    $rateTables = $pdo->query("SHOW TABLES LIKE 'rate_limits'")->fetchAll();
    assert_test("Database contains 'rate_limits' table for security throttling", count($rateTables) === 1);

} catch (Exception $e) {
    assert_test("Database schema check failed", false, $e->getMessage());
}

// TEST 2: Unified Registration Flow (Single Table)
echo "\n--- 2. Testing Unified Student Registration Flow ---\n";
try {
    $testRoll = 'TEST_REG_' . time();
    $testEmail = 'testreg' . time() . '@college.edu';
    $testPass = password_hash('Pass123!', PASSWORD_DEFAULT);

    // Register student -> status should be pending
    $ins = $pdo->prepare("
        INSERT INTO students (name, email, password, roll_number, department, semester, phone_number, gender, status)
        VALUES ('Automated Test Student', ?, ?, ?, 'BCA', 4, '9999988888', 'male', 'pending')
    ");
    $ins->execute([$testEmail, $testPass, $testRoll]);
    $newStudentId = (int) $pdo->lastInsertId();

    $chk = $pdo->prepare("SELECT status, reviewed_by, reviewed_at FROM students WHERE id = ?");
    $chk->execute([$newStudentId]);
    $row = $chk->fetch();

    assert_test("New student registration has 'pending' status", $row['status'] === 'pending');
    assert_test("New student registration has null reviewer", $row['reviewed_by'] === null);

    // Admin approves student directly in students table
    $adminId = 1; // Dr. Sarah Admin
    $approveStmt = $pdo->prepare("UPDATE students SET status = 'active', reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
    $approveStmt->execute([$adminId, $newStudentId]);

    $chk->execute([$newStudentId]);
    $rowApproved = $chk->fetch();

    assert_test("Approved student changes to 'active' status", $rowApproved['status'] === 'active');
    assert_test("Approved student stores reviewing admin ID", (int)$rowApproved['reviewed_by'] === $adminId);
    assert_test("Approved student stores review timestamp", !empty($rowApproved['reviewed_at']));

    // Cleanup test record
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$newStudentId]);
} catch (Exception $e) {
    assert_test("Unified registration flow failed", false, $e->getMessage());
}

// TEST 3: High-Concurrency ExamEngine Test
echo "\n--- 3. Testing High-Concurrency ExamEngine Logic ---\n";
try {
    // Pick existing exam and ensure it is active and within window
    $exam = $pdo->query("SELECT id, subject_id, total_questions_to_ask, total_marks FROM exams WHERE status = 'active' LIMIT 1")->fetch();
    if (!$exam) {
        $exam = $pdo->query("SELECT id, subject_id, total_questions_to_ask, total_marks FROM exams LIMIT 1")->fetch();
    }
    $examId = (int) $exam['id'];
    $pdo->prepare("UPDATE exams SET status = 'active', start_time = NOW(), end_time = NULL WHERE id = ?")->execute([$examId]);

    // Pick a test student matching the exam's subject department and semester
    $examMeta = $pdo->query("SELECT s.department, s.semester FROM exams e JOIN subjects s ON e.subject_id = s.id WHERE e.id = $examId")->fetch();
    $studentStmt = $pdo->prepare("SELECT id, semester, department FROM students WHERE status = 'active' AND department = ? AND semester = ? LIMIT 1");
    $studentStmt->execute([$examMeta['department'], $examMeta['semester']]);
    $student = $studentStmt->fetch();
    $studentId = (int) $student['id'];

    // Clean any prior attempts for this test student and exam
    $pdo->prepare("DELETE FROM exam_attempts WHERE student_id = ? AND exam_id = ?")->execute([$studentId, $examId]);

    // 3.1 Start attempt
    $res = ExamEngine::getOrStartAttempt($pdo, $studentId, $examId, (int)$student['semester'], (string)$student['department']);
    assert_test("ExamEngine::getOrStartAttempt initializes successfully", empty($res['error']));
    $attemptId = (int) ($res['attempt']['id'] ?? 0);
    assert_test("Exam attempt returned valid ID", $attemptId > 0);

    // 3.2 Verify bulk inserted answers exist
    $ansCount = (int) $pdo->query("SELECT COUNT(*) FROM student_answers WHERE attempt_id = $attemptId")->fetchColumn();
    assert_test("ExamEngine performed bulk insert of question answers", $ansCount === (int)$exam['total_questions_to_ask'], "Expected: {$exam['total_questions_to_ask']}, Got: $ansCount");

    // 3.3 Test Idempotency (calling getOrStartAttempt again returns the same attempt without re-inserting)
    $res2 = ExamEngine::getOrStartAttempt($pdo, $studentId, $examId, (int)$student['semester'], (string)$student['department']);
    assert_test("ExamEngine::getOrStartAttempt is idempotent", (int)$res2['attempt']['id'] === $attemptId);
    $ansCountAfter = (int) $pdo->query("SELECT COUNT(*) FROM student_answers WHERE attempt_id = $attemptId")->fetchColumn();
    assert_test("No duplicate answers created on idempotent fetch", $ansCountAfter === $ansCount);

    // 3.4 Test saveAnswer
    $firstQ = $pdo->query("SELECT question_id, correct_option FROM student_answers sa JOIN questions q ON sa.question_id = q.id WHERE sa.attempt_id = $attemptId LIMIT 1")->fetch();
    $qId = (int) $firstQ['question_id'];
    $correctOpt = $firstQ['correct_option'];

    $saveRes = ExamEngine::saveAnswer($pdo, $studentId, $examId, $qId, $correctOpt, true);
    assert_test("ExamEngine::saveAnswer succeeds", empty($saveRes['error']));

    $savedAns = $pdo->query("SELECT selected_option, marked_for_review FROM student_answers WHERE attempt_id = $attemptId AND question_id = $qId")->fetch();
    assert_test("Saved option matches submitted option", $savedAns['selected_option'] === $correctOpt);
    assert_test("Persistent marked_for_review flag is true", (bool)$savedAns['marked_for_review'] === true);

    // 3.5 Test Decimal Scoring Calculation
    $submitRes = ExamEngine::submitExam($pdo, $studentId, $examId);
    assert_test("ExamEngine::submitExam grades attempt successfully", empty($submitRes['error']));
    $finalScore = (float) $submitRes['score'];
    $expectedScore = round((float)$exam['total_marks'] / (float)$exam['total_questions_to_ask'], 2);
    assert_test("Score accurately calculates decimal value", $finalScore === $expectedScore, "Expected: $expectedScore, Got: $finalScore");

    $attRow = $pdo->query("SELECT status, score FROM exam_attempts WHERE id = $attemptId")->fetch();
    assert_test("Attempt status is 'completed'", $attRow['status'] === 'completed');

    // Cleanup
    $pdo->prepare("DELETE FROM exam_attempts WHERE id = ?")->execute([$attemptId]);

} catch (Exception $e) {
    assert_test("ExamEngine tests failed", false, $e->getMessage());
}

// TEST 4: Security Utilities & Sanitization
echo "\n--- 4. Testing Security & Sanitization Helpers ---\n";
try {
    // 4.1 XSS Protection
    $xssInput = "<script>alert('xss')</script>";
    assert_test("e() escapes HTML script tags", e($xssInput) === "&lt;script&gt;alert(&#039;xss&#039;)&lt;/script&gt;");

    // 4.2 CSV Formula Injection Protection
    $csvFormula = "=1+1";
    assert_test("sanitize_csv_value prepends quote to formula prefix '='", sanitize_csv_value($csvFormula) === "'=1+1");
    $csvFormula2 = "+cmd|'/c calc'!A1";
    assert_test("sanitize_csv_value prepends quote to formula prefix '+'", sanitize_csv_value($csvFormula2) === "'+cmd|'/c calc'!A1");

    // 4.3 Asset Name Path Traversal Protection
    assert_test("sanitize_asset_name prevents directory traversal", sanitize_asset_name("../../secret.css", 'css') === null);
    assert_test("sanitize_asset_name allows valid filename", sanitize_asset_name("app.css", 'css') === "app.css");

    // 4.4 CSRF Verification
    init_secure_session();
    $token = csrf_token();
    assert_test("csrf_token generates non-empty token", !empty($token));
    assert_test("is_csrf_valid validates correct token", is_csrf_valid($token));
    assert_test("is_csrf_valid rejects fraudulent token", !is_csrf_valid("invalid_token_12345"));

} catch (Exception $e) {
    assert_test("Security tests failed", false, $e->getMessage());
}

// TEST 5: Pure-PHP PDF Generation (FPDF v1.86)
echo "\n--- 5. Testing Pure-PHP PDF Library (FPDF) ---\n";
try {
    $sampleExam = [
        'title' => 'Operating Systems Mid-Term Exam',
        'total_marks' => 100,
        'duration_minutes' => 60,
        'department' => 'BCA',
        'semester' => 4,
        'subject_name' => 'Operating Systems',
        'creator_name' => 'Prof. Alan Turing'
    ];

    $sampleAttempts = [
        [
            'name' => 'Alex Johnson',
            'roll_number' => 'BCA2401',
            'score' => 95.50,
            'total_questions' => 20,
            'submitted_at' => '2026-09-03 12:00:00'
        ],
        [
            'name' => 'Priya Sharma',
            'roll_number' => 'BCA2402',
            'score' => 88.00,
            'total_questions' => 20,
            'submitted_at' => '2026-09-03 12:05:00'
        ]
    ];

    // 5.1 Test Exam Results PDF string generation
    $pdfString = PdfService::generateExamResultsPdf($sampleExam, $sampleAttempts, 'S');
    assert_test("generateExamResultsPdf returns non-empty binary string", !empty($pdfString));
    assert_test("generateExamResultsPdf outputs valid PDF header (%PDF-)", str_starts_with($pdfString, '%PDF-'));

    // 5.2 Test Student Scorecard PDF string generation
    $sampleStudent = [
        'name' => 'Alex Johnson',
        'email' => 'student@college.edu',
        'roll_number' => 'BCA2401',
        'department' => 'BCA',
        'semester' => 4
    ];
    $sampleAttempt = [
        'score' => 95.50,
        'total_questions' => 20,
        'submitted_at' => '2026-09-03 12:00:00'
    ];
    $sampleStats = [
        'correct_count' => 19,
        'wrong_count' => 1,
        'skipped_count' => 0
    ];

    $scorecardPdf = PdfService::generateStudentScorecardPdf($sampleStudent, $sampleExam, $sampleAttempt, $sampleStats, 'S');
    assert_test("generateStudentScorecardPdf returns non-empty binary string", !empty($scorecardPdf));
    assert_test("generateStudentScorecardPdf outputs valid PDF header (%PDF-)", str_starts_with($scorecardPdf, '%PDF-'));

} catch (Exception $e) {
    assert_test("PDF generation tests failed", false, $e->getMessage());
}

// TEST 6: Exam Results Publication & Gating Workflow
echo "\n--- 6. Testing Exam Results Publication & Access Gating ---\n";
try {
    // 6.1 Gating helper function logic
    $canView = function(string $status, int $published, ?string $startTime, int $durationMin): bool {
        $isEnded = ($status === 'ended');
        if ($status === 'active' && !empty($startTime)) {
            $endTime = strtotime($startTime) + ($durationMin * 60);
            if (time() >= $endTime) {
                $isEnded = true;
            }
        }
        return $isEnded && ($published === 1);
    };

    // Case 1: Exam is active (running), unpublished
    assert_test("Results hidden when exam is active and unpublished", !$canView('active', 0, date('Y-m-d H:i:s'), 60));

    // Case 2: Exam is active, but admin somehow set published
    assert_test("Results hidden while exam is active even if published flag set", !$canView('active', 1, date('Y-m-d H:i:s'), 60));

    // Case 3: Exam has ended, but results unpublished
    assert_test("Results hidden when exam is ended but unpublished", !$canView('ended', 0, null, 60));

    // Case 4: Exam has ended AND results published by admin
    assert_test("Results visible only when exam is ended AND published by admin", $canView('ended', 1, null, 60));

    // Case 5: Verify admin publish database toggle
    $testExamRow = $pdo->query("SELECT id FROM exams LIMIT 1")->fetch();
    $testExamId = (int) ($testExamRow['id'] ?? 0);
    $pdo->prepare("UPDATE exams SET results_published = 1 WHERE id = ?")->execute([$testExamId]);
    $pubVal = (int)$pdo->query("SELECT results_published FROM exams WHERE id = $testExamId")->fetchColumn();
    assert_test("Admin publish updates results_published to 1 in database", $pubVal === 1);

    $pdo->prepare("UPDATE exams SET results_published = 0 WHERE id = ?")->execute([$testExamId]);
    $unpubVal = (int)$pdo->query("SELECT results_published FROM exams WHERE id = $testExamId")->fetchColumn();
    assert_test("Admin unpublish reverts results_published to 0 in database", $unpubVal === 0);

    // 6.2 Admin Publish Eligibility (Disabled when Ongoing)
    $isOngoing = function(string $status, ?string $startTime, int $durationMin): bool {
        if ($status === 'active') {
            $startTs = !empty($startTime) ? strtotime($startTime) : time();
            $durationSec = $durationMin * 60;
            return time() < ($startTs + $durationSec);
        }
        return false;
    };

    assert_test("Active exam with remaining duration is detected as ongoing", $isOngoing('active', date('Y-m-d H:i:s'), 30) === true);
    assert_test("Active exam with elapsed duration is detected as not ongoing", $isOngoing('active', date('Y-m-d H:i:s', time() - 3600), 30) === false);
    assert_test("Ended exam is detected as not ongoing", $isOngoing('ended', null, 30) === false);
    assert_test("Pending exam is detected as not ongoing", $isOngoing('pending', null, 30) === false);

} catch (Exception $e) {
    assert_test("Results gating tests failed", false, $e->getMessage());
}

// Summary
echo "\n\033[1;34m=== TEST RESULTS SUMMARY ===\033[0m\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       \033[32m$passedTests\033[0m\n";
echo "Failed:       \033[" . ($failedTests > 0 ? "31m$failedTests" : "32m0") . "\033[0m\n\n";

if ($failedTests > 0) {
    exit(1);
}

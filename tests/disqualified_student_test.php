<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

ob_start();
ini_set('session.use_cookies', '0');

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

echo "\n=== ISSUE #32: DISQUALIFIED STUDENT ACCESS & SUBMISSION TEST SUITE ===\n\n";

// --- 1. Static Code Analysis & Route Guarding Invariants ---
echo "--- 1. Testing Code Invariants & Server-Side Gates ---\n";

$examPhp = (string)file_get_contents($rootDir . '/student/exam.php');
assert_test("student/exam.php checks for existing disqualified attempt before PIN/join", str_contains($examPhp, "\$existingAttempt['status'] === 'disqualified'") && str_contains($examPhp, "set_flash('error'"));
assert_test("student/exam.php redirects disqualified students to dashboard.php", str_contains($examPhp, "redirect('dashboard.php')"));
assert_test("student/exam.php WebSocket listener handles student_disqualified event", str_contains($examPhp, "student_disqualified"));

$resultPhp = (string)file_get_contents($rootDir . '/student/result.php');
assert_test("student/result.php redirects disqualified attempts to dashboard with flash message", str_contains($resultPhp, "\$attempt['status'] === 'disqualified'") && str_contains($resultPhp, "redirect('dashboard.php')"));
assert_test("student/result.php handles disqualified submitExam result gracefully", str_contains($resultPhp, "!empty(\$res['disqualified'])") && str_contains($resultPhp, "set_flash('error'"));

$questionPhp = (string)file_get_contents($rootDir . '/student/question.php');
assert_test("student/question.php GET rejects disqualified attempt with 403", str_contains($questionPhp, "\$attempt['status'] === 'disqualified'") && str_contains($questionPhp, "'disqualified' => true"));

$dashboardPhp = (string)file_get_contents($rootDir . '/student/dashboard.php');
assert_test("student/dashboard.php recognizes disqualified category", str_contains($dashboardPhp, "\$is_disqualified"));
assert_test("student/dashboard.php displays Disqualified indicator on exam card", str_contains($dashboardPhp, "Disqualified"));

$statusBadgePhp = (string)file_get_contents($rootDir . '/components/status-badge.php');
assert_test("components/status-badge.php includes disqualified badge in proctor context", str_contains($statusBadgePhp, "case 'disqualified':"));

$proctorPhp = (string)file_get_contents($rootDir . '/admin/proctor-exam.php');
assert_test("admin/proctor-exam.php has disqualify_student_attempt handler", str_contains($proctorPhp, "disqualify_student_attempt"));

// --- 2. Live Database Integration & Exam Lifecycle Testing ---
echo "\n--- 2. Testing ExamEngine Concurrency & State Invariants ---\n";

try {
    // Setup test teacher, subject, questions, exam, and student
    $uniq = (string)time() . '_' . mt_rand(100, 999);
    $adminEmail = "test_teacher_{$uniq}@examify.offline";
    $studentEmail = "test_student_{$uniq}@examify.offline";
    $studentRoll = "TEST-DISQ-{$uniq}";

    $pdo->prepare("INSERT INTO admins (name, email, password, role, status) VALUES (?, ?, ?, 'teacher', 'active')")
        ->execute(["Invigilator {$uniq}", $adminEmail, password_hash("Admin@123", PASSWORD_BCRYPT)]);
    $teacherId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO subjects (name, department, semester, created_by) VALUES (?, 'BCA', 4, ?)")
        ->execute(["Subject Disq Test {$uniq}", $teacherId]);
    $subjectId = (int)$pdo->lastInsertId();

    $qStmt = $pdo->prepare("INSERT INTO questions (subject_id, question_text, unit_number, question_type, option_a, option_b, option_c, option_d, correct_option, marks, created_by) VALUES (?, ?, 1, 'single', 'Opt A', 'Opt B', 'Opt C', 'Opt D', 'A', 1, ?)");
    for ($i = 1; $i <= 5; $i++) {
        $qStmt->execute([$subjectId, "Question {$i} {$uniq}", $teacherId]);
    }

    $pdo->prepare("INSERT INTO exams (subject_id, title, duration_minutes, total_questions_to_ask, total_marks, status, start_time, end_time, created_by) VALUES (?, ?, 60, 5, 5, 'active', NOW(), DATE_ADD(NOW(), INTERVAL 60 MINUTE), ?)")
        ->execute([$subjectId, "Exam Disq Test {$uniq}", $teacherId]);
    $examId = (int)$pdo->lastInsertId();

    $pdo->prepare("INSERT INTO students (name, email, password, roll_number, department, semester, status) VALUES (?, ?, ?, ?, 'BCA', 4, 'active')")
        ->execute(["Candidate {$uniq}", $studentEmail, password_hash("Student@123", PASSWORD_BCRYPT), $studentRoll]);
    $studentId = (int)$pdo->lastInsertId();

    // 2.1 Start attempt as eligible student
    $startRes = ExamEngine::getOrStartAttempt($pdo, $studentId, $examId, 4, 'BCA');
    assert_test("Eligible student successfully starts exam attempt", !empty($startRes['success']) && !empty($startRes['attempt']['id']));

    $attemptId = (int)$startRes['attempt']['id'];

    // 2.2 Student can save an answer while in_progress
    $qIdStmt = $pdo->prepare("SELECT question_id FROM student_answers WHERE attempt_id = ? LIMIT 1");
    $qIdStmt->execute([$attemptId]);
    $firstQId = (int)$qIdStmt->fetchColumn();

    $saveRes = ExamEngine::saveAnswer($pdo, $studentId, $examId, $firstQId, 'A', false);
    assert_test("Eligible student successfully saves answer in progress", !empty($saveRes['success']));

    // 2.3 Proctor disqualifies the student attempt
    $disqUpdate = $pdo->prepare("UPDATE exam_attempts SET status = 'disqualified' WHERE id = ?");
    $disqUpdate->execute([$attemptId]);

    // 2.4 Verify ExamEngine::getOrStartAttempt rejects disqualified student
    $rejoinRes = ExamEngine::getOrStartAttempt($pdo, $studentId, $examId, 4, 'BCA');
    assert_test("ExamEngine::getOrStartAttempt blocks disqualified student with 403", !empty($rejoinRes['disqualified']) && ($rejoinRes['code'] ?? 0) === 403);
    assert_test("ExamEngine::getOrStartAttempt returns clear disqualification message", str_contains($rejoinRes['error'] ?? '', 'disqualified'));

    // 2.5 Verify ExamEngine::saveAnswer rejects disqualified student
    $saveAfterDisq = ExamEngine::saveAnswer($pdo, $studentId, $examId, $firstQId, 'B', false);
    assert_test("ExamEngine::saveAnswer blocks disqualified student with 403", !empty($saveAfterDisq['disqualified']) && ($saveAfterDisq['code'] ?? 0) === 403);

    // 2.6 Verify ExamEngine::submitExam returns disqualified gracefully
    $submitRes = ExamEngine::submitExam($pdo, $studentId, $examId);
    assert_test("ExamEngine::submitExam handles disqualified attempt gracefully", !empty($submitRes['disqualified']));
    assert_test("ExamEngine::submitExam returns clear disqualification reason", str_contains($submitRes['error'] ?? '', 'disqualified'));

    // Cleanup test artifacts
    $pdo->prepare("DELETE FROM exam_attempts WHERE id = ?")->execute([$attemptId]);
    $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$examId]);
    $pdo->prepare("DELETE FROM questions WHERE subject_id = ?")->execute([$subjectId]);
    $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$subjectId]);
    $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$studentId]);
    $pdo->prepare("DELETE FROM admins WHERE id = ?")->execute([$teacherId]);

} catch (Throwable $e) {
    echo "[FAIL] Unexpected exception during integration test: " . $e->getMessage() . "\n";
    $failedTests++;
}

echo "\n=== DISQUALIFIED STUDENT TEST SUMMARY ===\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       $passedTests\n";
echo "Failed:       $failedTests\n\n";

if ($failedTests > 0) {
    exit(1);
}
exit(0);

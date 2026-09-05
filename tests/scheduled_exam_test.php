<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/ExamEngine.php';

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

echo "\n\033[1;34m=== EXAMIFY SCHEDULED EXAM & TIMEZONE TEST SUITE ===\033[0m\n\n";

// --- 1. Testing Timezone Synchronization ---
echo "--- 1. Testing Timezone Parity ---\n";
$phpTz = date_default_timezone_get();
assert_test("PHP default timezone is Asia/Kolkata", $phpTz === 'Asia/Kolkata', "Got $phpTz");

$tzStmt = $pdo->query("SELECT @@session.time_zone AS tz, NOW() AS db_now");
$tzRow = $tzStmt->fetch();
assert_test("MySQL session timezone is +05:30", $tzRow['tz'] === '+05:30', "Got {$tzRow['tz']}");

$phpTime = time();
$dbTime = strtotime($tzRow['db_now']);
$diffSeconds = abs($phpTime - $dbTime);
assert_test("MySQL NOW() matches PHP time within 2 seconds", $diffSeconds <= 2, "Diff: {$diffSeconds}s");

// --- 2. Testing Scheduled Exam Auto-Activation ---
echo "\n--- 2. Testing Scheduled Exam Auto-Activation ---\n";

// Create test subject and questions
$pdo->prepare("INSERT INTO subjects (name, department, semester) VALUES ('Auto-Sched Sub', 'BCA', 4)")->execute();
$subId = (int)$pdo->lastInsertId();

$insQ = $pdo->prepare("INSERT INTO questions (subject_id, question_text, option_a, option_b, correct_option, unit_number) VALUES (?, 'Q Auto 1', 'Opt A', 'Opt B', 'A', 1)");
$insQ->execute([$subId]);

// Student
$testRoll = 'SCHED_' . time();
$pdo->prepare("INSERT INTO students (name, email, password, roll_number, department, semester, status) VALUES ('Sched Stu', ?, 'hash', ?, 'BCA', 4, 'active')")->execute(["{$testRoll}@college.edu", $testRoll]);
$stuId = (int)$pdo->lastInsertId();

// Create exam scheduled 2 seconds in the future
$schedStart = date('Y-m-d H:i:s', time() + 2);
$schedEnd = date('Y-m-d H:i:s', time() + 300);

$insExam = $pdo->prepare("
    INSERT INTO exams (title, subject_id, duration_minutes, total_marks, total_questions_to_ask, status, start_time, end_time)
    VALUES ('Auto-Activate Exam', ?, 10, 10, 1, 'scheduled', ?, ?)
");
$insExam->execute([$subId, $schedStart, $schedEnd]);
$examId = (int)$pdo->lastInsertId();

// Before start_time: syncExamStatuses does NOT activate it prematurely
ExamEngine::syncExamStatuses($pdo);
$statusBefore = $pdo->query("SELECT status FROM exams WHERE id = $examId")->fetchColumn();
assert_test("Exam remains 'scheduled' before start_time arrives", $statusBefore === 'scheduled');

// Wait for scheduled start_time to arrive
sleep(3);

// After start_time: syncExamStatuses auto-transitions it to active
ExamEngine::syncExamStatuses($pdo);
$statusAfter = $pdo->query("SELECT status FROM exams WHERE id = $examId")->fetchColumn();
assert_test("Exam automatically transitions to 'active' in database once start_time arrives", $statusAfter === 'active', "Got $statusAfter");

// Student check-exams query
$chkStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM exams e
    JOIN subjects s ON e.subject_id = s.id
    WHERE s.department = 'BCA'
      AND s.semester = 4
      AND e.status = 'active'
      AND (
          (e.end_time IS NOT NULL AND NOW() < e.end_time)
          OR
          (e.end_time IS NULL AND e.start_time IS NOT NULL AND NOW() <= DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE))
          OR
          (e.start_time IS NULL)
      )
");
$chkStmt->execute();
$activeCount = (int)$chkStmt->fetchColumn();
assert_test("student/check-exams counts the auto-activated exam", $activeCount >= 1, "Count: $activeCount");

// Attempt can now be started
$attRes = ExamEngine::getOrStartAttempt($pdo, $stuId, $examId, 4, 'BCA');
assert_test("Student can start attempt on auto-activated exam", !empty($attRes['success']), $attRes['error'] ?? '');

// --- 3. Testing Auto-Expiration when window ends ---
echo "\n--- 3. Testing Auto-Expiration ---\n";
$expiredEnd = date('Y-m-d H:i:s', time() - 10);
$pdo->prepare("UPDATE exams SET end_time = ? WHERE id = ?")->execute([$expiredEnd, $examId]);

ExamEngine::syncExamStatuses($pdo);
$statusExpired = $pdo->query("SELECT status FROM exams WHERE id = $examId")->fetchColumn();
assert_test("Exam automatically transitions to 'ended' once window passes", $statusExpired === 'ended', "Got $statusExpired");

// Cleanup test records
$pdo->prepare("DELETE FROM student_answers WHERE attempt_id IN (SELECT id FROM exam_attempts WHERE exam_id = ?)")->execute([$examId]);
$pdo->prepare("DELETE FROM exam_attempts WHERE exam_id = ?")->execute([$examId]);
$pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$examId]);
$pdo->prepare("DELETE FROM questions WHERE subject_id = ?")->execute([$subId]);
$pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$subId]);
$pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$stuId]);

echo "\n\033[1;34m=== SCHEDULED EXAM TEST SUMMARY ===\033[0m\n";
echo "Total Tests: $totalTests\n";
echo "Passed:      \033[32m$passedTests\033[0m\n";
echo "Failed:      " . ($failedTests > 0 ? "\033[31m$failedTests\033[0m" : "0") . "\n\n";

if ($failedTests > 0) {
    exit(1);
}

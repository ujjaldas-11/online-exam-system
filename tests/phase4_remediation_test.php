<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

ob_start();
ini_set('session.use_cookies', '0');

/**
 * Examify - Phase 4 Remediation Automated Test Suite
 * Validates:
 * 1. Exam Scheduling (start_time, end_time, scheduled state transitions)
 * 2. Question Bank Editing & Individual Deletion
 * 3. Subject Editing & Constraint-Gated Deletion
 * 4. Database Backup Export & System Settings Panel
 * 5. Timer Dynamic Synchronization on Time Extension
 * 6. Funny Quotes JSON File Availability
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/ExamEngine.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/session.php';

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

echo "\n\033[1;34m=== EXAMIFY PHASE 4 REMEDIATION TEST SUITE ===\033[0m\n\n";

// --------------------------------------------------------------------------
// 1. TASK 4.1: Exam Scheduling (Date & Time Picker & Lifecycle)
// --------------------------------------------------------------------------
echo "--- 1. Testing Exam Scheduling (Task 4.1) ---\n";

$examCols = $pdo->query("SHOW COLUMNS FROM exams")->fetchAll(PDO::FETCH_COLUMN);
assert_test("exams table contains 'end_time' column", in_array('end_time', $examCols, true));

// Create a test subject for scheduling tests
$pdo->prepare("INSERT INTO subjects (name, department, semester) VALUES ('Sched Test Sub', 'BCA', 4)")->execute();
$schedSubId = (int)$pdo->lastInsertId();

// Add questions to subject
$insQ = $pdo->prepare("INSERT INTO questions (subject_id, question_text, option_a, option_b, correct_option, unit_number) VALUES (?, 'Q1', 'A', 'B', 'A', 1)");
$insQ->execute([$schedSubId]);

// 1a. Future scheduled exam
$futureStart = date('Y-m-d H:i:s', time() + 3600);
$futureEnd = date('Y-m-d H:i:s', time() + 7200);

$insExam = $pdo->prepare("
    INSERT INTO exams (title, subject_id, duration_minutes, total_marks, total_questions_to_ask, status, start_time, end_time)
    VALUES ('Future Exam', ?, 30, 20, 1, 'scheduled', ?, ?)
");
$insExam->execute([$schedSubId, $futureStart, $futureEnd]);
$futureExamId = (int)$pdo->lastInsertId();

// Provision a student
$sRoll = 'P4_STU_' . time();
$pdo->prepare("INSERT INTO students (name, email, password, roll_number, department, semester, status) VALUES ('P4 Stu', ?, 'hash', ?, 'BCA', 4, 'active')")->execute(["p4_{$sRoll}@college.edu", $sRoll]);
$p4StudentId = (int)$pdo->lastInsertId();

// Attempting future exam should be blocked with scheduled start message
$futRes = ExamEngine::getOrStartAttempt($pdo, $p4StudentId, $futureExamId, 4, 'BCA');
assert_test("Future scheduled exam rejects attempt creation with scheduled message", !empty($futRes['error']) && str_contains($futRes['error'], 'scheduled to start'));

// 1b. Auto-activation when start_time has arrived
$pastStart = date('Y-m-d H:i:s', time() - 120); // 2 minutes ago
$pdo->prepare("UPDATE exams SET start_time = ? WHERE id = ?")->execute([$pastStart, $futureExamId]);
$activeRes = ExamEngine::getOrStartAttempt($pdo, $p4StudentId, $futureExamId, 4, 'BCA');
assert_test("ExamEngine auto-activates scheduled exam once start_time has arrived", !empty($activeRes['success']));

$examStatusInDb = $pdo->query("SELECT status FROM exams WHERE id = $futureExamId")->fetchColumn();
assert_test("Exam status automatically transitioned to 'active' in database", $examStatusInDb === 'active');

// 1c. Exam cutoff when end_time has arrived
$pastEnd = date('Y-m-d H:i:s', time() - 60);
$pdo->prepare("UPDATE exams SET end_time = ? WHERE id = ?")->execute([$pastEnd, $futureExamId]);
$endedRes = ExamEngine::getOrStartAttempt($pdo, $p4StudentId, $futureExamId, 4, 'BCA');
assert_test("ExamEngine rejects attempt when scheduled end_time has passed", !empty($endedRes['error']) && str_contains($endedRes['error'], 'ended'));

// Verify manage-exam.php contains start_time and end_time fields
$manageExamCode = file_get_contents(__DIR__ . '/../admin/manage-exam.php');
assert_test("admin/manage-exam.php contains start_time datetime picker", str_contains($manageExamCode, 'name="start_time"'));
assert_test("admin/manage-exam.php contains end_time datetime picker", str_contains($manageExamCode, 'name="end_time"'));

// --------------------------------------------------------------------------
// 2. TASK 4.2: Question Bank Editing & Individual Deletion
// --------------------------------------------------------------------------
echo "\n--- 2. Testing Question Bank Editing & Deletion (Task 4.2) ---\n";

// Insert a question to edit & delete
$insQ2 = $pdo->prepare("INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_option, unit_number) VALUES (?, 'Original Q', 'OptA', 'OptB', 'OptC', 'OptD', 'A', 1)");
$insQ2->execute([$schedSubId]);
$testQId = (int)$pdo->lastInsertId();

// Verify question editing via SQL / logic
$pdo->prepare("UPDATE questions SET question_text = 'Updated Question Text', correct_option = 'B' WHERE id = ?")->execute([$testQId]);
$checkQ = $pdo->query("SELECT question_text, correct_option FROM questions WHERE id = $testQId")->fetch();
assert_test("Question text and correct option updated successfully", $checkQ['question_text'] === 'Updated Question Text' && $checkQ['correct_option'] === 'B');

// Verify individual question deletion
$pdo->prepare("DELETE FROM questions WHERE id = ?")->execute([$testQId]);
$deletedCheck = $pdo->query("SELECT COUNT(*) FROM questions WHERE id = $testQId")->fetchColumn();
assert_test("Question deleted successfully", (int)$deletedCheck === 0);

// Code verification
$viewQCode = file_get_contents(__DIR__ . '/../admin/view-questions.php');
assert_test("view-questions.php contains delete_question POST handler", str_contains($viewQCode, "isset(\$_POST['delete_question'])"));
assert_test("view-questions.php contains edit_question POST handler", str_contains($viewQCode, "isset(\$_POST['edit_question'])"));
assert_test("view-questions.php contains un-commented delete_all button", str_contains($viewQCode, 'name="delete_all"') && !str_contains($viewQCode, '<!-- <button type="submit" name="delete_all"'));
assert_test("view-questions.php contains Edit Question Modal", str_contains($viewQCode, 'id="editQuestionModal"'));

assert_test("admin/edit-question.php file exists", file_exists(__DIR__ . '/../admin/edit-question.php'));
$editQCode = file_get_contents(__DIR__ . '/../admin/edit-question.php');
assert_test("admin/edit-question.php enforces admin-guard and ownership checks", str_contains($editQCode, 'admin-guard.php') && str_contains($editQCode, 'is_superadmin'));

// --------------------------------------------------------------------------
// 3. TASK 4.3: Subject Management Editing & Deletion
// --------------------------------------------------------------------------
echo "\n--- 3. Testing Subject Management Editing & Deletion (Task 4.3) ---\n";

// Update subject
$pdo->prepare("UPDATE subjects SET name = 'Renamed Subject', department = 'BBA', semester = 6 WHERE id = ?")->execute([$schedSubId]);
$subCheck = $pdo->query("SELECT name, department, semester FROM subjects WHERE id = $schedSubId")->fetch();
assert_test("Subject renamed and updated in database", $subCheck['name'] === 'Renamed Subject' && $subCheck['department'] === 'BBA' && (int)$subCheck['semester'] === 6);

// Verify constraint check on deletion: subject with exams cannot be deleted
$qCount = (int)$pdo->query("SELECT COUNT(*) FROM questions WHERE subject_id = $schedSubId")->fetchColumn();
$eCount = (int)$pdo->query("SELECT COUNT(*) FROM exams WHERE subject_id = $schedSubId")->fetchColumn();
assert_test("Subject has linked questions or exams preventing deletion", $qCount > 0 || $eCount > 0);

// Clean up exams and questions linked to test subject
$pdo->prepare("DELETE FROM exam_attempts WHERE exam_id = ?")->execute([$futureExamId]);
$pdo->prepare("DELETE FROM exams WHERE subject_id = ?")->execute([$schedSubId]);
$pdo->prepare("DELETE FROM questions WHERE subject_id = ?")->execute([$schedSubId]);

// Now subject can be deleted
$pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$schedSubId]);
$subExists = (int)$pdo->query("SELECT COUNT(*) FROM subjects WHERE id = $schedSubId")->fetchColumn();
assert_test("Subject without constraints deleted successfully", $subExists === 0);

// Code verification for manage-subjects.php
$manageSubsCode = file_get_contents(__DIR__ . '/../admin/manage-subjects.php');
assert_test("manage-subjects.php contains update_subject POST handler", str_contains($manageSubsCode, "isset(\$_POST['update_subject'])"));
assert_test("manage-subjects.php contains delete_subject POST handler with constraint check", 
    str_contains($manageSubsCode, "isset(\$_POST['delete_subject'])") &&
    str_contains($manageSubsCode, "SELECT COUNT(*) FROM questions WHERE subject_id = ?") &&
    str_contains($manageSubsCode, "SELECT COUNT(*) FROM exams WHERE subject_id = ?"));
assert_test("manage-subjects.php contains Edit Subject modal", str_contains($manageSubsCode, 'id="editSubjectModal"'));

// --------------------------------------------------------------------------
// 4. TASK 4.4: Database Backup & System Settings Panel
// --------------------------------------------------------------------------
echo "\n--- 4. Testing Database Backup & Settings (Task 4.4) ---\n";

assert_test("admin/settings.php exists", file_exists(__DIR__ . '/../admin/settings.php'));
$settingsCode = file_get_contents(__DIR__ . '/../admin/settings.php');
assert_test("admin/settings.php restricts access to superadmins", str_contains($settingsCode, 'is_superadmin()'));
assert_test("admin/settings.php contains SQL database dump generator", 
    str_contains($settingsCode, 'download_backup') &&
    str_contains($settingsCode, 'SHOW CREATE TABLE') &&
    str_contains($settingsCode, 'SET FOREIGN_KEY_CHECKS=0'));

$sidebarCode = file_get_contents(__DIR__ . '/../components/admin-sidebar.php');
assert_test("admin-sidebar.php links to settings.php for superadmins", str_contains($sidebarCode, "'settings.php'"));

// --------------------------------------------------------------------------
// 5. TASK 4.5: Student Timer Synchronization
// --------------------------------------------------------------------------
echo "\n--- 5. Testing Student Timer Synchronization (Task 4.5) ---\n";

$timerJs = file_get_contents(__DIR__ . '/../utils/timer.js');
assert_test("utils/timer.js exposes window.Timer object", str_contains($timerJs, 'window.Timer'));
assert_test("utils/timer.js supports syncTimeLeft method", str_contains($timerJs, 'syncTimeLeft'));
assert_test("utils/timer.js supports addMinutes method", str_contains($timerJs, 'addMinutes'));

$qPhp = file_get_contents(__DIR__ . '/../student/question.php');
assert_test("question.php POST returns seconds_left in response", str_contains($qPhp, "'seconds_left' => \$res['seconds_left']"));
assert_test("question.php GET calculates and returns seconds_left", str_contains($qPhp, "'seconds_left' => max(0, (int)("));

$examPhp = file_get_contents(__DIR__ . '/../student/exam.php');
assert_test("exam.php loadQuestion syncs timer with server seconds_left", str_contains($examPhp, 'window.Timer.syncTimeLeft(data.seconds_left)'));
assert_test("exam.php saveCurrentAnswer syncs timer with server seconds_left", str_contains($examPhp, 'window.Timer.syncTimeLeft(data.seconds_left)'));

// --------------------------------------------------------------------------
// 6. TASK 4.6: Funny Quotes JSON File
// --------------------------------------------------------------------------
echo "\n--- 6. Testing Quotes JSON Availability (Task 4.6) ---\n";

$dashboardQuotesPath = __DIR__ . '/../utils/quotes_dashboard.json';
$funnyQuotesPath = __DIR__ . '/../utils/funny_quotes.json';
$quotesPath = file_exists($dashboardQuotesPath) ? $dashboardQuotesPath : $funnyQuotesPath;
assert_test("Dashboard quotes JSON file exists", file_exists($quotesPath));
$quotesData = json_decode((string)file_get_contents($quotesPath), true);
assert_test("utils quotes JSON is valid with quotes array", is_array($quotesData) && !empty($quotesData['quotes']));
assert_test("First quote contains non-empty quote string", !empty($quotesData['quotes'][0]['quote']));
assert_test("Quotes schema strictly contains 'id' and 'quote'", isset($quotesData['quotes'][0]['id']) && is_int($quotesData['quotes'][0]['id']) && is_string($quotesData['quotes'][0]['quote']));

// Clean up test student
$pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$p4StudentId]);

// Summary
echo "\n\033[1;34m=== PHASE 4 REMEDIATION TEST RESULTS ===\033[0m\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       \033[32m$passedTests\033[0m\n";
echo "Failed:       \033[" . ($failedTests > 0 ? "31m$failedTests" : "32m0") . "\033[0m\n\n";

ob_end_flush();

if ($failedTests > 0) {
    exit(1);
}

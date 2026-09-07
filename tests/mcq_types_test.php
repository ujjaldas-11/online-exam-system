<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

error_reporting(E_ALL);
ini_set('display_errors', '1');

$rootDir = dirname(__DIR__);
require_once $rootDir . '/config/database.php';
require_once $rootDir . '/services/ExamEngine.php';
require_once $rootDir . '/services/PdfService.php';
require_once $rootDir . '/services/CsvService.php';

$totalTests = 0;
$passedTests = 0;
$failedTests = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void
{
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

echo "\n=======================================================\n";
echo "🎯 EXAMIFY MULTI-TYPE MCQ VERIFICATION SUITE\n";
echo "=======================================================\n\n";

// --------------------------------------------------------------------------
// 1. Database Schema Verification
// --------------------------------------------------------------------------
echo "--- 1. Testing Database Schema Widening & question_type Column ---\n";

$qCols = $pdo->query("SHOW COLUMNS FROM questions")->fetchAll(PDO::FETCH_ASSOC);
$colNames = array_column($qCols, 'Field');
assert_test("questions table has question_type column", in_array('question_type', $colNames, true));

$correctCol = null;
$typeCol = null;
foreach ($qCols as $c) {
    if ($c['Field'] === 'correct_option') $correctCol = $c;
    if ($c['Field'] === 'question_type') $typeCol = $c;
}
assert_test("questions.correct_option is VARCHAR (supports multiple letters)", str_starts_with(strtolower($correctCol['Type'] ?? ''), 'varchar'));
assert_test("questions.question_type defaults to 'single'", ($typeCol['Default'] ?? '') === 'single');

$saCols = $pdo->query("SHOW COLUMNS FROM student_answers")->fetchAll(PDO::FETCH_ASSOC);
$saSelectedCol = null;
foreach ($saCols as $c) {
    if ($c['Field'] === 'selected_option') $saSelectedCol = $c;
}
assert_test("student_answers.selected_option is VARCHAR (supports multiple letters)", str_starts_with(strtolower($saSelectedCol['Type'] ?? ''), 'varchar'));

// --------------------------------------------------------------------------
// 2. ExamEngine::saveAnswer Answer Storage & Normalization
// --------------------------------------------------------------------------
echo "\n--- 2. Testing Answer Normalization & Validation in ExamEngine ---\n";

$testSubId = 0;
$testStuId = 0;
$testExamId = 0;
$attemptId = 0;

try {
    $pdo->exec("INSERT INTO subjects (name, department, semester, created_by) VALUES ('MCQ Test Subject', 'CSE', 5, 1)");
    $testSubId = (int) $pdo->lastInsertId();

    $pdo->exec("INSERT INTO students (name, email, password, roll_number, department, semester, status) 
                VALUES ('MCQ Student', 'mcq.student@college.edu', 'hash', 'MCQ-001', 'CSE', 5, 'active')");
    $testStuId = (int) $pdo->lastInsertId();

    // Create single-select and multi-select questions
    $insQ = $pdo->prepare("
        INSERT INTO questions (subject_id, question_text, unit_number, question_type, option_a, option_b, option_c, option_d, correct_option, marks, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    $insQ->execute([$testSubId, 'Single choice Q', 1, 'single', 'Alpha', 'Beta', 'Gamma', 'Delta', 'B', 1]);
    $qSingleId = (int) $pdo->lastInsertId();

    $insQ->execute([$testSubId, 'Multi choice Q', 1, 'multiple', 'Apple', 'Banana', 'Cherry', 'Date', 'A,C,D', 1]);
    $qMultiId = (int) $pdo->lastInsertId();

    $insQ->execute([$testSubId, 'Case Study Q', 2, 'case_study', 'Option 1', 'Option 2', 'Option 3', 'Option 4', 'A', 1]);
    $qCaseId = (int) $pdo->lastInsertId();

    // Create exam with negative marking (0.25 penalty)
    $insExam = $pdo->prepare("
        INSERT INTO exams (subject_id, title, duration_minutes, total_questions_to_ask, total_marks, negative_marks_per_question, status, access_pin, target_units, start_time, created_by)
        VALUES (?, 'MCQ Evaluation Exam', 30, 3, 3, 0.25, 'active', '1234', 'all', NOW(), 1)
    ");
    $insExam->execute([$testSubId]);
    $testExamId = (int) $pdo->lastInsertId();

    // Start attempt
    $attRes = ExamEngine::getOrStartAttempt($pdo, $testStuId, $testExamId, 5, 'CSE');
    assert_test("Attempt started successfully", empty($attRes['error']));
    $attemptId = (int) ($attRes['attempt']['id'] ?? 0);

    // 2.1 Single choice saving
    $sRes = ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qSingleId, 'B', false);
    assert_test("Single choice 'B' saved successfully", empty($sRes['error']));

    $ansVal = $pdo->query("SELECT selected_option FROM student_answers WHERE attempt_id = $attemptId AND question_id = $qSingleId")->fetchColumn();
    assert_test("Stored single choice is 'B'", $ansVal === 'B');

    // 2.2 Clearing answer
    $clearRes = ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qSingleId, '', false);
    assert_test("Empty string clears answer", empty($clearRes['error']));
    $clearedVal = $pdo->query("SELECT selected_option FROM student_answers WHERE attempt_id = $attemptId AND question_id = $qSingleId")->fetchColumn();
    assert_test("Cleared answer stores NULL", $clearedVal === null);

    // 2.3 Invalid single choice rejected
    $errRes = ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qSingleId, 'E', false);
    assert_test("Invalid single option 'E' returns error", !empty($errRes['error']));

    // 2.4 Multi choice saving (unordered tokens 'D, A, C' normalized to 'A,C,D')
    $mRes = ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qMultiId, 'D, A, C', false);
    assert_test("Multi choice 'D, A, C' saved successfully", empty($mRes['error']));

    $mVal = $pdo->query("SELECT selected_option FROM student_answers WHERE attempt_id = $attemptId AND question_id = $qMultiId")->fetchColumn();
    assert_test("Stored multi choice is normalized and sorted as 'A,C,D'", $mVal === 'A,C,D');

    // 2.5 Multi choice deduplication ('A,C,C,A' -> 'A,C')
    $dedupRes = ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qMultiId, 'A,C,C,A', false);
    assert_test("Duplicate multi choice tokens saved without error", empty($dedupRes['error']));
    $dedupVal = $pdo->query("SELECT selected_option FROM student_answers WHERE attempt_id = $attemptId AND question_id = $qMultiId")->fetchColumn();
    assert_test("Duplicate tokens deduplicated to 'A,C'", $dedupVal === 'A,C');

    // 2.6 Invalid token in multi-choice rejected ('A,Z')
    $badMultiRes = ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qMultiId, 'A,Z', false);
    assert_test("Invalid token in multi-choice 'A,Z' rejected", !empty($badMultiRes['error']));

    // --------------------------------------------------------------------------
    // 3. ExamEngine::submitExam Grading Logic (Single, Multiple, Archetypes)
    // --------------------------------------------------------------------------
    echo "\n--- 3. Testing Grading & Scoring Across MCQ Types ---\n";

    // Scenario A: Exact match on all questions -> Full 3.00 score
    ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qSingleId, 'B', false);
    ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qMultiId, 'A,C,D', false); // correct: A,C,D
    ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qCaseId, 'A', false);     // correct: A

    $subA = ExamEngine::submitExam($pdo, $testStuId, $testExamId);
    assert_test("Submission A graded successfully", empty($subA['error']));
    assert_test("Full points awarded for exact match (3.00)", (float)$subA['score'] === 3.0);

    // Scenario B: Multi-choice partial answer (All-or-Nothing grading)
    // Re-open attempt for grading simulation test
    $pdo->prepare("UPDATE exam_attempts SET status = 'in_progress' WHERE id = ?")->execute([$attemptId]);
    ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qSingleId, 'B', false);   // Correct: +1.0
    ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qMultiId, 'A,C', false);   // Partial (missing D): 0.0, penalty: -0.25
    ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qCaseId, 'A', false);     // Correct: +1.0

    $subB = ExamEngine::submitExam($pdo, $testStuId, $testExamId);
    // Score expected: 1.0 (single) + (-0.25 partial/wrong) + 1.0 (case) = 1.75
    $scoreB = (float)$subB['score'];
    assert_test("Partial multi-answer does not receive full credit (All-or-Nothing)", $scoreB < 3.0);
    assert_test("Partial multi-answer incurs negative marking penalty when answered incorrectly", $scoreB === 1.75, "Expected: 1.75, Got: $scoreB");

    // Scenario C: Multi-choice with wrong option selected ('A,B,C,D')
    $pdo->prepare("UPDATE exam_attempts SET status = 'in_progress' WHERE id = ?")->execute([$attemptId]);
    ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qSingleId, 'B', false);     // Correct: +1.0
    ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qMultiId, 'A,B,C,D', false); // Superfluous 'B': -0.25
    ExamEngine::saveAnswer($pdo, $testStuId, $testExamId, $qCaseId, '', false);        // Skipped: 0.0 (no penalty)

    $subC = ExamEngine::submitExam($pdo, $testStuId, $testExamId);
    $scoreC = (float)$subC['score'];
    // Score expected: 1.0 - 0.25 + 0.0 = 0.75
    assert_test("Superfluous option fails multi-select and incurs penalty", $scoreC === 0.75, "Expected: 0.75, Got: $scoreC");

    // --------------------------------------------------------------------------
    // 4. Detailed PDF Answer Sheet Generation
    // --------------------------------------------------------------------------
    echo "\n--- 4. Testing PDF Detailed Answer Sheet Output ---\n";

    $reviewQuestions = ExamEngine::getAttemptReviewQuestions($pdo, $attemptId);
    assert_test("Review questions fetched with question_type", !empty($reviewQuestions) && isset($reviewQuestions[0]['question_type']));

    $pdfContent = PdfService::generateDetailedAnswerSheetPdf(
        ['name' => 'Test Candidate', 'roll_number' => 'MCQ-001'],
        ['title' => 'Multi-Type Assessment'],
        $reviewQuestions,
        'S'
    );
    assert_test("PDF Detailed Answer Sheet generated as string without error", !empty($pdfContent) && str_starts_with($pdfContent, '%PDF'));

} finally {
    if ($attemptId > 0) {
        $pdo->prepare("DELETE FROM student_answers WHERE attempt_id = ?")->execute([$attemptId]);
        $pdo->prepare("DELETE FROM exam_attempts WHERE id = ?")->execute([$attemptId]);
    }
    if ($testExamId > 0) {
        $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$testExamId]);
    }
    if ($testSubId > 0) {
        $pdo->prepare("DELETE FROM questions WHERE subject_id = ?")->execute([$testSubId]);
        $pdo->prepare("DELETE FROM subjects WHERE id = ?")->execute([$testSubId]);
    }
    if ($testStuId > 0) {
        $pdo->prepare("DELETE FROM students WHERE id = ?")->execute([$testStuId]);
    }
}

// --------------------------------------------------------------------------
// 5. CSV Parsing Logic Verification
// --------------------------------------------------------------------------
echo "\n--- 5. Testing CSV Import Parsing for Multi-Type MCQs ---\n";

// Test parsing logic simulating manage-questions.php with CsvService::normalizeQuestionRow
$testCsvData = "Question Text,Unit Number,Option A,Option B,Option C,Option D,Correct Option,Question Type\n"
    . "\"What is an OS?\",1,System Software,Application,Hardware,Firmware,A,single\n"
    . "\"Select valid IPC mechanisms\",2,Pipes,Shared Memory,Queues,Registers,\"A,B,C\",multiple\n"
    . "\"Select contiguous options\",2,Pipes,Shared Memory,Queues,Registers,ABC,multiple\n"
    . "\"A scenario question\",3,Alpha,Beta,Gamma,Delta,D,case_study\n"
    . "\"Assertion (A): Multiprogramming increases CPU utilization.\\nReason (R): It allows multiple processes to reside in memory simultaneously.\",1,Both A and R are true, and R is the correct explanation of A,Both A and R are true, but R is NOT the correct explanation of A,A is true, but R is false,A is false, but R is true,A,assertion_reason\n"
    . "\"Match List-I with List-II:\",4,1-Q, 2-P, 3-R,1-P, 2-Q, 3-R,1-R, 2-P, 3-Q,1-Q, 2-R, 3-P,A,matching\n"
    . "\"Which are valid IPCs?\",2,Pipes,Shared Memory,Queues,Registers,A,B,C,multiple\n"
    . "\"Assertion (A): Paging eliminates external fragmentation.\\nReason (R): Frames are fixed size.\",3,\"Both A and R are true, and R is the correct explanation of A\",\"Both A and R are true, but R is NOT the correct explanation of A\",\"A is true, but R is false\",\"A is false, but R is true\",A,assertion_reason\n";

$stream = fopen('php://memory', 'r+');
fwrite($stream, $testCsvData);
rewind($stream);

$parsedRows = [];
$isHeader = true;
$allowedOptions = ['A', 'B', 'C', 'D'];
$validTypes = ['single', 'multiple', 'case_study', 'assertion_reason', 'matching'];

while (($data = fgetcsv($stream, 4000, ',')) !== false) {
    if ($isHeader) {
        $isHeader = false;
        continue;
    }

    $data = CsvService::normalizeQuestionRow($data);

    $rawCorrect = strtoupper(trim($data[6] ?? ''));
    $letters = [];
    if (str_contains($rawCorrect, ',')) {
        $tokens = array_filter(array_map('trim', explode(',', $rawCorrect)));
        foreach ($tokens as $tok) {
            if (in_array($tok, $allowedOptions, true) && !in_array($tok, $letters, true)) {
                $letters[] = $tok;
            }
        }
    } else {
        $len = strlen($rawCorrect);
        for ($i = 0; $i < $len; $i++) {
            $ch = $rawCorrect[$i];
            if (in_array($ch, $allowedOptions, true) && !in_array($ch, $letters, true)) {
                $letters[] = $ch;
            }
        }
    }
    sort($letters);
    $correct = implode(',', $letters);

    $rawType = strtolower(trim((string)($data[7] ?? '')));
    if (!empty($rawType) && in_array($rawType, $validTypes, true)) {
        $qType = $rawType;
    } else {
        $qType = (count($letters) > 1) ? 'multiple' : 'single';
    }

    $parsedRows[] = [
        'q' => $data[0],
        'opt_a' => $data[2] ?? '',
        'opt_b' => $data[3] ?? '',
        'opt_c' => $data[4] ?? '',
        'opt_d' => $data[5] ?? '',
        'correct' => $correct,
        'type' => $qType
    ];
}
fclose($stream);

assert_test("CSV parsed 8 rows successfully", count($parsedRows) === 8);
assert_test("Row 1 parsed as 'single' with correct 'A'", $parsedRows[0]['type'] === 'single' && $parsedRows[0]['correct'] === 'A');
assert_test("Row 2 parsed comma-separated multi 'A,B,C'", $parsedRows[1]['type'] === 'multiple' && $parsedRows[1]['correct'] === 'A,B,C');
assert_test("Row 3 parsed contiguous letters 'ABC' as 'A,B,C'", $parsedRows[2]['type'] === 'multiple' && $parsedRows[2]['correct'] === 'A,B,C');
assert_test("Row 4 parsed case_study archetype", $parsedRows[3]['type'] === 'case_study' && $parsedRows[3]['correct'] === 'D');
assert_test("Row 5 (unquoted assertion_reason) parsed as 'assertion_reason' with correct 'A'", $parsedRows[4]['type'] === 'assertion_reason' && $parsedRows[4]['correct'] === 'A');
assert_test("Row 5 reconstructed Option A with comma properly", $parsedRows[4]['opt_a'] === 'Both A and R are true, and R is the correct explanation of A');
assert_test("Row 5 reconstructed Option C with comma properly", $parsedRows[4]['opt_c'] === 'A is true, but R is false');
assert_test("Row 6 (unquoted matching) parsed as 'matching' with correct 'A'", $parsedRows[5]['type'] === 'matching' && $parsedRows[5]['correct'] === 'A');
assert_test("Row 6 reconstructed Option A with matching pairs", $parsedRows[5]['opt_a'] === '1-Q, 2-P, 3-R');
assert_test("Row 7 (unquoted multi-answer) parsed as 'multiple' with correct 'A,B,C'", $parsedRows[6]['type'] === 'multiple' && $parsedRows[6]['correct'] === 'A,B,C');
assert_test("Row 8 (quoted assertion_reason) parsed as 'assertion_reason' with correct 'A'", $parsedRows[7]['type'] === 'assertion_reason' && $parsedRows[7]['correct'] === 'A');

// --------------------------------------------------------------------------
// Summary Results
// --------------------------------------------------------------------------
echo "\n=======================================================\n";
echo "🏁 MULTI-TYPE MCQ SUITE RESULTS\n";
echo "Total Tests:  $totalTests\n";
echo "Passed:       $passedTests\n";
echo "Failed:       $failedTests\n";
echo "=======================================================\n\n";

if ($failedTests > 0) {
    exit(1);
}

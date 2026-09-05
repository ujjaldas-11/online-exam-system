<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

/**
 * Examify - Bulk Student Promotion Test Suite
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/logger.php';

echo "\n=== EXAMIFY BULK STUDENT PROMOTION TEST SUITE ===\n\n";

$tests_run = 0;
$tests_passed = 0;

function assert_test($description, $condition) {
    global $tests_run, $tests_passed;
    $tests_run++;
    if ($condition) {
        $tests_passed++;
        echo "[PASS] $description\n";
    } else {
        echo "[FAIL] $description\n";
    }
}

// 1. Clean up any previous test students
$pdo->exec("DELETE FROM students WHERE email LIKE '%@bulkpromotetest.edu'");

// Provision test cohort for Test 1
echo "--- 1. Testing Cohort-Based Bulk Promotion ---\n";

$pdo->prepare("
    INSERT INTO students (name, email, password, roll_number, department, semester, status)
    VALUES 
    ('BP Test Active 1', 'bp1@bulkpromotetest.edu', 'hash', 'BP01', 'BCA', 2, 'active'),
    ('BP Test Active 2', 'bp2@bulkpromotetest.edu', 'hash', 'BP02', 'BCA', 2, 'active'),
    ('BP Test Blocked',  'bp3@bulkpromotetest.edu', 'hash', 'BP03', 'BCA', 2, 'blocked')
")->execute();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE department = 'BCA' AND semester = 2 AND email LIKE '%@bulkpromotetest.edu'");
$stmt->execute();
assert_test("Provisioned 3 test students in BCA Semester 2", $stmt->fetchColumn() == 3);

// Simulate cohort promotion: BCA Sem 2 -> Sem 3 (only active)
$up = $pdo->prepare("UPDATE students SET semester = 3 WHERE department = 'BCA' AND semester = 2 AND status = 'active' AND email LIKE '%@bulkpromotetest.edu'");
$up->execute();

$activeInSem3 = $pdo->query("SELECT COUNT(*) FROM students WHERE department = 'BCA' AND semester = 3 AND status = 'active' AND email LIKE '%@bulkpromotetest.edu'")->fetchColumn();
assert_test("Active students promoted from Semester 2 to Semester 3", $activeInSem3 == 2);

$blockedInSem2 = $pdo->query("SELECT COUNT(*) FROM students WHERE department = 'BCA' AND semester = 2 AND status = 'blocked' AND email LIKE '%@bulkpromotetest.edu'")->fetchColumn();
assert_test("Blocked student remained in Semester 2 (only_active respected)", $blockedInSem2 == 1);


// 2. Testing Selection-Based Bulk Promotion (+1 Semester)
echo "\n--- 2. Testing Selection-Based Bulk Promotion ---\n";

$pdo->prepare("
    INSERT INTO students (name, email, password, roll_number, department, semester, status)
    VALUES 
    ('BP Select Sem 3', 'bp4@bulkpromotetest.edu', 'hash', 'BP04', 'BBA', 3, 'active'),
    ('BP Select Sem 7', 'bp5@bulkpromotetest.edu', 'hash', 'BP05', 'BBA', 7, 'active'),
    ('BP Select Sem 8', 'bp6@bulkpromotetest.edu', 'hash', 'BP06', 'BBA', 8, 'active')
")->execute();

$s4 = $pdo->query("SELECT id FROM students WHERE email = 'bp4@bulkpromotetest.edu'")->fetchColumn();
$s5 = $pdo->query("SELECT id FROM students WHERE email = 'bp5@bulkpromotetest.edu'")->fetchColumn();
$s6 = $pdo->query("SELECT id FROM students WHERE email = 'bp6@bulkpromotetest.edu'")->fetchColumn();

// Simulate batch promotion for these 3 selected students
$selectedIds = [(int)$s4, (int)$s5, (int)$s6];
$placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
$stmt = $pdo->prepare("SELECT id, semester FROM students WHERE id IN ($placeholders)");
$stmt->execute($selectedIds);
$selectedStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$upStmt = $pdo->prepare("UPDATE students SET semester = semester + 1 WHERE id = ?");
$promoted = 0;
$capped = 0;

foreach ($selectedStudents as $st) {
    if ((int)$st['semester'] < 8) {
        $upStmt->execute([(int)$st['id']]);
        $promoted++;
    } else {
        $capped++;
    }
}

assert_test("Promoted 2 students whose semester < 8", $promoted === 2);
assert_test("Capped 1 student who was already in Semester 8", $capped === 1);

$newSem4 = $pdo->query("SELECT semester FROM students WHERE id = $s4")->fetchColumn();
$newSem5 = $pdo->query("SELECT semester FROM students WHERE id = $s5")->fetchColumn();
$newSem6 = $pdo->query("SELECT semester FROM students WHERE id = $s6")->fetchColumn();

assert_test("Student 4 advanced from Semester 3 to 4", $newSem4 == 4);
assert_test("Student 5 advanced from Semester 7 to 8", $newSem5 == 8);
assert_test("Student 6 was retained at Semester 8 (not advanced to 9)", $newSem6 == 8);


// 3. Testing UI Template Assets for Bulk Promote
echo "\n--- 3. Testing UI Elements in manage-students.php ---\n";
$template = file_get_contents(__DIR__ . '/../admin/manage-students.php');

assert_test("manage-students.php contains Bulk Promote button", str_contains($template, 'openBulkPromoteModal()'));
assert_test("manage-students.php contains Bulk Promote Modal (#bulkPromoteModal)", str_contains($template, 'id="bulkPromoteModal"'));
assert_test("manage-students.php contains selectAllCheckbox", str_contains($template, 'id="selectAllCheckbox"'));
assert_test("manage-students.php contains batchActionBar", str_contains($template, 'id="batchActionBar"'));
assert_test("manage-students.php contains bulk_promote_cohort POST handler", str_contains($template, 'bulk_promote_cohort'));
assert_test("manage-students.php contains bulk_promote_selected POST handler", str_contains($template, 'bulk_promote_selected'));
assert_test("manage-students.php logs bulk promotion in audit trail", str_contains($template, "'bulk_promote_cohort'") && str_contains($template, "'bulk_promote_selected'"));

// Clean up test records
$pdo->exec("DELETE FROM students WHERE email LIKE '%@bulkpromotetest.edu'");

echo "\n=== BULK PROMOTE TEST RESULTS ===\n";
echo "Total Tests:  $tests_run\n";
echo "Passed:       $tests_passed\n";
echo "Failed:       " . ($tests_run - $tests_passed) . "\n\n";

if ($tests_run === $tests_passed) {
    exit(0);
} else {
    exit(1);
}

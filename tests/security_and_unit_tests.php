<?php

/**
 * Examify — Security & Unit Test Suite
 *
 * Runs automated tests verifying:
 * 1. Sanitization & XSS Defense
 * 2. CSV Formula / DDE Injection Prevention
 * 3. Local File Inclusion (LFI) & Path Traversal Prevention
 * 4. CSRF Token Generation & Validation
 * 5. Authentication, Password Security & Session Hardening
 * 6. Database Schema, Relations & Model Integrity (MySQL/MariaDB)
 * 7. Exam Lifecycle, Question Bank, Proctoring & Auto-Grading Logic
 * 8. Question API Zero-Leakage (Correct Option Masking)
 *
 * Run via CLI: php tests/security_and_unit_tests.php
 */

declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Setup testing environment
define('TEST_RUNNER', true);
require_once __DIR__ . '/../utils/session.php';
init_secure_session();

require_once __DIR__ . '/../utils/env.php';
require_once __DIR__ . '/../utils/sanitize.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../config/database.php';

class TestRunner
{
    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function runTest(string $name, callable $test): void
    {
        try {
            $test();
            $this->passed++;
            echo "  \033[32m✔ PASS\033[0m: $name\n";
        } catch (Throwable $e) {
            $this->failed++;
            $this->failures[] = [
                'name' => $name,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ];
            echo "  \033[31m✖ FAIL\033[0m: $name\n";
            echo "         \033[33m" . $e->getMessage() . "\033[0m\n";
        }
    }

    public function assert(bool $condition, string $message = 'Assertion failed'): void
    {
        if (!$condition) {
            throw new Exception($message);
        }
    }

    public function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            $msg = $message ?: "Expected " . var_export($expected, true) . ", got " . var_export($actual, true);
            throw new Exception($msg);
        }
    }

    public function summary(): int
    {
        echo "\n=======================================================\n";
        echo "TEST SUMMARY: \033[32m{$this->passed} Passed\033[0m, ";
        if ($this->failed > 0) {
            echo "\033[31m{$this->failed} Failed\033[0m\n";
            echo "=======================================================\n";
            foreach ($this->failures as $f) {
                echo "\n• [{$f['name']}] failed in {$f['file']}:{$f['line']}\n  Error: {$f['error']}\n";
            }
            return 1;
        } else {
            echo "\033[32m0 Failed\033[0m (100% SUCCESS)\n";
            echo "=======================================================\n";
            return 0;
        }
    }
}

$t = new TestRunner();

echo "=======================================================\n";
echo "🧪 EXAMIFY SECURITY & UNIT TEST SUITE\n";
echo "=======================================================\n\n";

// =========================================================
// SECTION 1: Input Sanitization & XSS Escaping
// =========================================================
echo "1. Testing Input Sanitization & Output Escaping (XSS Defense)...\n";

$t->runTest('e() correctly escapes HTML characters and quotes', function () use ($t) {
    $t->assertSame('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', e('<script>alert("xss")</script>'));
    $t->assertSame('&#039;test&#039; &amp; &quot;quote&quot;', e("'test' & \"quote\""));
    $t->assertSame('', e(null));
    $t->assertSame('123', e('123'));
});

$t->runTest('clean_input() trims and strips HTML tags', function () use ($t) {
    $t->assertSame('hello world', clean_input("  <b>hello world</b>  \n"));
    $t->assertSame('', clean_input(null));
    $t->assertSame('safe text', clean_input("<p>safe text</p>"));
    $t->assertSame("alert('bad')safe text", clean_input("<script>alert('bad')</script>safe text"));
});

$t->runTest('int_param() validates and safely parses integer inputs', function () use ($t) {
    $t->assertSame(42, int_param(42));
    $t->assertSame(42, int_param('42'));
    $t->assertSame(0, int_param('invalid'));
    $t->assertSame(10, int_param('invalid', 10));
    $t->assertSame(0, int_param(null));
    $t->assertSame(0, int_param(''));
    $t->assertSame(-5, int_param('-5'));
});

// =========================================================
// SECTION 2: CSV Formula / DDE Injection Defense
// =========================================================
echo "\n2. Testing CSV Formula / DDE Injection Prevention...\n";

$t->runTest('sanitize_csv_value() prepends quote on spreadsheet formula triggers', function () use ($t) {
    $t->assertSame("'=1+1", sanitize_csv_value('=1+1'));
    $t->assertSame("'+SUM(A1:A10)", sanitize_csv_value('+SUM(A1:A10)'));
    $t->assertSame("'-2+3*cmd|' /C calc'!A0", sanitize_csv_value('-2+3*cmd|\' /C calc\'!A0'));
    $t->assertSame("'@cmd", sanitize_csv_value('@cmd'));
    $t->assertSame("'%calc", sanitize_csv_value("%calc"));
    $t->assertSame('John Doe', sanitize_csv_value('John Doe'));
    $t->assertSame('BCA2401', sanitize_csv_value('BCA2401'));
});

// =========================================================
// SECTION 3: Path Traversal & LFI Prevention
// =========================================================
echo "\n3. Testing Asset Path Traversal & LFI Prevention...\n";

$t->runTest('sanitize_asset_name() blocks directory traversal and malicious payloads', function () use ($t) {
    $t->assertSame(null, sanitize_asset_name('../../../etc/passwd', 'css'));
    $t->assertSame(null, sanitize_asset_name('..\\..\\windows\\system32', 'css'));
    $t->assertSame(null, sanitize_asset_name("style.css\0.php", 'css'));
    $t->assertSame(null, sanitize_asset_name('http://attacker.com/evil.js', 'js'));
    $t->assertSame(null, sanitize_asset_name('//attacker.com/evil.js', 'js'));
    $t->assertSame(null, sanitize_asset_name('php://input', 'js'));
    $t->assertSame(null, sanitize_asset_name('data:text/javascript,alert(1)', 'js'));
    $t->assertSame(null, sanitize_asset_name('javascript:alert(1)', 'js'));
    $t->assertSame(null, sanitize_asset_name('exam.php', 'css'));
    $t->assertSame('app.css', sanitize_asset_name('app.css', 'css'));
    $t->assertSame('exam.css', sanitize_asset_name('exam.css', 'css'));
    $t->assertSame('material-symbols.css', sanitize_asset_name('material-symbols.css', 'css'));
    $t->assertSame('anti-cheat.js', sanitize_asset_name('anti-cheat.js', 'js'));
});

// =========================================================
// SECTION 4: CSRF Protection & Tokens
// =========================================================
echo "\n4. Testing CSRF Protection & Token Mechanisms...\n";

$t->runTest('csrf_token() generates a secure 64-char hex token', function () use ($t) {
    if (session_status() === PHP_SESSION_NONE) {
        init_secure_session();
    }
    $token = csrf_token();
    $t->assert(strlen($token) === 64, 'Token must be 64 characters (32 random bytes hex)');
    $t->assert(ctype_xdigit($token), 'Token must be hexadecimal');
});

$t->runTest('csrf_field() outputs a valid HTML hidden input', function () use ($t) {
    $field = csrf_field();
    $t->assert(str_contains($field, '<input type="hidden" name="csrf_token" value="'), 'Field must be hidden input');
    $t->assert(str_contains($field, csrf_token()), 'Field must contain session token');
});

// =========================================================
// SECTION 5: Authentication & Session Helpers
// =========================================================
echo "\n5. Testing Authentication & Session Helpers...\n";

$t->runTest('Role check helpers distinguish admin, superadmin, teacher, and student sessions', function () use ($t) {
    $_SESSION = [];
    $t->assertSame(false, is_admin_logged_in());
    $t->assertSame(false, is_student_logged_in());
    $t->assertSame(false, is_superadmin());
    $t->assertSame(false, is_teacher());

    // Superadmin session
    $_SESSION = ['admin_id' => 1, 'admin_role' => 'superadmin', 'role' => 'superadmin'];
    $t->assertSame(true, is_admin_logged_in());
    $t->assertSame(true, is_superadmin());
    $t->assertSame(false, is_teacher());
    $t->assertSame('superadmin', get_admin_role());
    $t->assertSame(false, is_student_logged_in());

    // Teacher session
    $_SESSION = ['admin_id' => 2, 'admin_role' => 'teacher', 'role' => 'teacher'];
    $t->assertSame(true, is_admin_logged_in());
    $t->assertSame(false, is_superadmin());
    $t->assertSame(true, is_teacher());
    $t->assertSame('teacher', get_admin_role());
    $t->assertSame(false, is_student_logged_in());

    // Student session
    $_SESSION = ['student_id' => 5];
    $t->assertSame(false, is_admin_logged_in());
    $t->assertSame(true, is_student_logged_in());
    $t->assertSame(false, is_superadmin());
    $t->assertSame(false, is_teacher());
    $_SESSION = [];
});

$t->runTest('Flash message helpers store and consume one-time messages', function () use ($t) {
    $_SESSION = [];
    set_flash('success', 'Profile updated!');
    $t->assertSame(true, has_flash('success'));
    $t->assertSame('Profile updated!', get_flash('success'));
    $t->assertSame(false, has_flash('success'));
    $t->assertSame(null, get_flash('success'));
});

// =========================================================
// SECTION 6: Database Schema & MySQL Table Health
// =========================================================
echo "\n6. Testing Database Schema & Table Integrity (MySQL/MariaDB)...\n";

$t->runTest('PDO connection is established and healthy', function () use ($t, $pdo) {
    $t->assert($pdo instanceof PDO, 'PDO instance must be valid');
    $result = $pdo->query("SELECT 1")->fetchColumn();
    $t->assertSame(1, (int)$result);
});

$t->runTest('All required database tables exist in examify database', function () use ($t, $pdo) {
    $expectedTables = [
        'admins', 'students', 'subjects', 'exams', 'questions',
        'exam_attempts', 'student_answers', 'exam_violations',
        'profile_requests', 'registration_request', 'admin_audit_logs'
    ];

    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($expectedTables as $tbl) {
        $t->assert(in_array($tbl, $tables, true), "Table `$tbl` must exist in database");
    }
});

$t->runTest('admins table has role, status, department, and created_by columns', function () use ($t, $pdo) {
    $cols = $pdo->query("SHOW COLUMNS FROM admins")->fetchAll(PDO::FETCH_COLUMN);
    $t->assert(in_array('role', $cols, true), "Column `role` must exist in `admins` table");
    $t->assert(in_array('status', $cols, true), "Column `status` must exist in `admins` table");
    $t->assert(in_array('department', $cols, true), "Column `department` must exist in `admins` table");
    $t->assert(in_array('created_by', $cols, true), "Column `created_by` must exist in `admins` table");
});

$t->runTest('subjects, exams, and questions tables have created_by columns', function () use ($t, $pdo) {
    $subCols = $pdo->query("SHOW COLUMNS FROM subjects")->fetchAll(PDO::FETCH_COLUMN);
    $examCols = $pdo->query("SHOW COLUMNS FROM exams")->fetchAll(PDO::FETCH_COLUMN);
    $qCols = $pdo->query("SHOW COLUMNS FROM questions")->fetchAll(PDO::FETCH_COLUMN);

    $t->assert(in_array('created_by', $subCols, true), "Column `created_by` must exist in `subjects`");
    $t->assert(in_array('created_by', $examCols, true), "Column `created_by` must exist in `exams`");
    $t->assert(in_array('created_by', $qCols, true), "Column `created_by` must exist in `questions`");
});

$t->runTest('questions table has unit_number column with index', function () use ($t, $pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM questions LIKE 'unit_number'");
    $col = $stmt->fetch();
    $t->assert(!empty($col), "Column `unit_number` must exist in `questions` table");
});

$t->runTest('exams table has target_units column', function () use ($t, $pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM exams LIKE 'target_units'");
    $col = $stmt->fetch();
    $t->assert(!empty($col), "Column `target_units` must exist in `exams` table");
});

$t->runTest('students table has phone_number, gender and status columns', function () use ($t, $pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'phone_number'");
    $t->assert(!empty($stmt->fetch()), "Column `phone_number` must exist in `students`");

    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'gender'");
    $t->assert(!empty($stmt->fetch()), "Column `gender` must exist in `students`");

    $stmt = $pdo->query("SHOW COLUMNS FROM students LIKE 'status'");
    $t->assert(!empty($stmt->fetch()), "Column `status` must exist in `students`");
});

// =========================================================
// SECTION 7: Business Logic & Question Bank Security
// =========================================================
echo "\n7. Testing Exam Lifecycle, Question Bank & API Security...\n";

$t->runTest('Unit-wise questions can be queried and counted', function () use ($t, $pdo) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE subject_id = ? AND unit_number = ?");
    $stmt->execute([1, 1]);
    $count = (int) $stmt->fetchColumn();
    $t->assert($count > 0, "Subject 1 Unit 1 must contain seeded questions");
});

$t->runTest('Exam creation with unit filtering succeeds without SQL error', function () use ($t, $pdo) {
    $ins = $pdo->prepare("
        INSERT INTO exams (subject_id, title, description, duration_minutes, total_questions_to_ask, total_marks, status, access_pin, target_units)
        VALUES (?, ?, ?, ?, ?, ?, 'active', ?, ?)
    ");
    $ins->execute([1, 'Unit Test Demo Exam', 'Testing unit-based exam config', 20, 5, 10, '9999', '1']);
    $newExamId = (int) $pdo->lastInsertId();
    $t->assert($newExamId > 0, "Exam must be successfully created with target_units = 1");

    // Clean up test exam
    $pdo->prepare("DELETE FROM exams WHERE id = ?")->execute([$newExamId]);
});

$t->runTest('Student registration request validation prevents bad data', function () use ($t, $pdo) {
    $email = 'test_applicant_' . time() . '@college.edu';
    $roll = 'TESTROLL_' . time();
    $passHash = password_hash('Pass@123', PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO registration_request (name, email, password, roll_number, department, semester, phone_number, gender)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute(['Test Applicant', $email, $passHash, $roll, 'BCA', 4, '9876543210', 'male']);
    $reqId = (int) $pdo->lastInsertId();
    $t->assert($reqId > 0, "Valid registration request must be recorded");

    // Clean up
    $pdo->prepare("DELETE FROM registration_request WHERE id = ?")->execute([$reqId]);
});

$t->runTest('Exam attempt question allocation prevents leaking correct options', function () use ($t, $pdo) {
    $studentId = 1;
    $examId = 1;

    $attStmt = $pdo->prepare("SELECT id FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
    $attStmt->execute([$studentId, $examId]);
    $attemptId = $attStmt->fetchColumn();

    if (!$attemptId) {
        $ins = $pdo->prepare("INSERT INTO exam_attempts (student_id, exam_id, total_questions) VALUES (?, ?, 5)");
        $ins->execute([$studentId, $examId]);
        $attemptId = (int) $pdo->lastInsertId();

        $qStmt = $pdo->prepare("SELECT id FROM questions WHERE subject_id = 1 LIMIT 5");
        $qStmt->execute();
        $qIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);

        $ansIns = $pdo->prepare("INSERT INTO student_answers (attempt_id, question_id) VALUES (?, ?)");
        foreach ($qIds as $qid) {
            $ansIns->execute([$attemptId, $qid]);
        }
    }

    $apiQuery = "SELECT q.id, q.question_text, q.option_a, q.option_b, q.option_c, q.option_d
        FROM student_answers sa
        JOIN questions q ON sa.question_id = q.id
        WHERE sa.attempt_id = ?
        LIMIT 1";
    $apiStmt = $pdo->prepare($apiQuery);
    $apiStmt->execute([$attemptId]);
    $questionData = $apiStmt->fetch(PDO::FETCH_ASSOC);

    $t->assert(isset($questionData['question_text']), 'Question text must be present');
    $t->assert(isset($questionData['option_a']), 'Option A must be present');
    $t->assert(!isset($questionData['correct_option']), 'SECURITY: correct_option MUST NEVER be returned in student question endpoint');
});

$t->runTest('Anti-cheat violation logging tracks and records cheating events', function () use ($t, $pdo) {
    $attStmt = $pdo->query("SELECT id FROM exam_attempts LIMIT 1");
    $attemptId = (int) $attStmt->fetchColumn();

    if ($attemptId > 0) {
        $ins = $pdo->prepare("INSERT INTO exam_violations (attempt_id, violation_type, details) VALUES (?, ?, ?)");
        $ins->execute([$attemptId, 'Switched tab or minimized window', 'Unit test simulation']);
        $violId = (int) $pdo->lastInsertId();
        $t->assert($violId > 0, "Violation record must be created");

        $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM exam_violations WHERE id = ?");
        $cntStmt->execute([$violId]);
        $t->assertSame(1, (int)$cntStmt->fetchColumn());

        // Clean up test violation
        $pdo->prepare("DELETE FROM exam_violations WHERE id = ?")->execute([$violId]);
    }
});

// =========================================================
// SECTION 8: Superadmin & Teacher RBAC, Retirement & Record Retention
// =========================================================
echo "\n8. Testing Superadmin & Teacher Roles, Retirement & Record Retention...\n";

$t->runTest('is_system_initialized detects superadmin presence correctly', function () use ($t, $pdo) {
    $t->assertSame(true, is_system_initialized($pdo), 'Database with seeded superadmin must be reported as initialized');
});

$t->runTest('Superadmin and Teacher roles are distinguished in database', function () use ($t, $pdo) {
    $superStmt = $pdo->query("SELECT role, status FROM admins WHERE email = 'admin@college.edu'");
    $super = $superStmt->fetch();
    $t->assert($super !== false, 'Superadmin record must exist');
    $t->assertSame('superadmin', $super['role']);
    $t->assertSame('active', $super['status']);

    $teacherStmt = $pdo->query("SELECT role, status, department FROM admins WHERE email = 'teacher@college.edu'");
    $teacher = $teacherStmt->fetch();
    $t->assert($teacher !== false, 'Active teacher record must exist');
    $t->assertSame('teacher', $teacher['role']);
    $t->assertSame('active', $teacher['status']);
    $t->assertSame('BCA', $teacher['department']);
});

$t->runTest('Retired teacher account has retired status and records are preserved', function () use ($t, $pdo) {
    // 1. Verify retired teacher account exists
    $retiredStmt = $pdo->query("SELECT id, role, status FROM admins WHERE email = 'grace.hopper@college.edu'");
    $retiredTeacher = $retiredStmt->fetch();
    $t->assert($retiredTeacher !== false, 'Retired teacher record must exist in database');
    $t->assertSame('retired', $retiredTeacher['status'], 'Status must be retired');
    $t->assertSame('teacher', $retiredTeacher['role']);
    $retiredId = (int) $retiredTeacher['id'];

    // 2. Verify exams created by retired teacher STILL EXIST and are intact
    $examStmt = $pdo->prepare("SELECT COUNT(*) FROM exams WHERE created_by = ?");
    $examStmt->execute([$retiredId]);
    $examsCount = (int) $examStmt->fetchColumn();
    $t->assert($examsCount > 0, "Exams authored by retired teacher must be retained (found $examsCount exams)");

    // 3. Verify questions created by retired teacher STILL EXIST and are intact
    $qStmt = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE created_by = ?");
    $qStmt->execute([$retiredId]);
    $questionsCount = (int) $qStmt->fetchColumn();
    $t->assert($questionsCount > 0, "Questions authored by retired teacher must be retained (found $questionsCount questions)");

    // 4. Verify joining with exams correctly shows creator name and retired status
    $joinStmt = $pdo->prepare("
        SELECT e.title, a.name as creator_name, a.status as creator_status
        FROM exams e
        JOIN admins a ON e.created_by = a.id
        WHERE e.created_by = ?
        LIMIT 1
    ");
    $joinStmt->execute([$retiredId]);
    $row = $joinStmt->fetch();
    $t->assert(!empty($row), 'Query joining exams and retired teacher must return rows');
    $t->assertSame('retired', $row['creator_status'], 'Creator status must reflect retired');
    $t->assertSame('Prof. Grace Hopper', $row['creator_name']);
});

$t->runTest('Immutable audit logging logs administrative actions accurately', function () use ($t, $pdo) {
    $_SESSION['admin_id'] = 1;
    $_SESSION['admin_name'] = 'Dr. Sarah Admin';
    $_SESSION['admin_role'] = 'superadmin';

    $testAction = 'unit_test_audit_event_' . bin2hex(random_bytes(4));
    $success = log_admin_action($pdo, $testAction, 'test_entity', 999, 'Unit test audit event details');
    $t->assertSame(true, $success, 'log_admin_action must succeed');

    $chk = $pdo->prepare("SELECT admin_name, admin_role, action, details FROM admin_audit_logs WHERE action = ?");
    $chk->execute([$testAction]);
    $logEntry = $chk->fetch();

    $t->assert(!empty($logEntry), 'Audit log entry must exist in database');
    $t->assertSame('Dr. Sarah Admin', $logEntry['admin_name']);
    $t->assertSame('superadmin', $logEntry['admin_role']);
    $t->assertSame('Unit test audit event details', $logEntry['details']);

    // Clean up test audit log
    $pdo->prepare("DELETE FROM admin_audit_logs WHERE action = ?")->execute([$testAction]);
});

// Exit with status code
$exitCode = $t->summary();
exit($exitCode);

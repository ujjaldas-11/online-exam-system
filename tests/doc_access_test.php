<?php
/**
 * Test Suite: Documentation Access Control
 * Verifies that:
 * 1. user-doc.php is publicly accessible to anyone without credentials.
 * 2. admin-doc.php denies access to unauthenticated visitors and students.
 * 3. admin-doc.php grants access to authenticated teachers and superadmins.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/auth.php';

echo "\n=== EXAMIFY DOCUMENTATION ACCESS CONTROL TEST SUITE ===\n\n";
$pass = 0;
$fail = 0;

function assert_test(bool $cond, string $msg, &$pass, &$fail): void {
    if ($cond) {
        echo "[PASS] $msg\n";
        $pass++;
    } else {
        echo "[FAIL] $msg\n";
        $fail++;
    }
}

// 1. Test public access to user-doc.php
echo "--- 1. Testing Public User Documentation Access ---\n";
ob_start();
$_SESSION = []; // No session
include __DIR__ . '/../docs/user/user-doc.php';
$userDocHtml = ob_get_clean();

assert_test(strpos($userDocHtml, 'Examify — User Documentation') !== false, "user-doc.php outputs User Documentation title", $pass, $fail);
assert_test(strpos($userDocHtml, 'Student Portal Guide') !== false, "user-doc.php contains Student Portal Guide", $pass, $fail);
assert_test(strpos($userDocHtml, 'Universal Password Visibility') !== false, "user-doc.php contains password visibility guide", $pass, $fail);
assert_test(strpos($userDocHtml, 'admin-doc.php') !== false, "user-doc.php links to admin-doc.php", $pass, $fail);
assert_test(strpos($userDocHtml, 'Touchscreen Gating') !== false, "user-doc.php contains hardware & touchscreen gating policy", $pass, $fail);
assert_test(strpos($userDocHtml, 'Submitting Answers (In-DOM Confirmation Modal)') !== false, "user-doc.php contains in-DOM submit modal", $pass, $fail);

// Helper script runner to test isolated execution
function run_isolated_test(string $setupPhp): string {
    $script = tempnam(sys_get_temp_dir(), 'doc_test_');
    $code = "<?php\n" .
        "require_once 'F:/DEV/rewrite/online-exam/config/database.php';\n" .
        "require_once 'F:/DEV/rewrite/online-exam/utils/session.php';\n" .
        $setupPhp . "\n" .
        "include 'F:/DEV/rewrite/online-exam/docs/user/admin-doc.php';\n";
    file_put_contents($script, $code);
    $result = shell_exec("php \"$script\" 2>&1");
    @unlink($script);
    return $result ?: '';
}

// 2. Test unauthenticated access to admin-doc.php
echo "\n--- 2. Testing Unauthenticated Access to Admin Documentation ---\n";
$unauthOutput = run_isolated_test("
// No session set
");
assert_test(strpos($unauthOutput, 'Examify — Administrator Documentation') === false, "Unauthenticated access does NOT render Admin Documentation", $pass, $fail);
assert_test(empty(trim($unauthOutput)), "Unauthenticated access exits immediately without document body", $pass, $fail);

// 3. Test Student Role rejection
echo "\n--- 3. Testing Student Role Rejection on Admin Documentation ---\n";
$studentOutput = run_isolated_test("
init_secure_session();
\$_SESSION['student_id'] = 101;
\$_SESSION['role'] = 'student';
\$_SESSION['student_name'] = 'John Doe';
");
assert_test(strpos($studentOutput, 'Examify — Administrator Documentation') === false, "Student session does NOT render Admin Documentation", $pass, $fail);
assert_test(empty(trim($studentOutput)), "Student session exits immediately without document body", $pass, $fail);

// 4. Test Teacher Role acceptance
echo "\n--- 4. Testing Teacher Role Acceptance on Admin Documentation ---\n";
$stmt = $pdo->prepare("SELECT id, role, name, active_session_id FROM admins WHERE role = 'teacher' AND status = 'active' LIMIT 1");
$stmt->execute();
$teacher = $stmt->fetch();

if ($teacher) {
    $teacherOutput = run_isolated_test("
init_secure_session();
\$mySid = session_id();
\$pdo->prepare('UPDATE admins SET active_session_id = ? WHERE id = ?')->execute([\$mySid, {$teacher['id']}]);
\$_SESSION['admin_id'] = {$teacher['id']};
\$_SESSION['admin_role'] = 'teacher';
\$_SESSION['role'] = 'teacher';
\$_SESSION['admin_name'] = '{$teacher['name']}';
");

    assert_test(strpos($teacherOutput, 'Examify — Administrator Documentation') !== false, "Teacher session renders Admin Documentation title", $pass, $fail);
    assert_test(strpos($teacherOutput, 'Live Classroom Proctoring Panel') !== false, "Teacher sees Live Proctoring section", $pass, $fail);
    assert_test(strpos($teacherOutput, 'Authenticated:') !== false, "Teacher identity badge is displayed in topbar", $pass, $fail);
    assert_test(strpos($teacherOutput, 'Student Management Panel') !== false, "Teacher sees Student Management Panel section", $pass, $fail);
} else {
    echo "[SKIP] No active teacher record found in DB\n";
}

// 5. Test Superadmin Role acceptance
echo "\n--- 5. Testing Superadmin Role Acceptance on Admin Documentation ---\n";
$stmtSuper = $pdo->prepare("SELECT id, role, name, active_session_id FROM admins WHERE role = 'superadmin' AND status = 'active' LIMIT 1");
$stmtSuper->execute();
$super = $stmtSuper->fetch();

if ($super) {
    $superOutput = run_isolated_test("
init_secure_session();
\$mySid = session_id();
\$pdo->prepare('UPDATE admins SET active_session_id = ? WHERE id = ?')->execute([\$mySid, {$super['id']}]);
\$_SESSION['admin_id'] = {$super['id']};
\$_SESSION['admin_role'] = 'superadmin';
\$_SESSION['role'] = 'superadmin';
\$_SESSION['admin_name'] = '{$super['name']}';
");

    assert_test(strpos($superOutput, 'Examify — Administrator Documentation') !== false, "Superadmin session renders Admin Documentation title", $pass, $fail);
    assert_test(strpos($superOutput, 'Student Management Panel') !== false, "Superadmin sees Student Management Panel section", $pass, $fail);
    assert_test(strpos($superOutput, 'Bulk Student Promotion') !== false, "Superadmin sees Bulk Student Promotion section", $pass, $fail);
    assert_test(strpos($superOutput, 'Institutional Audit Trail') !== false, "Superadmin sees Audit Trail section", $pass, $fail);
    assert_test(strpos($superOutput, 'Teacher Accounts, Provisioning & Permanent Record Retention') !== false, "Superadmin sees Teacher Provisioning section", $pass, $fail);
} else {
    echo "[SKIP] No active superadmin record found in DB\n";
}

echo "\n=== ACCESS TEST SUMMARY ===\n";
echo "Total Tests: " . ($pass + $fail) . "\n";
echo "Passed:      $pass\n";
echo "Failed:      $fail\n\n";

exit($fail > 0 ? 1 : 0);

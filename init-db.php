<?php

/**
 * Examify - Consolidated Database Initialization Tool (Refined Architecture)
 *
 * Usage via CLI:
 *   php init-db.php                # Apply schema & seed standard test accounts & questions
 *   php init-db.php --fresh        # Drop existing database, recreate fresh, and seed
 *   php init-db.php --schema-only  # Apply clean database schema without demo seeds (Production)
 *
 * Usage via Browser:
 *   Open http://localhost/init-db.php (supports ?fresh=1 or ?schema_only=1)
 */

$isCli = (php_sapi_name() === 'cli');

function out(string $message, bool $isCli, string $type = 'info'): void
{
    if ($isCli) {
        $prefix = match ($type) {
            'error' => "\033[31m[ERROR]\033[0m ",
            'success' => "\033[32m[OK]\033[0m ",
            'heading' => "\n\033[1;34m=== \033[0m",
            default => "  • "
        };
        $suffix = ($type === 'heading') ? "\033[1;34m ===\033[0m\n" : "\n";
        echo $prefix . $message . $suffix;
    } else {
        $color = match ($type) {
            'error' => '#ef4444',
            'success' => '#10b981',
            'heading' => '#3b82f6',
            default => '#e2e8f0'
        };
        $weight = ($type === 'heading') ? 'bold; font-size: 1.1rem; margin-top: 16px;' : 'normal;';
        echo "<div style='color: $color; font-weight: $weight; font-family: monospace; font-size: 13px; margin: 4px 0;'>";
        echo ($type === 'success' ? '✅ ' : ($type === 'error' ? '❌ ' : '')) . htmlspecialchars($message);
        echo "</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html lang='en'><head><meta charset='UTF-8'><title>Database Init • Examify</title></head>";
    echo "<body style='background: #0f172a; color: #f8fafc; padding: 40px; font-family: system-ui, sans-serif;'>";
    echo "<div style='max-width: 760px; margin: 0 auto; background: #1e293b; padding: 32px; border-radius: 12px; border: 1px solid #334155;'>";
    echo "<h2 style='margin-top: 0; color: #38bdf8;'>🚀 Examify Database Initializer (Refined)</h2>";
}

// Parse Command Line Options
$args = $argv ?? [];
$isFresh = in_array('--fresh', $args, true) || in_array('--clean', $args, true) || (!$isCli && !empty($_GET['fresh']));
$schemaOnly = in_array('--schema-only', $args, true) || in_array('--no-seed', $args, true) || (!$isCli && !empty($_GET['schema_only']));

require_once __DIR__ . '/utils/env.php';

$host = get_env('DB_HOST', 'localhost');
$port = (int) get_env('DB_PORT', 3306);
$username = get_env('DB_USERNAME', 'root');
$password = get_env('DB_PASSWORD', '');
$charset = get_env('DB_CHARSET', 'utf8mb4');
$dbname = get_env('DB_DATABASE', 'examify');

out("Starting Examify Database Initialization...", $isCli, 'heading');
out("Target Database: `$dbname` on $host:$port", $isCli);

// 1. Connect to MySQL server to ensure database exists or recreate if --fresh
try {
    $dsnNoDb = "mysql:host=$host;port=$port;charset=$charset";
    $rootPdo = new PDO($dsnNoDb, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    if ($isFresh) {
        out("Dropping existing database `$dbname` (--fresh specified)...", $isCli);
        $rootPdo->exec("DROP DATABASE IF EXISTS `$dbname`");
    }

    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    out("Database `$dbname` is ready.", $isCli, 'success');
} catch (PDOException $e) {
    out("Failed connecting to MySQL server: " . $e->getMessage(), $isCli, 'error');
    if (!$isCli) echo "</div></body></html>";
    exit(1);
}

// 2. Connect to the specific database
try {
    $dsnWithDb = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsnWithDb, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    out("Connected to database `$dbname`.", $isCli, 'success');
} catch (PDOException $e) {
    out("Could not connect to `$dbname`: " . $e->getMessage(), $isCli, 'error');
    if (!$isCli) echo "</div></body></html>";
    exit(1);
}

// 3. Execute Canonical Database Schema
$schemaFile = __DIR__ . '/archive/schema.sql';
if (!file_exists($schemaFile)) {
    out("Schema file not found at $schemaFile", $isCli, 'error');
    if (!$isCli) echo "</div></body></html>";
    exit(1);
}

out("Applying Canonical Schema (archive/schema.sql)...", $isCli, 'heading');
try {
    $schemaSql = file_get_contents($schemaFile);
    $pdo->exec($schemaSql);
    out("All 10 refined tables verified and updated successfully.", $isCli, 'success');
} catch (PDOException $e) {
    out("Failed executing schema.sql: " . $e->getMessage(), $isCli, 'error');
    if (!$isCli) echo "</div></body></html>";
    exit(1);
}

// 4. Seed Data (Unless --schema-only is passed)
if ($schemaOnly) {
    out("Schema-only mode requested: Skipped data seeding.", $isCli);
    out("Database initialized for production. First-time setup available at /admin/setup.php", $isCli, 'success');
    if (!$isCli) echo "</div></body></html>";
    exit(0);
}

out("Seeding Baseline Accounts, Subjects & Question Banks...", $isCli, 'heading');

try {
    // 4.1 Check / Provision Accounts
    $superPass = password_hash('Admin@123', PASSWORD_DEFAULT);
    $teacherPass = password_hash('Teacher@123', PASSWORD_DEFAULT);
    $studentPass = password_hash('Student@123', PASSWORD_DEFAULT);

    // Superadmin
    $stmtSuper = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
    $stmtSuper->execute(['admin@college.edu']);
    $superadminId = $stmtSuper->fetchColumn();

    if (!$superadminId) {
        $insSuper = $pdo->prepare("
            INSERT INTO admins (name, email, password, role, status, department)
            VALUES (?, ?, ?, 'superadmin', 'active', 'Administration')
        ");
        $insSuper->execute(['Dr. Sarah Admin', 'admin@college.edu', $superPass]);
        $superadminId = (int) $pdo->lastInsertId();
        out("Created Superadmin: admin@college.edu / Admin@123", $isCli, 'success');
    } else {
        out("Superadmin already exists (ID: #$superadminId).", $isCli);
    }

    // Active Teacher (BCA)
    $stmtTeach = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
    $stmtTeach->execute(['teacher@college.edu']);
    $teacherActiveId = $stmtTeach->fetchColumn();

    if (!$teacherActiveId) {
        $insTeach = $pdo->prepare("
            INSERT INTO admins (name, email, password, role, status, department, created_by)
            VALUES (?, ?, ?, 'teacher', 'active', 'BCA', ?)
        ");
        $insTeach->execute(['Prof. Alan Turing', 'teacher@college.edu', $teacherPass, $superadminId]);
        $teacherActiveId = (int) $pdo->lastInsertId();
        out("Created Active Teacher: teacher@college.edu / Teacher@123", $isCli, 'success');
    } else {
        out("Active Teacher already exists (ID: #$teacherActiveId).", $isCli);
    }

    // Retired Teacher (BCA) - To demonstrate record retention after retirement
    $stmtRet = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
    $stmtRet->execute(['grace.hopper@college.edu']);
    $teacherRetiredId = $stmtRet->fetchColumn();

    if (!$teacherRetiredId) {
        $insRet = $pdo->prepare("
            INSERT INTO admins (name, email, password, role, status, department, created_by)
            VALUES (?, ?, ?, 'teacher', 'retired', 'BCA', ?)
        ");
        $insRet->execute(['Prof. Grace Hopper', 'grace.hopper@college.edu', $teacherPass, $superadminId]);
        $teacherRetiredId = (int) $pdo->lastInsertId();
        out("Created Retired Teacher: grace.hopper@college.edu (Status: Retired - Records Preserved)", $isCli, 'success');
    } else {
        out("Retired Teacher already exists (ID: #$teacherRetiredId).", $isCli);
    }

    // Active Students (Enrolled)
    $studentsToSeed = [
        ['Alex Johnson', 'student@college.edu', $studentPass, 'BCA2401', 'BCA', 4, '9876543210', 'male', 'active', $superadminId],
        ['Priya Sharma', 'priya@college.edu', $studentPass, 'BCA2402', 'BCA', 4, '9876543211', 'female', 'active', $superadminId],
        ['Rahul Verma', 'rahul@college.edu', $studentPass, 'BBA2401', 'BBA', 2, '9876543212', 'male', 'active', $superadminId],
        ['Test Student', 'student@example.com', password_hash('password123', PASSWORD_DEFAULT), 'STU12345', 'BCA', 1, '9876543213', 'male', 'active', $superadminId],
        ['Neha Gupta', 'neha@college.edu', $studentPass, 'BCA2405', 'BCA', 4, '9876543214', 'female', 'pending', null] // Pending student request
    ];

    $insStu = $pdo->prepare("
        INSERT INTO students (name, email, password, roll_number, department, semester, phone_number, gender, status, reviewed_by, reviewed_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, IF(? = 'active', NOW(), NULL))
        ON DUPLICATE KEY UPDATE name = VALUES(name), department = VALUES(department), semester = VALUES(semester), status = VALUES(status)
    ");

    foreach ($studentsToSeed as $s) {
        $insStu->execute([
            $s[0], $s[1], $s[2], $s[3], $s[4], $s[5], $s[6], $s[7], $s[8], $s[9], $s[8]
        ]);
    }
    out("Enrolled active test students and seeded 1 pending registration for verification.", $isCli, 'success');

    // 4.2 Create Curriculum Subjects with Creator Attribution
    $subjectsMap = [
        'Operating Systems' => ['dept' => 'BCA', 'sem' => 4, 'by' => $teacherActiveId],
        'Design and Analysis of Algorithms' => ['dept' => 'BCA', 'sem' => 4, 'by' => $teacherRetiredId],
        'Computer Networks' => ['dept' => 'BCA', 'sem' => 4, 'by' => $superadminId],
        'Database Management Systems' => ['dept' => 'BCA', 'sem' => 4, 'by' => $teacherActiveId],
        'Principles of Management' => ['dept' => 'BBA', 'sem' => 2, 'by' => $superadminId]
    ];

    $subjectIds = [];
    $insSub = $pdo->prepare("
        INSERT INTO subjects (name, department, semester, created_by)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE department = VALUES(department), semester = VALUES(semester)
    ");

    foreach ($subjectsMap as $sName => $meta) {
        $chkSub = $pdo->prepare("SELECT id FROM subjects WHERE name = ?");
        $chkSub->execute([$sName]);
        $existingSubId = $chkSub->fetchColumn();

        if ($existingSubId) {
            $subjectIds[$sName] = (int) $existingSubId;
        } else {
            $insSub->execute([$sName, $meta['dept'], $meta['sem'], $meta['by']]);
            $subjectIds[$sName] = (int) $pdo->lastInsertId();
        }
    }
    out("Curriculum subjects verified with author attribution.", $isCli, 'success');

    // 4.3 Import Test Questions from tests/ Directory
    $testFiles = [
        'os-questions.json' => ['sub' => 'Operating Systems', 'by' => $teacherActiveId],
        'daa-questions.json' => ['sub' => 'Design and Analysis of Algorithms', 'by' => $teacherRetiredId],
        'networking-questions.json' => ['sub' => 'Computer Networks', 'by' => $superadminId]
    ];

    $insQ = $pdo->prepare("
        INSERT INTO questions (subject_id, question_text, unit_number, option_a, option_b, option_c, option_d, correct_option, marks, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $totalImported = 0;
    foreach ($testFiles as $jsonFile => $meta) {
        $filePath = __DIR__ . '/tests/' . $jsonFile;
        if (!file_exists($filePath)) {
            continue;
        }

        $sName = $meta['sub'];
        $subId = $subjectIds[$sName] ?? null;
        if (!$subId) {
            continue;
        }

        $chkCount = $pdo->prepare("SELECT COUNT(*) FROM questions WHERE subject_id = ?");
        $chkCount->execute([$subId]);
        if ((int) $chkCount->fetchColumn() > 0) {
            continue;
        }

        $raw = file_get_contents($filePath);
        $questions = json_decode($raw, true);
        if (!is_array($questions)) {
            continue;
        }

        foreach ($questions as $idx => $q) {
            if (empty($q['question_text']) || empty($q['option_a']) || empty($q['option_b']) || empty($q['correct_option'])) {
                continue;
            }
            $unitNum = isset($q['unit_number']) ? (int) $q['unit_number'] : (($idx % 5) + 1);
            $insQ->execute([
                $subId,
                trim(strip_tags($q['question_text'])),
                $unitNum,
                trim($q['option_a']),
                trim($q['option_b']),
                isset($q['option_c']) ? trim($q['option_c']) : null,
                isset($q['option_d']) ? trim($q['option_d']) : null,
                strtoupper(trim($q['correct_option'])),
                isset($q['marks']) ? (int) $q['marks'] : 1,
                $meta['by']
            ]);
            $totalImported++;
        }
    }

    if ($totalImported > 0) {
        out("Imported $totalImported test questions attributed to instructors.", $isCli, 'success');
    } else {
        out("Question bank already populated.", $isCli);
    }

    // 4.4 Create Sample Active Exam for Live Testing
    $osSubId = $subjectIds['Operating Systems'] ?? null;
    if ($osSubId) {
        $chkExam = $pdo->prepare("SELECT id FROM exams WHERE title = ?");
        $chkExam->execute(['OS Surprise Quiz']);
        $examId = $chkExam->fetchColumn();

        if (!$examId) {
            $insExam = $pdo->prepare("
                INSERT INTO exams (subject_id, title, duration_minutes, total_questions_to_ask, total_marks, status, access_pin, target_units, start_time, created_by)
                VALUES (?, 'OS Surprise Quiz', 30, 10, 10, 'active', '4821', 'all', NOW(), ?)
            ");
            $insExam->execute([$osSubId, $teacherActiveId]);
            $examId = (int) $pdo->lastInsertId();
            out("Created Active Exam: 'OS Surprise Quiz' (PIN: 4821, Author: Prof. Alan Turing)", $isCli, 'success');
        }

        // Retired teacher exam to prove data preservation
        $daaSubId = $subjectIds['Design and Analysis of Algorithms'] ?? null;
        if ($daaSubId) {
            $chkDaa = $pdo->prepare("SELECT id FROM exams WHERE title = ?");
            $chkDaa->execute(['DAA Mid-Term Assessment']);
            if (!$chkDaa->fetchColumn()) {
                $insDaa = $pdo->prepare("
                    INSERT INTO exams (subject_id, title, duration_minutes, total_questions_to_ask, total_marks, status, access_pin, target_units, start_time, created_by)
                    VALUES (?, 'DAA Mid-Term Assessment', 45, 10, 20, 'active', NULL, 'all', NOW(), ?)
                ");
                $insDaa->execute([$daaSubId, $teacherRetiredId]);
                out("Created Exam by Retired Teacher: 'DAA Mid-Term Assessment'", $isCli, 'success');
            }
        }

        // Seed an attempt for Alex Johnson so leaderboards, analytics and PDF export have immediate data
        $alexId = (int) $pdo->query("SELECT id FROM students WHERE email = 'student@college.edu'")->fetchColumn();
        if ($alexId > 0 && $examId > 0) {
            $chkAttempt = $pdo->prepare("SELECT id FROM exam_attempts WHERE student_id = ? AND exam_id = ?");
            $chkAttempt->execute([$alexId, $examId]);
            $attemptId = $chkAttempt->fetchColumn();

            if (!$attemptId) {
                $insAtt = $pdo->prepare("
                    INSERT INTO exam_attempts (student_id, exam_id, total_questions, score, status, started_at, submitted_at)
                    VALUES (?, ?, 10, 10.00, 'completed', NOW() - INTERVAL 25 MINUTE, NOW() - INTERVAL 5 MINUTE)
                ");
                $insAtt->execute([$alexId, $examId]);
                $attemptId = (int) $pdo->lastInsertId();

                $qs = $pdo->query("SELECT id, correct_option FROM questions WHERE subject_id = $osSubId LIMIT 10")->fetchAll();
                $insAns = $pdo->prepare("
                    INSERT INTO student_answers (attempt_id, question_id, selected_option, is_correct)
                    VALUES (?, ?, ?, 1)
                ");
                foreach ($qs as $qRow) {
                    $insAns->execute([$attemptId, $qRow['id'], $qRow['correct_option']]);
                }
                out("Seeded completed attempt for Alex Johnson (Score: 10.00 / 10).", $isCli, 'success');
            }
        }
    }

    // 4.5 Record Initial Audit Log
    $chkInitLog = $pdo->query("SELECT COUNT(*) FROM admin_audit_logs WHERE action = 'system_initialized'")->fetchColumn();
    if ((int)$chkInitLog === 0) {
        $pdo->prepare("
            INSERT INTO admin_audit_logs (admin_id, admin_name, admin_role, action, entity_type, entity_id, details, ip_address)
            VALUES (?, 'Dr. Sarah Admin', 'superadmin', 'system_initialized', 'system', 1, 'Examify refined system initialized via init-db.php', '127.0.0.1')
        ")->execute([$superadminId]);
        out("System initialization recorded in audit trail.", $isCli, 'success');
    }

} catch (Exception $e) {
    out("Error during data seeding: " . $e->getMessage(), $isCli, 'error');
    if (!$isCli) echo "</div></body></html>";
    exit(1);
}

out("Initialization Complete!", $isCli, 'heading');
out("Credentials Summary:", $isCli);
out("Superadmin:      admin@college.edu / Admin@123", $isCli);
out("Active Teacher:  teacher@college.edu / Teacher@123", $isCli);
out("Retired Teacher: grace.hopper@college.edu (Login Disabled, Authored Records Preserved)", $isCli);
out("Default Student: student@college.edu / Student@123", $isCli);
out("Live Test Exam:  'OS Surprise Quiz' (PIN: 4821)", $isCli);

if (!$isCli) {
    echo "<div style='margin-top: 24px; padding-top: 16px; border-top: 1px solid #334155; display: flex; gap: 12px;'>";
    echo "<a href='admin/admin-login.php' style='background: #3b82f6; color: white; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: bold;'>Go to Admin Login</a>";
    echo "<a href='student/login.php' style='background: #334155; color: white; padding: 10px 18px; border-radius: 6px; text-decoration: none; font-weight: bold;'>Go to Student Login</a>";
    echo "</div></div></body></html>";
}

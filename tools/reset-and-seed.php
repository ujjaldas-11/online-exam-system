<?php

/**
 * Complete Database Reset & Seed Script with /tests questions
 */

$host = 'localhost';
$username = 'root';
$password = '';
$charset = 'utf8mb4';
$dbname = 'examify';

echo "=======================================================\n";
echo "🔄 EXAMIFY DATABASE RESET & SEED TOOL\n";
echo "=======================================================\n\n";

// 1. Connect without dbname to drop and recreate
try {
    $rootPdo = new PDO("mysql:host=$host;charset=$charset", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "1. Dropping existing database `$dbname` (if exists)...\n";
    $rootPdo->exec("DROP DATABASE IF EXISTS `$dbname`");

    echo "2. Creating fresh database `$dbname`...\n";
    $rootPdo->exec("CREATE DATABASE `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "   ✅ Database `$dbname` created successfully.\n\n";
} catch (PDOException $e) {
    echo "❌ [ERROR] Could not recreate database: " . $e->getMessage() . "\n";
    exit(1);
}

// 2. Connect to the fresh examify database
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo "❌ [ERROR] Could not connect to `$dbname`: " . $e->getMessage() . "\n";
    exit(1);
}

// 3. Execute archive/schema.sql
$schemaPath = __DIR__ . '/../archive/schema.sql';
if (!file_exists($schemaPath)) {
    echo "❌ [ERROR] archive/schema.sql not found at $schemaPath\n";
    exit(1);
}

echo "3. Applying database schema (archive/schema.sql)...\n";
$schemaSql = file_get_contents($schemaPath);
$pdo->exec($schemaSql);
echo "   ✅ Tables created: admins, students, subjects, exams, questions, exam_attempts, student_answers, exam_violations, profile_requests.\n\n";

// 4. Insert Default Administrators & Students
echo "4. Inserting Admin & Student credentials...\n";
$adminPass = password_hash('Admin@123', PASSWORD_DEFAULT);
$studentPass = password_hash('Student@123', PASSWORD_DEFAULT);

$insAdmin = $pdo->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
$insAdmin->execute(['Dr. Sarah Admin', 'admin@college.edu', $adminPass]);
$insAdmin->execute(['Test Admin', 'admin@example.com', password_hash('password123', PASSWORD_DEFAULT)]);

$insStudent = $pdo->prepare("INSERT INTO students (name, email, password, roll_number, department, semester, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
$insStudent->execute(['Alex Johnson', 'student@college.edu', $studentPass, 'BCA2401', 'BCA', 4]);
$insStudent->execute(['Priya Sharma', 'priya@college.edu', $studentPass, 'BCA2402', 'BCA', 4]);
$insStudent->execute(['Rahul Verma', 'rahul@college.edu', $studentPass, 'BBA2401', 'BBA', 2]);
$insStudent->execute(['Test Student', 'student@example.com', password_hash('password123', PASSWORD_DEFAULT), 'STU12345', 'BCA', 1]);
echo "   ✅ Default Admin: admin@college.edu / Admin@123\n";
echo "   ✅ Default Student: student@college.edu / Student@123 (BCA, Sem 4)\n\n";

// 5. Insert Subjects
echo "5. Creating curriculum subjects...\n";
$subjectsMap = [
    'Operating Systems' => ['dept' => 'BCA', 'sem' => 4],
    'Design and Analysis of Algorithms' => ['dept' => 'BCA', 'sem' => 4],
    'Computer Networks' => ['dept' => 'BCA', 'sem' => 4],
    'Database Management Systems' => ['dept' => 'BCA', 'sem' => 4],
    'Principles of Management' => ['dept' => 'BBA', 'sem' => 2]
];

$subjectIds = [];
$insSub = $pdo->prepare("INSERT INTO subjects (name, department, semester) VALUES (?, ?, ?)");
foreach ($subjectsMap as $name => $meta) {
    $insSub->execute([$name, $meta['dept'], $meta['sem']]);
    $subjectIds[$name] = (int) $pdo->lastInsertId();
    echo "   • Subject created: $name (ID: {$subjectIds[$name]}, {$meta['dept']}, Sem {$meta['sem']})\n";
}
echo "\n";

// 6. Import Questions from /tests directory
echo "6. Importing questions from /tests directory...\n";
$testFiles = [
    'os-questions.json' => 'Operating Systems',
    'daa-questions.json' => 'Design and Analysis of Algorithms',
    'networking-questions.json' => 'Computer Networks'
];

$insQ = $pdo->prepare("
    INSERT INTO questions (subject_id, question_text, option_a, option_b, option_c, option_d, correct_option, marks)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");

$totalImported = 0;

foreach ($testFiles as $jsonFile => $subjectName) {
    $filePath = __DIR__ . '/../tests/' . $jsonFile;
    if (!file_exists($filePath)) {
        echo "   ⚠️ File not found: $jsonFile\n";
        continue;
    }

    $raw = file_get_contents($filePath);
    $questions = json_decode($raw, true);

    if (!is_array($questions)) {
        echo "   ❌ Invalid JSON in $jsonFile\n";
        continue;
    }

    $subjectId = $subjectIds[$subjectName] ?? null;
    if (!$subjectId) {
        echo "   ❌ Subject '$subjectName' not found for $jsonFile\n";
        continue;
    }

    $fileCount = 0;
    $pdo->beginTransaction();
    foreach ($questions as $q) {
        if (empty($q['question_text']) || empty($q['option_a']) || empty($q['option_b']) || empty($q['correct_option'])) {
            continue;
        }

        $insQ->execute([
            $subjectId,
            trim(strip_tags($q['question_text'])),
            trim(strip_tags($q['option_a'])),
            trim(strip_tags($q['option_b'])),
            isset($q['option_c']) ? trim(strip_tags($q['option_c'])) : '',
            isset($q['option_d']) ? trim(strip_tags($q['option_d'])) : '',
            strtoupper(trim(strip_tags($q['correct_option']))),
            isset($q['marks']) ? (int) $q['marks'] : 1
        ]);
        $fileCount++;
    }
    $pdo->commit();
    $totalImported += $fileCount;
    echo "   ✅ $jsonFile: Inserted $fileCount questions for '$subjectName'.\n";
}
echo "   Total Questions in Bank: $totalImported\n\n";

// 7. Create Sample Live & Scheduled Exams
echo "7. Creating sample examinations...\n";
$insExam = $pdo->prepare("
    INSERT INTO exams (subject_id, title, description, duration_minutes, total_questions_to_ask, total_marks, status, access_pin, start_time)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
");

// 1 Active Surprise Test with Classroom PIN
$insExam->execute([
    $subjectIds['Operating Systems'],
    'OS Surprise Quiz — Processes & Scheduling',
    'Classroom surprise quiz covering CPU scheduling algorithms, deadlock detection, and memory management.',
    30,
    10,
    20,
    'active',
    '4821',
    date('Y-m-d H:i:s')
]);
$exam1Id = $pdo->lastInsertId();

// 1 Active Exam without PIN
$insExam->execute([
    $subjectIds['Design and Analysis of Algorithms'],
    'DAA Mid-Term Assessment',
    'Comprehensive assessment on algorithm design paradigms: divide-and-conquer, greedy, and dynamic programming.',
    45,
    15,
    30,
    'active',
    null,
    date('Y-m-d H:i:s')
]);

// 1 Scheduled Future Exam
$insExam->execute([
    $subjectIds['Computer Networks'],
    'Computer Networks — OSI & TCP/IP Model',
    'Upcoming unit test covering network layer routing, IP addressing, and transport protocols.',
    40,
    10,
    20,
    'scheduled',
    '1234',
    date('Y-m-d H:i:s', strtotime('+2 days 10:00:00'))
]);

echo "   ✅ Sample Live Exam 1: 'OS Surprise Quiz' (Active, Duration: 30m, 10 Qs, Classroom PIN: 4821)\n";
echo "   ✅ Sample Live Exam 2: 'DAA Mid-Term Assessment' (Active, Duration: 45m, 15 Qs, Open Access)\n";
echo "   ✅ Sample Scheduled Exam: 'Computer Networks' (Scheduled in 2 days)\n\n";

echo "=======================================================\n";
echo "🎉 SUCCESS: DATABASE RESET & SEEDING COMPLETE!\n";
echo "=======================================================\n";
echo "Admin Portal:   http://localhost/online-exam-system/admin/admin-login.php\n";
echo "  • Email:      admin@college.edu\n";
echo "  • Password:   Admin@123\n\n";
echo "Student Portal: http://localhost/online-exam-system/student/login.php\n";
echo "  • Email:      student@college.edu\n";
echo "  • Password:   Student@123\n";
echo "  • Class:      BCA, Semester 4 (Roll: BCA2401)\n";
echo "=======================================================\n";

<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$success_count = 0;
$skip_count = 0;
$errors = [];
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    verify_csrf();

    $csv_content = '';

    if (!empty($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $csv_content = file_get_contents($_FILES['csv_file']['tmp_name']);
    } elseif (!empty($_POST['csv_raw'])) {
        $csv_content = trim($_POST['csv_raw']);
    }

    if (empty($csv_content)) {
        $errors[] = "Please upload a valid CSV file or paste CSV text.";
    } else {
        $lines = explode("\n", str_replace("\r", "", $csv_content));
        $header_skipped = false;

        $checkStmt = $pdo->prepare("SELECT id FROM students WHERE email = ? OR roll_number = ?");
        $insertStmt = $pdo->prepare("INSERT INTO students (name, email, password, roll_number, department, semester) VALUES (?, ?, ?, ?, ?, ?)");

        $pdo->beginTransaction();

        foreach ($lines as $lineIndex => $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            $row = str_getcsv($line);
            if (count($row) < 5) {
                continue;
            }

            // Skip header if present
            if (!$header_skipped && (stripos($row[0], 'name') !== false || stripos($row[1], 'email') !== false)) {
                $header_skipped = true;
                continue;
            }

            $name = clean_input($row[0] ?? '');
            $email = clean_input($row[1] ?? '');
            $roll = clean_input($row[2] ?? '');
            $dept = clean_input($row[3] ?? '');
            $sem = int_param($row[4] ?? 0);
            $raw_pass = !empty($row[5]) ? trim($row[5]) : $roll; // Default password is Roll Number if omitted

            if (!$name || !$email || !$roll || !$dept || $sem < 1 || $sem > 8) {
                $errors[] = "Row " . ($lineIndex + 1) . ": Invalid or missing fields for $name ($roll).";
                $skip_count++;
                continue;
            }

            // Check duplicate
            $checkStmt->execute([$email, $roll]);
            if ($checkStmt->rowCount() > 0) {
                $skip_count++;
                continue;
            }

            try {
                $hashed = password_hash($raw_pass, PASSWORD_DEFAULT);
                $insertStmt->execute([$name, $email, $hashed, $roll, $dept, $sem]);
                $success_count++;
            } catch (PDOException $e) {
                $skip_count++;
                log_error("Failed to import student $name", $e);
            }
        }

        $pdo->commit();
        $message = "Import complete: $success_count new students added, $skip_count duplicates/invalid skipped.";
    }
}

$page_title = 'Batch Student CSV Import • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/navbar.php';
?>

<div class="container" style="max-width: 750px;">
    <div class="page-header">
        <div>
            <h1>Batch Student CSV Roster Import</h1>
            <p>Quickly enroll entire classrooms for LAN surprise examinations</p>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">CSV Format Structure</div>
        <div class="alert alert-info" style="text-align: left; margin-bottom: 20px;">
            Columns must be ordered as: <code>Name, Email, Roll Number, Department, Semester, Password</code><br>
            <em>Example:</em><br>
            <code>Alex Smith, alex@college.edu, BCA2401, BCA, 4, pass123</code><br>
            <small>*Note: If Password column is omitted, the student's default password will be set to their Roll Number.</small>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Upload .CSV File</label>
                <input type="file" name="csv_file" accept=".csv,text/csv">
            </div>

            <div class="form-group">
                <label>Or Paste CSV Text Directly</label>
                <textarea name="csv_raw" rows="6" placeholder="John Doe, john@college.edu, BCA2401, BCA, 4&#10;Jane Smith, jane@college.edu, BCA2402, BCA, 4"></textarea>
            </div>

            <button type="submit" name="import_csv" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">upload_file</span> Import Classroom Roster
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

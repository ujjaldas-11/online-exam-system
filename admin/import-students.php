<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

// Fallback if CsvService is missing or restructuring
if (file_exists('../services/CsvService.php')) {
    require_once '../services/CsvService.php';
}

// =========================================================================
// 1. DYNAMIC SAMPLE CSV DOWNLOADER
// =========================================================================
if (isset($_GET['download_sample'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="examify_student_import_sample.csv"');
    // Output headers
    echo "Name,Email,Roll Number,Department,Semester,Phone Number,Gender,Password\n";
    // Output sample rows
    echo "Alex Smith,alex.s@college.edu,BCA2401,BCA,4,9876543210,Male,pass123\n";
    echo "Maria Garcia,maria.g@college.edu,BCA2402,BCA,4,9876543211,Female,\n";
    echo "Rohan Sharma,rohan@college.edu,BCA2403,BCA,4,9876543212,Male,\n";
    exit;
}

$success_count = 0;
$skip_count = 0;
$errors = [];
$message = '';

// =========================================================================
// CSV PROCESSING LOGIC (OPTIMIZED FOR 100+ RECORDS)
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['import_csv'])) {
    verify_csrf();

    //  Prevent timeout on large batches
    set_time_limit(300); // 5 minutes max execution time
    ini_set('memory_limit', '256M');

    $maxFileSize = 5 * 1024 * 1024; // 5MB
    $handle = null;

    // Handle File Upload vs Raw Text Stream
    if (!empty($_FILES['csv_file']['name']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        if ($_FILES['csv_file']['size'] > $maxFileSize) {
            $errors[] = "File is too large. Maximum 5MB allowed.";
        } else {
            $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        }
    } elseif (!empty($_POST['csv_raw'])) {
        if (strlen($_POST['csv_raw']) > $maxFileSize) {
            $errors[] = "CSV text payload too large. Maximum 5MB allowed.";
        } else {
            $handle = fopen('php://memory', 'r+');
            fwrite($handle, trim($_POST['csv_raw']));
            rewind($handle);
        }
    } else {
        $errors[] = "Please upload a valid CSV file or paste CSV text.";
    }

    if ($handle && empty($errors)) {
        $adminId = $_SESSION['admin_id'] ?? null;
        $lineIndex = 0;
        $headerSkipped = false;

        try {
            // Pre-fetch existing emails and roll numbers into lookup sets (O(1) checks)
            $existingEmails = [];
            $existingRolls  = [];

            $existingStmt = $pdo->query("SELECT email, roll_number FROM students");
            while ($r = $existingStmt->fetch(PDO::FETCH_ASSOC)) {
                $existingEmails[strtolower($r['email'])] = true;
                $existingRolls[strtoupper($r['roll_number'])] = true;
            }

            //  Prepare single INSERT statement
            $insertStmt = $pdo->prepare("
                INSERT INTO students 
                (name, email, password, roll_number, department, semester, phone_number, gender, status, reviewed_by, reviewed_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ");

            // Process in transaction chunks of 100 rows to keep lock times low
            $batchSize = 100;
            $rowsInCurrentBatch = 0;
            $pdo->beginTransaction();

            // Stream line by line using fgetcsv
            while (($row = fgetcsv($handle)) !== false) {
                $lineIndex++;

                // Enforce maximum row threshold
                if ($lineIndex > 5005) {
                    $errors[] = "Reached limit of 5,000 records. Remaining rows ignored.";
                    break;
                }

                // Skip empty lines
                if (empty(array_filter($row))) {
                    continue;
                }

                // Minimum required columns
                if (count($row) < 7) {
                    $errors[] = "Row $lineIndex: Expected at least 7 columns.";
                    $skip_count++;
                    continue;
                }

                // Dynamic header check
                if (!$headerSkipped && (stripos($row[0], 'name') !== false || stripos($row[1], 'email') !== false)) {
                    $headerSkipped = true;
                    continue;
                }

                // Map and clean input
                $name   = clean_input($row[0] ?? '');
                $email  = clean_input($row[1] ?? '');
                $roll   = strtoupper(clean_input($row[2] ?? ''));
                $dept   = clean_input($row[3] ?? '');
                $sem    = (int)($row[4] ?? 0);
                $phone  = clean_input($row[5] ?? '');
                $gender = clean_input($row[6] ?? '');
                $raw_pass = !empty(trim($row[7] ?? '')) ? trim($row[7]) : $roll;

                // Validation
                if (!$name || !$email || !$roll || !$dept || $sem < 1 || $sem > 8 || !$phone || !$gender) {
                    $errors[] = "Row $lineIndex: Missing required fields for $name ($roll).";
                    $skip_count++;
                    continue;
                }

                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = "Row $lineIndex: Invalid email format ($email).";
                    $skip_count++;
                    continue;
                }

                $emailLower = strtolower($email);

                // In-memory duplicate check (against DB and earlier CSV rows)
                if (isset($existingEmails[$emailLower]) || isset($existingRolls[$roll])) {
                    $skip_count++;
                    continue;
                }

                // Hash password and insert
                try {
                    // Cost parameter (e.g. 10) ensures consistent speed and memory bounds
                    $hashed = password_hash($raw_pass, PASSWORD_BCRYPT, ['cost' => 10]);
                    
                    $insertStmt->execute([
                        $name,
                        $email,
                        $hashed,
                        $roll,
                        $dept,
                        $sem,
                        $phone,
                        $gender,
                        $adminId
                    ]);

                    // Register in lookup maps to catch duplicates within the same CSV
                    $existingEmails[$emailLower] = true;
                    $existingRolls[$roll] = true;

                    $success_count++;
                    $rowsInCurrentBatch++;

                    // Commit in batches
                    if ($rowsInCurrentBatch >= $batchSize) {
                        $pdo->commit();
                        $pdo->beginTransaction();
                        $rowsInCurrentBatch = 0;
                    }
                } catch (PDOException $e) {
                    $skip_count++;
                    log_error("Failed to insert student row $lineIndex ($name)", $e);
                }
            }

            // Commit remaining records
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }

            fclose($handle);

            if (function_exists('log_admin_action')) {
                log_admin_action($pdo, 'import_students', 'student', null, "Batch imported $success_count students ($skip_count skipped)");
            }

            if ($success_count > 0) {
                $message = "Import complete! Successfully added $success_count new students. ($skip_count duplicates or invalid rows skipped).";
            } elseif ($skip_count > 0 && empty($errors)) {
                $errors[] = "No new students were imported. All $skip_count rows were either duplicates or invalid.";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_resource($handle)) {
                fclose($handle);
            }
            log_error("Failed during CSV batch processing", $e);
            $errors[] = "Database error occurred during import: " . $e->getMessage();
        }
    }
}

$page_title = 'Batch Import Students • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>



<div class="container main-content" style="max-width: 850px;">
    <div class="page-header">
        <div>
            <h1>Batch Student Import</h1>
            <p>Upload a CSV file to rapidly enroll hundreds of students at once.</p>
        </div>
        <a href="?download_sample=true" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">download</span> Download Sample CSV
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success" style="display: flex; align-items: center; gap: 8px;">
            <span class="material-symbols-outlined icon-sm">check_circle</span> <?= e($message) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="error-box">
            <strong style="color: #991b1b; display: block; margin-bottom: 8px; font-size: 0.9rem;">Import Issues (<?= count($errors) ?>):</strong>
            <?php foreach ($errors as $err): ?>
                <div><?= e($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Format Guide -->
    <div class="card" style="margin-bottom: 24px;">
        <div class="card-title" style="display: flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm" style="color: var(--color-primary);">info</span> 
            Required CSV Format
        </div>
        
        <div style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 8px;">
            Your CSV file must contain exactly <strong>8 columns</strong> in the following order. The top row (headers) will be automatically skipped.
        </div>

        <div class="csv-guide-grid">
            <div class="csv-guide-item"><strong>1. Name</strong><span>e.g., John Doe</span></div>
            <div class="csv-guide-item"><strong>2. Email</strong><span>Must be unique</span></div>
            <div class="csv-guide-item"><strong>3. Roll Number</strong><span>Must be unique</span></div>
            <div class="csv-guide-item"><strong>4. Department</strong><span>e.g., BCA</span></div>
            <div class="csv-guide-item"><strong>5. Semester</strong><span>Numbers 1-8</span></div>
            <div class="csv-guide-item"><strong>6. Phone Number</strong><span>10 digits</span></div>
            <div class="csv-guide-item"><strong>7. Gender</strong><span>Male / Female</span></div>
            <div class="csv-guide-item"><strong>8. Password</strong><span>Optional. Defaults to Roll No.</span></div>
        </div>
    </div>

    <!-- Import Form -->
    <div class="card">
        <form method="POST" enctype="multipart/form-data">
            <?= csrf_field() ?>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: 600;">Option 1: Upload .CSV File</label>
                <div style="padding: 16px; border: 2px dashed var(--color-border); border-radius: var(--radius-md); background: #fafafa;">
                    <input type="file" name="csv_file" accept=".csv,text/csv" style="width: 100%;">
                </div>
            </div>

            <div style="text-align: center; margin: 16px 0; color: var(--color-text-secondary); font-weight: 600; font-size: 0.9rem;">
                &mdash; OR &mdash;
            </div>

            <div class="form-group">
                <label style="font-weight: 600;">Option 2: Paste CSV Text Directly</label>
                <textarea name="csv_raw" rows="7" class="form-control" style="font-family: var(--font-mono); font-size: 0.85rem;" placeholder="Alex Smith, alex@college.edu, BCA2401, BCA, 4, 9876543210, Male, mypassword123&#10;Maria Garcia, maria@college.edu, BCA2402, BCA, 4, 9876543211, Female,"></textarea>
                <small style="color: var(--color-text-secondary); margin-top: 6px; display: block;">If pasting, ensure you include commas between every value. Leave the last value blank if using the default password.</small>
            </div>

            <div style="border-top: 1px solid var(--color-border); padding-top: 16px; margin-top: 24px; text-align: right;">
                <button type="submit" name="import_csv" class="btn btn-primary" style="padding: 10px 24px; font-size: 0.95rem;">
                    <span class="material-symbols-outlined icon-sm">cloud_upload</span> Process & Import Students
                </button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$student_id = (int) $_SESSION['student_id'];
$message = '';
$error = '';

$stmt = $pdo->prepare("SELECT id FROM profile_requests WHERE student_id = ? AND status = 'pending'");
$stmt->execute([$student_id]);
$has_pending_request = (bool) $stmt->fetchColumn();

// Handle Profile Update request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_update']) && !$has_pending_request) {
    verify_csrf();

    $name = clean_input($_POST['name'] ?? '');
    $roll_number = clean_input($_POST['roll_number'] ?? '');
    $department = clean_input($_POST['department'] ?? '');
    $semester = int_param($_POST['semester'] ?? 0);

    if (empty($name) || empty($roll_number) || empty($department) || $semester < 1 || $semester > 8) {
        $error = "All fields are required and must be valid.";
    } else {
        try {
            // Check if roll number is already used by another student
            $checkStmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ? AND id != ?");
            $checkStmt->execute([$roll_number, $student_id]);

            if ($checkStmt->rowCount() > 0) {
                $error = "This Roll Number is already registered to another student.";
            } else {
                $insertStmt = $pdo->prepare("
                    INSERT INTO profile_requests (student_id, new_name, new_roll_no, new_department, new_semester)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$student_id, $name, $roll_number, $department, $semester]);

                $has_pending_request = true;
                $message = "Update request sent to instructor/admin for approval!";
            }
        } catch (PDOException $e) {
            $error = safe_db_error($e, "Failed to submit request. Please try again.");
        }
    }
}

// Fetch current details
$stmt = $pdo->prepare("SELECT name, email, roll_number, department, semester FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student record not found.");
}

$page_title = 'Edit Profile • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<div class="container" style="max-width: 650px;">
    <div class="card">
        <div class="page-header">
            <div>
                <h1>Edit Student Profile</h1>
                <p>Request changes to your academic information</p>
            </div>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-success"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= e($error) ?></div>
        <?php endif; ?>

        <?php if ($has_pending_request): ?>
            <div class="alert alert-warning">
                <strong>Request Pending:</strong> You have a profile update waiting for instructor approval. You cannot make another change until the current request is reviewed.
            </div>
            <div style="margin-top: 16px;">
                <a href="profile.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-sm">arrow_back</span> Back to My Profile
                </a>
            </div>
        <?php else: ?>
            <form method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label>Email Address (read-only)</label>
                    <input type="email" value="<?= e($student['email']) ?>" readonly style="background-color: var(--color-gray-100); cursor: not-allowed;">
                </div>

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" required value="<?= e($student['name']) ?>">
                </div>

                <div class="form-group">
                    <label>Roll Number / Student ID</label>
                    <input type="text" name="roll_number" required value="<?= e($student['roll_number']) ?>">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Department</label>
                        <select name="department" required>
                            <option value="BCA" <?= $student['department'] === 'BCA' ? 'selected' : '' ?>>BCA</option>
                            <option value="BBA" <?= $student['department'] === 'BBA' ? 'selected' : '' ?>>BBA</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Current Semester</label>
                        <select name="semester" required>
                            <?php for ($i = 1; $i <= 8; $i++): ?>
                                <option value="<?= $i ?>" <?= $student['semester'] == $i ? 'selected' : '' ?>>
                                    Semester <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                </div>

                <div style="display: flex; gap: 12px; margin-top: 24px; flex-wrap: wrap;">
                    <button type="submit" name="request_update" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-outlined icon-sm">send</span> Request Update
                    </button>
                    <a href="profile.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-outlined icon-sm">close</span> Cancel
                    </a>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

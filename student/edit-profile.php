<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/CurriculumService.php';

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
    $roll_number = strtoupper(clean_input($_POST['roll_number'] ?? ''));
    $department = clean_input($_POST['department'] ?? '');
    $semester = int_param($_POST['semester'] ?? 0);
    $gender = strtolower(clean_input($_POST['gender'] ?? ''));

    if (empty($name) || empty($roll_number) || empty($department) || empty($gender) || $semester < 1 || $semester > 8) {
        $error = "All fields are required and must be valid.";
    } elseif (strlen($name) > 100) {
        $error = "Name cannot exceed 100 characters.";
    } elseif (strlen($roll_number) > 50) {
        $error = "Roll number cannot exceed 50 characters.";
    } elseif (!CurriculumService::isValidDepartment($pdo, $department)) {
        $error = "Invalid department selected.";
    } elseif (!in_array($gender, ['male', 'female', 'other'])) {
        $error = "Invalid gender selected.";
    } else {
        try {
            // Check if roll number is already used by another student
            $checkStmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ? AND id != ?");
            $checkStmt->execute([$roll_number, $student_id]);

            if ($checkStmt->rowCount() > 0) {
                $error = "This Roll Number is already registered to another student.";
            } else {
                // Included new_gender in the insert statement
                $insertStmt = $pdo->prepare("
                    INSERT INTO profile_requests (student_id, new_name, new_roll_no, new_department, new_semester, new_gender)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([$student_id, $name, $roll_number, $department, $semester, $gender]);

                $has_pending_request = true;
                $message = "Update request sent to instructor/admin for approval!";
            }
        } catch (PDOException $e) {
            $error = safe_db_error($e, "Failed to submit request. Please try again.");
        }
    }
}

// Fetch current details (Added gender to SELECT)
$stmt = $pdo->prepare("SELECT name, email, roll_number, department, semester, gender FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    die("Student record not found.");
}

$page_title = 'Edit Profile • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<div class="container" style="max-width: 600px;">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <div>
            <h1>Request Profile Change</h1>
            <p>Updates must be reviewed by your course coordinator</p>
        </div>
        <a href="profile.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-xs">arrow_back</span> Back to Profile
        </a>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($has_pending_request): ?>
        <div class="alert alert-warning" style="display: flex; align-items: center; gap: 8px;">
            <span class="material-symbols-outlined icon-sm">hourglass_top</span>
            <div>You have a pending profile change request awaiting administrator review.</div>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="POST">
            <?= csrf_field() ?>

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required value="<?= e($student['name']) ?>" <?= $has_pending_request ? 'disabled' : '' ?>>
            </div>

            <div class="form-group">
                <label>Student Email (Fixed)</label>
                <input type="email" value="<?= e($student['email']) ?>" disabled style="background: var(--color-gray-100); cursor: not-allowed;">
                <small style="color: var(--color-text-secondary);">Email cannot be changed directly.</small>
            </div>

            <div class="form-group">
                <label>Roll Number / Student ID</label>
                <input type="text"
                    name="roll_number"
                    style="text-transform: uppercase;"
                    oninput="this.value = this.value.toUpperCase()"
                    required
                    value="<?= e($student['roll_number']) ?>"
                    <?= $has_pending_request ? 'disabled' : '' ?>>
            </div>

            <div class="form-group">
                <label>Gender</label>
                <select name="gender" required <?= $has_pending_request ? 'disabled' : '' ?>>
                    <option value="">— Select Gender —</option>
                    <option value="male" <?= strtolower($student['gender'] ?? '') === 'male' ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= strtolower($student['gender'] ?? '') === 'female' ? 'selected' : '' ?>>Female</option>
                    <option value="other" <?= strtolower($student['gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other</option>
                </select>
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label>Department</label>
                    <select name="department" required <?= $has_pending_request ? 'disabled' : '' ?>>
                        <?php foreach (CurriculumService::getDepartments($pdo) as $d): ?>
                            <option value="<?= e($d) ?>" <?= $student['department'] === $d ? 'selected' : '' ?>><?= e($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Semester</label>
                    <select name="semester" required <?= $has_pending_request ? 'disabled' : '' ?>>
                        <?php for ($i = 1; $i <= 8; $i++): ?>
                            <option value="<?= $i ?>" <?= (int)$student['semester'] === $i ? 'selected' : '' ?>>
                                Semester <?= $i ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>

            <div style="margin-top: 24px; display: flex; gap: 12px;">
                <?php if (!$has_pending_request): ?>
                    <button type="submit" name="request_update" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-outlined icon-sm">send</span> Submit Change Request
                    </button>
                <?php endif; ?>
                <a href="profile.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

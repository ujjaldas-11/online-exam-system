<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/csrf.php';
require_once __DIR__ . '/../utils/auth.php';
require_once __DIR__ . '/../utils/logger.php';
require_once __DIR__ . '/../utils/sanitize.php';

init_secure_session();

if (is_student_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name = clean_input($_POST['name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $roll = strtoupper(clean_input($_POST['roll_number'] ?? ''));
    $dept = clean_input($_POST['department'] ?? '');
    $phone = clean_input($_POST['phone_number'] ?? '');
    $gender = clean_input($_POST['gender'] ?? '');
    $pass = $_POST['password'] ?? '';
    $cpass = $_POST['confirm_password'] ?? '';
    $sem = int_param($_POST['semester'] ?? 0);

    if (!$name || !$email || !$pass || !$roll || !$sem || !$dept || !$phone || !$gender) {
        $error = "All fields are required.";
    } elseif (strlen($name) > 100) {
        $error = "Name cannot exceed 100 characters.";
    } elseif (strlen($email) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email address format.";
    } elseif (strlen($roll) > 50) {
        $error = "Roll number cannot exceed 50 characters.";
    } elseif (!in_array($dept, ['BCA', 'BBA'], true)) {
        $error = "Invalid department selected.";
    } elseif (!in_array($gender, ['male', 'female', 'others'], true)) {
        $error = "Invalid gender selected.";
    } elseif ($pass !== $cpass) {
        $error = "Passwords do not match.";
    } elseif (strlen($pass) < 6) {
        $error = "Password must be at least 6 characters.";
    } elseif (!preg_match('/^[0-9]{10}$/', $phone)) {
        $error = "Phone number must be exactly 10 numeric digits.";
    } elseif ($sem < 1 || $sem > 8) {
        $error = "Semester must be between 1 and 8.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, status FROM students WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $existingEmail = $stmt->fetch();

            if ($existingEmail) {
                if ($existingEmail['status'] === 'pending') {
                    $error = "An account with this email is already registered and awaiting administrator approval.";
                } else {
                    $error = "An account with this email already exists. Please log in.";
                }
            } else {
                $stmt = $pdo->prepare("SELECT id, status FROM students WHERE roll_number = ? LIMIT 1");
                $stmt->execute([$roll]);
                $existingRoll = $stmt->fetch();

                if ($existingRoll) {
                    $error = "Roll number is already registered.";
                } else {
                    $hashed = password_hash($pass, PASSWORD_DEFAULT);

                    $ins = $pdo->prepare("
                        INSERT INTO students (name, email, password, roll_number, department, semester, phone_number, gender, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')
                    ");
                    $ins->execute([$name, $email, $hashed, $roll, $dept, $sem, $phone, $gender]);

                    $success = "Registration submitted successfully! Your account is pending instructor approval. Once approved, you can log in.";
                    $_POST = [];
                }
            }
        } catch (PDOException $e) {
            $error = safe_db_error($e, "Registration failed. Please check your information.");
        }
    }
}

$page_title = 'Student Registration • Examify';
$body_class = 'auth-body';
include __DIR__ . '/../components/header.php';
?>

<?php if ($success): ?>
    <div class="auth-card" style="max-width: 520px; text-align: center; padding: 40px 28px;">
        <div style="display: inline-flex; align-items: center; justify-content: center; width: 72px; height: 72px; border-radius: 50%; background: rgba(16, 185, 129, 0.12); color: #10b981; margin-bottom: 20px;">
            <span class="material-symbols-outlined" style="font-size: 42px;">verified</span>
        </div>

        <h1 style="font-size: 1.5rem; font-weight: 800; color: var(--color-dark); margin-bottom: 8px;">
            Registration Submitted!
        </h1>

        <p style="color: var(--color-text-secondary); font-size: 0.95rem; line-height: 1.6; margin-bottom: 24px;">
            <?= e($success) ?>
        </p>

        <!-- 30-Second Animated Timeout Progress Bar -->
        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 18px 20px; margin-bottom: 24px; text-align: left;">
            <div style="display: flex; align-items: center; gap: 6px; font-size: 0.88rem; color: #64748b; margin-bottom: 10px;">
                <span class="material-symbols-outlined icon-xs" style="color: #10b981;">schedule</span>
                <span>Redirecting to homepage...</span>
            </div>

            <!-- Animated Timeout Bar -->
            <div style="background: #e2e8f0; border-radius: 9999px; height: 8px; overflow: hidden; width: 100%;">
                <div id="timeout-bar" style="background: linear-gradient(90deg, #10b981, #059669); height: 100%; width: 100%; border-radius: 9999px; transition: width 1s linear, background-color 0.5s ease;"></div>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="../index.php" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 12px; font-weight: 700;">
                <span class="material-symbols-outlined icon-sm">home</span> Return to Homepage Now
            </a>
            <a href="login.php" class="btn btn-secondary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; padding: 10px; font-size: 0.88rem;">
                <span class="material-symbols-outlined icon-xs">login</span> Go to Login
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let secondsLeft = 30;
            const totalSeconds = 30;
            const barEl = document.getElementById('timeout-bar');

            const timer = setInterval(() => {
                secondsLeft--;

                if (barEl) {
                    const percent = Math.max(0, (secondsLeft / totalSeconds) * 100);
                    barEl.style.width = percent + '%';
                    if (percent <= 20) {
                        barEl.style.background = '#ef4444'; // Red near end
                    } else if (percent <= 50) {
                        barEl.style.background = '#f59e0b'; // Amber midway
                    }
                }

                if (secondsLeft <= 0) {
                    clearInterval(timer);
                    window.location.href = '../index.php';
                }
            }, 1000);
        });
    </script>
<?php else: ?>
<div class="auth-card auth-card-wide">
    <div class="auth-header">
        <img src="../assets/images/examify_logo.png" alt="Examify Logo" class="auth-logo">
        <div class="auth-header-text">
            <h1>Create Account</h1>
            <p class="subtitle">Request registration for classroom examinations</p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?= csrf_field() ?>

        <div class="auth-row-2">
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required value="<?= e($_POST['name'] ?? '') ?>" placeholder="e.g. Tulasi Benjamin Khan">
            </div>

            <div class="form-group">
                <label>Student Email</label>
                <input type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>" placeholder="student@college.edu">
            </div>
        </div>

        <div class="auth-row-2">
            <div class="form-group">
                <label>Roll Number / Student ID</label>
                <input type="text"
                    name="roll_number"
                    id="roll_number"
                    required
                    style="text-transform: uppercase;"
                    oninput="this.value = this.value.toUpperCase()"
                    value="<?= e($_POST['roll_number'] ?? '') ?>"
                    placeholder="e.g. B26BCA01">
            </div>

            <div class="form-group">
                <label>Phone Number</label>
                <input type="text"
                    name="phone_number"
                    inputmode="numeric"
                    pattern="[0-9]{10}"
                    maxlength="10"
                    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"
                    value="<?= e($_POST['phone_number'] ?? '') ?>"
                    placeholder="10 digit number"
                    required
                >
            </div>
        </div>

        <div class="auth-row-3">
            <div class="form-group">
                <label>Department</label>
                <select name="department" required>
                    <option value="">Select Department</option>
                    <option value="BCA" <?= (($_POST['department'] ?? '') === 'BCA') ? 'selected' : '' ?>>BCA</option>
                    <option value="BBA" <?= (($_POST['department'] ?? '') === 'BBA') ? 'selected' : '' ?>>BBA</option>
                </select>
            </div>

            <div class="form-group">
                <label>Semester</label>
                <select name="semester" required>
                    <option value="">Select Semester</option>
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>" <?= (($_POST['semester'] ?? '') == $i) ? 'selected' : '' ?>>
                            Semester <?= $i ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Gender</label>
                <select name="gender" required>
                    <option value="">Select Gender</option>
                    <option value="male" <?= (($_POST['gender'] ?? '') === 'male') ? 'selected' : '' ?>>Male</option>
                    <option value="female" <?= (($_POST['gender'] ?? '') === 'female') ? 'selected' : '' ?>>Female</option>
                    <option value="others" <?= (($_POST['gender'] ?? '') === 'others') ? 'selected' : '' ?>>Others</option>
                </select>
            </div>
        </div>

        <div class="auth-row-2">
            <div class="form-group">
                <label>Password</label>
                <div class="password-wrapper">
                    <input type="password" name="password" required placeholder="Min 6 characters">
                    <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password">
                        <span class="material-symbols-outlined">visibility</span>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label>Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" name="confirm_password" required placeholder="Re-enter password">
                    <button type="button" class="password-toggle-btn" aria-label="Show password" title="Show password">
                        <span class="material-symbols-outlined">visibility</span>
                    </button>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">person_add</span> Request Registration
        </button>
    </form>

    <p class="footer">
        Already have an account? <a href="login.php">Login here</a>
    </p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/../components/footer.php'; ?>

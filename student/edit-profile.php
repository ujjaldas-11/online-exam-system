<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];
$message = '';
$error = '';

// Handle Profile Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name        = trim(strip_tags($_POST['name'] ?? ''));
    $roll_number = trim(strip_tags($_POST['roll_number'] ?? ''));
    $department  = trim(strip_tags($_POST['department'] ?? ''));
    $semester    = (int)($_POST['semester'] ?? 0);

    if (empty($name) || empty($roll_number) || empty($department) || $semester < 1 || $semester > 8) {
        $error = "All fields are required and must be valid.";
    } else {
        // Check if roll number is already used by another student
        $checkStmt = $pdo->prepare("SELECT id FROM students WHERE roll_number = ? AND id != ?");
        $checkStmt->execute([$roll_number, $student_id]);

        if ($checkStmt->rowCount() > 0) {
            $error = "This Roll Number is already registered to another student.";
        } else {
            $updateStmt = $pdo->prepare("
                UPDATE students 
                SET name = ?, roll_number = ?, department = ?, semester = ? 
                WHERE id = ?
            ");

            if ($updateStmt->execute([$name, $roll_number, $department, $semester, $student_id])) {
                $message = "Profile updated successfully!";

                // Update session so dashboard reflects changes immediately
                $_SESSION['student_name'] = $name;
                $_SESSION['department']   = $department;
                $_SESSION['semester']     = $semester;
            } else {
                $error = "Failed to update profile. Please try again.";
            }
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile • Examify</title>
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --success: #16a34a;
            --error: #dc2626;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.5;
        }

        .container {
            max-width: 560px;
            margin: 0 auto;
            padding: 32px 20px;
        }
        h1 {
            font-size: 1.6rem;
            margin-bottom: 4px;
        }
        .subtitle {
            color: var(--gray);
            margin-bottom: 24px;
        }

        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 28px 24px;
        }

        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .alert.success {
            background: #dcfce7;
            color: var(--success);
        }
        .alert.error {
            background: #fee2e2;
            color: var(--error);
        }

        .form-group {
            margin-bottom: 16px;
        }
        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
            color: #334155;
        }
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
            background: white;
        }
        input:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        input[readonly] {
            background: #f1f5f9;
            color: var(--gray);
            cursor: not-allowed;
        }

        .btn {
            width: 100%;
            padding: 12px;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            margin-top: 8px;
        }
        .btn:hover { background: #1d4ed8; }

        .btn-secondary {
            display: block;
            width: 100%;
            text-align: center;
            margin-top: 12px;
            padding: 11px;
            background: #e2e8f0;
            color: #334155;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
        }
        .btn-secondary:hover {
            background: #cbd5e1;
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
    <h1>Edit Profile</h1>
    <p class="subtitle">Update your personal information</p>

    <div class="card">
        <?php if ($message): ?>
            <div class="alert success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email Address (cannot be changed)</label>
                <input type="email" value="<?= htmlspecialchars($student['email']) ?>" readonly>
            </div>

            <div class="form-group">
                <label>Full Name</label>
                <input type="text" name="name" required 
                       value="<?= htmlspecialchars($student['name']) ?>">
            </div>

            <div class="form-group">
                <label>Roll Number</label>
                <input type="text" name="roll_number" required 
                       value="<?= htmlspecialchars($student['roll_number']) ?>">
            </div>

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

            <button type="submit" name="update_profile" class="btn">Save Changes</button>
            <a href="profile.php" class="btn-secondary">Cancel & Go Back</a>
        </form>
    </div>
</div>

</body>
</html>
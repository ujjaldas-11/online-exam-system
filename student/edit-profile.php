<?php
require_once 'student-guard.php';
require_once '../config/database.php';

$student_id = $_SESSION['student_id'];
$message = '';
$error = '';

$stmt = $pdo->prepare("
    SELECT id
    FROM profile_requests
    WHERE student_id = ? AND status = 'pending'
");
$stmt->execute([$student_id]);
$has_pending_request = $stmt->fetchColumn();

// Handle Profile Update request
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['request_update']) &&
    !$has_pending_request
) {
    $name = trim(strip_tags($_POST['name'] ?? ''));
    $roll_number = trim(strip_tags($_POST['roll_number'] ?? ''));
    $department = trim(strip_tags($_POST['department'] ?? ''));
    $semester = (int)($_POST['semester'] ?? 0);

    if (
        empty($name) ||
        empty($roll_number) ||
        empty($department) ||
        $semester < 1 ||
        $semester > 8
    ) {
        $error = "All fields are required and must be valid.";
    } else {
        // Check if roll number is already used by another student
        $checkStmt = $pdo->prepare("
            SELECT id
            FROM students
            WHERE roll_number = ? AND id != ?
        ");
        $checkStmt->execute([$roll_number, $student_id]);

        if ($checkStmt->rowCount() > 0) {
            $error = "This Roll Number is already registered to another student.";
        } else {
            $insertStmt = $pdo->prepare("
                INSERT INTO profile_requests
                (student_id, new_name, new_roll_no, new_department, new_semester)
                VALUES (?, ?, ?, ?, ?)
            ");

            $insertStmt->execute([
                $student_id,
                $name,
                $roll_number,
                $department,
                $semester
            ]);

            $has_pending_request = true;
            $message = "Update request sent to admin for approval!";
        }
    }
}

// Fetch current details
$stmt = $pdo->prepare("
    SELECT name, email, roll_number, department, semester
    FROM students
    WHERE id = ?
");
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
            --blue: #2563eb;
            --blue-hover: #1d4ed8;
            --bg: #d5a979;
            --card: #faf4fa;
            --text: #171717;
            --gray: #64748b;
            --border: #dbe1eb;

            --success-bg: #dcfce7;
            --success-text: #166534;

            --error-bg: #fee2e2;
            --error-text: #991b1b;

            --notice-bg: #fef3c7;
            --notice-text: #92400e;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.5;
        }

        .container {
            max-width: 650px;
            margin: 40px auto;
            padding: 32px;
            background: var(--card);
            border-radius: 12px;
        }

        .header {
            margin-bottom: 28px;
        }

        h1 {
            font-size: 1.6rem;
            margin-bottom: 3px;
        }

        .subtitle {
            color: var(--gray);
            font-size: .9rem;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            color: #334155;
            font-size: .85rem;
            font-weight: 500;
            margin-bottom: 6px;
        }

        input,
        select {
            width: 100%;
            padding: 11px 12px;

            background: white;
            border: 1px solid var(--border);
            border-radius: 8px;

            color: var(--text);
            font-family: inherit;
            font-size: .9rem;

            outline: none;
        }

        input:focus,
        select:focus {
            border-color: var(--blue);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, .1);
        }

        input[readonly] {
            background: #f1f5f9;
            color: var(--gray);
            cursor: not-allowed;
        }

        .btn {
            display: inline-block;

            background: var(--blue);
            color: white;

            padding: 10px 16px;

            border: 0;
            border-radius: 8px;

            text-decoration: none;

            font-family: inherit;
            font-size: .9rem;
            font-weight: 600;

            cursor: pointer;
        }

        .btn:hover {
            background: var(--blue-hover);
        }

        .btn-secondary {
            display: inline-block;

            margin-left: 8px;
            padding: 9px 15px;

            background: white;
            color: #475569;

            border: 1px solid var(--border);
            border-radius: 8px;

            text-decoration: none;

            font-size: .9rem;
            font-weight: 500;
        }

        .btn-secondary:hover {
            background: #f8fafc;
        }

        .alert {
            padding: 11px 13px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: .88rem;
        }

        .alert.success {
            background: var(--success-bg);
            color: var(--success-text);
            border: 1px solid #bbf7d0;
        }

        .alert.error {
            background: var(--error-bg);
            color: var(--error-text);
            border: 1px solid #fecaca;
        }

        .notice {
            padding: 14px;
            border-radius: 8px;

            background: var(--notice-bg);
            color: var(--notice-text);

            border: 1px solid #fde68a;

            font-size: .9rem;
            line-height: 1.5;
        }

        .notice strong {
            font-weight: 600;
        }

        .form-actions {
            margin-top: 24px;
        }

        @media (max-width: 640px) {
            .container {
                margin: 20px 12px;
                padding: 22px 18px;
            }

            h1 {
                font-size: 1.4rem;
            }

            .form-actions {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .btn,
            .btn-secondary {
                width: 100%;
                margin-left: 0;
                text-align: center;
            }
        }
    </style>
</head>

<body>

<?php include '../components/navbar.php'; ?>

<div class="container">

    <div class="header">
        <h1>Edit Profile</h1>
        <p class="subtitle">
            Update your personal information
        </p>
    </div>


    <?php if ($message): ?>
        <div class="alert success">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>


    <?php if ($error): ?>
        <div class="alert error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>


    <?php if ($has_pending_request): ?>

        <div class="notice">
            <strong>Request Pending:</strong>
            You have a profile update waiting for admin approval.
            You cannot make another change until the current request is reviewed.
        </div>

    <?php else: ?>

        <form method="POST">

            <div class="form-group">
                <label>Email Address (cannot be changed)</label>

                <input
                    type="email"
                    value="<?= htmlspecialchars($student['email']) ?>"
                    readonly
                >
            </div>


            <div class="form-group">
                <label>Full Name</label>

                <input
                    type="text"
                    name="name"
                    required
                    value="<?= htmlspecialchars($student['name']) ?>"
                >
            </div>


            <div class="form-group">
                <label>Roll Number</label>

                <input
                    type="text"
                    name="roll_number"
                    required
                    value="<?= htmlspecialchars($student['roll_number']) ?>"
                >
            </div>


            <div class="form-group">
                <label>Department</label>

                <select name="department" required>

                    <option
                        value="BCA"
                        <?= $student['department'] === 'BCA' ? 'selected' : '' ?>
                    >
                        BCA
                    </option>

                    <option
                        value="BBA"
                        <?= $student['department'] === 'BBA' ? 'selected' : '' ?>
                    >
                        BBA
                    </option>

                </select>
            </div>


            <div class="form-group">
                <label>Current Semester</label>

                <select name="semester" required>

                    <?php for ($i = 1; $i <= 8; $i++): ?>

                        <option
                            value="<?= $i ?>"
                            <?= $student['semester'] == $i ? 'selected' : '' ?>
                        >
                            Semester <?= $i ?>
                        </option>

                    <?php endfor; ?>

                </select>
            </div>


            <div class="form-actions">

                <button
                    type="submit"
                    name="request_update"
                    class="btn"
                >
                    Request to Update
                </button>

                <a
                    href="profile.php"
                    class="btn-secondary"
                >
                    Cancel & Go Back
                </a>

            </div>

        </form>

    <?php endif; ?>

</div>

</body>
</html>
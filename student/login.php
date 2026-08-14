<?php
session_start();
require_once '../config/database.php';

if (isset($_SESSION['student_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM students WHERE email = ?");
            $stmt->execute([$email]);
            $student = $stmt->fetch();

            if ($student && password_verify($password, $student['password'])) {
                $_SESSION['student_id']   = $student['id'];
                $_SESSION['student_name'] = $student['name'];
                $_SESSION['roll_number']  = $student['roll_number'];
                $_SESSION['semester']     = $student['semester'];
                $_SESSION['department']   = $student['department'];

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid email or password.";
            }
        } catch (PDOException $e) {
            $error = "Something went wrong. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Login • Examify</title>
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --error: #dc2626;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }
        .card {
            background: white;
            width: 100%;
            max-width: 400px;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 32px 28px;
        }
        h1 {
            font-size: 1.5rem;
            text-align: center;
            margin-bottom: 6px;
        }
        .subtitle {
            text-align: center;
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 24px;
        }
        .form-group { margin-bottom: 16px; }
        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 500;
            margin-bottom: 5px;
            color: #334155;
        }
        input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 0.95rem;
        }
        input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
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
        .alert {
            padding: 11px 14px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 0.9rem;
            text-align: center;
            font-weight: 500;
            background: #fee2e2;
            color: var(--error);
        }
        .footer {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
            color: var(--gray);
        }
        .footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="card">
        <h1>Student Login</h1>
        <p class="subtitle">Sign in to your account</p>

        <?php if ($error): ?>
            <div class="alert"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit" class="btn">Login</button>
        </form>

        <p class="footer">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </div>
</body>
</html>
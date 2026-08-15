<?php
session_start();
require_once '../config/database.php';

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_id = $_SESSION['student_id'];

try {
    // Fetch student details
    $stmt = $pdo->prepare("SELECT name, email, roll_number, department, semester FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        die("Student record not found.");
    }

    // Fetch exam history
    $resultStmt = $pdo->prepare("
        SELECT e.title, ea.score, ea.total_questions, ea.submitted_at 
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.student_id = ? AND ea.status = 'completed'
        ORDER BY ea.submitted_at DESC
    ");
    $resultStmt->execute([$student_id]);
    $past_results = $resultStmt->fetchAll();

} catch (PDOException $e) {
    die("Database Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile • Examify</title>
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --success: #16a34a;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background: var(--light);
            color: var(--dark);
            line-height: 1.5;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
            padding: 32px 20px;
        }
        h1 {
            font-size: 1.6rem;
            margin-bottom: 4px;
        }
        .subtitle {
            color: var(--gray);
            margin-bottom: 28px;
        }

        .card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }
        .card-header h2 {
            font-size: 1.15rem;
        }

        .btn {
            display: inline-block;
            background: var(--primary);
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .btn:hover { background: #1d4ed8; }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        .info-box {
            background: #f8fafc;
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 16px;
        }
        .info-label {
            font-size: 0.85rem;
            color: var(--gray);
            margin-bottom: 4px;
        }
        .info-value {
            font-size: 1.05rem;
            font-weight: 600;
        }

        .table-wrap { overflow-x: auto; }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        th, td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        th {
            background: #f1f5f9;
            font-weight: 600;
            color: #475569;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        tr:hover td { background: #f8fafc; }

        .score {
            font-weight: 700;
            color: var(--primary);
        }

        .empty {
            text-align: center;
            padding: 30px;
            color: var(--gray);
        }

        @media (max-width: 640px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
    <h1>My Profile</h1>
    <p class="subtitle">Your personal details and exam history</p>

    <!-- Profile Info -->
    <div class="card">
        <div class="card-header">
            <h2>Personal Information</h2>
            <a href="edit-profile.php" class="btn">Edit Profile</a>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <div class="info-label">Full Name</div>
                <div class="info-value"><?= htmlspecialchars($student['name']) ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Email Address</div>
                <div class="info-value"><?= htmlspecialchars($student['email']) ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Roll Number</div>
                <div class="info-value"><?= htmlspecialchars($student['roll_number']) ?></div>
            </div>
            <div class="info-box">
                <div class="info-label">Department & Semester</div>
                <div class="info-value">
                    <?= htmlspecialchars($student['department']) ?> • Semester <?= htmlspecialchars($student['semester']) ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Exam History -->
    <div class="card">
        <div class="card-header">
            <h2>Exam History</h2>
        </div>

        <?php if (empty($past_results)): ?>
            <div class="empty">You haven’t completed any exams yet.</div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Exam Title</th>
                            <th>Score</th>
                            <th>Submitted On</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past_results as $result): ?>
                            <tr>
                                <td><?= htmlspecialchars($result['title']) ?></td>
                                <td>
                                    <span class="score">
                                        <?= $result['score'] ?> / <?= $result['total_questions'] ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $result['submitted_at'] 
                                        ? date('d M Y, h:i A', strtotime($result['submitted_at'])) 
                                        : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

</body>
</html>
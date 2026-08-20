<?php
require_once 'student-guard.php';
require_once '../config/database.php';

$student_id = $_SESSION['student_id'];

try {
    $stmt = $pdo->prepare("
        SELECT name, email, roll_number, department, semester
        FROM students WHERE id = ?
    ");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        die("Student record not found.");
    }

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
            --blue: #2563eb;
            --blue-hover: #1d4ed8;
            --bg: #f5eadf;
            --card: #faf4fa;
            --text: #171717;
            --gray: #64748b;
            --border: #dbe1eb;
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
            max-width: 900px;
            margin: 40px auto;
            padding: 32px;
            background: var(--card);
            border-radius: 12px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
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

        .btn {
            background: var(--blue);
            color: white;
            padding: 9px 16px;
            border: 0;
            border-radius: 8px;
            text-decoration: none;
            font-size: .9rem;
            font-weight: 600;
        }

        .btn:hover {
            background: var(--blue-hover);
        }

        .section {
            margin-top: 26px;
        }

        .section-title {
            font-size: 1.1rem;
            margin-bottom: 14px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }

        .info {
            padding: 15px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        .label {
            color: var(--gray);
            font-size: .78rem;
            margin-bottom: 3px;
        }

        .value {
            font-size: .95rem;
            font-weight: 600;
            word-break: break-word;
        }

        .table-wrap {
            overflow-x: auto;
            background: white;
            border: 1px solid var(--border);
            border-radius: 9px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .9rem;
            min-width: 550px;
        }

        th,
        td {
            padding: 12px 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: .78rem;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        tr:hover td {
            background: #fafafa;
        }

        .score {
            color: var(--blue);
            font-weight: 700;
        }

        .empty {
            background: white;
            border: 1px solid var(--border);
            border-radius: 9px;
            padding: 28px;
            text-align: center;
            color: var(--gray);
            font-size: .9rem;
        }

        @media (max-width: 640px) {
            .container {
                margin: 20px 12px;
                padding: 22px 18px;
            }

            .header {
                align-items: flex-start;
                flex-direction: column;
                gap: 14px;
            }

            .btn {
                width: 100%;
                text-align: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            h1 {
                font-size: 1.4rem;
            }
        }
    </style>
</head>

<body>

    <?php include '../components/navbar.php'; ?>

    <div class="container">

        <div class="header">
            <div>
                <h1>My Profile</h1>
                <p class="subtitle">Your personal details and exam history</p>
            </div>

            <a href="edit-profile.php" class="btn">Edit Profile</a>
        </div>


        <!-- Personal Information -->

        <div class="section">

            <h2 class="section-title">Personal Information</h2>

            <div class="info-grid">

                <div class="info">
                    <div class="label">Full Name</div>
                    <div class="value">
                        <?= htmlspecialchars($student['name']) ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">Email Address</div>
                    <div class="value">
                        <?= htmlspecialchars($student['email']) ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">Roll Number</div>
                    <div class="value">
                        <?= htmlspecialchars($student['roll_number']) ?>
                    </div>
                </div>

                <div class="info">
                    <div class="label">Department & Semester</div>
                    <div class="value">
                        <?= htmlspecialchars($student['department']) ?>
                        • Semester <?= htmlspecialchars($student['semester']) ?>
                    </div>
                </div>

            </div>

        </div>


        <!-- Exam History -->

        <div class="section">

            <h2 class="section-title">Exam History</h2>

            <?php if (empty($past_results)): ?>

                <div class="empty">
                    You haven't completed any exams yet.
                </div>

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

                                    <td>
                                        <?= htmlspecialchars($result['title']) ?>
                                    </td>

                                    <td>
                                        <span class="score">
                                            <?= htmlspecialchars($result['score']) ?>
                                            /
                                            <?= htmlspecialchars($result['total_questions']) ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?= $result['submitted_at']
                                            ? date(
                                                'd M Y, h:i A',
                                                strtotime($result['submitted_at'])
                                            )
                                            : '—'
                                            ?>
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

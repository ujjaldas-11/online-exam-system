<?php
session_start();
require_once '../config/database.php';

date_default_timezone_set('Asia/Kolkata');

if (!isset($_SESSION['student_id'])) {
    header("Location: login.php");
    exit();
}

$student_name = $_SESSION['student_name'];
$semester     = $_SESSION['semester'];
$department   = $_SESSION['department'];

try {
    // Get both active and scheduled exams for this student
    $sql = "SELECT 
                e.id, 
                e.title, 
                e.description, 
                e.duration_minutes, 
                e.total_marks, 
                e.total_questions_to_ask,
                e.status,
                e.start_time,
                s.name AS subject_name, 
                ea.id AS attempt_id, 
                ea.score, 
                ea.total_questions
            FROM exams e
            JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN exam_attempts ea 
                ON e.id = ea.exam_id AND ea.student_id = :student_id
            WHERE s.department = :department 
              AND s.semester = :semester 
              AND e.status IN ('active', 'scheduled', 'ended')
            ORDER BY 
                FIELD(e.status, 'active', 'scheduled', 'ended'),
                e.start_time DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':semester'   => $semester,
        ':department' => $department,
        ':student_id' => $_SESSION['student_id']
    ]);
    
    $available_exams = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard • Examify</title>
    <style>
        :root {
            --primary: #2563eb;
            --dark: #0f172a;
            --gray: #64748b;
            --light: #f8fafc;
            --border: #e2e8f0;
            --success: #16a34a;
            --warning: #d97706;
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

        .exam-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }
        .exam-card {
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 22px 24px;
        }
        .exam-card h3 {
            font-size: 1.2rem;
            margin-bottom: 6px;
        }
        .exam-card .desc {
            color: var(--gray);
            font-size: 0.95rem;
            margin-bottom: 14px;
        }
        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 12px 20px;
            font-size: 0.9rem;
            color: #475569;
            margin-bottom: 18px;
        }
        .meta span {
            font-weight: bold;
            align-items: center;
            gap: 5px;
        }

        .btn {
            display: inline-block;
            padding: 10px 18px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            color: white;
            background: var(--primary);
        }
        .btn:hover { background: #1d4ed8; }
        .btn-resume {
            background: var(--warning);
        }
        .btn-resume:hover { background: #b45309; }

        .status-box {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .status-box.completed {
            background: #dcfce7;
            color: var(--success);
        }
        .status-box.scheduled {
            background: #fef3c7;
            color: var(--warning);
        }
        .status-box.ended {
            background: #f1f5f9;
            color: #64748b;
        }

        .empty {
            text-align: center;
            padding: 50px 20px;
            background: white;
            border: 1px solid var(--border);
            border-radius: 12px;
            color: var(--gray);
        }

        @media (max-width: 640px) {
            .meta { gap: 8px 14px; }
        }
    </style>
</head>
<body>

<?php include 'navbar.php'; ?>

<div class="container">
    <h1>Available Exams</h1>
    <p class="subtitle">Exams for your department & semester</p>

    <?php if (empty($available_exams)): ?>
        <div class="empty">
            No exams available for you at the moment.
        </div>
    <?php else: ?>
        <div class="exam-list">
            <?php foreach ($available_exams as $exam): ?>
                <?php
                    $is_completed = !empty($exam['attempt_id']);
                    $is_ongoing   = isset($_SESSION['exam_answers'][$exam['id']]);
                    $status       = $exam['status'];
                ?>
                <div class="exam-card">
                    <h3><?= htmlspecialchars($exam['title']) ?></h3>
                    
                    <?php if (!empty($exam['description'])): ?>
                        <p class="desc"><?= htmlspecialchars($exam['description']) ?></p>
                    <?php endif; ?>

                    <div class="meta">
                        <p><span>Subject: </span><?= htmlspecialchars($exam['subject_name']) ?></p>
                        <p><span>Time: </span><?= $exam['duration_minutes'] ?> mins</p>
                        <p><span>Questions: </span><?= $exam['total_questions_to_ask'] ?> questions</p>
                        <p><span>Marks: </span><?= $exam['total_marks'] ?> marks</p>
                    </div>

                    <?php if ($is_completed): ?>
                        <div class="status-box completed">
                            Completed — Score: <?= $exam['score'] ?> / <?= $exam['total_questions'] ?? $exam['total_marks'] ?>
                        </div>

                    <?php elseif ($status === 'scheduled'): ?>
                        <div class="status-box scheduled">
                            Starts on <?= date('d M Y, h:i A', strtotime($exam['start_time'])) ?>
                        </div>

                    <?php elseif ($status === 'ended'): ?>
                        <div class="status-box ended">
                            Exam has ended
                        </div>

                    <?php elseif ($is_ongoing): ?>
                        <a href="exam.php?id=<?= $exam['id'] ?>" class="btn btn-resume">Resume Exam</a>

                    <?php else: ?>
                        <a href="exam.php?id=<?= $exam['id'] ?>" class="btn">Start Exam</a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>
<?php
require_once 'student-guard.php';
require_once '../config/database.php';

date_default_timezone_set('Asia/Kolkata');


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
    <link rel="stylesheet" href="../assets/css/student.css">
</head>
<body>

<?php include '../components/navbar.php'; ?>

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
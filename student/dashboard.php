<?php
require_once 'student-guard.php';
require_once '../config/database.php';

date_default_timezone_set('Asia/Kolkata');


$student_name = $_SESSION['student_name'];
$semester     = $_SESSION['semester'];
$department   = $_SESSION['department'];

try {
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

<?php
    $filtered_exams = [];
    $active_count = 0;

    foreach ($available_exams as $exam) {
        if ($exam['status'] === 'active') {
            $start_timestamp = strtotime($exam['start_time']);
            $duration_seconds = $exam['duration_minutes'] * 60;
            $end_timestamp = $start_timestamp + $duration_seconds;
            
            if (time() >= $end_timestamp) {
                continue; 
            }
            $active_count++; 
        }
        $filtered_exams[] = $exam; 
    }
?>

<div class="container">
    <h1>Available Exams</h1>
    <p class="subtitle">Exams for your department & semester</p>

    <?php if (empty($filtered_exams)): ?>
        
        <div class="empty" id="empty-state" style="text-align: center; padding: 50px 20px; background: white; border-radius: 12px; border: 1px dashed var(--border); box-shadow: 0 4px 6px rgba(0,0,0,0.02);">
            <h3 style="color: var(--gray); margin-bottom: 15px;">No exams scheduled right now</h3>
            <p id="funny-quote" style="font-size: 1.8rem; font-style: italic; font-weight: 500; color: #334155;">
                <!-- JavaScript will inject the quote here -->
            </p>
        </div>

    <?php else: ?>
        <div class="exam-list">
            <?php foreach ($filtered_exams as $exam): ?>
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



<!-- MAGIC RELOAD & FUNNY QUOTES SCRIPTS -->
<script>
    <?php if (empty($filtered_exams)): ?>
    document.addEventListener("DOMContentLoaded", async function() {
        
       try {
        const response = await fetch('../funny_quotes.json');

        if(!response.ok) {
            throw new Error('Network response was not ok');
        }

        const data = await response.json();
        const quotesArray = data.quotes;

        console.log("Full Data:", data);
        console.log("First Item:", quotesArray[0]);


        if(quotesArray && quotesArray.length > 0) {
            const randomQuote = quotesArray[Math.floor(Math.random() * quotesArray.length)];

            const quoteText = randomQuote.quote;

            // console.log(quoteText.length)

            const target = document.getElementById('funny-quote');
                if (target) {
                    target.innerText = '"' + quoteText + '"';
                } else {
                    console.error('Element #funny-quote not found!');
                }

        } 

    } catch (e) {
        console.error('Failed to fetch quotes:', e);
    }


    });
    <?php endif; ?>

    let currentExamCount = <?= $active_count ?>;

    setInterval(async function() {
        try {
            const response = await fetch('check-exams.php');
            const data = await response.json();

            if (data.active_exams > currentExamCount) {
                window.location.reload();
            }
        } catch (error) {
            console.error("Auto-refresh ping failed.");
        }
    }, 10000); 
</script>

</body>
</html>
<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

date_default_timezone_set('Asia/Kolkata');

$student_name = $_SESSION['student_name'];
$semester = (int) $_SESSION['semester'];
$department = (string) $_SESSION['department'];
$student_id = (int) $_SESSION['student_id'];

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
        ':semester' => $semester,
        ':department' => $department,
        ':student_id' => $student_id,
    ]);

    $available_exams = $stmt->fetchAll();

} catch (PDOException $e) {
    log_error("Dashboard loading error for student $student_id", $e);
    die("Database error. Please try again later.");
}

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

$page_title = 'Student Dashboard • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/navbar.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1>Available Examinations</h1>
            <p>Department: <strong><?= e($department) ?></strong> • Semester: <strong><?= e((string)$semester) ?></strong></p>
        </div>
    </div>

    <?php if (empty($filtered_exams)): ?>
        <div class="card" id="empty-state" style="text-align: center; padding: 48px 24px; border: 2px dashed var(--color-border);">
            <h3 style="color: var(--color-text-secondary); margin-bottom: 16px;">No exams scheduled right now</h3>
            <p id="funny-quote" style="font-size: 1.4rem; font-style: italic; font-weight: 500; color: var(--color-dark); max-width: 600px; margin: 0 auto;">
                "Stay ready for surprise tests!"
            </p>
        </div>
    <?php else: ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
            <?php foreach ($filtered_exams as $exam): ?>
                <?php
                $is_completed = !empty($exam['attempt_id']);
                $is_ongoing = isset($_SESSION['exam_answers'][$exam['id']]);
                $status = $exam['status'];
                ?>

                <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 10px;">
                            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-dark);"><?= e($exam['title']) ?></h3>
                            <?php if ($status === 'active'): ?>
                                <span class="badge badge-active">Live</span>
                            <?php elseif ($status === 'scheduled'): ?>
                                <span class="badge badge-scheduled">Scheduled</span>
                            <?php else: ?>
                                <span class="badge badge-ended">Ended</span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($exam['description'])): ?>
                            <p style="color: var(--color-text-secondary); font-size: 0.9rem; margin-bottom: 16px;">
                                <?= e($exam['description']) ?>
                            </p>
                        <?php endif; ?>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 20px; font-size: 0.88rem; background: var(--color-gray-100); padding: 12px; border-radius: var(--radius-md);">
                            <div><span style="color: var(--color-text-secondary);">Subject:</span> <strong><?= e($exam['subject_name']) ?></strong></div>
                            <div><span style="color: var(--color-text-secondary);">Duration:</span> <strong><?= e((string)$exam['duration_minutes']) ?> mins</strong></div>
                            <div><span style="color: var(--color-text-secondary);">Questions:</span> <strong><?= e((string)$exam['total_questions_to_ask']) ?> Qs</strong></div>
                            <div><span style="color: var(--color-text-secondary);">Total Marks:</span> <strong><?= e((string)$exam['total_marks']) ?></strong></div>
                        </div>
                    </div>

                    <div>
                        <?php if ($is_completed): ?>
                            <div class="alert alert-success" style="margin-bottom: 0;">
                                Completed • Score: <strong><?= e((string)$exam['score']) ?> / <?= e((string)($exam['total_questions'] ?? $exam['total_marks'])) ?></strong>
                            </div>
                        <?php elseif ($status === 'scheduled'): ?>
                            <div class="alert alert-warning" style="margin-bottom: 0;">
                                Starts on <?= date('d M Y, h:i A', strtotime($exam['start_time'])) ?>
                            </div>
                        <?php elseif ($status === 'ended'): ?>
                            <div class="alert" style="margin-bottom: 0; background: var(--color-gray-100); color: var(--color-text-secondary);">
                                Examination Closed
                            </div>
                        <?php elseif ($is_ongoing): ?>
                            <a href="exam.php?id=<?= $exam['id'] ?>" class="btn btn-warning btn-block">Resume Exam</a>
                        <?php else: ?>
                            <a href="exam.php?id=<?= $exam['id'] ?>" class="btn btn-primary btn-block">Start Exam</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
    <?php if (empty($filtered_exams)): ?>
        document.addEventListener("DOMContentLoaded", async function () {
            try {
                const response = await fetch('../utils/funny_quotes.json');
                if (response.ok) {
                    const data = await response.json();
                    if (data.quotes && data.quotes.length > 0) {
                        const randomQuote = data.quotes[Math.floor(Math.random() * data.quotes.length)];
                        const target = document.getElementById('funny-quote');
                        if (target && randomQuote.quote) {
                            target.innerText = '"' + randomQuote.quote + '"';
                        }
                    }
                }
            } catch (e) {
                // Ignore quote error
            }
        });
    <?php endif; ?>

    let currentExamCount = <?= $active_count ?>;
    setInterval(async function () {
        try {
            const response = await fetch('check-exams.php');
            const data = await response.json();
            if (data.active_exams > currentExamCount) {
                window.location.reload();
            }
        } catch (error) {
            // Background check error
        }
    }, 10000);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>

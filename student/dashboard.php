<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../services/ExamEngine.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

date_default_timezone_set('Asia/Kolkata');

// Automatically sync exam statuses so due scheduled exams become active
ExamEngine::syncExamStatuses($pdo);

$student_name = $_SESSION['student_name'];
$semester = (int) $_SESSION['semester'];
$department = (string) $_SESSION['department'];
$student_id = (int) $_SESSION['student_id'];

try {
    $sql = "
        SELECT
            e.id,
            e.title,
            e.description,
            e.duration_minutes,
            e.total_marks,
            e.total_questions_to_ask,
            e.status,
            e.results_published,
            e.start_time,
            s.name AS subject_name,
            ea.id AS attempt_id,
            ea.score,
            ea.status AS attempt_status,
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
            e.start_time DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':semester' => $semester,
        ':department' => $department,
        ':student_id' => $student_id,
    ]);

    $available_exams = $stmt->fetchAll();
} catch (PDOException $e) {
    log_error("Dashboard loading error for student $student_id", $e);
    die('Database error. Please try again later.');
}

$filtered_exams = [];
$active_count = 0;

foreach ($available_exams as $exam) {
    if ($exam['status'] === 'scheduled' && !empty($exam['start_time']) && time() >= strtotime($exam['start_time'])) {
        $exam['status'] = 'active';
    }

    if ($exam['status'] === 'active') {
        $start_timestamp = !empty($exam['start_time']) ? strtotime($exam['start_time']) : time();
        $duration_seconds = $exam['duration_minutes'] * 60;
        $end_timestamp = $start_timestamp + $duration_seconds;

        if (time() >= $end_timestamp && empty($exam['attempt_status'])) {
            continue;
        }
        if (time() < $end_timestamp) {
            $active_count++;
        }
    }
    $filtered_exams[] = $exam;
}

$page_title = 'Student Dashboard • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1>Available Examinations</h1>
            <p>Department: <strong><?= e($department) ?></strong> • Semester: <strong><?= e((string) $semester) ?></strong></p>
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
                $attempt_status = $exam['attempt_status'] ?? '';
                $is_completed = ($attempt_status === 'completed');
                $is_ongoing = ($attempt_status === 'in_progress');
                $status = $exam['status'];
                if ($status === 'scheduled' && !empty($exam['start_time']) && time() >= strtotime($exam['start_time'])) {
                    $status = 'active';
                }

                $is_exam_ended = ($status === 'ended');
                if ($status === 'active' && !empty($exam['start_time'])) {
                    $durationSec = (int)$exam['duration_minutes'] * 60;
                    if (time() >= (strtotime($exam['start_time']) + $durationSec)) {
                        $is_exam_ended = true;
                    }
                }
                $is_published = !empty($exam['results_published']);
                $can_view_results = $is_exam_ended && $is_published;
                ?>

                <div class="card" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px; margin-bottom: 10px;">
                            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-dark);"><?= e($exam['title']) ?></h3>
                            <?php if ($status === 'active'): ?>
                                <span class="badge badge-active" style="display: inline-flex; align-items: center; gap: 3px;">
                                    <span class="material-symbols-outlined icon-xs">sensors</span> Live
                                </span>
                            <?php elseif ($status === 'scheduled'): ?>
                                <span class="badge badge-scheduled" style="display: inline-flex; align-items: center; gap: 3px;">
                                    <span class="material-symbols-outlined icon-xs">schedule</span> Scheduled
                                </span>
                            <?php else: ?>
                                <span class="badge badge-ended" style="display: inline-flex; align-items: center; gap: 3px;">
                                    <span class="material-symbols-outlined icon-xs">lock</span> Ended
                                </span>
                            <?php endif; ?>
                        </div>

                        <?php if (!empty($exam['description'])): ?>
                            <p style="color: var(--color-text-secondary); font-size: 0.9rem; margin-bottom: 16px;">
                                <?= e($exam['description']) ?>
                            </p>
                        <?php endif; ?>

                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; margin-bottom: 20px; font-size: 0.88rem; background: var(--color-gray-100); padding: 12px; border-radius: var(--radius-md);">
                            <div><span style="color: var(--color-text-secondary);">Subject:</span> <strong><?= e($exam['subject_name']) ?></strong></div>
                            <div><span style="color: var(--color-text-secondary);">Duration:</span> <strong><?= e((string) $exam['duration_minutes']) ?> mins</strong></div>
                            <div><span style="color: var(--color-text-secondary);">Questions:</span> <strong><?= e((string) $exam['total_questions_to_ask']) ?> Qs</strong></div>
                            <div><span style="color: var(--color-text-secondary);">Total Marks:</span> <strong><?= e((string) $exam['total_marks']) ?></strong></div>
                        </div>
                    </div>

                    <div>
                        <?php if ($is_completed): ?>
                            <?php if ($can_view_results): ?>
                                <div class="alert alert-success" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 6px;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span class="material-symbols-outlined icon-sm">check_circle</span>
                                        <div>Score: <strong><?= sprintf('%.2f', (float)$exam['score']) ?> / <?= e((string) $exam['total_marks']) ?></strong></div>
                                    </div>
                                    <div style="display: flex; gap: 6px;">
                                        <a href="result.php?exam_id=<?= (int)$exam['id'] ?>" class="btn btn-secondary btn-sm" title="View Full Scorecard">Score</a>
                                        <a href="review-exam.php?attempt_id=<?= (int)$exam['attempt_id'] ?>" class="btn btn-outline btn-sm">Review</a>
                                    </div>
                                </div>
                            <?php elseif (!$is_exam_ended): ?>
                                <div class="alert alert-info" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span class="material-symbols-outlined icon-sm">task_alt</span>
                                        <div>Score: <strong><?= sprintf('%.2f', (float)$exam['score']) ?> / <?= e((string) $exam['total_marks']) ?></strong> <span style="font-size: 0.8rem; opacity: 0.85;">(Live Session)</span></div>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <a href="result.php?exam_id=<?= (int)$exam['id'] ?>" class="btn btn-secondary btn-sm" title="View Score">Score</a>
                                        <span class="badge badge-pending" style="font-size: 0.72rem;">Review &amp; PDF Locked</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap;">
                                    <div style="display: flex; align-items: center; gap: 6px;">
                                        <span class="material-symbols-outlined icon-sm">hourglass_top</span>
                                        <div>Score: <strong><?= sprintf('%.2f', (float)$exam['score']) ?> / <?= e((string) $exam['total_marks']) ?></strong> <span style="font-size: 0.8rem; opacity: 0.85;">(Awaiting Publication)</span></div>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <a href="result.php?exam_id=<?= (int)$exam['id'] ?>" class="btn btn-secondary btn-sm" title="View Score">Score</a>
                                        <span class="badge badge-warning" style="font-size: 0.72rem;">Unpublished</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($status === 'scheduled'): ?>
                            <div class="alert alert-warning" style="margin-bottom: 0; display: flex; align-items: center; gap: 6px;">
                                <span class="material-symbols-outlined icon-sm">schedule</span>
                                <div>Starts on <?= date('d M Y, h:i A', strtotime($exam['start_time'])) ?></div>
                            </div>
                        <?php elseif ($status === 'ended'): ?>
                            <div class="alert" style="margin-bottom: 0; background: var(--color-gray-100); color: var(--color-text-secondary); display: flex; align-items: center; gap: 6px;">
                                <span class="material-symbols-outlined icon-sm">lock</span>
                                <div>Examination Closed</div>
                            </div>
                        <?php elseif ($is_ongoing): ?>
                            <a href="exam.php?id=<?= $exam['id'] ?>" class="btn btn-warning btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                <span class="material-symbols-outlined icon-sm">play_circle</span> Resume Exam
                            </a>
                        <?php else: ?>
                            <a href="exam.php?id=<?= $exam['id'] ?>" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                <span class="material-symbols-outlined icon-sm">play_arrow</span> Start Exam
                            </a>
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
                const response = await fetch('../utils/funny_quotes.json?v=<?= asset_version() ?>');
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

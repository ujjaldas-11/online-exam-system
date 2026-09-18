<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../services/ExamEngine.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../components/status-badge.php';

date_default_timezone_set('Asia/Kolkata');

// Automatically sync exam statuses so due scheduled exams become active[cite: 2]
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
$completed_count = 0;
$scheduled_count = 0;

foreach ($available_exams as $exam) {
    if ($exam['status'] === 'scheduled' && !empty($exam['start_time']) && time() >= strtotime($exam['start_time'])) {
        $exam['status'] = 'active';
    }

    $attempt_status = $exam['attempt_status'] ?? '';
    if ($attempt_status === 'completed') {
        $completed_count++;
    } elseif ($exam['status'] === 'scheduled') {
        $scheduled_count++;
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

<!-- Custom Modern Indigo Color Palette & Interactive Styles -->
<style>
    :root {
        --color-primary-indigo: #4f46e5;
        --color-primary-indigo-hover: #4338ca;
        --color-indigo-soft: rgba(79, 70, 229, 0.08);
    }

    @keyframes pulse-ring {
        0% {
            transform: scale(0.95);
            opacity: 0.8;
        }

        50% {
            transform: scale(1.05);
            opacity: 1;
        }

        100% {
            transform: scale(0.95);
            opacity: 0.8;
        }
    }

    .exam-card-item {
        transition: transform 0.25s ease, box-shadow 0.25s ease;
        border-top: 4px solid var(--color-border);
    }

    .exam-card-item.is-active-exam {
        border-top-color: var(--color-primary-indigo);
    }

    .exam-card-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 12px 28px -6px rgba(79, 70, 229, 0.12), 0 8px 10px -6px rgba(0, 0, 0, 0.04);
    }

    .filter-tab {
        cursor: pointer;
        transition: all 0.2s ease;
        border: 1px solid var(--color-border);
        background: #fff;
        color: var(--color-text-secondary);
    }

    .filter-tab.active {
        background: var(--color-primary-indigo) !important;
        color: #fff !important;
        border-color: var(--color-primary-indigo) !important;
        box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
    }
</style>

<div class="container" style="padding-top: 32px; padding-bottom: 56px;">
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <!-- Header Section with Indigo Accent Gradient Glow -->
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 24px; border-bottom: 1px solid var(--color-border); padding-bottom: 20px; flex-wrap: wrap; gap: 16px;">
        <div>
            <h1 style="font-size: 1.85rem; font-weight: 800; color: var(--color-dark); margin: 0 0 6px 0;">Available Examinations</h1>
            <p style="color: var(--color-text-secondary); margin: 0; font-size: 0.95rem;">
                Welcome back, <strong><?= e($student_name) ?></strong> &bull; Department: <strong><?= e($department) ?></strong> &bull; Semester: <strong><?= e((string) $semester) ?></strong>
            </p>
        </div>

        <!-- Quick Stats Counter Pill -->
        <div style="display: flex; gap: 12px; background: #f8fafc; border: 1px solid var(--color-border); padding: 10px 18px; border-radius: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
            <div style="text-align: center; padding-right: 12px; border-right: 1px solid var(--color-border);">
                <div style="font-size: 1.15rem; font-weight: 800; color: var(--color-primary-indigo);"><?= $active_count ?></div>
                <div style="font-size: 0.7rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 700;">Active</div>
            </div>
            <div style="text-align: center; padding-right: 12px; border-right: 1px solid var(--color-border);">
                <div style="font-size: 1.15rem; font-weight: 800; color: #d97706;"><?= $scheduled_count ?></div>
                <div style="font-size: 0.7rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 700;">Scheduled</div>
            </div>
            <div style="text-align: center;">
                <div style="font-size: 1.15rem; font-weight: 800; color: #059669;"><?= $completed_count ?></div>
                <div style="font-size: 0.7rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 700;">Done</div>
            </div>
        </div>
    </div>

    <!-- Interactive Control Bar: Search Input & Filter Tabs -->
    <?php if (!empty($filtered_exams)): ?>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; flex-wrap: wrap; gap: 16px;">
            <!-- Filter Tabs -->
            <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                <button type="button" class="filter-tab active btn btn-sm" data-filter="all" style="border-radius: 20px; padding: 6px 16px; font-weight: 600;">All Exams</button>
                <button type="button" class="filter-tab btn btn-sm" data-filter="active" style="border-radius: 20px; padding: 6px 16px; font-weight: 600;">Active Now</button>
                <button type="button" class="filter-tab btn btn-sm" data-filter="scheduled" style="border-radius: 20px; padding: 6px 16px; font-weight: 600;">Upcoming</button>
                <button type="button" class="filter-tab btn btn-sm" data-filter="completed" style="border-radius: 20px; padding: 6px 16px; font-weight: 600;">Completed</button>
            </div>

            <!-- Instant Search Bar -->
            <div style="position: relative; min-width: 260px; flex: 1; max-width: 320px;">
                <span class="material-symbols-outlined" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--color-text-secondary); font-size: 18px;">search</span>
                <input type="text" id="examSearchInput" placeholder="Search exam or subject..." style="width: 100%; padding: 8px 14px 8px 40px; border: 1px solid var(--color-border); border-radius: 20px; font-size: 0.9rem; background: #fff; outline: none; transition: border-color 0.2s, box-shadow 0.2s;" onfocus="this.style.borderColor='var(--color-primary-indigo)'; this.style.boxShadow='0 0 0 3px var(--color-indigo-soft)';" onblur="this.style.borderColor='var(--color-border)'; this.style.boxShadow='none';">
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($filtered_exams)): ?>
        <!-- Polished Empty State Card -->
        <div class="card" id="empty-state" style="text-align: center; padding: 64px 24px; border: 2px dashed var(--color-border); background: #fff; border-radius: 16px; box-shadow: var(--shadow-sm);">
            <div style="width: 68px; height: 68px; border-radius: 50%; background: var(--color-indigo-soft); color: var(--color-primary-indigo); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 20px;">
                <span class="material-symbols-outlined" style="font-size: 34px;">event_available</span>
            </div>
            <h3 style="color: var(--color-dark); font-size: 1.25rem; font-weight: 700; margin-bottom: 8px;">No exams scheduled right now</h3>
            <p style="color: var(--color-text-secondary); font-size: 0.95rem; margin-bottom: 24px;">Check back later or stay prepared for upcoming assessments[cite: 2].</p>
            <div style="background: #f8fafc; border: 1px solid var(--color-border); border-radius: 10px; padding: 16px 24px; max-width: 540px; margin: 0 auto;">
                <p id="funny-quote" style="font-size: 1.05rem; font-style: italic; font-weight: 500; color: var(--color-dark); margin: 0;">
                    "Stay ready for surprise tests!"
                </p>
            </div>
        </div>
    <?php else: ?>
        <!-- Modern Grid Layout for Exams -->
        <div id="examsGridContainer" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px;">
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

                $cardCategory = 'scheduled';
                if ($is_completed) {
                    $cardCategory = 'completed';
                } elseif ($status === 'active') {
                    $cardCategory = 'active';
                }
                $isActiveCard = ($status === 'active' && !$is_completed);
                ?>

                <!-- Individual Exam Card -->
                <div class="card exam-card-item <?= $isActiveCard ? 'is-active-exam' : '' ?>" data-category="<?= $cardCategory ?>" data-title="<?= strtolower(e($exam['title'] . ' ' . $exam['subject_name'])) ?>" style="margin-bottom: 0; display: flex; flex-direction: column; justify-content: space-between; border-radius: 14px; border-left: 1px solid var(--color-border); border-right: 1px solid var(--color-border); border-bottom: 1px solid var(--color-border); box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02), 0 2px 4px -2px rgba(0, 0, 0, 0.02); background: #fff; padding: 24px;">
                    <div>
                        <!-- Title & Status Badge Header -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 12px;">
                            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-dark); line-height: 1.4; margin: 0;"><?= e($exam['title']) ?></h3>
                            <div style="<?= $isActiveCard ? 'animation: pulse-ring 2s infinite;' : '' ?>">
                                <?= render_status_badge($status, 'student') ?>
                            </div>
                        </div>

                        <?php if (!empty($exam['description'])): ?>
                            <p style="color: var(--color-text-secondary); font-size: 0.9rem; line-height: 1.5; margin-bottom: 16px;">
                                <?= e($exam['description']) ?>
                            </p>
                        <?php endif; ?>

                        <!-- Metadata Grid Pill -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; font-size: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; padding: 14px; border-radius: 10px;">
                            <div><span style="color: var(--color-text-secondary); font-size: 0.78rem;">Subject:</span><br><strong style="color: var(--color-dark);"><?= e($exam['subject_name']) ?></strong></div>
                            <div><span style="color: var(--color-text-secondary); font-size: 0.78rem;">Duration:</span><br><strong style="color: var(--color-dark);"><?= e((string) $exam['duration_minutes']) ?> mins</strong></div>
                            <div><span style="color: var(--color-text-secondary); font-size: 0.78rem;">Questions:</span><br><strong style="color: var(--color-dark);"><?= e((string) $exam['total_questions_to_ask']) ?> Qs</strong></div>
                            <div><span style="color: var(--color-text-secondary); font-size: 0.78rem;">Total Marks:</span><br><strong style="color: var(--color-dark);"><?= e((string) $exam['total_marks']) ?></strong></div>
                        </div>
                    </div>

                    <!-- Card Footer Actions -->
                    <div>
                        <?php if ($is_completed): ?>
                            <?php if ($can_view_results): ?>
                                <div class="alert alert-success" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 6px; padding: 12px 14px; border-radius: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="material-symbols-outlined icon-sm">check_circle</span>
                                        <div style="font-size: 0.9rem;">Score: <strong><?= sprintf('%.2f', (float)$exam['score']) ?> / <?= e((string) $exam['total_marks']) ?></strong></div>
                                    </div>
                                    <div style="display: flex; gap: 6px;">
                                        <a href="result.php?exam_id=<?= (int)$exam['id'] ?>" class="btn btn-secondary btn-sm" title="View Full Scorecard">Score</a>
                                        <a href="review-exam.php?attempt_id=<?= (int)$exam['attempt_id'] ?>" class="btn btn-outline btn-sm">Review</a>
                                    </div>
                                </div>
                            <?php elseif (!$is_exam_ended): ?>
                                <div class="alert alert-info" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap; padding: 12px 14px; border-radius: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="material-symbols-outlined icon-sm">task_alt</span>
                                        <div style="font-size: 0.9rem;"><strong>Submission Received</strong></div>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <a href="result.php?exam_id=<?= (int)$exam['id'] ?>" class="btn btn-secondary btn-sm">Status</a>
                                        <span class="badge badge-pending" style="font-size: 0.72rem;">Pending</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-warning" style="margin-bottom: 0; display: flex; align-items: center; justify-content: space-between; gap: 6px; flex-wrap: wrap; padding: 12px 14px; border-radius: 10px;">
                                    <div style="display: flex; align-items: center; gap: 8px;">
                                        <span class="material-symbols-outlined icon-sm">hourglass_top</span>
                                        <div style="font-size: 0.9rem;"><strong>Submitted</strong></div>
                                    </div>
                                    <div style="display: flex; gap: 6px; align-items: center;">
                                        <a href="result.php?exam_id=<?= (int)$exam['id'] ?>" class="btn btn-secondary btn-sm">Status</a>
                                        <span class="badge badge-warning" style="font-size: 0.72rem;">Awaiting Publication</span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($status === 'scheduled'): ?>
                            <div class="alert alert-warning" style="margin-bottom: 0; display: flex; align-items: center; gap: 8px; padding: 12px 14px; border-radius: 10px; font-size: 0.9rem;">
                                <span class="material-symbols-outlined icon-sm">schedule</span>
                                <div>Starts on <strong><?= date('d M Y, h:i A', strtotime($exam['start_time'])) ?></strong></div>
                            </div>
                        <?php elseif ($status === 'ended'): ?>
                            <div class="alert" style="margin-bottom: 0; background: var(--color-gray-100); color: var(--color-text-secondary); display: flex; align-items: center; gap: 8px; padding: 12px 14px; border-radius: 10px; font-size: 0.9rem;">
                                <span class="material-symbols-outlined icon-sm">lock</span>
                                <div>Examination Closed</div>
                            </div>
                        <?php elseif ($is_ongoing): ?>
                            <a href="exam.php?id=<?= $exam['id'] ?>" class="btn btn-warning btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; font-weight: 600; border-radius: 8px;">
                                <span class="material-symbols-outlined icon-sm">play_circle</span> Resume Exam
                            </a>
                        <?php else: ?>
                            <a href="exam.php?id=<?= $exam['id'] ?>" class="btn btn-primary btn-block" style="display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; font-weight: 600; border-radius: 8px; background: var(--color-primary-indigo); border-color: var(--color-primary-indigo);" onmouseover="this.style.background='var(--color-primary-indigo-hover)'" onmouseout="this.style.background='var(--color-primary-indigo)'">
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
    document.addEventListener("DOMContentLoaded", function() {
        const filterTabs = document.querySelectorAll('.filter-tab');
        const searchInput = document.getElementById('examSearchInput');
        const examCards = document.querySelectorAll('.exam-card-item');

        let currentFilter = 'all';

        function filterExams() {
            const query = searchInput ? searchInput.value.toLowerCase().trim() : '';

            examCards.forEach(card => {
                const category = card.dataset.category;
                const titleText = card.dataset.title;

                const matchesTab = (currentFilter === 'all' || category === currentFilter);
                const matchesSearch = (!query || titleText.includes(query));

                if (matchesTab && matchesSearch) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        filterTabs.forEach(tab => {
            tab.addEventListener('click', function() {
                filterTabs.forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.filter;
                filterExams();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', filterExams);
        }

        <?php if (empty($filtered_exams)): ?>
            async function loadQuote() {
                try {
                    const response = await fetch('../assets/data/quotes_dashboard.json?v=<?= asset_version() ?>');
                    if (response.ok) {
                        const data = await response.json();
                        if (data.quotes && data.quotes.length > 0) {
                            const randomQuote = data.quotes[Math.floor(Math.random() * data.quotes.length)];
                            const target = document.getElementById('funny-quote');
                            if (target && randomQuote.quote) {
                                target.innerText = '"' + randomQuote.quote + '"';
                                return;
                            }
                        }
                    }
                } catch (e) {}
                const fallbackQuotes = [
                    "The only way to do great work is to love what you do.",
                    "Success is not final, failure is not fatal: it is the courage to continue that counts.",
                    "Believe you can and you're halfway there."
                ];
                const target = document.getElementById('funny-quote');
                if (target) {
                    target.innerText = '"' + fallbackQuotes[Math.floor(Math.random() * fallbackQuotes.length)] + '"';
                }
            }
            loadQuote();
        <?php endif; ?>
    });

    let currentExamCount = <?= $active_count ?>;
    setInterval(async function() {
        try {
            const response = await fetch('check-exams.php');
            const data = await response.json();
            if (data.active_exams > currentExamCount) {
                window.location.reload();
            }
        } catch (error) {}
    }, 10000);
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>
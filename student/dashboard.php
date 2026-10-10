<?php
require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../services/ExamEngine.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../components/status-badge.php';

date_default_timezone_set('Asia/Kolkata');

// Automatically sync exam statuses
ExamEngine::syncExamStatuses($pdo);

$student_name = $_SESSION['student_name'];
$semester     = (int) $_SESSION['semester'];
$department   = (string) $_SESSION['department'];
$student_id   = (int) $_SESSION['student_id'];

// --- Fetch All Applicable Exams ---
try {
    $sql = "
        SELECT
            e.id, e.title, e.description, e.duration_minutes, e.total_marks,
            e.total_questions_to_ask, e.status, e.results_published, e.start_time,
            s.name AS subject_name,
            ea.id AS attempt_id, ea.score, ea.status AS attempt_status, ea.total_questions
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.student_id = :student_id
        WHERE s.department = :department
          AND s.semester = :semester
          AND e.status IN ('active', 'scheduled', 'ended')
        ORDER BY FIELD(e.status, 'active', 'scheduled', 'ended'), e.start_time DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':semester' => $semester, ':department' => $department, ':student_id' => $student_id]);
    $available_exams = $stmt->fetchAll();
} catch (PDOException $e) {
    log_error("Dashboard loading error for student $student_id", $e);
    die('Database error. Please try again later.');
}

// --- Process & Categorize Logic ---
$processed_exams = [];
$active_count    = 0;
$completed_count = 0;
$scheduled_count = 0;

foreach ($available_exams as $exam) {
    if ($exam['status'] === 'scheduled' && !empty($exam['start_time']) && time() >= strtotime($exam['start_time'])) {
        $exam['status'] = 'active';
    }

    $attempt_status = $exam['attempt_status'] ?? '';
    $is_completed   = ($attempt_status === 'completed');
    $is_disqualified = ($attempt_status === 'disqualified');

    if ($is_completed) {
        $completed_count++;
        $exam['category'] = 'completed';
    } elseif ($is_disqualified) {
        $exam['category'] = 'disqualified';
    } elseif ($exam['status'] === 'scheduled') {
        $scheduled_count++;
        $exam['category'] = 'scheduled';
    } elseif ($exam['status'] === 'active') {
        $start_timestamp = !empty($exam['start_time']) ? strtotime($exam['start_time']) : time();
        $end_timestamp   = $start_timestamp + ($exam['duration_minutes'] * 60);

        if (time() >= $end_timestamp && empty($attempt_status)) {
            continue; // Exam time ran out and they never started it
        }
        if (time() < $end_timestamp) {
            $active_count++;
        }
        $exam['category'] = 'active';
    } else {
        $exam['category'] = 'ended';
    }

    $processed_exams[] = $exam;
}

// --- Filter Bar Configuration ---
$form_action = 'dashboard.php';
$search_placeholder = "Search exam or subject...";
$show_status = true;
$status_list = [
    'active'    => 'Active Now',
    'scheduled' => 'Upcoming',
    'completed' => 'Completed'
];

$filterQ      = strtolower(trim(clean_input($_GET['q'] ?? '')));
$filterStatus = clean_input($_GET['status'] ?? '');
$current_page = max(1, (int)($_GET['page'] ?? 1));
$per_page     = 10; 

// Apply Filters
$filtered_exams = [];
foreach ($processed_exams as $exam) {
    if ($filterStatus !== '' && $exam['category'] !== $filterStatus) {
        continue;
    }
    if ($filterQ !== '') {
        $searchable_text = strtolower($exam['title'] . ' ' . $exam['subject_name']);
        if (strpos($searchable_text, $filterQ) === false) {
            continue;
        }
    }
    $filtered_exams[] = $exam;
}

$total_items     = count($filtered_exams);
$paginated_exams = array_slice($filtered_exams, ($current_page - 1) * $per_page, $per_page);

$page_title = 'Student Dashboard • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<style>
    /* Sleek, industry-standard dashboard layout */
    .dashboard-wrapper { padding: 24px 0 64px; }
    
    /* Slim Quote Banner */
    .quote-slim-banner {
        background: var(--color-primary-soft, #f0f9ff);
        border: 1px solid rgba(13, 110, 253, 0.15);
        color: var(--color-primary, #0d6efd);
        padding: 10px 16px;
        border-radius: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 0.9rem;
        margin-bottom: 24px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.02);
    }
    .quote-slim-banner .quote-text { font-style: italic; font-weight: 500; flex: 1; }
    .quote-slim-banner .quote-author { font-size: 0.8rem; font-weight: 700; opacity: 0.8; text-transform: uppercase; letter-spacing: 0.5px; }

    /* Header & Stats */
    .dash-header-row {
        display: flex; justify-content: space-between; align-items: flex-end;
        flex-wrap: wrap; gap: 16px; margin-bottom: 24px;
        border-bottom: 1px solid var(--color-border); padding-bottom: 20px;
    }
    .student-meta { color: var(--color-text-secondary); margin: 4px 0 0; font-size: 0.95rem; }
    
    .stats-pill {
        display: flex; background: #fff; border: 1px solid var(--color-border);
        border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); overflow: hidden;
    }
    .stats-segment { padding: 6px 14px; text-align: center; border-right: 1px solid var(--color-border); }
    .stats-segment:last-child { border-right: none; }
    .stats-val { font-size: 1.2rem; font-weight: 800; line-height: 1; margin-bottom: 4px; }
    .stats-lbl { font-size: 0.7rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 700; letter-spacing: 0.5px; }

    /* Exam Cards Grid */
    .exam-grid {
        display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; margin-top: 16px; margin-bottom: 32px;
    }
    
    .exam-card {
        display: flex; flex-direction: column; background: #fff;
        border: 1px solid var(--color-border); border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.02); overflow: hidden;
        transition: transform 0.2s, box-shadow 0.2s;
    }
    .exam-card:hover { transform: translateY(-4px); box-shadow: 0 12px 24px rgba(0,0,0,0.08); }
    
    .exam-card-body { padding: 20px; flex: 1; }
    .exam-title-row { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; margin-bottom: 16px; }
    .exam-title { font-size: 1.1rem; font-weight: 700; color: var(--color-dark); margin: 0; line-height: 1.4; }
    .exam-desc { font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 16px; line-height: 1.5; }
    
    .exam-meta-grid {
        display: grid; grid-template-columns: 1fr 1fr; gap: 12px;
        background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #f1f5f9;
    }
    .meta-item { display: flex; flex-direction: column; gap: 2px; }
    .meta-lbl { font-size: 0.75rem; color: var(--color-text-secondary); }
    .meta-val { font-size: 0.9rem; font-weight: 600; color: var(--color-dark); }

    .exam-card-footer { padding: 16px 20px; background: #f8fafc; border-top: 1px solid var(--color-border); }
    .action-alert { display: flex; align-items: center; justify-content: space-between; gap: 8px; font-size: 0.9rem; }
    .action-alert strong { font-weight: 600; }
    .action-row { display: flex; gap: 8px; }

    /* ==============================================
       🔥 FEATURED LIVE EXAM STYLING (Full Row)
       ============================================== */
    @keyframes live-pulse {
        0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(13, 110, 253, 0); }
        100% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0); }
    }

    .live-exam-featured {
        grid-column: 1 / -1; /* Forces full row span */
        border: 2px solid var(--color-primary);
        background: linear-gradient(145deg, #ffffff, var(--color-primary-soft));
        position: relative;
    }
    
    .live-exam-featured .exam-card-body { position: relative; z-index: 2; }
    
    /* Desktop layout for full row card */
    @media (min-width: 768px) {
        .live-exam-featured {
            flex-direction: row; align-items: center;
        }
        .live-exam-featured .exam-card-body {
            flex: 1; border-right: 1px solid rgba(13, 110, 253, 0.15); padding: 28px;
        }
        .live-exam-featured .exam-meta-grid {
            grid-template-columns: repeat(4, 1fr); /* Put meta items in one straight line */
            background: rgba(255,255,255,0.7); border-color: rgba(13, 110, 253, 0.1);
        }
        .live-exam-featured .exam-card-footer {
            width: 280px; border-top: none; background: transparent; 
            display: flex; flex-direction: column; justify-content: center; padding: 28px;
        }
    }
    
    /* Empty State */
    .empty-state { text-align: center; padding: 64px 24px; background: #fff; border: 2px dashed var(--color-border); border-radius: 16px; margin-top: 16px; }
</style>

<div class="container dashboard-wrapper">
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <!-- Always-on Minimal Quote Banner -->
    <div class="quote-slim-banner" aria-live="polite">
        <span class="material-symbols-outlined">lightbulb</span>
        <span id="funny-quote" class="quote-text">"Success is the sum of small efforts, repeated day in and day out."</span>
        <span class="quote-author">— Examify</span>
    </div>

    <!--  Header & Stats -->
    <div class="dash-header-row">
        <div>
            <h1 style="font-size: 1.25rem; font-weight: 600; color: var(--color-dark); margin: 0;">Welcome back, <strong><?= e($student_name) ?></h1>
            <p class="student-meta">
                <?= e($department) ?> </strong> &bull; Sem <?= e((string) $semester) ?>
            </p>
        </div>
        <div class="stats-pill">
            <div class="stats-segment">
                <div class="stats-val" style="color: var(--color-primary);"><?= $active_count ?></div>
                <div class="stats-lbl">Active</div>
            </div>
            <div class="stats-segment">
                <div class="stats-val" style="color: #d97706;"><?= $scheduled_count ?></div>
                <div class="stats-lbl">Scheduled</div>
            </div>
            <div class="stats-segment">
                <div class="stats-val" style="color: #059669;"><?= $completed_count ?></div>
                <div class="stats-lbl">Done</div>
            </div>
        </div>
    </div>

    <!--  Global Filter Bar -->
    <?php include __DIR__ . '/../components/filter-bar.php'; ?>

    <!-- 4. Exams Grid -->
    <?php if (empty($paginated_exams)): ?>
        <div class="empty-state">
            <div style="width: 64px; height: 64px; border-radius: 50%; background: #f1f5f9; color: #64748b; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px;">
                <span class="material-symbols-outlined" style="font-size: 32px;">event_available</span>
            </div>
            <h3 style="margin: 0 0 8px; font-size: 1.25rem;">No exams found</h3>
            <p style="color: var(--color-text-secondary); margin: 0;">Check back later or adjust your search filters.</p>
        </div>
    <?php else: ?>
        <div class="exam-grid">
            <?php foreach ($paginated_exams as $exam): ?>
                <?php
                $is_completed = ($exam['category'] === 'completed');
                $is_disqualified = ($exam['category'] === 'disqualified' || ($exam['attempt_status'] ?? '') === 'disqualified');
                $is_active    = ($exam['category'] === 'active');
                $is_ongoing   = (($exam['attempt_status'] ?? '') === 'in_progress');
                $is_published = !empty($exam['results_published']);
                $is_ended     = ($exam['category'] === 'ended' || ($exam['status'] === 'ended'));
                $can_view_results = $is_ended && $is_published;
                
                // Determine if this is a live/active exam that should span the full row
                $is_live_featured = ($is_active && !$is_completed && !$is_disqualified);
                $card_classes = 'exam-card';
                if ($is_live_featured) {
                    $card_classes .= ' live-exam-featured';
                }
                ?>
                <div class="<?= $card_classes ?>">
                    <div class="exam-card-body">
                        <div class="exam-title-row">
                            <h3 class="exam-title"><?= e($exam['title']) ?></h3>
                            <div style="<?= $is_live_featured ? 'border-radius: 50px; animation: live-pulse 2s infinite;' : '' ?>">
                                <?= render_status_badge($is_disqualified ? 'disqualified' : $exam['status'], 'student') ?>
                            </div>
                        </div>
                        <?php if (!empty($exam['description'])): ?>
                            <p class="exam-desc"><?= e($exam['description']) ?></p>
                        <?php endif; ?>
                        <div class="exam-meta-grid">
                            <div class="meta-item">
                                <span class="meta-lbl">Subject</span>
                                <span class="meta-val"><?= e($exam['subject_name']) ?></span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-lbl">Duration</span>
                                <span class="meta-val"><?= e((string) $exam['duration_minutes']) ?> mins</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-lbl">Questions</span>
                                <span class="meta-val"><?= e((string) $exam['total_questions_to_ask']) ?> Qs</span>
                            </div>
                            <div class="meta-item">
                                <span class="meta-lbl">Total Marks</span>
                                <span class="meta-val"><?= e((string) $exam['total_marks']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="exam-card-footer">
                        <?php if ($is_disqualified): ?>
                            <div class="action-alert" style="color: var(--color-danger, #b91c1c); background: #fef2f2; border: 1px solid #fecaca; border-radius: var(--radius-md); padding: 10px 14px; text-align: center;">
                                <span style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; font-weight: 600;">
                                    <span class="material-symbols-outlined icon-sm" style="color: var(--color-danger, #b91c1c);">block</span>
                                    Disqualified (Integrity Violation)
                                </span>
                            </div>
                        <?php elseif ($is_completed): ?>
                            <?php if ($can_view_results): ?>
                                <div class="action-alert" style="color: #0f5132;">
                                    <span>Score: <strong><?= sprintf('%.2f', (float)$exam['score']) ?> / <?= e((string) $exam['total_marks']) ?></strong></span>
                                    <div class="action-row">
                                        <a href="result.php?exam_id=<?= (int)$exam['id'] ?>" class="btn btn-secondary btn-sm">Score</a>
                                        <a href="review-exam.php?attempt_id=<?= (int)$exam['attempt_id'] ?>" class="btn btn-outline btn-sm">Review</a>
                                    </div>
                                </div>
                            <?php elseif (!$is_ended): ?>
                                <div class="action-alert" style="color: #055160;">
                                    <span><strong>Submission Received</strong></span>
                                    <div class="action-row">
                                        <span class="badge badge-pending">Results Pending</span>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="action-alert" style="color: #664d03;">
                                    <span><strong>Submitted</strong></span>
                                    <span class="badge badge-warning">Results Pending</span>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($exam['category'] === 'scheduled'): ?>
                            <div class="action-alert" style="color: #664d03;">
                                <span>Starts: <strong><?= date('d M Y, h:i A', strtotime($exam['start_time'])) ?></strong></span>
                            </div>
                        <?php elseif ($exam['category'] === 'ended'): ?>
                            <div class="action-alert" style="color: #475569;">
                                <span><span class="material-symbols-outlined icon-sm" style="vertical-align: middle;">lock</span> Examination Closed</span>
                            </div>
                        <?php elseif ($is_ongoing): ?>
                            <a href="exam.php?id=<?= $exam['id'] ?>" class="btn btn-warning btn-block" style="justify-content: center; height: 100%; font-size: 1.05rem;">
                                Resume Exam
                            </a>
                        <?php else: ?>
                            <a href="exam.php?id=<?= $exam['id'] ?>" class="btn btn-primary btn-block" style="justify-content: center; height: 100%; font-size: 1.05rem;">
                                Start Exam
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <!--Global Pagination -->
        <?php include __DIR__ . '/../components/pagination.php'; ?>
    <?php endif; ?>
</div>

<script>
    document.addEventListener("DOMContentLoaded", async function() {
        // Fetch dynamic quote
        try {
            const response = await fetch('../assets/data/quotes_dashboard.json?v=<?= asset_version() ?>');
            if (response.ok) {
                const data = await response.json();
                if (data.quotes && data.quotes.length > 0) {
                    const randomQuote = data.quotes[Math.floor(Math.random() * data.quotes.length)];
                    if (randomQuote.quote) {
                        document.getElementById('funny-quote').innerText = '"' + randomQuote.quote + '"';
                    }
                }
            }
        } catch (e) {}
    });

    // Auto-refresh logic to check for newly active exams
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

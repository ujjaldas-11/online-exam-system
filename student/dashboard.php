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
$semester = (int) $_SESSION['semester'];
$department = (string) $_SESSION['department'];
$student_id = (int) $_SESSION['student_id'];

// --- 1. Fetch All Applicable Exams ---
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

// --- Process Exams ---
$processed_exams = [];
$active_count = 0;
$completed_count = 0;
$scheduled_count = 0;

foreach ($available_exams as $exam) {
    if ($exam['status'] === 'scheduled' && !empty($exam['start_time']) && time() >= strtotime($exam['start_time'])) {
        $exam['status'] = 'active';
    }

    $attempt_status = $exam['attempt_status'] ?? '';
    $is_completed = ($attempt_status === 'completed');
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
        $end_timestamp = $start_timestamp + ($exam['duration_minutes'] * 60);

        if (time() >= $end_timestamp && empty($attempt_status)) {
            continue;
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

// --- 2. LEADERBOARD & BADGE CALCULATION LOGIC ---
try {
    // Fetch TOTAL exams conducted per Dept & Semester (For GOAT logic)
    $examCountStmt = $pdo->query("
        SELECT s.department, s.semester, COUNT(e.id) as total_exams 
        FROM exams e 
        JOIN subjects s ON e.subject_id = s.id 
        WHERE e.status IN ('ended', 'active') 
        GROUP BY s.department, s.semester
    ");
    $semester_exam_counts = [];
    while ($row = $examCountStmt->fetch()) {
        $key = $row['department'] . '_' . $row['semester'];
        $semester_exam_counts[$key] = (int) $row['total_exams'];
    }

    // Fetch ALL completed attempts for the ENTIRE college
    $rankSql = "
        SELECT 
            st.id AS student_id, st.name, st.roll_number, 
            st.department AS student_dept, st.semester AS student_sem,
            ea.score, e.total_marks, ea.submitted_at,
            s.department AS exam_dept, s.semester AS exam_sem
        FROM students st
        JOIN exam_attempts ea ON st.id = ea.student_id
        JOIN exams e ON ea.exam_id = e.id
        JOIN subjects s ON e.subject_id = s.id
        WHERE ea.status = 'completed' AND st.status = 'active'
        ORDER BY ea.submitted_at ASC
    ";
    $rankStmt = $pdo->query($rankSql);
    $all_attempts = $rankStmt->fetchAll();

    $college_stats = [];
    foreach ($all_attempts as $att) {
        $sid = $att['student_id'];
        if (!isset($college_stats[$sid])) {
            $college_stats[$sid] = [
                'id'                => $sid,
                'name'              => $att['name'],
                'roll'              => $att['roll_number'],
                'student_dept'      => $att['student_dept'],
                'student_sem'       => $att['student_sem'],
                'full_marks_count'  => 0,
                'local_percentage'  => 0,
                'local_exams_taken' => 0,
                'badge'             => null
            ];
        }

        // FRESH START LOGIC: 
        // We ONLY count exams that belong to the student's CURRENT department and semester.
        // If they get promoted, their old exams are ignored here and counts drop to zero.
        if ($att['exam_dept'] === $att['student_dept'] && (int)$att['exam_sem'] === (int)$att['student_sem']) {
            
            // Add to Local Average
            $percentage = ($att['total_marks'] > 0) ? ($att['score'] / $att['total_marks']) * 100 : 0;
            $college_stats[$sid]['local_percentage'] += $percentage;
            $college_stats[$sid]['local_exams_taken']++;

            // Add to Cumulative Badge Count
            if ($att['score'] == $att['total_marks'] && $att['total_marks'] > 0) {
                $college_stats[$sid]['full_marks_count']++; 
            }
        }
    }

    $badge_holders = [];
    $class_leaderboard = [];

    foreach ($college_stats as &$stat) {
        $fm_count = $stat['full_marks_count'];
        
        // Find out how many exams this specific student's class has had
        $class_key = $stat['student_dept'] . '_' . $stat['student_sem'];
        $total_class_exams = $semester_exam_counts[$class_key] ?? 0;

        // COLLEGE-WIDE BADGE ASSIGNMENTS (Strictly tied to current semester)
        if ($total_class_exams >= 5 && $fm_count === $total_class_exams) {
            $stat['badge'] = ['title' => 'GOAT', 'tier' => 'goat', 'icon' => '𓃵', 'desc' => 'Flawless Semester!'];
        } elseif ($fm_count >= 7) {
            $stat['badge'] = ['title' => 'Legend', 'tier' => 'legend', 'icon' => '🗿', 'desc' => '7x Full Marks!'];
        } elseif ($fm_count >= 3) {
            $stat['badge'] = ['title' => 'Knight', 'tier' => 'knight', 'icon' => '🥷🏻', 'desc' => '3x Full Marks!'];
        }

        if ($stat['badge']) {
            $badge_holders[] = $stat;
        }

        // LOCAL CLASS LEADERBOARD (Only show logged-in student's exact classmates)
        if ($stat['student_dept'] === $department && (int)$stat['student_sem'] === $semester) {
            $stat['average'] = $stat['local_exams_taken'] > 0 
                ? ($stat['local_percentage'] / $stat['local_exams_taken']) 
                : 0;
            $class_leaderboard[] = $stat;
        }
    }
    unset($stat);

    // Sort Hall of Fame by most Full Marks first
    usort($badge_holders, function ($a, $b) {
        return $b['full_marks_count'] <=> $a['full_marks_count'];
    });

    // Sort Local Class Leaderboard by average DESC
    usort($class_leaderboard, function ($a, $b) {
        return $b['average'] <=> $a['average'];
    });

    $current_student_rank_data = null;
    $rank = 1;
    foreach ($class_leaderboard as &$stat) {
        $stat['rank'] = $rank++;
        if ($stat['id'] == $student_id) {
            $current_student_rank_data = $stat;
        }
    }
    unset($stat);

    $top_10 = array_slice($class_leaderboard, 0, 10);
    
} catch (PDOException $e) {
    log_error('Leaderboard generation failed', $e);
    $college_stats = [];
    $badge_holders = [];
    $class_leaderboard = [];
    $top_10 = [];
    $current_student_rank_data = null;
}

// --- 3. Filter Bar Configuration ---
$form_action = 'dashboard.php';
$search_placeholder = 'Search exam or subject...';
$show_status = true;
$status_list = [
    'active' => 'Active Now',
    'scheduled' => 'Upcoming',
    'completed' => 'Completed'
];

$filterQ = strtolower(trim(clean_input($_GET['q'] ?? '')));
$filterStatus = clean_input($_GET['status'] ?? '');
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 9;

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

$total_items = count($filtered_exams);
$paginated_exams = array_slice($filtered_exams, ($current_page - 1) * $per_page, $per_page);

$page_title = 'Student Dashboard • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<style>
    .dashboard-wrapper { padding: 24px 30px 64px; }

    /* ---------- Hero ---------- */
    .dash-hero {
        display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;
        padding: 24px 28px; margin-bottom: 20px;
        background: linear-gradient(120deg, var(--color-primary), var(--color-primary-hover));
        color: var(--color-surface);
        border-radius: var(--radius-lg);
        border-bottom: 3px solid var(--color-gold);
        box-shadow: var(--shadow-md);
    }
    .dash-hero h1 { margin: 0; font-size: 1.5rem; font-weight: 700; letter-spacing: -0.02em; color: var(--color-surface); }
    .dash-hero h1 strong { color: var(--color-gold-hover); }
    .student-meta { margin: 6px 0 0; font-size: 0.95rem; color: color-mix(in srgb, var(--color-surface) 75%, transparent); }

    .hero-stats { display: flex; gap: 10px; flex-wrap: wrap; }
    .hero-stat {
        min-width: 96px; padding: 10px 16px; text-align: center;
        border-radius: var(--radius-md);
        background: color-mix(in srgb, var(--color-surface) 10%, transparent);
        border: 1px solid color-mix(in srgb, var(--color-surface) 18%, transparent);
    }
    .hero-stat__val { font-size: 1.6rem; font-weight: 800; line-height: 1; color: var(--color-surface); }
    .hero-stat--live .hero-stat__val { color: var(--color-gold-hover); }
    .hero-stat__lbl { margin-top: 5px; font-size: 0.75rem; font-weight: 600; color: color-mix(in srgb, var(--color-surface) 75%, transparent); }

    /* ---------- Layout: rank board on the far left ---------- */
    .dashboard-layout { display: grid; grid-template-columns: minmax(0, 1fr); gap: 24px; margin-top: 8px; align-items: start; }
    .dash-main { min-width: 0; }
    .dash-sidebar { display: flex; flex-direction: column; gap: 16px; min-width: 0; }
    @media (min-width: 992px) {
        .dashboard-layout { grid-template-columns: minmax(0, 1fr) 320px; }
        .dash-sidebar {
            /* position: sticky; top: 16px; max-height: calc(100vh - 32px); overflow-y: auto; */
            scrollbar-width: none; -ms-overflow-style: none;
        }
        .dash-sidebar::-webkit-scrollbar { display: none; }
    }

    /* ---------- Exam cards ---------- */
    .exam-grid { display: grid; grid-template-columns: minmax(0, 1fr); gap: 20px; margin-bottom: 28px; }
    @media (min-width: 640px)  { .exam-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    @media (min-width: 1100px) { .exam-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); } }

    .ex-card {
        --accent: var(--color-border);
        display: flex; flex-direction: column;
        background: var(--color-surface);
        border: 1px solid var(--color-border);
        border-top: 3px solid var(--accent);
        border-radius: var(--radius-lg);
        box-shadow: var(--shadow-sm);
        transition: box-shadow 0.2s ease, transform 0.2s ease;
    }
    .ex-card:hover { box-shadow: var(--shadow-lg); transform: translateY(-2px); }
    .ex-card--active       { --accent: var(--color-primary-light); }
    .ex-card--scheduled    { --accent: var(--color-warning); }
    .ex-card--completed    { --accent: var(--color-success); }
    .ex-card--disqualified { --accent: var(--color-error); }
    .ex-card--ended        { --accent: var(--color-text-muted); }

    .ex-card--live {
        grid-column: 1 / -1;
        background: linear-gradient(160deg, var(--color-surface), var(--color-primary-soft));
        border-color: var(--color-primary-light);
        border-top-width: 4px;
        animation: live-pulse 2.4s ease-out infinite;
    }
    .ex-card--live .ex-title { font-size: 1.35rem; }
    .ex-live-tag {
        display: inline-flex; align-items: center; gap: 6px; margin-bottom: 8px;
        padding: 3px 10px; border-radius: var(--radius-full);
        font-size: 0.75rem; font-weight: 700;
        background: var(--color-primary-light); color: var(--color-surface);
    }
    .ex-live-dot { width: 7px; height: 7px; border-radius: var(--radius-full); background: var(--color-surface); animation: live-blink 1.2s ease-in-out infinite; }
    @keyframes live-blink { 50% { opacity: 0.25; } }

    @media (min-width: 720px) {
        .ex-card--live { flex-direction: row; align-items: stretch; }
        .ex-card--live .ex-card__body { display: flex; flex-direction: column; justify-content: center; padding: 24px 28px; }
        .ex-card--live .ex-card__footer {
            width: 260px; flex-shrink: 0; display: flex; align-items: center; justify-content: center;
            border-top: 0; border-left: 1px solid var(--color-border);
            border-radius: 0 var(--radius-lg) var(--radius-lg) 0;
            background: color-mix(in srgb, var(--color-primary-light) 8%, var(--color-surface));
        }
        .ex-card--live .ex-card__footer .btn-block { width: 100%; }
    }
    @keyframes live-pulse {
        0%   { box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-primary-light) 35%, transparent); }
        70%  { box-shadow: 0 0 0 10px color-mix(in srgb, var(--color-primary-light) 0%, transparent); }
        100% { box-shadow: 0 0 0 0 color-mix(in srgb, var(--color-primary-light) 0%, transparent); }
    }

    .ex-card__body { padding: 18px 20px 16px; flex: 1; }
    .ex-card__top { display: flex; justify-content: space-between; align-items: flex-start; gap: 12px; }
    .ex-subject { display: block; margin-bottom: 4px; font-size: 0.8rem; font-weight: 600; color: var(--color-text-secondary); }
    .ex-title { margin: 0; font-size: 1.1rem; font-weight: 700; letter-spacing: -0.01em; line-height: 1.3; color: var(--color-dark); }

    .ex-meta { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 16px; }
    .ex-meta__item {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 6px 10px; border-radius: var(--radius-full);
        background: var(--color-bg); border: 1px solid var(--color-border);
        font-size: 0.8rem; font-weight: 600; color: var(--color-text);
    }
    .ex-meta__item .material-symbols-outlined { font-size: 16px; color: var(--color-text-secondary); }

    .ex-card__footer { padding: 14px 20px; background: var(--color-bg); border-top: 1px solid var(--color-border); border-radius: 0 0 var(--radius-lg) var(--radius-lg); }
    .ex-note { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 0.9rem; color: var(--color-text-secondary); }
    .ex-note--error   { color: var(--color-error); }
    .ex-note--success { color: var(--color-success); }
    .ex-note--warning { color: var(--color-warning); }
    .ex-note__actions { display: flex; gap: 8px; }
    .ex-card__footer .btn-block { justify-content: center; }

    /* ---------- Quote banner ---------- */
    .quote-slim-banner {
        display: flex; align-items: center; gap: 10px;
        padding: 10px 16px; margin-bottom: 24px;
        background: var(--color-primary);
        border: 1px solid color-mix(in srgb, var(--color-primary-light) 20%, transparent);
        border-left: 4px solid var(--color-gold);
        border-radius: var(--radius-md);
        color: var(--color-gold);
        font-size: 1rem;
        font-weight: bold;
        box-shadow: var(--shadow-sm);
    }
    .quote-slim-banner .material-symbols-outlined { color: var(--color-gold); }
    .quote-slim-banner .quote-text { flex: 1; font-style: italic; font-weight: 500; }
    .quote-slim-banner .quote-author { font-size: 0.8rem; font-weight: 700; color: var(--color-gold); white-space: nowrap; }

    .ex-desc { margin: 12px 0 0; font-size: 0.85rem; line-height: 1.5; color: var(--color-text-secondary); }

    /* ---------- Empty state ---------- */
    .dash-empty {
        text-align: center; padding: 64px 24px;
        background: var(--color-surface); border: 2px dashed var(--color-border); border-radius: var(--radius-lg);
    }
    .dash-empty .material-symbols-outlined { font-size: 40px; color: var(--color-text-muted); }
    .dash-empty h3 { margin: 8px 0 4px; font-size: 1.2rem; color: var(--color-dark); }
    .dash-empty p { margin: 0; color: var(--color-text-secondary); }

    @media (max-width: 520px) {
        .dash-hero { padding: 20px; }
        .hero-stats { width: 100%; }
        .hero-stat { flex: 1; min-width: 0; }
        .exam-grid { grid-template-columns: minmax(0, 1fr); }
    }
</style>

<div class="dashboard-wrapper">
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <div class="dashboard-layout">
        <!-- Left: quotes, filter bar, exams -->
        <div class="dash-main">
            <!-- Quote banner -->
            <div class="quote-slim-banner" aria-live="polite">
                <span class="material-symbols-outlined">lightbulb</span>
                <span id="funny-quote" class="quote-text">"Success is the sum of small efforts, repeated day in and day out."</span>
                <span class="quote-author">&mdash; Examify</span>
            </div>

            <!-- Filter Bar -->
            <div style="display: flex; justify-content: space-between; gap: 2px;">
                <div style="width: 95%;">
                    <?php include __DIR__ . '/../components/filter-bar.php'; ?>
                </div>
                <a href="exam-history.php" class="btn btn-outline btn-sm" style="height: 2.5rem;" global-data-tooltip="view your exam history">
                <span class="material-symbols-outlined">history</span>    
                History</a>
            </div>
                

            <?php if (empty($paginated_exams)): ?>
                <div class="dash-empty">
                    <span class="material-symbols-outlined">event_available</span>
                    <h3>No exams found</h3>
                    <p>Check back later or adjust your filters.</p>
                </div>
            <?php else: ?>
                <div class="exam-grid">
                    <?php foreach ($paginated_exams as $exam): ?>
                        <?php
                        $is_completed = ($exam['category'] === 'completed');
                        $is_disqualified = ($exam['category'] === 'disqualified' || ($exam['attempt_status'] ?? '') === 'disqualified');
                        $is_active = ($exam['category'] === 'active');
                        $is_ongoing = (($exam['attempt_status'] ?? '') === 'in_progress');
                        $is_published = !empty($exam['results_published']);
                        $is_ended = ($exam['category'] === 'ended' || ($exam['status'] === 'ended'));
                        $can_view_results = $is_ended && $is_published;

                        $card_classes = 'ex-card ex-card--' . e($exam['category']);
                        if ($is_active && !$is_completed && !$is_disqualified) {
                            $card_classes .= ' ex-card--live';
                        }
                        ?>
                        <article class="<?= $card_classes ?>">
                            <div class="ex-card__body">
                                <div class="ex-card__top">
                                    <div>
                                        <?php if (strpos($card_classes, 'ex-card--live') !== false): ?>
                                            <span class="ex-live-tag"><span class="ex-live-dot"></span>Live now</span><br>
                                        <?php endif; ?>
                                        <span class="ex-subject"><?= e($exam['subject_name']) ?></span>
                                        <h3 class="ex-title"><?= e($exam['title']) ?></h3>
                                    </div>
                                    <?= render_status_badge($is_disqualified ? 'disqualified' : $exam['status'], 'student') ?>
                                </div>
                                <?php if (!empty($exam['description'])): ?>
                                    <p class="ex-desc"><?= e($exam['description']) ?></p>
                                <?php endif; ?>
                                <div class="ex-meta">
                                    <span class="ex-meta__item"><span class="material-symbols-outlined">timer</span><?= e((string) $exam['duration_minutes']) ?> min</span>
                                    <span class="ex-meta__item"><span class="material-symbols-outlined">quiz</span><?= e((string) $exam['total_questions_to_ask']) ?> questions</span>
                                    <span class="ex-meta__item"><span class="material-symbols-outlined">grade</span><?= e((string) $exam['total_marks']) ?> marks</span>
                                </div>
                            </div>

                            <div class="ex-card__footer">
                                <?php if ($is_disqualified): ?>
                                    <div class="ex-note ex-note--error"><strong>Disqualified (integrity violation)</strong></div>
                                <?php elseif ($is_completed): ?>
                                    <?php if ($can_view_results): ?>
                                        <div class="ex-note ex-note--success">
                                            <span>Score: <strong><?= sprintf('%.2f', (float) $exam['score']) ?> / <?= e((string) $exam['total_marks']) ?></strong></span>
                                            <div class="ex-note__actions">
                                                <a href="result.php?exam_id=<?= (int) $exam['id'] ?>" class="btn btn-secondary btn-sm">Score</a>
                                                <a href="review-exam.php?attempt_id=<?= (int) $exam['attempt_id'] ?>" class="btn btn-outline btn-sm">Review</a>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="ex-note ex-note--warning"><strong>Submitted</strong> <span class="badge badge-warning">Results pending</span></div>
                                    <?php endif; ?>
                                <?php elseif ($exam['category'] === 'scheduled'): ?>
                                    <div class="ex-note ex-note--warning">Starts: <strong><?= date('d M Y, h:i A', strtotime($exam['start_time'])) ?></strong></div>
                                <?php elseif ($exam['category'] === 'ended'): ?>
                                    <div class="ex-note">Examination closed</div>
                                <?php elseif ($is_ongoing): ?>
                                    <a href="exam.php?id=<?= (int) $exam['id'] ?>" class="btn btn-warning btn-block">Resume exam</a>
                                <?php else: ?>
                                    <a href="exam.php?id=<?= (int) $exam['id'] ?>" class="btn btn-block">Start exam</a>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php include __DIR__ . '/../components/pagination.php'; ?>
            <?php endif; ?>
        </div>

        <!-- Right: Class rank board (component) -->
        <div class="dash-sidebar">
            <?php include __DIR__ . '/../components/hall-of-fame.php'; ?>
            <?php include __DIR__ . '/../components/rank-board.php'; ?>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', async function () {
        // Load a random quote
        try {
            const response = await fetch('../assets/data/quotes_dashboard.json?v=<?= asset_version() ?>');
            if (response.ok) {
                const data = await response.json();
                if (data.quotes && data.quotes.length > 0) {
                    const randomQuote = data.quotes[Math.floor(Math.random() * data.quotes.length)];
                    if (randomQuote.quote) {
                        document.getElementById('funny-quote').textContent = '"' + randomQuote.quote + '"';
                    }
                }
            }
        } catch (e) {}
    });

    // Auto-refresh: reload when a new exam goes live
    let currentExamCount = <?= (int) $active_count ?>;
    setInterval(async function () {
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

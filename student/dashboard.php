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
        } elseif ($fm_count >= 1) {
            $stat['badge'] = ['title' => 'Ace', 'tier' => 'ace', 'icon' => '★', 'desc' => '1x Full Marks!'];
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
    'ended' => 'Ended',
];

$filterQ = strtolower(trim(clean_input($_GET['q'] ?? '')));
$filterStatus = clean_input($_GET['status'] ?? '');
$current_page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 9;

$filtered_exams = [];
foreach ($processed_exams as $exam) {
    if ($filterStatus !== '' && $exam['status'] !== $filterStatus) {
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

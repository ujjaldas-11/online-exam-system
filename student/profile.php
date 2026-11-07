<?php
require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../utils/csrf.php';

$student_id = (int) $_SESSION['student_id'];

// --- PASSWORD CHANGE LOGIC (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    verify_csrf();

    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        set_flash('error', 'All password fields are required.');
    } elseif ($new_password !== $confirm_password) {
        set_flash('error', 'New passwords do not match.');
    } elseif (strlen($new_password) < 8) {
        set_flash('error', 'New password must be at least 8 characters long.');
    } else {
        try {
            $stmt = $pdo->prepare('SELECT password FROM students WHERE id = ?');
            $stmt->execute([$student_id]);
            $hash = $stmt->fetchColumn();

            if ($hash && password_verify($current_password, $hash)) {
                $new_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare('UPDATE students SET password = ? WHERE id = ?');
                $updateStmt->execute([$new_hash, $student_id]);
                set_flash('success', 'Password updated successfully.');
                log_admin_action($pdo, 'password_change', 'student', $student_id, 'Student changed their password.');
            } else {
                set_flash('error', 'Incorrect current password.');
            }
        } catch (PDOException $e) {
            log_error("Password change failed for student $student_id", $e);
            set_flash('error', 'Failed to update password. Please try again.');
        }
    }

    header('Location: profile.php');
    exit;
}

// --- DATA FETCHING & BADGE LOGIC (GET) ---
try {
    $stmt = $pdo->prepare('SELECT name, email, roll_number, department, semester, gender, status, created_at FROM students WHERE id = ?');
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        die('Student record not found.');
    }

    // Determine the semester to filter by (defaults to current semester)
    $current_semester = (int) $student['semester'];
    $raw_sem = $_GET['sem'] ?? null;
    
    if ($raw_sem === 'all') {
        $target_sem = 'all';
    } else {
        $target_sem = isset($_GET['sem']) ? (int) $_GET['sem'] : $current_semester;
        if ($target_sem > $current_semester || $target_sem < 1) {
            $target_sem = $current_semester;
        }
    }
    // 1. Fetch Total Exams per Semester (For GOAT logic)
    $examCountStmt = $pdo->query("
        SELECT s.department, s.semester, COUNT(e.id) as total_exams 
        FROM exams e 
        JOIN subjects s ON e.subject_id = s.id 
        WHERE e.status IN ('ended', 'active') 
        GROUP BY s.department, s.semester
    ");
    $semester_totals = [];
    while ($row = $examCountStmt->fetch()) {
        $semester_totals[$row['department'] . '_' . $row['semester']] = (int) $row['total_exams'];
    }

    // 2. Fetch all completed attempts with historical subject info
    $resultStmt = $pdo->prepare("
        SELECT ea.score, ea.submitted_at, e.total_marks, 
               s.department AS subject_dept, s.semester AS subject_sem
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN subjects s ON e.subject_id = s.id
        WHERE ea.student_id = ? AND ea.status = 'completed'
        ORDER BY ea.submitted_at DESC
    ");
    $resultStmt->execute([$student_id]);
    $past_results = $resultStmt->fetchAll();

    // Reset Tracking Variables
    $semester_buckets = [];
    $filtered_results = [];
    $percentages = [];
    
    $best_score_pct = 0.0;
    $best_score_raw = 0.0;
    $best_score_max = 0.0;

    // Process all historical results to build badge buckets, but filter stats for the Target Sem
    foreach ($past_results as &$r) {
        $score = (float) $r['score'];
        $total = (float) $r['total_marks'];
        $pct = ($total > 0) ? ($score / $total) * 100 : 0;
        $r['percentage'] = $pct;

        // Bucket by Department & Semester for Badge Logic globally
        $bucket_key = $r['subject_dept'] . '_' . $r['subject_sem'];
        if (!isset($semester_buckets[$bucket_key])) {
            $semester_buckets[$bucket_key] = 0;
        }
        if ($score == $total && $total > 0) {
            $semester_buckets[$bucket_key]++;
        }

        // Only push to charts and snapshots if it matches the selected Target Semester
        if ($target_sem === 'all' || (int)$r['subject_sem'] === $target_sem) {
            $filtered_results[] = $r;
            $percentages[] = $pct;

            if ($pct >= $best_score_pct) {
                $best_score_pct = $pct;
                $best_score_raw = $score;
                $best_score_max = $total;
            }
        }
    }
    unset($r);

    // Calculate Snapshot Data specifically for the Target Semester
    $completed_exams = count($filtered_results);
    $average_score = $completed_exams > 0 ? (array_sum($percentages) / $completed_exams) : 0.0;
    $last_submission = $completed_exams > 0 ? $filtered_results[0]['submitted_at'] : null;
    $recent_scores = $completed_exams > 0 ? array_reverse(array_slice($filtered_results, 0, 6)) : [];

    // --- TARGET SEMESTER BADGE LOGIC (For the Achievements Card) ---
    $badge_counts = ['goat' => 0, 'legend' => 0, 'knight' => 0, 'ace' => 0];
    $target_fm_count = 0;
    $target_total_exams = 0;

    if ($target_sem === 'all') {
        // Calculate cumulative all-time stats across every semester
        for ($s = 1; $s <= $current_semester; $s++) {
            $k = $student['department'] . '_' . $s;
            $fm = $semester_buckets[$k] ?? 0;
            $tot = $semester_totals[$k] ?? 0;
            
            $target_fm_count += $fm;
            $target_total_exams += $tot;

            if ($fm >= 1) $badge_counts['ace']++;
            if ($fm >= 3) $badge_counts['knight']++;
            if ($fm >= 7) $badge_counts['legend']++;
            if ($tot >= 5 && $fm === $tot) $badge_counts['goat']++;
        }
    } else {
        // Calculate strictly for the selected semester
        $target_key = $student['department'] . '_' . $target_sem;
        $fm = $semester_buckets[$target_key] ?? 0;
        $tot = $semester_totals[$target_key] ?? 0;
        
        $target_fm_count = $fm;
        $target_total_exams = $tot;
        
        if ($fm >= 1) $badge_counts['ace']++;
        if ($fm >= 3) $badge_counts['knight']++;
        if ($fm >= 7) $badge_counts['legend']++;
        if ($tot >= 5 && $fm === $tot) $badge_counts['goat']++;
    }

    $my_badges = []; 
    if ($badge_counts['ace'] > 0) $my_badges[] = 'ace';
    if ($badge_counts['knight'] > 0) $my_badges[] = 'knight';
    if ($badge_counts['legend'] > 0) $my_badges[] = 'legend';
    if ($badge_counts['goat'] > 0) $my_badges[] = 'goat';


    // --- CURRENT SEMESTER BADGE LOGIC (Keeps the Avatar Header Icon stable) ---
    $current_key = $student['department'] . '_' . $current_semester;
    $current_fm_count = $semester_buckets[$current_key] ?? 0;
    $current_total_exams = $semester_totals[$current_key] ?? 0;
    
    $current_badges = [];
    if ($current_fm_count >= 1) $current_badges[] = 'ace';
    if ($current_fm_count >= 3) $current_badges[] = 'knight';
    if ($current_fm_count >= 7) $current_badges[] = 'legend';
    if ($current_total_exams >= 5 && $current_fm_count === $current_total_exams) {
        $current_badges[] = 'goat';
    }
    $primary_badge = !empty($current_badges) ? end($current_badges) : null; 

} catch (PDOException $e) {
    log_error("Failed to load profile for student $student_id", $e);
    die('Database Error. Please try again later.');
}

// --- AUTOMATED AVATAR LOGIC ---
$student_gender = strtolower($student['gender'] ?? 'male');

if($student_gender === 'female') {
    $current_avatar_url = '../assets/avatars/female.jpg';
} elseif($student_gender === 'male') {
    $current_avatar_url = '../assets/avatars/male.jpg';
} else {
    $current_avatar_url = '../assets/avatars/all-gender.webp';
}

$page_title = 'My Profile • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>
<?php

$hof_img_base = '../assets/badges/';

$tier_meta = [
    'goat' => ['title' => 'GOAT', 'icon' => '𓃵', 'img' => 'goat.jpg', 'description' => 'Score full marks in all exams.'],
    'legend' => ['title' => 'LEGEND', 'icon' => '🗿', 'img' => 'legend.jpg', 'description' => 'Score full marks in 7 exams.'],
    'knight' => ['title' => 'KNIGHT', 'icon' => '🥷🏻','img' => 'knight.jpg', 'description' => 'Score full marks in 3 exams.'],
    'ace' => ['title' => 'ACE', 'icon' => '★', 'img' => 'ace.png', 'description' => 'Score full marks in one exam.'], 
];

$status_key = strtolower((string) $student['status']);
$status_class = ['active' => 'success', 'pending' => 'warning', 'blocked' => 'error'][$status_key] ?? 'info';

$hint_completed = $completed_exams > 0 ? 'Keep it up!' : 'Take your first exam';
$hint_average = $average_score >= 75 ? 'Great average' : 'Improve your average';
$hint_best = $best_score_pct >= 90 ? 'Excellent!' : 'Keep pushing';

// Chart geometry (percent based, 0-100)
$chart_n = count($recent_scores ?? []);
$line_points = [];
$area_points = [];
if ($chart_n > 0) {
    foreach ($recent_scores as $i => $r) {
        $x = (($i + 0.5) / $chart_n) * 100;
        $y = 100 - max(0, min(100, (float) $r['percentage']));
        $line_points[] = round($x, 2) . ',' . round($y, 2);
    }
    $first_x = round((0.5 / $chart_n) * 100, 2);
    $last_x = round((($chart_n - 0.5) / $chart_n) * 100, 2);
    $area_points = array_merge(["$first_x,100"], $line_points, ["$last_x,100"]);
}
?>

<div class="profile-page">
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <div class="profile-layout">

        <!-- LEFT: identity + academic details -->
        <section class="card pf-left">
            <div class="pf-banner">
                <a href="edit-profile.php" class="btn btn-secondary btn-sm pf-edit">
                    <span class="material-symbols-outlined icon-sm">edit</span>
                    Edit Profile
                </a>
            </div>

            <div class="pf-identity">
                <div class="pf-avatar-wrap">
                    <img src="<?= e($current_avatar_url) ?>" alt="Profile avatar" class="pf-avatar" loading="lazy">
                    <span class="pf-status-dot pf-status-dot--<?= e($status_class) ?>" title="<?= e($student['status']) ?>"></span>
                </div>
                <div class="pf-identity-text">
                    <h2 class="pf-name">
                        <?= e($student['name']) ?>
                        <?php if (!empty($primary_badge) && isset($tier_meta[$primary_badge])): ?>
                            <span class="primary-badge badge-<?= e($primary_badge) ?>" global-data-tooltip="You are <?= $tier_meta[$primary_badge]['title'] ?>">
                                <span class="badge-icon" style="font-size: 1.1rem;"><?= $tier_meta[$primary_badge]['icon'] ?></span> 
                                <?= e($tier_meta[$primary_badge]['title']) ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                </div>
            </div>

            <div class="pf-institute">
                <span class="material-symbols-outlined">account_balance</span>
                <span>Bengal Institute of Science &amp; Technology</span>
            </div>

            <div class="pf-chips">
                <span class="badge pf-chip"><span class="material-symbols-outlined">school</span><?= e($student['department']) ?> Student</span>
                <span class="badge pf-chip">Semester <?= e((string) $student['semester']) ?></span>
            </div>

            <div class="pf-details">
                <h2 class="pf-title">
                    <span class="pf-title__icon"><span class="material-symbols-outlined">school</span></span>
                    Academic Details
                </h2>
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">person</span>Full Name</dt>
                        <dd><?= e($student['name']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">mail</span>Email</dt>
                        <dd><?= e($student['email']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">badge</span>Roll Number</dt>
                        <dd><?= e($student['roll_number']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">account_balance</span>Department</dt>
                        <dd><?= e($student['department']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">layers</span>Semester</dt>
                        <dd><?= e((string) $student['semester']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">verified_user</span>Account Status</dt>
                        <dd><span class="badge badge-<?= e($status_class) ?>"><?= e($student['status']) ?></span></dd>
                    </div>
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">event</span>Joined</dt>
                        <dd><?= date('F Y', strtotime($student['created_at'])) ?></dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- RIGHT: Snapshot, Chart, Badges -->
        <div class="pf-right">

            <!-- Performance Snapshot -->
            <section class="card">
                <div class="pf-head" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                    <h2 class="pf-title" style="margin: 0;">
                        <span class="pf-title__icon"><span class="material-symbols-outlined">bar_chart</span></span>
                        Performance Snapshot
                    </h2>
                    
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <!-- Semester Filter Form -->
                        <form method="GET" style="margin: 0; display: flex; align-items: center; gap: 8px;">
                            <label for="sem_filter" style="font-size: 0.85rem; color: var(--color-text-secondary); font-weight: 600;">View:</label>
                            <select name="sem" id="sem_filter" onchange="this.form.submit()" style="...">
                                <option value="all" <?= $target_sem === 'all' ? 'selected' : '' ?>>All Semesters</option>
                                <?php for($i = 1; $i <= $current_semester; $i++): ?>
                                    <option value="<?= $i ?>" <?= $i === $target_sem ? 'selected' : '' ?>>Sem <?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </form>
                        <a href="exam-history.php" class="pf-link" style="margin: 0;">History <span class="material-symbols-outlined icon-sm">arrow_forward</span></a>
                    </div>
                </div>
                
                <div class="pf-stats">
                    <div class="pf-stat">
                        <span class="pf-stat__icon"><span class="material-symbols-outlined">description</span></span>
                        <div class="pf-stat__num"><?= e((string) $completed_exams) ?></div>
                        <div class="pf-stat__label">Completed Exams</div>
                        <span class="pf-stat__hint"><?= e($hint_completed) ?></span>
                    </div>
                    <div class="pf-stat pf-stat--info">
                        <span class="pf-stat__icon"><span class="material-symbols-outlined">donut_large</span></span>
                        <div class="pf-stat__num"><?= sprintf('%.1f', $average_score) ?>%</div>
                        <div class="pf-stat__label">Average Score</div>
                        <span class="pf-stat__hint"><?= e($hint_average) ?></span>
                    </div>
                    <div class="pf-stat pf-stat--success">
                        <span class="pf-stat__icon"><span class="material-symbols-outlined">emoji_events</span></span>
                        <div class="pf-stat__num"><?= sprintf('%.1f', $best_score_pct) ?>%</div>
                        <div class="pf-stat__label">Best (<?= (float) $best_score_raw ?>/<?= (float) $best_score_max ?>)</div>
                        <span class="pf-stat__hint"><?= e($hint_best) ?></span>
                    </div>
                    <div class="pf-stat pf-stat--warning">
                        <span class="pf-stat__icon"><span class="material-symbols-outlined">calendar_month</span></span>
                        <div class="pf-stat__num"><?= $last_submission ? date('d M', strtotime($last_submission)) : '—' ?></div>
                        <div class="pf-stat__label">Last Submission</div>
                        <span class="pf-stat__hint">Stay consistent</span>
                    </div>
                </div>
            </section>
            
            <div class="pf-sub-right">
                <!-- Performance Over Time -->
                <section class="card pf-chart-card">
                    <div class="pf-head">   
                        <h2 class="pf-title">
                            <span class="pf-title__icon"><span class="material-symbols-outlined">monitoring</span></span>
                            Performance Over Time
                        </h2>
                        <span class="pf-muted">Last <?= (int) $chart_n ?> scores</span>
                    </div>

                    <?php if ($chart_n > 0): ?>
                        <div class="pf-chart" role="img" aria-label="Chart of your most recent exam scores">
                            <div class="pf-plot">
                                <?php foreach ([0, 25, 50, 75, 100] as $g): ?>
                                    <div class="pf-gridline" style="bottom: <?= $g ?>%;"><span><?= $g ?>%</span></div>
                                <?php endforeach; ?>

                                <svg class="pf-line-svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                                    <polygon class="pf-area" points="<?= e(implode(' ', $area_points)) ?>"/>
                                    <polyline class="pf-line" points="<?= e(implode(' ', $line_points)) ?>"/>
                                </svg>

                                <div class="pf-bars" style="grid-template-columns: repeat(<?= (int) $chart_n ?>, minmax(0, 1fr));">
                                    <?php
                                    foreach ($recent_scores as $r):
                                        $pct = max(0, min(100, (float) $r['percentage']));
                                        ?>
                                        <div class="pf-bar-col" title="<?= (float) $r['score'] ?>/<?= (float) $r['total_marks'] ?>">
                                            <span class="pf-bar-val" style="--pct: <?= round($pct, 1) ?>;"><?= sprintf('%.0f', $pct) ?>%</span>
                                            <span class="pf-bar" style="--pct: <?= round($pct, 1) ?>;"></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="pf-xaxis" style="grid-template-columns: repeat(<?= (int) $chart_n ?>, minmax(0, 1fr));">
                                <?php foreach ($recent_scores as $r): ?>
                                    <span><?= e(date('d M', strtotime($r['submitted_at']))) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="pf-empty">
                            <span class="material-symbols-outlined">quiz</span>
                            <p>No exams completed <?= $target_sem === 'all' ? 'yet.' : 'in Semester ' . e((string)$target_sem) . '.' ?></p>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Badges & Achievements -->
                <section class="card no-print">
                    <div class="pf-head">
                        <h2 class="pf-title">
                            <span class="pf-title__icon"><span class="material-symbols-outlined">emoji_events</span></span>
                            <?= $target_sem === 'all' ? 'All-Time' : 'Semester ' . e((string)$target_sem) ?> Achievements
                        </h2>
                        <button type="button" id="openBadgeRulesBtn" class="pf-link" style="background:none; border:none; cursor:pointer; padding:0;">
                            <span class="material-symbols-outlined icon-sm">help</span> How to earn
                        </button>
                    </div>
                    
                    <div class="pf-ach-container" style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <?php if (empty($my_badges)): ?>
                            <div class="pf-empty" style="flex-direction: row; padding: 10px; opacity: 0.7; width: 100%;">
                                <span class="material-symbols-outlined icon-sm">lock</span>
                                <span style="font-size: 0.85rem;">No badges earned <?= $target_sem === 'all' ? 'yet' : 'in Semester ' . e((string)$target_sem) ?>. Score full marks to unlock!</span>
                            </div>
                        <?php else: ?>
                            <?php 
                            $reversed_badges = array_reverse($my_badges);
                            foreach ($reversed_badges as $tier): 
                                $meta = $tier_meta[$tier];
                            ?>
                                <!-- Render the badge image container -->
                                <div class="pf-ach-chip badge-<?= $tier ?>" style="position: relative; display: inline-block;">
                                    <img src="<?= e($hof_img_base . $meta['img']) ?>" 
                                        alt="<?= e($meta['title']) ?>" 
                                        title="<?= e($meta['title']) ?> For a reason." 
                                        width="70px" height="50px" 
                                        style="border-radius: 50%; object-fit: cover; box-shadow: var(--shadow-sm);">
                                        
                                    <!-- Show multiplier badge (e.g., 2) if they earned it in multiple semesters -->
                                    <?php if ($badge_counts[$tier] > 1): ?>
                                        <span style="position: absolute; top: -4px; right: -4px; background: var(--color-bg); color: white; border-radius: 50%; width: 22px; height: 22px; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: bold; border: 2px solid var(--color-surface); z-index: 10;">
                                            <?= $badge_counts[$tier] ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Small summary text underneath the badges -->
                    <div style="margin-top: 12px; text-align: right;">
                        <span style="font-size: 0.8rem; color: var(--color-text-secondary); font-weight: 600;">
                            <?= $target_fm_count ?> / <?= $target_total_exams ?> Perfect Scores
                        </span>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<!-- ===================== BADGE RULES MODAL ===================== -->
<div id="badgeModal" class="no-print" style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.6); z-index: 99999; align-items: center; justify-content: center; padding: 16px;">
    
    <div style="background: var(--color-surface); width: 100%; max-width: 450px; border-radius: var(--radius-lg); overflow: hidden; box-shadow: var(--shadow-lg);">
        
        <div style="padding: 20px; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between; align-items: center;">
            <h3 style="margin: 0; font-size: 1.2rem; color: var(--color-dark); display: flex; align-items: center; gap: 8px;">
                <span class="material-symbols-outlined" style="color: var(--color-warning);">military_tech</span>
                How to Earn Badges
            </h3>
            <button type="button" id="closeBadgeBtn" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--color-text-secondary);">&times;</button>
        </div>
        
        <div style="padding: 20px;">
            <p style="margin-top: 0; margin-bottom: 16px; font-size: 0.9rem; color: var(--color-text-secondary);">
                Badges are awarded based on the number of exams where you score <strong>Full Marks (100%)</strong> in your current semester.
            </p>
            
            <div style="display: flex; flex-direction: column; gap: 12px;">
                
                <!-- GOAT -->
                <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px; background: var(--color-warning-bg); border: 1px solid var(--color-warning); border-radius: var(--radius-md);">
                    <span style="font-size: 1.6rem; color: var(--color-warning);">𓃵</span>
                    <div>
                        <strong style="display: block; color: var(--color-warning); margin-bottom: 2px;">GOAT (Flawless Semester)</strong>
                        <span style="font-size: 0.85rem; color: var(--color-text-secondary);">Score 100% on <strong>ALL</strong> exams in a semester (minimum 5 exams required).</span>
                    </div>
                </div>
                
                <!-- Legend -->
                <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px; background: var(--color-success-bg); border: 1px solid var(--color-success); border-radius: var(--radius-md);">
                    <span style="font-size: 1.6rem; color: var(--color-success);">🗿</span>
                    <div>
                        <strong style="display: block; color: var(--color-success); margin-bottom: 2px;">Legend</strong>
                        <span style="font-size: 0.85rem; color: var(--color-text-secondary);">Score 100% on <strong>7</strong> exams in a semester.</span>
                    </div>
                </div>
                
                <!-- Knight -->
                <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px; background: var(--color-info-bg); border: 1px solid var(--color-info); border-radius: var(--radius-md);">
                    <span style="font-size: 1.6rem; color: var(--color-info);">🥷🏻</span>
                    <div>
                        <strong style="display: block; color: var(--color-info); margin-bottom: 2px;">Knight</strong>
                        <span style="font-size: 0.85rem; color: var(--color-text-secondary);">Score 100% on <strong>3</strong> exams in a semester.</span>
                    </div>
                </div>
                
                <!-- Ace -->
                <div style="display: flex; align-items: flex-start; gap: 12px; padding: 12px; background: var(--color-primary-soft); border: 1px solid var(--color-primary); border-radius: var(--radius-md);">
                    <span style="font-size: 1.6rem; color: var(--color-primary);">★</span>
                    <div>
                        <strong style="display: block; color: var(--color-primary); margin-bottom: 2px;">Ace</strong>
                        <span style="font-size: 0.85rem; color: var(--color-text-secondary);">Score 100% on <strong>1</strong> exam in a semester.</span>
                    </div>
                </div>
                
            </div>

            <!-- Clarification Rule -->
            <div style="margin-top: 16px; padding-top: 12px; border-top: 1px dashed var(--color-border); text-align: center;">
                <span style="font-size: 0.82rem; color: var(--color-text-muted); display: inline-flex; align-items: center; justify-content: center; gap: 4px;">
                    <span class="material-symbols-outlined" style="font-size: 16px;">info</span>
                    Badges reset at the start of every new semester.
                </span>
            </div>
            
        </div>
    </div>
</div>

<script>
    const badgeModal = document.getElementById('badgeModal');
    const openBadgeBtn = document.getElementById('openBadgeRulesBtn');
    const closeBadgeBtn = document.getElementById('closeBadgeBtn');

    if (openBadgeBtn && badgeModal && closeBadgeBtn) {
        openBadgeBtn.addEventListener('click', () => {
            badgeModal.style.display = 'flex';
        });

        closeBadgeBtn.addEventListener('click', () => {
            badgeModal.style.display = 'none';
        });

        badgeModal.addEventListener('click', (e) => {
            if (e.target === badgeModal) {
                badgeModal.style.display = 'none';
            }
        });
    }
</script>

<?php include __DIR__ . '/../components/footer.php'; ?>

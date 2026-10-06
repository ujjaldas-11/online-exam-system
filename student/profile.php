<?php
require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$student_id = (int) $_SESSION['student_id'];

// --- DATA FETCHING (GET) ---
try {
    $stmt = $pdo->prepare('SELECT name, email, roll_number, department, semester, gender FROM students WHERE id = ?');
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        die('Student record not found.');
    }

    $resultStmt = $pdo->prepare("
        SELECT ea.score, ea.submitted_at
        FROM exam_attempts ea
        WHERE ea.student_id = ? AND ea.status = 'completed'
        ORDER BY ea.submitted_at DESC
    ");
    $resultStmt->execute([$student_id]);
    $past_results = $resultStmt->fetchAll();

    $completed_exams = count($past_results);
    $average_score   = 0.0;
    $best_score      = 0.0;
    $last_submission = null;
    $recent_scores   = [];

    if ($completed_exams > 0) {
        $scores          = array_map(static fn($r) => (float) $r['score'], $past_results);
        $average_score   = array_sum($scores) / count($scores);
        $best_score      = max($scores);
        $last_submission = $past_results[0]['submitted_at'] ?? null;

        // Last 6 attempts, oldest -> newest (for the trend bars)
        $recent_scores = array_reverse(array_slice($past_results, 0, 6));
    }
} catch (PDOException $e) {
    log_error("Failed to load profile for student $student_id", $e);
    die('Database Error. Please try again later.');
}

// --- AUTOMATED AVATAR LOGIC ---
$student_gender = strtolower($student['gender'] ?? 'male');
$current_avatar_url = ($student_gender === 'female')
    ? '../assets/avatars/female.jpg'
    : '../assets/avatars/male.jpg';

$page_title = 'My Profile • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<div class="profile-page">
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <div class="profile-header">
        <div>
            <h1 class="page-title">My Profile</h1>
            <p class="page-subtitle">Your academic details and examination progress</p>
        </div>
        <div style="display:flex; justify-content: center; align-items: center; gap: 10px;">
            <a href="edit-profile.php" class="btn btn-primary">
                <span class="material-symbols-outlined icon-sm">edit</span>
                Edit Profile
            </a>
            <a href="logout.php" class="nav-logout btn btn-danger">
                <span class="material-symbols-outlined">logout</span>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <div class="profile-layout">

        <!-- LEFT: student details -->
        <section class="card profile-card">
            <div class="profile-identity">
                <img src="<?= e($current_avatar_url) ?>" alt="Profile avatar" class="profile-avatar-img">
                <div class="profile-identity-text">
                    <h2 class="profile-name"><?= e($student['name']) ?></h2>
                    <p class="profile-sub"><?= e($student['department']) ?> &middot; Semester <?= e((string) $student['semester']) ?></p>
                    <p class="profile-sub" style="font-weight: bold;">Bengal Institute of Science & Technology</p>
                </div>
            </div>

            <div class="card-header profile-section-head">
                <h2 class="card-title">
                    <span class="material-symbols-outlined">person</span>
                    Student Details
                </h2>
            </div>
            <div class="card-body profile-details-body">
                <dl class="detail-list">
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">badge</span>Full Name</dt>
                        <dd><?= e($student['name']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">mail</span>Email</dt>
                        <dd><?= e($student['email']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">tag</span>Roll Number</dt>
                        <dd><?= e($student['roll_number']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">school</span>Department</dt>
                        <dd><?= e($student['department']) ?></dd>
                    </div>
                    <div class="detail-row">
                        <dt><span class="material-symbols-outlined">calendar_month</span>Semester</dt>
                        <dd><?= e((string) $student['semester']) ?></dd>
                    </div>
                </dl>
            </div>
        </section>

        <!-- RIGHT: performance snapshot -->
        <section class="card profile-card">
            <div class="card-header profile-section-head">
                <h2 class="card-title">
                    <span class="material-symbols-outlined">insights</span>
                    Performance Snapshot
                </h2>
                <a href="exam-history.php" class="btn btn-secondary btn-sm">
                    <span class="material-symbols-outlined icon-sm">history_edu</span>
                    View History
                </a>
            </div>

            <div class="card-body profile-perf-body">
                <div class="stats profile-stats">
                    <div class="stat-card">
                        <div class="stat-num"><?= e((string) $completed_exams) ?></div>
                        <div class="stat-label">Completed Exams</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-num"><?= sprintf('%.1f', $average_score) ?></div>
                        <div class="stat-label">Average Score</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-num"><?= sprintf('%.1f', $best_score) ?></div>
                        <div class="stat-label">Best Score</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-num">
                            <?= $last_submission ? date('d M', strtotime($last_submission)) : '—' ?>
                        </div>
                        <div class="stat-label">Last Submission</div>
                    </div>
                </div>

                <div class="trend">
                    <div class="trend-head">
                        <h3 class="trend-title">Recent attempts</h3>
                        <span class="profile-sub">Last <?= count($recent_scores) ?: 0 ?> scores</span>
                    </div>

                    <?php if ($recent_scores): ?>
                        <div class="trend-bars" role="img" aria-label="Bar chart of your most recent exam scores">
                            <?php foreach ($recent_scores as $r):
                                $score = (float) $r['score'];
                                $pct   = $best_score > 0 ? max(6, round(($score / $best_score) * 100)) : 6;
                            ?>
                                <div class="trend-col">
                                    <span class="trend-value"><?= sprintf('%.1f', $score) ?></span>
                                    <div class="trend-track">
                                        <div class="trend-fill" style="height: <?= (int) $pct ?>%"></div>
                                    </div>
                                    <span class="trend-date"><?= e(date('d M', strtotime($r['submitted_at']))) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="trend-empty">
                            <span class="material-symbols-outlined">quiz</span>
                            <p class="profile-sub">No completed exams yet. Your scores will appear here after your first attempt.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

    </div>

</div>

<style>
    /* ---- One-screen layout (desktop): page fills viewport below navbar, no scroll ---- */
    :root { --profile-nav-h: 72px; } /* adjust to your navbar height */

    @media (min-width: 901px) and (min-height: 600px) {
        html, body:has(.profile-page)
        .profile-page { height: calc(100vh - var(--profile-nav-h)); height: calc(100dvh - var(--profile-nav-h)); }
    }

    .profile-page {
        padding: 24px 30px;
        display: flex; flex-direction: column; gap: 20px;
        box-sizing: border-box; min-height: 0;
    }
    .profile-header {
        display: flex; align-items: center; justify-content: space-between; gap: 16px;
        flex-shrink: 0;
    }
    .profile-header .page-title { margin: 0; }
    .profile-header .page-subtitle { margin: 4px 0 0; }

    .profile-layout {
        flex: 1; min-height: 0;
        display: grid; grid-template-columns: minmax(300px, 5fr) minmax(0, 7fr);
        gap: 24px;
    }
    .profile-card {
        display: flex; flex-direction: column; min-height: 0; overflow: hidden;
    }
    .profile-section-head {
        display: flex; align-items: center; justify-content: space-between; gap: 12px;
        flex-shrink: 0;
    }

    /* ---- Left: identity + details ---- */
    .profile-identity {
        display: flex; align-items: center; gap: 16px;
        padding: 24px; border-bottom: 1px solid var(--color-border);
        flex-shrink: 0;
    }
    .profile-avatar-img {
        width: 120px; height: 120px; border-radius: 50%; object-fit: cover; flex-shrink: 0;
        border: 3px solid var(--color-border);
        box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    }
    .profile-identity-text { min-width: 0; }
    .profile-name { margin: 0 0 4px; font-size: 1.25rem; line-height: 1.2; overflow-wrap: anywhere; }
    .profile-sub { margin: 0; font-size: 0.9rem; }

    .profile-details-body { flex: 1; min-height: 0; display: flex; }
    .detail-list { margin: 0; flex: 1; display: flex; flex-direction: column; }
    .detail-row {
        flex: 1; display: flex; align-items: center; justify-content: space-between; gap: 16px;
        border-bottom: 1px solid var(--color-border); min-height: 0;
    }
    .detail-row:last-child { border-bottom: 0; }
    .detail-row dt {
        display: flex; align-items: center; gap: 8px;
        font-size: 0.85rem; flex-shrink: 0;
    }
    .detail-row dt .material-symbols-outlined { font-size: 18px; opacity: 0.7; }
    .detail-row dd { margin: 0; font-weight: 600; text-align: right; overflow-wrap: anywhere; }

    /* ---- Right: performance ---- */
    .profile-perf-body {
        flex: 1; min-height: 0;
        display: flex; flex-direction: column; gap: 20px;
    }
    .profile-stats {
        display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 16px; margin: 0; flex-shrink: 0;
    }

    .trend {
        flex: 1; min-height: 0;
        display: flex; flex-direction: column; gap: 12px;
        border-top: 1px solid var(--color-border); padding-top: 16px;
    }
    .trend-head { display: flex; align-items: baseline; justify-content: space-between; gap: 12px; }
    .trend-title { margin: 0; font-size: 1rem; }

    .trend-bars {
        flex: 1; min-height: 0;
        display: grid; grid-auto-flow: column; grid-auto-columns: 1fr; gap: 16px;
        align-items: stretch;
    }
    .trend-col {
        display: flex; flex-direction: column; align-items: center; gap: 6px; min-height: 0;
    }
    .trend-value { font-size: 0.85rem; font-weight: 600; }
    .trend-date  { font-size: 0.75rem; opacity: 0.7; }
    .trend-track {
        flex: 1; width: 100%; max-width: 56px; min-height: 0;
        display: flex; align-items: flex-end;
        background: var(--color-border); border-radius: 8px; overflow: hidden;
        opacity: 1;
    }
    .trend-fill {
        width: 100%; border-radius: 8px;
        background: var(--color-primary, currentColor);
    }
    .trend-empty {
        flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center;
        gap: 8px; text-align: center; padding: 16px;
    }
    .trend-empty .material-symbols-outlined { font-size: 40px; opacity: 0.5; }

    /* ---- Tablet / mobile: single column, natural scrolling ---- */
    @media (max-width: 900px), (max-height: 599px) {
        .profile-page { height: auto; }
        .profile-layout { grid-template-columns: 1fr; }
        .trend-bars { min-height: 200px; }
        .detail-row { padding: 12px 0; }
    }
    @media (max-width: 560px) {
        .profile-page { padding: 18px; }
        .profile-header { flex-direction: column; align-items: flex-start; }
        .profile-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
</style>

<?php include __DIR__ . '/../components/footer.php'; ?>

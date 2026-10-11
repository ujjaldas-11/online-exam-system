<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/logger.php';
require_once '../utils/sanitize.php';
require_once '../components/status-badge.php';

$admin_name = $_SESSION['admin_name'] ?? 'Admin';
$admin_role = get_admin_role();
$isAdminSuper = is_superadmin();
$admin_id = (int) ($_SESSION['admin_id'] ?? 0);

try {
    //  High-Level KPIs
    $total_students = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'")->fetchColumn();
    $total_exams = (int) $pdo->query("SELECT COUNT(*) FROM exams")->fetchColumn();
    $active_exams_count = (int) $pdo->query("SELECT COUNT(*) FROM exams WHERE status = 'active'")->fetchColumn();
    $total_submissions = (int) $pdo->query("SELECT COUNT(*) FROM exam_attempts WHERE status IN ('completed', 'disqualified')")->fetchColumn();
    
    // Pending Tasks (Profile requests + Pending student accounts)
    $pending_profiles = (int) $pdo->query("SELECT COUNT(*) FROM profile_requests WHERE status = 'pending'")->fetchColumn();
    $pending_students = (int) $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'pending'")->fetchColumn();
    $total_pending_tasks = $pending_profiles + $pending_students;

    //  Live / Ongoing Exams
    // Exams that are active and currently within their time window
    $liveExamsStmt = $pdo->query("
        SELECT e.id, e.title, e.duration_minutes, s.department, s.semester 
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.status = 'active' 
          AND e.start_time <= NOW() 
          AND DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE) >= NOW()
        ORDER BY e.start_time DESC LIMIT 4
    ");
    $live_exams = $liveExamsStmt->fetchAll(PDO::FETCH_ASSOC);

    //  Scheduled / Upcoming Exams
    $scheduledExamsStmt = $pdo->query("
        SELECT e.id, e.title, e.start_time, s.department, s.semester 
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.status = 'scheduled'
        ORDER BY e.start_time ASC LIMIT 4
    ");
    $scheduled_exams = $scheduledExamsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch Top All-Time Submissions by percentage
    $topSubmissionsStmt = $pdo->query("
        SELECT 
            ea.id AS attempt_id,
            st.name AS student_name,
            st.roll_number,
            st.department,
            st.semester,
            e.title AS exam_title,
            ea.score,
            e.total_marks,
            ROUND((ea.score / NULLIF(e.total_marks, 0)) * 100, 2) AS percentage,
            ea.submitted_at
        FROM exam_attempts ea
        JOIN students st ON ea.student_id = st.id
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.status IN ('completed', 'disqualified')
        AND e.total_marks > 0
        ORDER BY percentage DESC, ea.submitted_at ASC
        LIMIT 5
    ");
    $top_submissions = $topSubmissionsStmt->fetchAll(PDO::FETCH_ASSOC);

    // 5. System Audit Logs
    $auditStmt = $pdo->query("
        SELECT details, created_at 
        FROM admin_audit_logs 
        ORDER BY created_at DESC LIMIT 5
    ");
    $recent_logs = $auditStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    log_error("Admin dashboard database error", $e);
    die("Database Error. Please try again later.");
}

$page_title = 'Admin Dashboard • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<style>
    /* Compact Dashboard Layout Styles */
    .compact-kpi { padding: 16px; border-radius: var(--radius-md); background: var(--color-bg); border: 1px solid var(--color-border); display: flex; flex-direction: column; justify-content: center; box-shadow: 0 1px 2px rgba(0,0,0,0.02); }
    .compact-kpi .num { font-size: 1.7rem; font-weight: 800; color: var(--color-dark); line-height: 1.1; margin-bottom: 4px; }
    .compact-kpi .label { font-size: 0.82rem; color: var(--color-text-secondary); display: flex; align-items: center; gap: 4px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.5px; }
    
    .dash-grid-main { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px; }
    .dash-grid-half { display: grid; grid-template-columns: 1fr; gap: 16px; margin-bottom: 16px; }
    
    @media (min-width: 1024px) {
        /* This puts them in one row: 2 equal columns for tables, 1 narrower column for buttons */
        .dash-grid-main { grid-template-columns: 1fr 1fr 280px; }
        .dash-grid-half { grid-template-columns: 1fr 1fr; }
    }

    .compact-table { width: 100%; border-collapse: collapse; }
    .compact-table th, .compact-table td { padding: 8px 12px; font-size: 0.85rem; border-bottom: 1px solid var(--color-border); text-align: left; }
    .compact-table th { background: #f8fafc; color: var(--color-text-secondary); font-weight: 600; }
    .compact-table tbody tr:last-child td { border-bottom: none; }
    .compact-table tbody tr:hover { background: #f8fafc; }

    .quick-action-btn { justify-content: flex-start; padding: 10px 14px; gap: 10px; margin-bottom: 8px; width: 100%; display: flex; align-items: center; text-align: left; border-radius: var(--radius-md); }
    .quick-action-btn .icon-md { font-size: 22px; }
    .quick-action-btn strong { font-size: 0.9rem; display: block; }
    .quick-action-btn small { font-size: 0.75rem; font-weight: normal; opacity: 0.85; display: block; line-height: 1.2; }
</style>

<div class="container main-content" style="padding-top: 12px; margin-top: 3rem;">
    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <!-- Header Section -->
    <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 16px; flex-wrap: wrap; gap: 12px;">
        <div>
            <h1 style="font-size: 1.5rem; margin: 0 0 4px 0;"><?= $isAdminSuper ? 'Superadmin Dashboard' : 'Instructor Dashboard' ?></h1>
            <p style="margin: 0; font-size: 0.88rem; color: var(--color-text-secondary);">
                Welcome back, <strong><?= e($admin_name) ?></strong> 
                <span class="badge <?= $isAdminSuper ? 'badge-active' : 'badge-active' ?>" style="margin-left: 4px; font-size: 0.7rem; padding: 2px 6px;">
                    <?= $isAdminSuper ? 'Superadmin' : 'Teacher' ?>
                </span>
            </p>
        </div>
        <div class="badge badge-active">
            <span class="material-symbols-outlined icon-sm">calendar_today</span> <?= date('D, d M Y • h:i A') ?>
        </div>
    </div>

    <!-- Top Row: Compact KPIs -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 16px;">
        <div class="compact-kpi">
            <div class="num"><?= $total_students ?></div>
            <div class="label"><span class="material-symbols-outlined icon-sm">school</span> Active Students</div>
        </div>
        <div class="compact-kpi">
            <div class="num"><?= $total_exams ?> <span style="font-size: 0.9rem; font-weight: 500; color: var(--color-text-secondary);">/ <?= $active_exams_count ?> Live</span></div>
            <div class="label"><span class="material-symbols-outlined icon-sm">assignment</span> Exams</div>
        </div>
        <div class="compact-kpi">
            <div class="num"><?= $total_submissions ?></div>
            <div class="label"><span class="material-symbols-outlined icon-sm">task_alt</span> Submissions</div>
        </div>
        <a href="manage-requests.php" class="compact-kpi" style="text-decoration: none; cursor: pointer; border-color: <?= $total_pending_tasks > 0 ? '#fca5a5' : 'var(--color-border)' ?>; background: <?= $total_pending_tasks > 0 ? '#fef2f2' : 'var(--color-bg)' ?>;">
            <div class="num" style="color: <?= $total_pending_tasks > 0 ? '#dc2626' : 'var(--color-dark)' ?>;"><?= $total_pending_tasks ?></div>
            <div class="label" style="color: <?= $total_pending_tasks > 0 ? '#dc2626' : 'var(--color-text-secondary)' ?>;"><span class="material-symbols-outlined icon-sm">notifications_active</span> Pending Notifications</div>
        </a>
    </div>

    <!-- Middle Row: Operational Action Queue -->
<!-- Middle Row: Operational Action Queue -->
    <div class="dash-grid-main">
        
        <!-- 1. Live Exams -->
        <div class="card" style="margin-bottom: 0; border-left: 3px solid var(--color-success);">
            <div class="card-title" style="padding: 12px 16px; font-size: 0.95rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between;">
                <span style="display: flex; align-items: center; gap: 6px; color: var(--color-success);"><span class="material-symbols-outlined icon-sm">sensors</span> Live & Ongoing</span>
            </div>
            <?php if (empty($live_exams)): ?>
                <p style="padding: 16px; font-size: 0.85rem; color: var(--color-text-secondary); margin: 0; text-align: center;">No exams are currently active.</p>
            <?php else: ?>
                <table class="compact-table">
                    <tbody>
                        <?php foreach ($live_exams as $le): ?>
                            <tr>
                                <td>
                                    <strong><?= e($le['title']) ?></strong><br>
                                    <span style="color: var(--color-text-secondary); font-size: 0.75rem;"><?= e($le['department']) ?>, Sem <?= e((string)$le['semester']) ?></span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="proctor-exam.php?exam_id=<?= $le['id'] ?>" class="btn btn-success btn-sm">
                                        <span class="material-symbols-outlined" style="font-size: 14px;">visibility</span> Proctor
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 2. Scheduled Exams -->
        <div class="card" style="margin-bottom: 0; border-left: 3px solid var(--color-primary);">
            <div class="card-title" style="padding: 12px 16px; font-size: 0.95rem; border-bottom: 1px solid var(--color-border); display: flex; justify-content: space-between;">
                <span style="display: flex; align-items: center; gap: 6px;"><span class="material-symbols-outlined icon-sm">event</span> Upcoming Scheduled</span>
            </div>
            <?php if (empty($scheduled_exams)): ?>
                <p style="padding: 16px; font-size: 0.85rem; color: var(--color-text-secondary); margin: 0; text-align: center;">No upcoming exams scheduled.</p>
            <?php else: ?>
                <table class="compact-table">
                    <tbody>
                        <?php foreach ($scheduled_exams as $se): 
                            $is_soon = (strtotime($se['start_time']) - time()) < 86400; // Less than 24 hours
                        ?>
                            <tr>
                                <td>
                                    <strong><?= e($se['title']) ?></strong><br>
                                    <span style="color: var(--color-text-secondary); font-size: 0.75rem;"><?= e($se['department']) ?>, Sem <?= e((string)$se['semester']) ?></span>
                                </td>
                                <td>
                                    <span style="font-size: 0.8rem; <?= $is_soon ? 'color: #ea580c; font-weight: 600;' : 'color: var(--color-text-secondary);' ?>">
                                        <?= date('d M, h:i A', strtotime($se['start_time'])) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <a href="manage-exam.php?edit=<?= $se['id'] ?>" class="btn btn-secondary btn-sm" style="padding: 4px 8px; font-size: 0.75rem;">Edit</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- 3. Dense Quick Actions -->
        <div class="card" style="padding: 12px; margin-bottom: 0;">
            <div class="card-title" style="font-size: 0.95rem; margin-bottom: 12px;">Quick Tasks</div>
            
            <a href="control-exams.php" class="btn btn-primary quick-action-btn" style="background: #1e3a8a; border-color: #1e3a8a;">
                <span class="material-symbols-outlined icon-md">tune</span>
                <div><strong>Control & Proctor</strong><small>Start, stop, set PINs</small></div>
            </a>
            
            <a href="manage-exam.php" class="btn btn-secondary quick-action-btn">
                <span class="material-symbols-outlined icon-md">add_circle</span>
                <div><strong>Create New Exam</strong><small>Draft a new assessment</small></div>
            </a>
            
            <a href="manage-questions.php" class="btn btn-secondary quick-action-btn">
                <span class="material-symbols-outlined icon-md">help</span>
                <div><strong>Question Bank</strong><small>Add/edit subject questions</small></div>
            </a>
            
            <a href="manage-students.php" class="btn btn-secondary quick-action-btn">
                <span class="material-symbols-outlined icon-md">group</span>
                <div><strong>Manage Students</strong><small>Edit roster & passwords</small></div>
            </a>
            
            <a href="manage-teachers.php" class="btn btn-secondary quick-action-btn">
                <span class="material-symbols-outlined icon-md">school</span>
                <div><strong>Manage Teachers</strong><small>Edit roster & passwords</small></div>
            </a>

        </div>
    </div>
    <!-- Bottom Row: Historical Data & Audits -->
    <div class="dash-grid-half">
        <!-- Recent Submissions -->
        <div class="card">
            <div class="card-title" style="padding: 12px 16px; font-size: 0.95rem; border-bottom: 1px solid var(--color-border);">
                <span class="material-symbols-outlined icon-sm">trophy</span> Top 5 Submissions (All Time)
            </div>
            <?php if (empty($top_submissions)): ?>
                <p style="padding: 16px; font-size: 0.85rem; color: var(--color-text-secondary); margin: 0; text-align: center;">No completed exam submissions found.</p>
            <?php else: ?>
                <div style="overflow-x: auto;">
                    <table class="compact-table">
                        <thead>
                            <tr>
                                <!-- <th style="width: 40px;">#</th> -->
                                <th>Student</th>
                                <th>department</th>
                                <th>Exam</th>
                                <th>Score</th>
                                <th>Result</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $pos = 1;
                            foreach ($top_submissions as $ts): 
                                $pct = (float) $ts['percentage'];
                            ?>
                                <tr>
                                    <!-- <td><strong>#<?= $pos++ ?></strong></td> -->
                                    <td>
                                        <strong><?= e($ts['student_name']) ?></strong><br>
                                        <small style="color: var(--color-text-secondary);"><?= e($ts['roll_number']) ?></small>
                                    </td>

                                    <td>
                                        <strong><?= e($ts['department']) ?></strong>
                                        <small style="color: var(--color-text-secondary);">, Sem<?= e($ts['semester']) ?></small>
                                    </td>
                                    <td style="color: var(--color-text-secondary); max-width: 140px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="<?= e($ts['exam_title']) ?>">
                                        <?= e($ts['exam_title']) ?>
                                    </td>
                                    <td>
                                        <strong><?= sprintf('%.2f', (float)$ts['score']) ?></strong> / <?= (int)$ts['total_marks'] ?>
                                    </td>
                                    <td>
                                        <span class="badge <?= $pct >= 50 ? 'badge-active' : 'badge-rejected' ?>" style="font-size: 0.72rem; padding: 2px 6px;">
                                            <?= $pct ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- System Audit Log -->
        <div class="card">
            <div class="card-title" style="padding: 12px 16px; font-size: 0.95rem; border-bottom: 1px solid var(--color-border);"><span class="material-symbols-outlined icon-sm" style="vertical-align: middle; margin-right: 4px;">history</span> Recent Activity</div>
            <?php if (empty($recent_logs)): ?>
                <p style="padding: 16px; font-size: 0.85rem; color: var(--color-text-secondary); margin: 0; text-align: center;">No recent activity.</p>
            <?php else: ?>
                <ul style="list-style: none; padding: 0; margin: 0;">
                    <?php foreach ($recent_logs as $log): ?>
                        <li style="padding: 10px 16px; border-bottom: 1px solid var(--color-border); font-size: 0.85rem;">
                            <div style="color: var(--color-dark); margin-bottom: 2px;"><?= e($log['details']) ?></div>
                            <div style="color: var(--color-text-secondary); font-size: 0.75rem;"><span class="material-symbols-outlined" style="font-size: 11px; vertical-align: middle;">schedule</span> <?= date('d M Y, h:i A', strtotime($log['created_at'])) ?></div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            <div style="padding: 8px 16px; text-align: right; border-top: 1px solid var(--color-border); background: #f8fafc;">
                <a href="audit-logs.php" style="font-size: 0.8rem; font-weight: 600; color: var(--color-primary); text-decoration: none;">View Full Audit Trail &rarr;</a>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

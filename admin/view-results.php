<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../utils/csrf.php';

if (empty($_GET['exam_id'])) {
    die('No exam selected.');
}

$exam_id = int_param($_GET['exam_id']);

try {
    // Fetch Exam Details
    $examStmt = $pdo->prepare('
        SELECT e.id, e.title, e.total_marks, e.duration_minutes, e.start_time, e.status, e.results_published,
               a.name AS creator_name, a.status AS creator_status,
               s.name AS subject_name, s.department, s.semester
        FROM exams e
        LEFT JOIN admins a ON e.created_by = a.id
        JOIN subjects s ON e.subject_id = s.id
        WHERE e.id = ?
    ');
    $examStmt->execute([$exam_id]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        die('Exam not found.');
    }

    $is_ongoing = false;
    if ($exam['status'] === 'active' && !empty($exam['start_time'])) {
        $startTs = strtotime($exam['start_time']);
        $durationSec = ((int) $exam['duration_minutes']) * 60;
        if (time() < ($startTs + $durationSec)) {
            $is_ongoing = true;
        }
    }

    // Fetch all eligible students + any student who attempted this exam
    // Strictly 3 statuses: 'completed', 'disqualified', 'not_attempted'
    $rosterSql = "
        SELECT s.id AS student_id,
               s.name,
               s.roll_number,
               s.email,
               s.department,
               s.semester,
               ea.id AS attempt_id,
               COALESCE(ea.score, 0) AS score,
               COALESCE(ea.total_questions, 0) AS total_questions,
               ea.submitted_at,
               CASE
                   WHEN ea.status IN ('completed', 'disqualified') THEN ea.status
                   ELSE 'not_attempted'
               END AS attempt_status
        FROM students s
        LEFT JOIN exam_attempts ea
               ON ea.student_id = s.id
              AND ea.exam_id = ?
              AND ea.status IN ('completed', 'disqualified')
        WHERE (s.status = 'active' AND s.department = ? AND s.semester = ?)
           OR ea.id IS NOT NULL
        ORDER BY
            CASE
                WHEN ea.status = 'completed' THEN 1
                WHEN ea.status = 'disqualified' THEN 2
                ELSE 3
            END ASC,
            ea.score DESC,
            ea.submitted_at ASC,
            s.roll_number ASC
    ";

    // Handle PDF Export
    if (isset($_GET['download_pdf'])) {
        if (!can_admin_manage_exam($pdo, $exam_id)) {
            http_response_code(403);
            die("Access Denied: You can only export results for exams you have authored.");
        }
        require_once '../services/PdfService.php';
        $attemptsStmt = $pdo->prepare($rosterSql);
        $attemptsStmt->execute([$exam_id, $exam['department'], $exam['semester']]);
        $pdfAttempts = $attemptsStmt->fetchAll();

        log_admin_action($pdo, 'export_pdf_results', 'exam', $exam_id, "Exported PDF results report for exam #$exam_id ({$exam['title']})");

        if (ob_get_length()) {
            ob_end_clean();
        }
        PdfService::generateExamResultsPdf($exam, $pdfAttempts, 'D');
        exit;
    }

    // Handle Publish / Unpublish Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();

        if (!can_admin_manage_exam($pdo, $exam_id)) {
            set_flash('error', 'Access Denied: You can only publish or manage results for exams you have authored.');
            redirect("view-results.php?exam_id=$exam_id");
        }

        if (isset($_POST['publish_results'])) {
            if ($is_ongoing) {
                set_flash('error', 'Cannot publish results while the examination is still ongoing.');
                redirect("view-results.php?exam_id=$exam_id");
            }
            $pdo->prepare('UPDATE exams SET results_published = 1 WHERE id = ?')->execute([$exam_id]);
            log_admin_action($pdo, 'publish_results', 'exam', $exam_id, "Published results for exam #$exam_id to students");
            set_flash('success', "Results for '{$exam['title']}' have been published! Students can now view their scores and answer breakdowns.");
            redirect("view-results.php?exam_id=$exam_id");
        } elseif (isset($_POST['unpublish_results'])) {
            $pdo->prepare('UPDATE exams SET results_published = 0 WHERE id = ?')->execute([$exam_id]);
            log_admin_action($pdo, 'unpublish_results', 'exam', $exam_id, "Unpublished results for exam #$exam_id");
            set_flash('success', "Results for '{$exam['title']}' are now hidden from students.");
            redirect("view-results.php?exam_id=$exam_id");
        }
    }

    // Fetch All Results & Eligible Roster
    $resultsStmt = $pdo->prepare($rosterSql);
    $resultsStmt->execute([$exam_id, $exam['department'], $exam['semester']]);
    $all_results = $resultsStmt->fetchAll();

    // Status Breakdown Counts
    $counts = [
        'completed'     => 0,
        'disqualified'  => 0,
        'not_attempted' => 0,
    ];
    foreach ($all_results as $r) {
        $st = $r['attempt_status'] ?? 'not_attempted';
        if (isset($counts[$st])) {
            $counts[$st]++;
        }
    }

    // Top 3 Scorers (Strictly from 'completed' attempts)
    $completed_only = array_values(array_filter($all_results, static function ($r) {
        return ($r['attempt_status'] ?? '') === 'completed';
    }));
    $top_scorers = array_slice($completed_only, 0, 3);
} catch (PDOException $e) {
    log_error("Failed to fetch exam result list for exam $exam_id", $e);
    die('Database Error.');
}

$page_title = 'Results: ' . ($exam['title'] ?? 'Exam') . ' • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">

    <div class="page-header">
        <div>
            <h1><?= e($exam['title']) ?></h1>
            <p>
                Total Examination Marks: <strong><?= e((string) $exam['total_marks']) ?></strong> •
                Total Submissions: <strong><?= $counts['completed'] + $counts['disqualified'] ?></strong>
                (Completed: <strong><?= $counts['completed'] ?></strong>,
                Disqualified: <strong><?= $counts['disqualified'] ?></strong>, <br>
                Not Attempted: <strong><?= $counts['not_attempted'] ?></strong>) •
                Author: <strong><?= e($exam['creator_name'] ?? 'System') ?></strong>
                <?php if (($exam['creator_status'] ?? '') === 'retired'): ?>
                    <span class="badge badge-warning" style="font-size: 0.65rem; padding: 1px 4px; vertical-align: middle;">Retired</span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;" class="no-print">
                    <a href="results.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                        <span class="material-symbols-outlined icon-sm">arrow_back</span> Back to All Exams
                    </a>
            <a href="view-results.php?exam_id=<?= $exam['id'] ?>&download_pdf=1" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">picture_as_pdf</span> Download  PDF
            </a>
        </div>
    </div>

    <?php include __DIR__ . '/../components/flash-messages.php'; ?>

    <!-- Publication Status Banner -->
    <div class="card no-print" style="margin-bottom: 24px; padding: 18px 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; background: <?= !empty($exam['results_published']) ? '#f0fdf4' : ($is_ongoing ? '#fefce8' : '#fffbeb') ?>; border-color: <?= !empty($exam['results_published']) ? '#bbf7d0' : ($is_ongoing ? '#fef08a' : '#fde68a') ?>;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div style="width: 44px; height: 44px; border-radius: 50%; background: <?= !empty($exam['results_published']) ? '#dcfce7' : ($is_ongoing ? '#fef9c3' : '#fef3c7') ?>; color: <?= !empty($exam['results_published']) ? '#16a34a' : ($is_ongoing ? '#ca8a04' : '#d97706') ?>; display: inline-flex; align-items: center; justify-content: center;">
                <span class="material-symbols-outlined" style="font-size: 24px;"><?= !empty($exam['results_published']) ? 'visibility' : ($is_ongoing ? 'pending_actions' : 'visibility_off') ?></span>
            </div>
            <div>
                <h4 style="margin: 0 0 2px; font-weight: 700; color: var(--color-dark);">
                    <?= !empty($exam['results_published']) ? 'Results are Published' : ($is_ongoing ? 'Exam is Currently Ongoing' : 'Results are Unpublished') ?>
                </h4>
                <p style="margin: 0; font-size: 0.88rem; color: var(--color-text-secondary);">
                    <?= !empty($exam['results_published'])
                        ? 'Students can view their scores, detailed answer reviews, and download official scorecards.'
                        : ($is_ongoing
                            ? 'The exam is currently in progress. Results can only be released after the exam concludes.'
                            : 'Scores and answer keys are currently hidden from students until you publish them.') ?>
                </p>
            </div>
        </div>

        <div>
            <?php if (empty($exam['results_published'])): ?>
                <?php if ($is_ongoing): ?>
                    <button type="button" class="btn btn-success" disabled style="display: inline-flex; align-items: center; gap: 6px;" title="Cannot publish results while the exam is still ongoing">
                        <span class="material-symbols-outlined icon-sm">publish</span> Publish Results to Students
                    </button>
                <?php else: ?>
                    <form method="POST" style="display: inline;" onsubmit="return confirm('Publish results to all students now? Students will immediately see their scores and answer breakdowns.');">
                        <?= csrf_field() ?>
                        <button type="submit" name="publish_results" class="btn btn-success" style="display: inline-flex; align-items: center; gap: 6px;">
                            <span class="material-symbols-outlined icon-sm">publish</span> Publish Results to Students
                        </button>
                    </form>
                <?php endif; ?>
            <?php else: ?>
                <form method="POST" style="display: inline;" onsubmit="return confirm('Hide results from students? Scores and answer reviews will be locked.');">
                    <?= csrf_field() ?>
                    <button type="submit" name="unpublish_results" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                        <span class="material-symbols-outlined icon-sm">visibility_off</span> Unpublish Results
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($all_results)): ?>
        <div class="card">
            <p style="color: var(--color-text-secondary); text-align: center; padding: 32px 0;">
                No students have completed this exam yet.
            </p>
        </div>
    <?php else: ?>
        <!-- Top Performers Podium -->
        <?php if (!empty($top_scorers)): ?>
            <div class="card" style="margin-bottom: 24px;">
                <div class="card-title" style="display: flex; align-items: center; gap: 6px;">
                    <span class="material-symbols-outlined icon-md" style="color: var(--color-primary);">military_tech</span> Top Performers
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                    <?php
                    $medals = [
                        '<span class="material-symbols-outlined icon-sm" style="color: #ca8a04;">workspace_premium</span> 1st Place',
                        '<span class="material-symbols-outlined icon-sm" style="color: #64748b;">workspace_premium</span> 2nd Place',
                        '<span class="material-symbols-outlined icon-sm" style="color: #b45309;">workspace_premium</span> 3rd Place'
                    ];
                    $bgColors = ['#fef08a', '#f1f5f9', '#ffedd5'];
                    foreach ($top_scorers as $index => $student):
                        ?>
                        <div style="background: <?= $bgColors[$index] ?>; border: 1px solid var(--color-border); border-radius: var(--radius-lg); padding: 20px; text-align: center;">
                            <div style="font-weight: 800; font-size: 1.1rem; margin-bottom: 6px; display: inline-flex; align-items: center; justify-content: center; gap: 4px;"><?= $medals[$index] ?></div>
                            <h3 style="font-size: 1.15rem; font-weight: 700; color: var(--color-dark); margin-bottom: 2px;"><?= e($student['name']) ?></h3>
                            <div style="font-size: 0.85rem; color: var(--color-text-secondary); margin-bottom: 8px;">Roll: <?= e($student['roll_number']) ?></div>
                            <div style="font-size: 1.5rem; font-weight: 800; color: var(--color-primary);">
                                <?= sprintf('%.2f', (float) $student['score']) ?> <span style="font-size: 0.9rem; color: var(--color-text-secondary);">/ <?= e((string) $exam['total_marks']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- All Student Submissions -->
        <div class="card">
            <div style="display: flex; flex-direction: row; justify-content: space-between; align-items: center; padding: 10px; flex-wrap: wrap; gap: 12px;">
                <div class="card-title" style="margin: 0;">All Student Submissions (<?= count($all_results) ?>)</div>
                <div style="width: 50%; min-width: 240px;">
                    <?php include __DIR__ . '/../components/searchbar.php'; ?>
                </div>
            </div>

            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 60px;">Rank</th>
                            <th>Student Details</th>
                            <th>Score</th>
                            <th>Percentage</th>
                            <th>Submitted At</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rank = 1;
                        foreach ($all_results as $row):
                            $status = $row['attempt_status'] ?? 'not_attempted';
                            $hasAttempt = ($status !== 'not_attempted' && !empty($row['attempt_id']));
                            $percentage = ($hasAttempt && (float) $exam['total_marks'] > 0)
                                ? round(((float) $row['score'] / (float) $exam['total_marks']) * 100)
                                : 0;

                            $submittedDisplay = !empty($row['submitted_at'])
                                ? date('d M Y, h:i A', strtotime($row['submitted_at']))
                                : '—';
                            ?>
                            <tr>
                                <td>
                                    <strong><?= $status === 'completed' ? '#' . $rank++ : '—' ?></strong>
                                </td>
                                <td>
                                    <strong><?= e($row['name']) ?></strong><br>
                                    <small style="color: var(--color-text-secondary);">
                                        <?= e($row['roll_number']) ?> • <?= e($row['department']) ?>, Sem <?= e((string) $row['semester']) ?>
                                    </small>
                                </td>
                                <td>
                                    <?php if ($hasAttempt): ?>
                                        <strong><?= sprintf('%.2f', (float) $row['score']) ?></strong> / <?= e((string) $exam['total_marks']) ?>
                                    <?php else: ?>
                                        <span style="color: var(--color-text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hasAttempt): ?>
                                        <span class="badge <?= $percentage >= 50 ? 'badge-active' : 'badge-rejected' ?>">
                                            <?= $percentage ?>%
                                        </span>
                                    <?php else: ?>
                                        <span style="color: var(--color-text-secondary);">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= e($submittedDisplay) ?></td>
                                <td>
                                    <?php if ($status === 'completed'): ?>
                                        <span class="badge badge-success">completed</span>
                                    <?php elseif ($status === 'disqualified'): ?>
                                        <span class="badge badge-danger">disqualified</span>
                                    <?php else: ?>
                                        <span class="badge" style="background: #f1f5f9; color: #475569;">not_attempted</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($hasAttempt): ?>
                                        <a href="view-student-answers.php?attempt_id=<?= (int) $row['attempt_id'] ?>" 
                                           class="btn btn-secondary btn-sm" 
                                           title="View Detailed Answer Sheet"
                                           style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px;">
                                            <span class="material-symbols-outlined icon-sm">visibility</span> View Answers
                                        </a>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-secondary btn-sm" disabled
                                                style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 8px; opacity: 0.5; cursor: not-allowed;">
                                            <span class="material-symbols-outlined icon-sm">block</span> No Attempt
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

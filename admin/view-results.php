<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

require_once '../utils/csrf.php';

if (empty($_GET['exam_id'])) {
    die("No exam selected.");
}

$exam_id = int_param($_GET['exam_id']);

try {
    // Fetch Exam Details
    $examStmt = $pdo->prepare("
        SELECT e.id, e.title, e.total_marks, e.duration_minutes, e.start_time, e.status, e.results_published,
               a.name as creator_name, a.status as creator_status
        FROM exams e
        LEFT JOIN admins a ON e.created_by = a.id
        WHERE e.id = ?
    ");
    $examStmt->execute([$exam_id]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        die("Exam not found.");
    }

    $is_ongoing = false;
    if ($exam['status'] === 'active') {
        $startTs = !empty($exam['start_time']) ? strtotime($exam['start_time']) : time();
        $durationSec = ((int)$exam['duration_minutes']) * 60;
        if (time() < ($startTs + $durationSec)) {
            $is_ongoing = true;
        }
    }

    // Handle Publish / Unpublish Actions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();
        if (isset($_POST['publish_results'])) {
            if ($is_ongoing) {
                set_flash('error', "Cannot publish results while the examination is still ongoing.");
                redirect("view-results.php?exam_id=$exam_id");
            }
            $pdo->prepare("UPDATE exams SET results_published = 1 WHERE id = ?")->execute([$exam_id]);
            log_admin_action($pdo, 'publish_results', 'exam', $exam_id, "Published results for exam #$exam_id to students");
            set_flash('success', "Results for '{$exam['title']}' have been published! Students can now view their scores and answer breakdowns.");
            redirect("view-results.php?exam_id=$exam_id");
        } elseif (isset($_POST['unpublish_results'])) {
            $pdo->prepare("UPDATE exams SET results_published = 0 WHERE id = ?")->execute([$exam_id]);
            log_admin_action($pdo, 'unpublish_results', 'exam', $exam_id, "Unpublished results for exam #$exam_id");
            set_flash('success', "Results for '{$exam['title']}' are now hidden from students.");
            redirect("view-results.php?exam_id=$exam_id");
        }
    }

    // Fetch All Completed Attempts (Ordered by Score)
    $resultsSql = "SELECT s.name, s.roll_number, s.department, s.semester, ea.id AS attempt_id, ea.score, ea.total_questions, ea.submitted_at
        FROM exam_attempts ea
        JOIN students s ON ea.student_id = s.id
        WHERE ea.exam_id = :exam_id AND ea.status = 'completed'
        ORDER BY ea.score DESC, ea.submitted_at ASC";
    $resultsStmt = $pdo->prepare($resultsSql);
    $resultsStmt->execute([':exam_id' => $exam_id]);
    $all_results = $resultsStmt->fetchAll();

    // Top 3 Scorers
    $top_scorers = array_slice($all_results, 0, 3);
} catch (PDOException $e) {
    log_error("Failed to fetch exam result list for exam $exam_id", $e);
    die("Database Error.");
}

$page_title = 'Results: ' . ($exam['title'] ?? 'Exam') . ' • Examify';
include __DIR__ . '/../components/header.php';
?>

<div class="no-print">
    <?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
</div>

<div class="container main-content">
    <div style="margin-bottom: 16px;" class="no-print">
        <a href="results.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span class="material-symbols-outlined icon-sm">arrow_back</span> Back to All Exams
        </a>
    </div>

    <div class="page-header">
        <div>
            <h1><?= e($exam['title']) ?></h1>
            <p>
                Total Examination Marks: <strong><?= e((string)$exam['total_marks']) ?></strong> •
                Total Submissions: <strong><?= count($all_results) ?></strong> •
                Author: <strong><?= e($exam['creator_name'] ?? 'System') ?></strong>
                <?php if (($exam['creator_status'] ?? '') === 'retired'): ?>
                    <span class="badge badge-warning" style="font-size: 0.65rem; padding: 1px 4px; vertical-align: middle;">Retired</span>
                <?php endif; ?>
            </p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;" class="no-print">
            <a href="export-pdf.php?exam_id=<?= $exam['id'] ?>" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">picture_as_pdf</span> Download Results PDF
            </a>
        </div>
    </div>

    <?php if ($flash = get_flash('success')): ?>
        <div class="alert alert-success no-print" style="margin-bottom: 20px;">
            <?= e($flash) ?>
        </div>
    <?php endif; ?>
    <?php if ($flashError = get_flash('error')): ?>
        <div class="alert alert-error no-print" style="margin-bottom: 20px;">
            <?= e($flashError) ?>
        </div>
    <?php endif; ?>

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
        <div class="card">
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
                            <?= sprintf('%.2f', (float)$student['score']) ?> <span style="font-size: 0.9rem; color: var(--color-text-secondary);">/ <?= e((string)$exam['total_marks']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- All Submissions -->
        <div class="card">
            <div class="card-title">All Student Submissions (<?= count($all_results) ?>)</div>

            <div style="margin-bottom: 10px;">
                <?php include '../components/searchbar.php' ?>
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
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rank = 1;
                        foreach ($all_results as $row):
                            $percentage = (float)$exam['total_marks'] > 0 ? round(((float)$row['score'] / (float)$exam['total_marks']) * 100) : 0;
                            ?>
                            <tr>
                                <td><strong>#<?= $rank++ ?></strong></td>
                                <td>
                                    <strong><?= e($row['name']) ?></strong><br>
                                    <small style="color: var(--color-text-secondary);">
                                        <?= e($row['roll_number']) ?> • <?= e($row['department']) ?>, Sem <?= e((string)$row['semester']) ?>
                                    </small>
                                </td>
                                <td>
                                    <strong><?= sprintf('%.2f', (float)$row['score']) ?></strong> / <?= e((string)$exam['total_marks']) ?>
                                </td>
                                <td>
                                    <span class="badge <?= $percentage >= 50 ? 'badge-active' : 'badge-rejected' ?>">
                                        <?= $percentage ?>%
                                    </span>
                                </td>
                                <td><?= date('d M Y, h:i A', strtotime($row['submitted_at'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

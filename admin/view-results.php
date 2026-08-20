<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

if (empty($_GET['exam_id'])) {
    die("No exam selected.");
}

$exam_id = int_param($_GET['exam_id']);

try {
    // Fetch Exam Details
    $examStmt = $pdo->prepare("SELECT title, total_marks FROM exams WHERE id = ?");
    $examStmt->execute([$exam_id]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        die("Exam not found.");
    }

    // Fetch All Completed Attempts (Ordered by Score)
    $resultsSql = "SELECT s.name, s.roll_number, s.department, s.semester, ea.score, ea.total_questions, ea.submitted_at
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

$page_title = 'Results: ' . e($exam['title']) . ' • Examify';
include __DIR__ . '/../components/header.php';
?>

<div class="no-print">
    <?php include __DIR__ . '/../components/navbar.php'; ?>
</div>

<div class="container">
    <div style="margin-bottom: 16px;" class="no-print">
        <a href="results.php" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
            <span class="material-symbols-outlined icon-sm">arrow_back</span> Back to All Exams
        </a>
    </div>

    <div class="page-header">
        <div>
            <h1><?= e($exam['title']) ?></h1>
            <p>Total Examination Marks: <strong><?= e((string)$exam['total_marks']) ?></strong> • Total Submissions: <strong><?= count($all_results) ?></strong></p>
        </div>
        <button onclick="window.print()" class="btn btn-primary no-print" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">print</span> Print / Save PDF
        </button>
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
                            <?= e((string)$student['score']) ?> <span style="font-size: 0.9rem; color: var(--color-text-secondary);">/ <?= e((string)$exam['total_marks']) ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- All Submissions -->
        <div class="card">
            <div class="card-title">All Student Submissions (<?= count($all_results) ?>)</div>

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
                            $percentage = (int)$exam['total_marks'] > 0 ? round(($row['score'] / $exam['total_marks']) * 100) : 0;
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
                                    <strong><?= e((string)$row['score']) ?></strong> / <?= e((string)$exam['total_marks']) ?>
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

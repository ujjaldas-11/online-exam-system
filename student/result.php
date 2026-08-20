<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$student_id = (int) $_SESSION['student_id'];
$exam_id = int_param($_POST['exam_id'] ?? $_GET['exam_id'] ?? 0);

if ($exam_id <= 0) {
    redirect('dashboard.php');
}

$submitted_answers = $_SESSION['exam_answers'][$exam_id] ?? [];
$score = 0;
$total_marks = 0;
$already_submitted = false;

try {
    // Check attempt status
    $checkSql = "SELECT id, score, total_questions, status, started_at FROM exam_attempts WHERE student_id = :student_id AND exam_id = :exam_id LIMIT 1";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':student_id' => $student_id, ':exam_id' => $exam_id]);
    $attempt = $checkStmt->fetch();

    if (!$attempt) {
        die("Error: No active attempt found for this exam.");
    }

    $attempt_id = (int) $attempt['id'];

    $examStmt = $pdo->prepare("SELECT title, total_marks, total_questions_to_ask, duration_minutes, start_time FROM exams WHERE id = :exam_id");
    $examStmt->execute([':exam_id' => $exam_id]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        die("Error: Exam not found.");
    }

    $total_marks = (int) $exam['total_marks'];

    if ($attempt['status'] === 'completed') {
        $already_submitted = true;
        $score = (float) $attempt['score'];
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verify_csrf();

        $points_per_question = ($exam['total_questions_to_ask'] > 0) ? ($exam['total_marks'] / $exam['total_questions_to_ask']) : 0;
        $points_per_question = (float) $points_per_question;

        // Fetch assigned questions and their correct options
        $qSql = "SELECT sa.id AS ans_id, q.id AS question_id, q.correct_option, sa.selected_option AS db_selected
            FROM student_answers sa
            JOIN questions q ON sa.question_id = q.id
            WHERE sa.attempt_id = :attempt_id";
        $qStmt = $pdo->prepare($qSql);
        $qStmt->execute([':attempt_id' => $attempt_id]);
        $assigned_questions = $qStmt->fetchAll();

        $pdo->beginTransaction();
        $updateAnsSql = "UPDATE student_answers SET selected_option = :selected_option, is_correct = :is_correct WHERE id = :ans_id";
        $updateAnsStmt = $pdo->prepare($updateAnsSql);

        foreach ($assigned_questions as $q) {
            $q_id = $q['question_id'];
            $ans_id = $q['ans_id'];

            // Prefer session answer, fallback to previously synced DB answer
            $selected_option = isset($submitted_answers[$q_id]) ? clean_input($submitted_answers[$q_id]) : $q['db_selected'];
            $is_correct = ($selected_option === $q['correct_option']) ? 1 : 0;

            if ($is_correct) {
                $score += $points_per_question;
            }

            $updateAnsStmt->execute([
                ':selected_option' => $selected_option,
                ':is_correct' => $is_correct,
                ':ans_id' => $ans_id,
            ]);
        }

        $attemptUpdateSql = "UPDATE exam_attempts SET score = :score, status = 'completed', submitted_at = NOW() WHERE id = :attempt_id";
        $attemptUpdateStmt = $pdo->prepare($attemptUpdateSql);
        $attemptUpdateStmt->execute([':score' => round($score, 2), ':attempt_id' => $attempt_id]);

        $pdo->commit();

        unset($_SESSION['exam_answers'][$exam_id]);
        unset($_SESSION['exam_reviews'][$exam_id]);
    } else {
        redirect("exam.php?id=$exam_id");
    }

    // Fetch Correct, Wrong, and Skipped counts
    $statsStmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN is_correct = 1 THEN 1 ELSE 0 END) as correct_count,
            SUM(CASE WHEN is_correct = 0 AND selected_option IS NOT NULL AND selected_option != '' THEN 1 ELSE 0 END) as wrong_count,
            SUM(CASE WHEN selected_option IS NULL OR selected_option = '' THEN 1 ELSE 0 END) as skipped_count
        FROM student_answers
        WHERE attempt_id = :attempt_id
    ");
    $statsStmt->execute([':attempt_id' => $attempt_id]);
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

    $correct_count = (int) ($stats['correct_count'] ?? 0);
    $wrong_count = (int) ($stats['wrong_count'] ?? 0);
    $skipped_count = (int) ($stats['skipped_count'] ?? 0);

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    log_error("Result calculation error for student $student_id, exam $exam_id", $e);
    die("Database error calculating score. Please contact your instructor.");
}

$percentage = ($total_marks > 0) ? round(($score / $total_marks) * 100) : 0;
$page_title = 'Exam Result • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/navbar.php';
?>

<div class="container" style="max-width: 700px;">
    <div class="card" style="text-align: center; padding: 40px 24px;">
        <div style="margin-bottom: 12px;">
            <span class="material-symbols-outlined icon-2xl" style="color: <?= $percentage >= 50 ? 'var(--color-success)' : 'var(--color-primary)' ?>;">
                <?= $percentage >= 50 ? 'celebration' : 'auto_stories' ?>
            </span>
        </div>

        <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--color-dark); margin-bottom: 4px;">
            <?= e($exam['title']) ?>
        </h1>
        <p style="color: var(--color-text-secondary); margin-bottom: 24px;">Examination Result & Performance Breakdown</p>

        <!-- Final Score Display -->
        <div style="background: var(--color-primary-soft); border: 2px solid var(--color-primary-light); border-radius: var(--radius-lg); padding: 24px; margin-bottom: 28px;">
            <div style="font-size: 0.9rem; font-weight: 700; text-transform: uppercase; color: var(--color-primary); letter-spacing: 0.5px;">Your Total Score</div>
            <div style="font-size: 3rem; font-weight: 800; color: var(--color-primary); line-height: 1.1; margin: 6px 0;">
                <?= e((string)$score) ?> <span style="font-size: 1.5rem; color: var(--color-text-secondary); font-weight: 600;">/ <?= e((string)$total_marks) ?></span>
            </div>
            <div style="font-weight: 700; font-size: 1.1rem; color: <?= $percentage >= 50 ? 'var(--color-success)' : 'var(--color-error)' ?>;">
                Score Percentage: <?= $percentage ?>%
            </div>
        </div>

        <!-- Metrics Grid -->
        <div class="stats" style="margin-bottom: 32px;">
            <div class="stat-card" style="background: var(--color-success-bg); border-color: #86efac;">
                <div class="stat-num" style="color: var(--color-success);"><?= $correct_count ?></div>
                <div class="stat-label" style="color: #15803d;">Correct Answers</div>
            </div>

            <div class="stat-card" style="background: var(--color-error-bg); border-color: #fecaca;">
                <div class="stat-num" style="color: var(--color-error);"><?= $wrong_count ?></div>
                <div class="stat-label" style="color: #b91c1c;">Wrong Answers</div>
            </div>

            <div class="stat-card" style="background: var(--color-gray-100);">
                <div class="stat-num" style="color: var(--color-text-secondary);"><?= $skipped_count ?></div>
                <div class="stat-label">Skipped / Unanswered</div>
            </div>
        </div>

        <div style="display: flex; justify-content: center; gap: 14px; flex-wrap: wrap;">
            <a href="dashboard.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">dashboard</span> Return to Dashboard
            </a>
            <a href="profile.php" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">history</span> View Profile History
            </a>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

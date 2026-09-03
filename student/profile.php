<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

$student_id = (int) $_SESSION['student_id'];

try {
    $stmt = $pdo->prepare('SELECT name, email, roll_number, department, semester FROM students WHERE id = ?');
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();

    if (!$student) {
        die('Student record not found.');
    }

    $resultStmt = $pdo->prepare("
        SELECT e.title, e.total_marks, ea.id AS attempt_id, ea.score, ea.total_questions, ea.submitted_at
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        WHERE ea.student_id = ? AND ea.status = 'completed'
        ORDER BY ea.submitted_at DESC
    ");
    $resultStmt->execute([$student_id]);
    $past_results = $resultStmt->fetchAll();

} catch (PDOException $e) {
    log_error("Failed to load profile for student $student_id", $e);
    die('Database Error. Please try again later.');
}

$page_title = 'My Profile • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/student-navbar.php';
?>

<div class="container">
    <div class="page-header">
        <div>
            <h1>Student Profile</h1>
            <p>View your academic credentials and completed examinations</p>
        </div>
        <a href="edit-profile.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px;">
            <span class="material-symbols-outlined icon-sm">edit</span> Edit Profile
        </a>
    </div>

    <!-- Student Credentials Card -->
    <div class="card">
        <div class="card-title">Academic Details</div>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px;">
            <div>
                <label style="color: var(--color-text-secondary); margin-bottom: 2px;">Full Name</label>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--color-dark);"><?= e($student['name']) ?></div>
            </div>
            <div>
                <label style="color: var(--color-text-secondary); margin-bottom: 2px;">Email Address</label>
                <div style="font-size: 1.1rem; font-weight: 600;"><?= e($student['email']) ?></div>
            </div>
            <div>
                <label style="color: var(--color-text-secondary); margin-bottom: 2px;">Roll Number / Student ID</label>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--color-primary);"><?= e($student['roll_number']) ?></div>
            </div>
            <div>
                <label style="color: var(--color-text-secondary); margin-bottom: 2px;">Department & Semester</label>
                <div style="font-size: 1.1rem; font-weight: 600;"><?= e($student['department']) ?> • Semester <?= e((string)$student['semester']) ?></div>
            </div>
        </div>
    </div>

    <!-- Exam History Card -->
    <div class="card">
        <div class="card-title">Exam History</div>
        <?php include '../components/searchbar.php' ?>

        <?php if (empty($past_results)): ?>
            <p style="color: var(--color-text-secondary); padding: 12px 0;">You haven't completed any examinations yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Exam Title</th>
                            <th>Score</th>
                            <th>Submitted On</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($past_results as $result): ?>
                            <tr>
                                <td><strong><?= e($result['title']) ?></strong></td>
                                <td>
                                    <span class="badge badge-active" style="font-size: 0.85rem;">
                                        <?= sprintf('%.2f', (float)$result['score']) ?> / <?= e((string)$result['total_marks']) ?>
                                    </span>
                                </td>
                                <td>
                                    <?= $result['submitted_at'] ? date('d M Y, h:i A', strtotime($result['submitted_at'])) : '—' ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                        <a href="review-exam.php?attempt_id=<?= $result['attempt_id'] ?>" class="btn btn-outline btn-sm">
                                            Review
                                        </a>
                                        <a href="download-card.php?attempt_id=<?= $result['attempt_id'] ?>" class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 4px;">
                                            <span class="material-symbols-outlined icon-xs">picture_as_pdf</span> PDF
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

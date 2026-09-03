<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/PdfService.php';

$exam_id = int_param($_GET['exam_id'] ?? 0);
if ($exam_id <= 0) {
    die("Invalid request: No examination specified.");
}

try {
    // 1. Fetch Exam Meta
    $examStmt = $pdo->prepare("
        SELECT e.id, e.title, e.total_marks, e.duration_minutes, e.start_time,
               s.name AS subject_name, s.department, s.semester,
               a.name AS creator_name
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        LEFT JOIN admins a ON e.created_by = a.id
        WHERE e.id = ?
    ");
    $examStmt->execute([$exam_id]);
    $exam = $examStmt->fetch();

    if (!$exam) {
        die("Examination not found.");
    }

    // 2. Fetch all completed attempts ordered by score
    $attemptsStmt = $pdo->prepare("
        SELECT ea.id, ea.score, ea.total_questions, ea.submitted_at,
               s.name, s.roll_number, s.email, s.department, s.semester
        FROM exam_attempts ea
        JOIN students s ON ea.student_id = s.id
        WHERE ea.exam_id = ? AND ea.status = 'completed'
        ORDER BY ea.score DESC, ea.submitted_at ASC
    ");
    $attemptsStmt->execute([$exam_id]);
    $attempts = $attemptsStmt->fetchAll();

    log_admin_action($pdo, 'export_pdf_results', 'exam', $exam_id, "Exported PDF results report for exam #$exam_id ({$exam['title']})");

    PdfService::generateExamResultsPdf($exam, $attempts, 'I');
} catch (PDOException $e) {
    log_error("Failed to generate PDF for exam $exam_id", $e);
    die("Database Error generating PDF report.");
}

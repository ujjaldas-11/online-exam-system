<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';
require_once '../services/PdfService.php';
require_once '../services/ExamEngine.php';

$student_id = (int) $_SESSION['student_id'];
$attempt_id = int_param($_GET['attempt_id'] ?? 0);

if ($attempt_id <= 0) {
    die("Invalid request: No attempt specified.");
}

try {
    // 1. Fetch Attempt & Exam
    $stmt = $pdo->prepare("
        SELECT ea.id, ea.score, ea.total_questions, ea.submitted_at, ea.status,
               e.title, e.total_marks, e.duration_minutes, e.status AS exam_status, e.results_published, e.start_time,
               s.name, s.email, s.roll_number, s.department, s.semester
        FROM exam_attempts ea
        JOIN exams e ON ea.exam_id = e.id
        JOIN students s ON ea.student_id = s.id
        WHERE ea.id = ? AND ea.student_id = ? AND ea.status = 'completed'
    ");
    $stmt->execute([$attempt_id, $student_id]);
    $data = $stmt->fetch();

    if (!$data) {
        die("Scorecard not found or examination has not been completed.");
    }

    $is_ended = ExamEngine::isExamEnded($data);
    $is_published = !empty($data['results_published']);

    if (!$is_ended || !$is_published) {
        http_response_code(403);
        die("Scorecard is not available yet. Results must be published by the instructor after the examination concludes.");
    }

    // 2. Fetch answer breakdown
    $stats = ExamEngine::getAttemptStats($pdo, $attempt_id);

    $student = [
        'name' => $data['name'],
        'email' => $data['email'],
        'roll_number' => $data['roll_number'],
        'department' => $data['department'],
        'semester' => $data['semester']
    ];

    $exam = [
        'title' => $data['title'],
        'total_marks' => $data['total_marks'],
        'duration_minutes' => $data['duration_minutes']
    ];

    $attempt = [
        'score' => $data['score'],
        'total_questions' => $data['total_questions'],
        'submitted_at' => $data['submitted_at']
    ];

    PdfService::generateStudentScorecardPdf($student, $exam, $attempt, $stats, 'I');
} catch (PDOException $e) {
    log_error("Failed generating scorecard PDF for attempt $attempt_id", $e);
    die("Error generating PDF document.");
}

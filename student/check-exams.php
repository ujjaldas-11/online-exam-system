<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../services/ExamEngine.php';
require_once '../utils/response.php';

$studentId = (int) ($_SESSION['student_id'] ?? 0);
$semester = (int) ($_SESSION['semester'] ?? 0);
$department = (string) ($_SESSION['department'] ?? '');

// Release session lock immediately to prevent serializing parallel requests
release_session_lock();

try {
    // Fetch active or auto-activated exams available to take or resume for this student
    $stmt = $pdo->prepare("
        SELECT e.id, e.title
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        LEFT JOIN exam_attempts ea ON e.id = ea.exam_id AND ea.student_id = :student_id
        WHERE s.department = :department
          AND s.semester = :semester
          AND (
              e.status = 'active'
              OR (e.status = 'scheduled' AND e.start_time IS NOT NULL AND e.start_time <= NOW())
          )
          AND (ea.id IS NULL OR ea.status = 'in_progress')
          AND (
              (e.end_time IS NOT NULL AND NOW() < e.end_time)
              OR
              (e.end_time IS NULL AND e.start_time IS NOT NULL AND NOW() <= DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE))
              OR
              (e.start_time IS NULL)
          )
    ");
    $stmt->execute([
        ':student_id' => $studentId,
        ':department' => $department,
        ':semester'   => $semester,
    ]);
    $activeExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $activeIds = array_map(fn($ex) => (int)$ex['id'], $activeExams);

    json_response([
        'active_exams' => count($activeIds),
        'active_ids'   => $activeIds,
        'titles'       => array_column($activeExams, 'title'),
    ]);
} catch (PDOException $e) {
    json_response(['active_exams' => 0, 'active_ids' => []]);
}

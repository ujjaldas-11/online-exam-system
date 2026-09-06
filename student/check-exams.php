<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../services/ExamEngine.php';
require_once '../utils/response.php';

$semester = (int) $_SESSION['semester'];
$department = (string) $_SESSION['department'];

// Throttle global status sync to at most once per minute per session to eliminate write-lock thrashing
$lastSync = (int) ($_SESSION['last_exam_status_sync'] ?? 0);
$shouldSync = (time() - $lastSync > 60);
if ($shouldSync) {
    $_SESSION['last_exam_status_sync'] = time();
}
release_session_lock();

try {
    // 1. Auto-synchronize scheduled exams that have arrived (throttled)
    if ($shouldSync) {
        ExamEngine::syncExamStatuses($pdo);
    }

    // 2. Count active exams in progress for this student cohort
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE s.department = ?
          AND s.semester = ?
          AND e.status = 'active'
          AND (
              (e.end_time IS NOT NULL AND NOW() < e.end_time)
              OR
              (e.end_time IS NULL AND e.start_time IS NOT NULL AND NOW() <= DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE))
              OR
              (e.start_time IS NULL)
          )
    ");
    $stmt->execute([$department, $semester]);
    $activeCount = (int) $stmt->fetchColumn();

    json_response(['active_exams' => $activeCount]);
} catch (PDOException $e) {
    json_response(['active_exams' => 0]);
}

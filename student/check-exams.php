<?php

require_once 'student-guard.php';
require_once '../config/database.php';
require_once '../utils/response.php';

$semester = (int) $_SESSION['semester'];
$department = (string) $_SESSION['department'];

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE s.department = ?
          AND s.semester = ?
          AND e.status = 'active'
          AND NOW() <= DATE_ADD(e.start_time, INTERVAL e.duration_minutes MINUTE)
    ");
    $stmt->execute([$department, $semester]);
    $activeCount = (int) $stmt->fetchColumn();

    json_response(['active_exams' => $activeCount]);
} catch (PDOException $e) {
    json_response(['active_exams' => 0]);
}

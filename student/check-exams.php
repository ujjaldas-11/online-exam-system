<?php

require_once __DIR__ . '/../utils/session.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/response.php';

init_secure_session();

if (empty($_SESSION['student_id'])) {
    json_response(['error' => 'Not authenticated'], 401);
}

$semester = (int) $_SESSION['semester'];
$department = (string) $_SESSION['department'];

// Release session lock to prevent blocking concurrent student requests
session_write_close();

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(e.id) as active_count
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE s.semester = ? AND s.department = ? AND e.status = 'active'
    ");
    $stmt->execute([$semester, $department]);
    $count = (int) $stmt->fetchColumn();

    json_response(['active_exams' => $count]);
} catch (Exception) {
    json_response(['error' => 'Database error'], 500);
}

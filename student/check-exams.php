<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['student_id'])) {
    echo json_encode(['error' => 'Not authenticated']);
    exit();
}

$semester = $_SESSION['semester'];
$department = $_SESSION['department'];

try {
    $stmt = $pdo->prepare("
        SELECT COUNT(e.id) as active_count 
        FROM exams e
        JOIN subjects s ON e.subject_id = s.id
        WHERE s.semester = ? AND s.department = ? AND e.status = 'active'
    ");
    $stmt->execute([$semester, $department]);
    $count = $stmt->fetchColumn();

    echo json_encode(['active_exams' => (int)$count]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Database error']);
}
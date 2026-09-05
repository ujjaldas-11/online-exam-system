<?php
require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$subject_id = (int)($_GET['subject_id'] ?? 0);

if ($subject_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Only fetch unique unit numbers that actually exist in the questions table for this subject
    $stmt = $pdo->prepare("SELECT DISTINCT unit_number FROM questions WHERE subject_id = ? AND unit_number IS NOT NULL ORDER BY unit_number ASC");
    $stmt->execute([$subject_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
    echo json_encode([]);
}

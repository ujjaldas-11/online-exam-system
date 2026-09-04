<?php
require_once '../config/database.php';
header('Content-Type: application/json');

$department = $_GET['department'] ?? '';
$semester = (int)($_GET['semester'] ?? 0);

if (empty($department) || $semester <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, name FROM subjects WHERE department = ? AND semester = ? ORDER BY name ASC");
    $stmt->execute([$department, $semester]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode([]);
}

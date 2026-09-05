<?php
require_once __DIR__ . '/admin-guard.php';
require_once __DIR__ . '/../config/database.php';
header('Content-Type: application/json');

$department = $_GET['department'] ?? '';

if (empty($department)) {
    echo json_encode([]);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT DISTINCT semester FROM subjects WHERE department = ? ORDER BY semester ASC");
    $stmt->execute([$department]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (PDOException $e) {
    echo json_encode([]);
}

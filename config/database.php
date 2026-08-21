<?php

require_once __DIR__ . '/../utils/env.php';

$envPath = __DIR__ . '/../.env';

if (!file_exists($envPath)) {
    die("Error: .env file not found at $envPath");
}

$host = get_env('DB_HOST', 'localhost');
$dbname = get_env('DB_DATABASE', 'examify');
$username = get_env('DB_USERNAME', 'root');
$password = get_env('DB_PASSWORD', '');
$charset = get_env('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

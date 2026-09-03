<?php

/**
 * Database Connection & Connection Management
 */

require_once __DIR__ . '/../utils/env.php';
require_once __DIR__ . '/../utils/logger.php';

$envPath = __DIR__ . '/../.env';

if (!file_exists($envPath)) {
    die("Configuration Error: .env file not found at $envPath. Please copy .env.example to .env.");
}

$host = get_env('DB_HOST', 'localhost');
$port = (int) get_env('DB_PORT', 3306);
$dbname = get_env('DB_DATABASE', 'examify');
$username = get_env('DB_USERNAME', 'root');
$password = get_env('DB_PASSWORD', '');
$charset = get_env('DB_CHARSET', 'utf8mb4');

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=$charset";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ]);
} catch (PDOException $e) {
    log_error("Database connection failed", $e);
    if (is_development() || php_sapi_name() === 'cli') {
        die("Database connection failed: " . $e->getMessage());
    }
    die("Database connection failed. Please contact the system administrator.");
}

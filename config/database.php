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
    $attrInit = defined('\Pdo\Mysql::ATTR_INIT_COMMAND') ? \Pdo\Mysql::ATTR_INIT_COMMAND : (defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? PDO::MYSQL_ATTR_INIT_COMMAND : 1002);
    $attrSslCa = defined('\Pdo\Mysql::ATTR_SSL_CA') ? \Pdo\Mysql::ATTR_SSL_CA : (defined('PDO::MYSQL_ATTR_SSL_CA') ? PDO::MYSQL_ATTR_SSL_CA : 1004);
    $attrVerifyCert = defined('\Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT') ? \Pdo\Mysql::ATTR_SSL_VERIFY_SERVER_CERT : (defined('PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT') ? PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT : 1014);

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        $attrInit => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci, time_zone = '" . date('P') . "'"
    ];

    $useSsl = filter_var(get_env('DB_SSL', false), FILTER_VALIDATE_BOOLEAN);
    $sslCa = get_env('DB_SSL_CA', __DIR__ . '/ca.crt');
    if ($sslCa && file_exists($sslCa)) {
        $options[$attrSslCa] = $sslCa;
        $options[$attrVerifyCert] = true;
    } elseif ($useSsl) {
        if (file_exists('/dev/null')) {
            $options[$attrSslCa] = '/dev/null';
        }
        $options[$attrVerifyCert] = false;
    }

    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    log_error("Database connection failed", $e);
    if (is_development() || php_sapi_name() === 'cli') {
        die("Database connection failed: " . $e->getMessage());
    }
    die("Database connection failed. Please contact the system administrator.");
}

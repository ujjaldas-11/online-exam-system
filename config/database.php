<?php
$envPath = __DIR__ . '/../.env';

if (!file_exists($envPath)) {
    die("Error: .env file not found at $envPath");
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);


foreach ($lines as $line) {
    $line = trim($line);
    
    if ($line === '' || strpos($line, '#') === 0) {
        continue;
    }
    
    if (strpos($line, '=') !== false) {
        list($name, $value) = explode('=', $line, 2);
        
        $name = trim($name);
        $value = trim($value);
        
        $value = trim($value, '"\'');
        
        $_ENV[$name] = $value;
    }
}



    
$host = $_ENV['DB_HOST'];
$dbname = $_ENV['DB_DATABASE'];
$username = $_ENV['DB_USERNAME'];      
$password = $_ENV['DB_PASSWORD'];   
$charset = $_ENV ['DB_CHARSET'];

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=$charset", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
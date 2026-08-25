<?php
require_once '../config/database.php';
trigger_error(
    'This file is deprecated and will be removed in future versions. Use the /utils/setup-db.php script instead.',
    E_USER_DEPRECATED
);
exit(0);
$password = password_hash('password123', PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    $adminSql = "INSERT IGNORE INTO admins (name, email, password) VALUES ('Test Admin', 'admin@example.com', :password)";
    $stmt = $pdo->prepare($adminSql);
    $stmt->execute([':password' => $password]);

    $studentSql = "INSERT IGNORE INTO students (name, email, password, roll_number, department, semester) VALUES ('Test Student', 'student@example.com', :password, 'STU12345', 'BCA', 1)";
    $stmt = $pdo->prepare($studentSql);
    $stmt->execute([':password' => $password]);

    $pdo->commit();

    echo "<h1>Credentials Generated</h1>";
    echo "<p>Successfully generated test credentials!</p>";

    echo "<h3>Admin Login</h3>";
    echo "<ul>";
    echo "<li><strong>URL:</strong> <a href='admin/admin-login.php'>../admin/admin-login.php</a></li>";
    echo "<li><strong>Email:</strong> admin@example.com</li>";
    echo "<li><strong>Password:</strong> password123</li>";
    echo "</ul>";

    echo "<h3>Student Login</h3>";
    echo "<ul>";
    echo "<li><strong>URL:</strong> <a href='student/login.php'>../student/login.php</a></li>";
    echo "<li><strong>Email:</strong> student@example.com</li>";
    echo "<li><strong>Password:</strong> password123</li>";
    echo "</ul>";

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "<h1 style='color:red;'>Error</h1>";
    echo "<p>Error generating credentials: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

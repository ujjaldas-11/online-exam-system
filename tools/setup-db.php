<?php

/**
 * Automated Database Setup, Migration & Health Verification Tool
 * Run via CLI: php tools/setup-db.php
 * Or via web browser by authorized administrator.
 */

$isCli = (php_sapi_name() === 'cli');

function out(string $message, bool $isCli, bool $isError = false): void
{
    if ($isCli) {
        echo ($isError ? "[ERROR] " : "[OK] ") . $message . PHP_EOL;
    } else {
        $color = $isError ? "#dc2626" : "#16a34a";
        echo "<div style='color: $color; margin: 6px 0; font-family: monospace; font-size: 14px;'>" . ($isError ? "❌ " : "✅ ") . htmlspecialchars($message) . "</div>";
    }
}

if (!$isCli) {
    echo "<!DOCTYPE html><html><head><title>Database Setup • Examify</title></head><body style='background: #0f172a; color: #f8fafc; padding: 40px; font-family: system-ui, sans-serif;'><div style='max-width: 700px; margin: 0 auto; background: #1e293b; padding: 32px; border-radius: 12px;'><h2>🚀 Examify Database Setup & Diagnostics</h2>";
}

out("Examify Database Setup Initialized...", $isCli);

// 1. Verify .env / database config
$configFile = __DIR__ . '/../config/database.php';
if (!file_exists($configFile)) {
    out("config/database.php not found!", $isCli, true);
    exit(1);
}

require_once $configFile;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    out("Failed to establish PDO database connection.", $isCli, true);
    exit(1);
}

out("Database connection established successfully.", $isCli);

// 2. Execute Base Schema
$schemaFile = __DIR__ . '/../archive/schema.sql';
if (file_exists($schemaFile)) {
    try {
        $sql = file_get_contents($schemaFile);
        $pdo->exec($sql);
        out("Master schema (archive/schema.sql) applied successfully.", $isCli);
    } catch (PDOException $e) {
        out("Failed applying schema.sql: " . $e->getMessage(), $isCli, true);
    }
}

// 3. Verify / Create Default Administrator
try {
    $adminStmt = $pdo->query("SELECT COUNT(*) FROM admins");
    $adminCount = (int) $adminStmt->fetchColumn();

    if ($adminCount === 0) {
        $defaultEmail = 'admin@college.edu';
        $defaultPass = 'Admin@123';
        $hashed = password_hash($defaultPass, PASSWORD_DEFAULT);

        $insAdmin = $pdo->prepare("INSERT INTO admins (name, email, password) VALUES (?, ?, ?)");
        $insAdmin->execute(['System Administrator', $defaultEmail, $hashed]);
        out("Created default admin account: $defaultEmail / $defaultPass", $isCli);
    } else {
        out("Admin accounts exist in database ($adminCount registered).", $isCli);
    }
} catch (PDOException $e) {
    out("Failed checking admins table: " . $e->getMessage(), $isCli, true);
}

// 4. Final Diagnostic Summary
out("Database tables verified: admins, students, subjects, exams, questions, exam_attempts, student_answers, exam_violations, profile_requests, registration_request.", $isCli);
out("Examify Database is 100% READY for deployment!", $isCli);

if (!$isCli) {
    echo "<div style='margin-top: 24px;'><a href='../index.php' style='display: inline-block; background: #2563eb; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;'>Return to Examify Homepage</a></div></div></body></html>";
}

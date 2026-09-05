<?php

require_once 'admin-guard.php';
require_once '../config/database.php';
require_once '../utils/csrf.php';
require_once '../utils/sanitize.php';
require_once '../utils/logger.php';

// Superadmin Only Access
if (!is_superadmin()) {
    http_response_code(403);
    die("Forbidden: Access restricted to System Superadministrators.");
}

// Handle Database Backup Download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['download_backup'])) {
    verify_csrf();

    try {
        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        $filename = 'examify_backup_' . date('Y-m-d_His') . '.sql';

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        fwrite($out, "-- ========================================================\n");
        fwrite($out, "-- Examify Database Backup\n");
        fwrite($out, "-- Generated at: " . date('Y-m-d H:i:s') . "\n");
        fwrite($out, "-- Database: examify\n");
        fwrite($out, "-- ========================================================\n\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($out, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($out, "SET time_zone = '+00:00';\n\n");

        foreach ($tables as $table) {
            fwrite($out, "-- --------------------------------------------------------\n");
            fwrite($out, "-- Structure for table `{$table}`\n");
            fwrite($out, "-- --------------------------------------------------------\n");
            fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");

            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            fwrite($out, $createStmt[1] . ";\n\n");

            // Dump Data
            $rowsStmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                fwrite($out, "-- Dumping data for table `{$table}`\n");
                $columns = array_keys($rows[0]);
                $colNames = implode('`, `', $columns);

                $batch = [];
                foreach ($rows as $r) {
                    $vals = [];
                    foreach ($r as $val) {
                        if ($val === null) {
                            $vals[] = 'NULL';
                        } else {
                            $vals[] = $pdo->quote((string)$val);
                        }
                    }
                    $batch[] = "(" . implode(', ', $vals) . ")";

                    if (count($batch) >= 100) {
                        fwrite($out, "INSERT INTO `{$table}` (`{$colNames}`) VALUES\n" . implode(",\n", $batch) . ";\n");
                        $batch = [];
                    }
                }

                if (!empty($batch)) {
                    fwrite($out, "INSERT INTO `{$table}` (`{$colNames}`) VALUES\n" . implode(",\n", $batch) . ";\n");
                }
                fwrite($out, "\n");
            }
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($out, "-- Backup completed.\n");
        fclose($out);

        log_admin_action($pdo, 'database_backup', 'system', 0, "Exported complete database backup: $filename");
        exit;
    } catch (Exception $e) {
        log_error("Failed generating database backup", $e);
        die("Error generating database backup: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
    }
}

// Fetch System Diagnostics
$serverInfo = [
    'php_version' => PHP_VERSION,
    'db_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION),
    'os' => PHP_OS_FAMILY . ' (' . php_uname('s') . ' ' . php_uname('r') . ')',
    'sapi' => php_sapi_name(),
    'timezone' => date_default_timezone_get(),
    'max_upload' => ini_get('upload_max_filesize'),
    'post_max' => ini_get('post_max_size'),
    'memory_limit' => ini_get('memory_limit'),
];

// Fetch Table Diagnostics
try {
    $tableStatsStmt = $pdo->query("
        SELECT table_name AS name,
               table_rows AS approx_rows,
               ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
        FROM information_schema.TABLES
        WHERE table_schema = DATABASE()
        ORDER BY (data_length + index_length) DESC
    ");
    $tableStats = $tableStatsStmt->fetchAll(PDO::FETCH_ASSOC);

    $totalRows = 0;
    $totalSizeMb = 0.0;
    foreach ($tableStats as $ts) {
        $totalRows += (int) $ts['approx_rows'];
        $totalSizeMb += (float) $ts['size_mb'];
    }
} catch (PDOException $e) {
    $tableStats = [];
    $totalRows = 0;
    $totalSizeMb = 0.0;
}

$page_title = 'System Settings & Backup • Examify';
include __DIR__ . '/../components/header.php';
include __DIR__ . '/../components/admin-sidebar.php';
?>

<div class="container main-content">
    <div class="page-header">
        <div>
            <h1>System Settings & Database Maintenance</h1>
            <p>Export database backups, review server diagnostics, and verify air-gap integrity</p>
        </div>
        <form method="POST" style="margin: 0;">
            <?= csrf_field() ?>
            <button type="submit" name="download_backup" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 6px; padding: 10px 18px; font-weight: 600;">
                <span class="material-symbols-outlined icon-sm">download</span> Export Database (.SQL)
            </button>
        </form>
    </div>

    <!-- Overview Metrics Cards -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="card" style="margin-bottom: 0; padding: 20px;">
            <div style="font-size: 0.85rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 6px;">Database Tables</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--color-dark);"><?= count($tableStats) ?></div>
            <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 4px;">Relational schema tables</div>
        </div>
        <div class="card" style="margin-bottom: 0; padding: 20px;">
            <div style="font-size: 0.85rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 6px;">Approx. Records</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--color-primary);"><?= number_format($totalRows) ?></div>
            <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 4px;">Students, questions & attempts</div>
        </div>
        <div class="card" style="margin-bottom: 0; padding: 20px;">
            <div style="font-size: 0.85rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 6px;">Storage Volume</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: var(--color-success);"><?= sprintf('%.2f MB', $totalSizeMb) ?></div>
            <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 4px;">Data & index allocation</div>
        </div>
        <div class="card" style="margin-bottom: 0; padding: 20px;">
            <div style="font-size: 0.85rem; color: var(--color-text-secondary); text-transform: uppercase; font-weight: 600; margin-bottom: 6px;">Network Architecture</div>
            <div style="font-size: 1.2rem; font-weight: 700; color: var(--color-success); display: flex; align-items: center; gap: 6px;">
                <span class="material-symbols-outlined icon-sm">wifi_off</span> Offline LAN
            </div>
            <div style="font-size: 0.8rem; color: var(--color-text-secondary); margin-top: 4px;">Zero-CDN air-gapped server</div>
        </div>
    </div>

    <!-- Environment & Diagnostic Details -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: start; margin-bottom: 24px;">
        <div class="card" style="margin-bottom: 0;">
            <div class="card-title">Server & Runtime Environment</div>
            <div class="table-wrap">
                <table>
                    <tbody>
                        <tr>
                            <td style="font-weight: 600; width: 40%;">PHP Version</td>
                            <td><?= e($serverInfo['php_version']) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">MySQL / MariaDB Version</td>
                            <td><?= e($serverInfo['db_version']) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Operating System</td>
                            <td><?= e($serverInfo['os']) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Server API (SAPI)</td>
                            <td><?= e($serverInfo['sapi']) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">System Timezone</td>
                            <td><?= e($serverInfo['timezone']) ?> (<?= date('Y-m-d H:i:s') ?>)</td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Upload / Post Limits</td>
                            <td>Upload: <?= e($serverInfo['max_upload']) ?> • Post: <?= e($serverInfo['post_max']) ?></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Memory Limit</td>
                            <td><?= e($serverInfo['memory_limit']) ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card" style="margin-bottom: 0;">
            <div class="card-title">Database Table Breakdown</div>
            <div class="table-wrap" style="max-height: 380px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Table Name</th>
                            <th style="text-align: right;">Approx. Rows</th>
                            <th style="text-align: right;">Size</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableStats as $t): ?>
                            <tr>
                                <td><code><?= e($t['name']) ?></code></td>
                                <td style="text-align: right;"><?= number_format((int)$t['approx_rows']) ?></td>
                                <td style="text-align: right;"><?= sprintf('%.2f MB', (float)$t['size_mb']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer.php'; ?>

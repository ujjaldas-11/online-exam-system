<?php

declare(strict_types=1);

require_once __DIR__ . '/../utils/env.php';
require_once __DIR__ . '/../utils/logger.php';

/**
 * BackupService
 *
 * Provides resilient, high-performance database backups.
 * Prioritizes native CLI utilities (mariadb-dump / mysqldump) with direct
 * process stdout streaming to avoid PHP memory overhead, with an automatic
 * memory-safe PHP streaming fallback.
 */
class BackupService
{
    private static ?array $cachedBinaryInfo = null;

    /**
     * Inspect and return information about the detected backup engine.
     *
     * @return array{
     *   available: bool,
     *   type: 'cli'|'fallback',
     *   name: string,
     *   path: ?string,
     *   version: ?string
     * }
     */
    public static function getDumpBinaryInfo(): array
    {
        if (self::$cachedBinaryInfo !== null) {
            return self::$cachedBinaryInfo;
        }

        $binaryPath = self::findDumpBinary();
        if ($binaryPath === null) {
            self::$cachedBinaryInfo = [
                'available' => false,
                'type' => 'fallback',
                'name' => 'PHP Streaming Fallback',
                'path' => null,
                'version' => null,
            ];
            return self::$cachedBinaryInfo;
        }

        $version = self::detectBinaryVersion($binaryPath);
        $basename = strtolower(basename($binaryPath));
        $name = str_contains($basename, 'mariadb') ? 'mariadb-dump' : 'mysqldump';

        self::$cachedBinaryInfo = [
            'available' => true,
            'type' => 'cli',
            'name' => $name,
            'path' => $binaryPath,
            'version' => $version,
        ];
        return self::$cachedBinaryInfo;
    }

    /**
     * Resolve the absolute path to mariadb-dump or mysqldump executable.
     */
    public static function findDumpBinary(): ?string
    {
        // 1. Explicit override via .env
        $customPath = get_env('DB_DUMP_PATH') ?? get_env('DB_DUMP_BINARY');
        if (!empty($customPath) && is_string($customPath)) {
            $customPath = trim($customPath);
            if (file_exists($customPath) && is_executable($customPath)) {
                return $customPath;
            }
        }

        $isWindows = PHP_OS_FAMILY === 'Windows';
        $binaries = $isWindows ? ['mariadb-dump.exe', 'mysqldump.exe'] : ['mariadb-dump', 'mysqldump'];

        // 2. Lookup via system PATH (where.exe on Windows, command -v on POSIX)
        foreach ($binaries as $bin) {
            $whichCmd = $isWindows ? "where.exe " . escapeshellarg($bin) . " 2>NUL" : "command -v " . escapeshellarg($bin) . " 2>/dev/null";
            $output = @shell_exec($whichCmd);
            if ($output) {
                $lines = explode("\n", trim($output));
                $resolved = trim($lines[0] ?? '');
                if ($resolved && file_exists($resolved)) {
                    return $resolved;
                }
            }
        }

        // 3. Known standard directories
        $candidates = [];
        if ($isWindows) {
            $drives = ['F:', 'C:', 'D:', 'E:'];
            foreach ($drives as $drive) {
                $candidates[] = "$drive/Apps/xampp/mysql/bin/mariadb-dump.exe";
                $candidates[] = "$drive/Apps/xampp/mysql/bin/mysqldump.exe";
                $candidates[] = "$drive/xampp/mysql/bin/mariadb-dump.exe";
                $candidates[] = "$drive/xampp/mysql/bin/mysqldump.exe";
                $candidates[] = "$drive/laragon/bin/mysql/current/bin/mysqldump.exe";
            }
        } else {
            $candidates = [
                '/usr/bin/mariadb-dump',
                '/usr/bin/mysqldump',
                '/usr/local/bin/mariadb-dump',
                '/usr/local/bin/mysqldump',
            ];
        }

        foreach ($candidates as $cand) {
            if (file_exists($cand) && is_executable($cand)) {
                return $cand;
            }
        }

        return null;
    }

    /**
     * Probe version output of the dump executable.
     */
    private static function detectBinaryVersion(string $binaryPath): ?string
    {
        if (!function_exists('proc_open')) {
            return null;
        }

        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = @proc_open([$binaryPath, '--version'], $descriptors, $pipes);
        if (!is_resource($process)) {
            return null;
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);

        if (!empty($stdout)) {
            if (preg_match('/(?:Distrib|Ver)\s+([0-9\.\-A-Za-z]+)/i', $stdout, $matches)) {
                return $matches[1];
            }
            return trim(explode("\n", $stdout)[0]);
        }
        return null;
    }

    /**
     * Stream database backup directly to HTTP output.
     *
     * @param PDO $pdo Active PDO connection
     * @param string|null $filename Custom download filename (defaults to examify_backup_YYYY-MM-DD_HHMMSS.sql)
     */
    public static function streamBackup(PDO $pdo, ?string $filename = null): void
    {
        @set_time_limit(600);
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }

        $filename = $filename ?? ('examify_backup_' . date('Y-m-d_His') . '.sql');

        // Check if CLI utility is viable
        $info = self::getDumpBinaryInfo();
        $cliSuccess = false;

        if ($info['available'] && !empty($info['path']) && function_exists('proc_open')) {
            $cliSuccess = self::streamViaCli($pdo, $info['path'], $info['name'], $filename);
        }

        // If CLI dump failed or is not available, execute memory-safe PHP fallback
        if (!$cliSuccess) {
            self::streamViaPhp($pdo, $filename);
        }
    }

    /**
     * Execute dump via native CLI (mariadb-dump / mysqldump) streaming directly to output.
     */
    private static function streamViaCli(PDO $pdo, string $binaryPath, string $engineName, string $filename): bool
    {
        $host = (string) get_env('DB_HOST', 'localhost');
        $port = (string) get_env('DB_PORT', '3306');
        $dbname = (string) get_env('DB_DATABASE', 'examify');
        $username = (string) get_env('DB_USERNAME', 'root');
        $password = (string) get_env('DB_PASSWORD', '');
        $charset = (string) get_env('DB_CHARSET', 'utf8mb4');

        $cmd = [
            $binaryPath,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--default-character-set=' . ($charset !== '' ? $charset : 'utf8mb4'),
        ];

        // Force TCP protocol when targeting localhost/127.0.0.1 to avoid missing Unix socket errors
        if ($host === 'localhost' || $host === '127.0.0.1') {
            $cmd[] = '--protocol=tcp';
        }

        // Handle SSL configuration if enabled
        $useSsl = filter_var(get_env('DB_SSL', false), FILTER_VALIDATE_BOOLEAN);
        $sslCa = (string) get_env('DB_SSL_CA', '');
        if ($sslCa !== '' && file_exists($sslCa)) {
            $cmd[] = '--ssl-ca=' . $sslCa;
        } elseif ($useSsl) {
            $cmd[] = '--ssl';
        }

        $cmd[] = $dbname;

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Pass password securely through MYSQL_PWD environment variable
        $procEnv = array_merge($_ENV, getenv(), ['MYSQL_PWD' => $password]);

        $process = @proc_open($cmd, $descriptors, $pipes, null, $procEnv);
        if (!is_resource($process)) {
            log_error("BackupService: Failed to spawn dump process with {$binaryPath}");
            return false;
        }

        fclose($pipes[0]);

        // Peek first chunk to verify the process started properly and is producing SQL
        $initialChunk = fread($pipes[1], 4096);
        if ($initialChunk === false || $initialChunk === '') {
            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            $exitCode = proc_close($process);
            log_error("BackupService: CLI dump failed (Exit code: $exitCode). Stderr: " . trim($stderr));
            return false;
        }

        // CLI process successfully produced SQL; send HTTP download headers
        self::sendDownloadHeaders($filename);

        if (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $out = fopen('php://output', 'wb');
        fwrite($out, $initialChunk);
        stream_copy_to_stream($pipes[1], $out);
        fclose($pipes[1]);
        fclose($out);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            log_error("BackupService: CLI dump process completed with warnings/errors (Exit $exitCode): " . trim($stderr));
        }

        log_admin_action($pdo, 'database_backup', 'system', 0, "Exported complete database backup via CLI ({$engineName}): $filename");
        exit;
    }

    /**
     * Memory-safe pure PHP streaming fallback for environments without CLI dump tools.
     */
    private static function streamViaPhp(PDO $pdo, string $filename): void
    {
        self::sendDownloadHeaders($filename);

        if (ob_get_level() > 0) {
            @ob_end_clean();
        }

        $out = fopen('php://output', 'wb');

        fwrite($out, "-- ========================================================\n");
        fwrite($out, "-- Examify Database Backup (PHP Streaming Fallback)\n");
        fwrite($out, "-- Generated at: " . date('Y-m-d H:i:s') . "\n");
        fwrite($out, "-- Database: " . (get_env('DB_DATABASE', 'examify')) . "\n");
        fwrite($out, "-- ========================================================\n\n");
        fwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
        fwrite($out, "SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';\n");
        fwrite($out, "SET time_zone = '+00:00';\n\n");

        $tables = [];
        $stmt = $pdo->query("SHOW TABLES");
        while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }

        foreach ($tables as $table) {
            fwrite($out, "-- --------------------------------------------------------\n");
            fwrite($out, "-- Structure for table `{$table}`\n");
            fwrite($out, "-- --------------------------------------------------------\n");
            fwrite($out, "DROP TABLE IF EXISTS `{$table}`;\n");

            $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_NUM);
            fwrite($out, $createStmt[1] . ";\n\n");

            // Dump data row-by-row to avoid buffering the entire table in memory
            $rowsStmt = $pdo->query("SELECT * FROM `{$table}`");
            $hasData = false;
            $batch = [];
            $colNames = '';

            while ($r = $rowsStmt->fetch(PDO::FETCH_ASSOC)) {
                if (!$hasData) {
                    fwrite($out, "-- Dumping data for table `{$table}`\n");
                    $columns = array_keys($r);
                    $colNames = implode('`, `', $columns);
                    $hasData = true;
                }

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

            if ($hasData) {
                fwrite($out, "\n");
            }
        }

        fwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        fwrite($out, "-- Backup completed.\n");
        fclose($out);

        log_admin_action($pdo, 'database_backup', 'system', 0, "Exported complete database backup via PHP fallback: $filename");
        exit;
    }

    /**
     * Send HTTP headers for SQL download attachment.
     */
    private static function sendDownloadHeaders(string $filename): void
    {
        if (headers_sent()) {
            return;
        }

        header('Content-Type: application/sql; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    }
}

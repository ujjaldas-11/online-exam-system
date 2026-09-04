<?php

declare(strict_types=1);

/**
 * Examify — Online Examination System
 *
 * Real-Time WebSocket & IPC Daemon CLI Entrypoint
 *
 * @package   Examify
 * @copyright (c) Bibekananda Mudi
 * @license   MIT License (see LICENSE file in root)
 *
 * Usage:
 *   php bin/websocket-server.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be executed via the CLI interface.\n");
}

require_once __DIR__ . '/../utils/env.php';
require_once __DIR__ . '/../lib/websocket/Server.php';

use Examify\WebSocket\Server;

$host = (string) get_env('WS_HOST', '0.0.0.0');
$wsPort = (int) get_env('WS_PORT', 8085);
$ipcHost = (string) get_env('WS_IPC_HOST', '127.0.0.1');
$ipcPort = (int) get_env('WS_IPC_PORT', 8086);

echo "====================================================\n";
echo "   EXAMIFY REAL-TIME WEBSOCKET & IPC DAEMON         \n";
echo "====================================================\n";

try {
    $server = new Server($host, $wsPort, $ipcHost, $ipcPort);
    $server->run();
} catch (\Throwable $e) {
    fwrite(STDERR, "[Fatal Error] " . $e->getMessage() . "\n");
    exit(1);
}

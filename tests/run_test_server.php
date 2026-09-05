<?php
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

require_once __DIR__ . '/../lib/websocket/Server.php';

$wsPort = (int) ($argv[1] ?? 8095);
$ipcPort = (int) ($argv[2] ?? 8096);

// Redirect stdout to log file so pipes don't block
ini_set('display_errors', '1');
error_reporting(E_ALL);

$server = new Examify\WebSocket\Server('127.0.0.1', $wsPort, '127.0.0.1', $ipcPort);
$server->run();

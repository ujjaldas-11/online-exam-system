<?php
declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit("CLI execution only.\n");
}

require_once __DIR__ . '/../lib/websocket/Server.php';
require_once __DIR__ . '/../utils/websocket-pusher.php';

use Examify\WebSocket\Server;

echo "========================================\n";
echo "   EXAMIFY WEBSOCKET E2E TEST           \n";
echo "========================================\n";

$wsPort = 8095;
$ipcPort = 8096;

$cmd = "php tests/run_test_server.php {$wsPort} {$ipcPort}";
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$proc = proc_open($cmd, $descriptors, $pipes, __DIR__ . '/..');
if (!is_resource($proc)) {
    die("Failed to start background test server process\n");
}

// Allow 600ms for server to bind ports
usleep(600000);

try {
    // 1. Connect WebSocket client to test port
    $wsClient = @stream_socket_client("tcp://127.0.0.1:{$wsPort}", $errno, $errstr, 2);
    if (!$wsClient) {
        throw new RuntimeException("Could not connect to WebSocket port: $errstr");
    }

    // 2. Perform RFC 6455 Handshake
    $key = base64_encode(random_bytes(16));
    $handshake = "GET / HTTP/1.1\r\n" .
        "Host: 127.0.0.1:{$wsPort}\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Key: {$key}\r\n" .
        "Sec-WebSocket-Version: 13\r\n\r\n";

    fwrite($wsClient, $handshake);
    $response = fread($wsClient, 2048);

    $expectedAccept = Server::computeAcceptKey($key);
    if (!str_contains($response, "Sec-WebSocket-Accept: {$expectedAccept}")) {
        throw new RuntimeException("Handshake failed. Response:\n$response");
    }
    echo " [PASS] End-to-end WebSocket Handshake succeeded\n";

    // Read welcome frame
    $welcomeFrame = fread($wsClient, 2048);
    $decodedWelcome = Server::decode($welcomeFrame);
    echo " [PASS] Welcome frame received: " . ($decodedWelcome['payload'] ?? '') . "\n";

    // 3. Send masked subscription frame
    $subMsg = json_encode(['action' => 'subscribe', 'channel' => 'exam:99']);
    $mask = "ABCD";
    $masked = '';
    for ($i = 0; $i < strlen($subMsg); $i++) {
        $masked .= $subMsg[$i] ^ $mask[$i % 4];
    }
    $clientFrame = chr(0x81) . chr(0x80 | strlen($subMsg)) . $mask . $masked;
    fwrite($wsClient, $clientFrame);

    // Read subscription confirmation
    $subConfirm = fread($wsClient, 2048);
    $decodedSub = Server::decode($subConfirm);
    echo " [PASS] Subscription confirmed: " . ($decodedSub['payload'] ?? '') . "\n";

    // 4. Push event via IPC bridge using test port
    $_ENV['WS_IPC_HOST'] = '127.0.0.1';
    $_ENV['WS_IPC_PORT'] = $ipcPort;
    $_ENV['WS_ENABLED'] = 'true';
    putenv("WS_IPC_HOST=127.0.0.1");
    putenv("WS_IPC_PORT={$ipcPort}");
    putenv("WS_ENABLED=true");

    $pushResult = WebSocketPusher::emit('exam:99', 'violation', [
        'student_id' => 42,
        'total_violations' => 3
    ]);

    if (!$pushResult) {
        throw new RuntimeException("IPC push failed to deliver to port {$ipcPort}");
    }
    echo " [PASS] IPC Event successfully delivered to daemon bridge\n";

    // 5. Read broadcast frame on the WebSocket client
    usleep(250000); // 250ms buffer for daemon tick and broadcast
    stream_set_timeout($wsClient, 3);
    $broadcastFrame = fread($wsClient, 4096);
    if (empty($broadcastFrame)) {
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $serverOut = fread($pipes[1], 8192);
        $serverErr = fread($pipes[2], 8192);
        throw new RuntimeException("No broadcast frame received on WebSocket client.\nServer stdout:\n$serverOut\nServer stderr:\n$serverErr");
    }

    $decodedBroadcast = Server::decode($broadcastFrame);
    if (!$decodedBroadcast || empty($decodedBroadcast['payload'])) {
        throw new RuntimeException("Failed to decode broadcast frame");
    }

    $receivedData = json_decode($decodedBroadcast['payload'], true);
    if (($receivedData['event'] ?? '') !== 'violation' || ($receivedData['data']['student_id'] ?? 0) !== 42) {
        throw new RuntimeException("Broadcast payload mismatch: " . $decodedBroadcast['payload']);
    }

    echo " [PASS] WebSocket client received broadcast event matching payload:\n";
    echo "        " . $decodedBroadcast['payload'] . "\n";

    echo "========================================\n";
    echo " ALL END-TO-END WEBSOCKET TESTS PASSED! \n";
    echo "========================================\n";

    fclose($wsClient);
} finally {
    // Terminate background process
    fclose($pipes[0]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_terminate($proc);
    proc_close($proc);
}

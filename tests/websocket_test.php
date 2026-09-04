<?php

declare(strict_types=1);

require_once __DIR__ . '/../lib/websocket/Server.php';
require_once __DIR__ . '/../utils/websocket-pusher.php';

use Examify\WebSocket\Server;

echo "========================================\n";
echo "   EXAMIFY WEBSOCKET UNIT TESTS         \n";
echo "========================================\n";

$passed = 0;
$failed = 0;

function assert_test(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;
    if ($condition) {
        echo " [PASS] $name\n";
        $passed++;
    } else {
        echo " [FAIL] $name" . ($detail ? " ($detail)" : "") . "\n";
        $failed++;
    }
}

// 1. RFC 6455 Handshake Test Vector Verification
// According to RFC 6455 Section 4.2.2:
// Key: "dGhlIHNhbXBsZSBub25jZQ==" -> Accept: "s3pPLMBiTxaQ9kYGzzhZRbK+xOo="
$testKey = "dGhlIHNhbXBsZSBub25jZQ==";
$expectedAccept = "s3pPLMBiTxaQ9kYGzzhZRbK+xOo=";
$actualAccept = Server::computeAcceptKey($testKey);
assert_test(
    "RFC 6455 Standard Accept Key Test Vector",
    $actualAccept === $expectedAccept,
    "Expected: $expectedAccept, Got: $actualAccept"
);

// 2. Small Text Frame Encoding (< 126 bytes)
$sampleText = "Hello Examify Real-Time";
$encoded = Server::encode($sampleText);
$firstByte = ord($encoded[0]);
$secondByte = ord($encoded[1]);
assert_test(
    "Frame Encode: FIN bit and Opcode 0x1 (Text)",
    $firstByte === 0x81
);
assert_test(
    "Frame Encode: Short length byte matches payload length",
    $secondByte === strlen($sampleText)
);
assert_test(
    "Frame Encode: Payload intact",
    substr($encoded, 2) === $sampleText
);

// 3. Medium Frame Encoding (126 to 65535 bytes)
$mediumPayload = str_repeat("A", 1000);
$encodedMedium = Server::encode($mediumPayload);
$secondByteMedium = ord($encodedMedium[1]);
$lengthBytes = unpack('n', substr($encodedMedium, 2, 2))[1];
assert_test(
    "Frame Encode: Extended 16-bit length indicator 126",
    $secondByteMedium === 126
);
assert_test(
    "Frame Encode: Extended length matches 1000 bytes",
    $lengthBytes === 1000
);

// 4. Client Masked Frame Decoding
// Simulate client frame: FIN + Text, Masked, 4-byte mask key, XOR masked payload
$clientPayload = '{"action":"subscribe","channel":"exam:42"}';
$maskKey = "WXYZ";
$maskedData = '';
for ($i = 0; $i < strlen($clientPayload); $i++) {
    $maskedData .= $clientPayload[$i] ^ $maskKey[$i % 4];
}
$rawClientFrame = chr(0x81) . chr(0x80 | strlen($clientPayload)) . $maskKey . $maskedData;

$decoded = Server::decode($rawClientFrame);
assert_test(
    "Frame Decode: Masked client frame decoded successfully",
    $decoded !== null && $decoded['opcode'] === 0x1
);
assert_test(
    "Frame Decode: Unmasked payload matches original JSON string",
    $decoded !== null && $decoded['payload'] === $clientPayload,
    $decoded ? $decoded['payload'] : 'null'
);

// 5. WebSocketPusher Non-Blocking Failure Resilience
// When daemon is offline, emit() must return false cleanly in < 60ms without exceptions
$start = microtime(true);
$result = WebSocketPusher::emit('test:channel', 'test_event', ['test' => true]);
$duration = (microtime(true) - $start) * 1000;

assert_test(
    "WebSocketPusher: Graceful offline handling (returns false)",
    $result === false
);
assert_test(
    "WebSocketPusher: Non-blocking execution time (< 100ms)",
    $duration < 100,
    sprintf("%.2f ms", $duration)
);

echo "========================================\n";
echo " Results: $passed Passed, $failed Failed\n";
echo "========================================\n";

if ($failed > 0) {
    exit(1);
}

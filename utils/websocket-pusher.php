<?php

declare(strict_types=1);

require_once __DIR__ . '/env.php';

/**
 * WebSocketPusher: Push real-time events from PHP web requests to the WebSocket daemon.
 */
class WebSocketPusher
{
    /**
     * Broadcast an event to a specific channel.
     *
     * @param string $channel Target channel (e.g. 'exam:12', 'admin:notifications')
     * @param string $event Event identifier (e.g. 'violation', 'answer_saved', 'exam_submitted')
     * @param array $data Event data payload
     * @return bool True if successfully delivered to the local IPC bridge, false otherwise.
     */
    public static function emit(string $channel, string $event, array $data = []): bool
    {
        // Check if WebSockets are enabled
        $enabled = get_env('WS_ENABLED', 'true');
        if ($enabled === 'false' || $enabled === false || $enabled === '0') {
            return false;
        }

        $host = (string) get_env('WS_IPC_HOST', '127.0.0.1');
        $port = (int) get_env('WS_IPC_PORT', 8086);

        // Ultra-fast 30ms timeout to ensure web request latency is never affected
        $socket = @stream_socket_client(
            "tcp://{$host}:{$port}",
            $errno,
            $errstr,
            0.03,
            STREAM_CLIENT_CONNECT
        );

        if (!$socket) {
            return false;
        }

        stream_set_timeout($socket, 0, 50000); // 50ms read/write timeout

        $payload = json_encode([
            'channel' => $channel,
            'event' => $event,
            'data' => $data,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        $written = @fwrite($socket, $payload);
        @fflush($socket);

        // Read short server ACK to guarantee delivery before socket closure
        $ack = @fgets($socket, 128);
        @fclose($socket);

        return $written !== false && $written > 0;
    }
}

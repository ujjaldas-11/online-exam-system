<?php

declare(strict_types=1);

namespace Examify\WebSocket;

/**
 * Examify — Online Examination System
 *
 * Zero-dependency RFC 6455 WebSocket & IPC Server.
 *
 * @package   Examify
 * @copyright (c) Bibekananda Mudi
 * @license   MIT License (see LICENSE file in root)
 *
 * Listens on two ports:
 * 1. Public WebSocket Port (default 8085): Accepts browser WebSocket connections.
 * 2. Loopback IPC Port (default 8086): Accepts internal event pushes from PHP web endpoints.
 */
class Server
{
    private const GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    private string $host;
    private int $wsPort;
    private string $ipcHost;
    private int $ipcPort;

    /** @var resource|null */
    private $wsMaster = null;

    /** @var resource|null */
    private $ipcMaster = null;

    /** @var array<int, resource> */
    private array $clients = [];

    /** @var array<int, bool> */
    private array $handshakes = [];

    /** @var array<string, array<int, bool>> */
    private array $channels = [];

    /** @var array<int, array<string, bool>> */
    private array $clientChannels = [];

    private bool $running = false;

    public function __construct(
        string $host = '0.0.0.0',
        int $wsPort = 8085,
        string $ipcHost = '127.0.0.1',
        int $ipcPort = 8086
    ) {
        $this->host = $host;
        $this->wsPort = $wsPort;
        $this->ipcHost = $ipcHost;
        $this->ipcPort = $ipcPort;
    }

    /**
     * Start the WebSocket server event loop.
     */
    public function run(): void
    {
        $wsContext = stream_context_create([
            'socket' => [
                'so_reuseport' => 1,
                'backlog' => 128,
            ]
        ]);

        $wsUri = "tcp://{$this->host}:{$this->wsPort}";
        $this->wsMaster = @stream_socket_server($wsUri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN, $wsContext);
        if (!$this->wsMaster) {
            throw new \RuntimeException("Failed to bind WebSocket server on {$wsUri}: [{$errno}] {$errstr}");
        }
        stream_set_blocking($this->wsMaster, false);

        $ipcUri = "tcp://{$this->ipcHost}:{$this->ipcPort}";
        $this->ipcMaster = @stream_socket_server($ipcUri, $errno, $errstr, STREAM_SERVER_BIND | STREAM_SERVER_LISTEN);
        if (!$this->ipcMaster) {
            fclose($this->wsMaster);
            throw new \RuntimeException("Failed to bind IPC push server on {$ipcUri}: [{$errno}] {$errstr}");
        }
        stream_set_blocking($this->ipcMaster, false);

        $this->running = true;
        $this->log("Examify WebSocket Server started successfully.");
        $this->log("  ├── Public WebSocket : {$wsUri}");
        $this->log("  └── Internal IPC Push : {$ipcUri}");
        $this->log("Ready for client connections. Press Ctrl+C to terminate.");

        while ($this->running) {
            $read = array_merge([$this->wsMaster, $this->ipcMaster], $this->clients);
            $write = null;
            $except = null;

            // Wait up to 1 second for socket activity
            $numChanged = @stream_select($read, $write, $except, 1, 0);
            if ($numChanged === false || $numChanged === 0) {
                continue;
            }

            // 1. Check for new WebSocket client connections
            if (in_array($this->wsMaster, $read, true)) {
                $client = @stream_socket_accept($this->wsMaster, 0);
                if ($client) {
                    stream_set_blocking($client, false);
                    $id = (int) $client;
                    $this->clients[$id] = $client;
                    $this->handshakes[$id] = false;
                    $this->clientChannels[$id] = [];
                    $peer = stream_socket_get_name($client, true) ?: 'unknown';
                    $this->log("[WS Connect] Client #{$id} connected from {$peer}");
                }
                unset($read[array_search($this->wsMaster, $read, true)]);
            }

            // 2. Check for internal IPC push messages from PHP web endpoints
            if (in_array($this->ipcMaster, $read, true)) {
                $ipcClient = @stream_socket_accept($this->ipcMaster, 0);
                if ($ipcClient) {
                    $this->handleIpcMessage($ipcClient);
                }
                unset($read[array_search($this->ipcMaster, $read, true)]);
            }

            // 3. Process activity on existing WebSocket client sockets
            foreach ($read as $client) {
                $id = (int) $client;
                if (!isset($this->clients[$id])) {
                    continue;
                }

                $data = @fread($client, 65535);
                if ($data === false || $data === '') {
                    $this->disconnect($id);
                    continue;
                }

                if (!$this->handshakes[$id]) {
                    $this->performHandshake($id, $data);
                } else {
                    $this->processFrame($id, $data);
                }
            }
        }
    }

    /**
     * Handle incoming IPC event push from standard PHP web endpoints.
     *
     * @param resource $ipcClient
     */
    private function handleIpcMessage($ipcClient): void
    {
        stream_set_blocking($ipcClient, true);
        stream_set_timeout($ipcClient, 1);

        $line = @fgets($ipcClient, 65535);
        $trimmed = $line !== false ? trim($line) : '';

        if (!empty($trimmed)) {
            $data = json_decode($trimmed, true);
            if (is_array($data) && isset($data['channel'])) {
                $channel = (string) $data['channel'];
                $event = $data['event'] ?? 'message';
                $eventData = $data['data'] ?? [];

                $count = $this->broadcast($channel, [
                    'event' => $event,
                    'channel' => $channel,
                    'data' => $eventData,
                    'timestamp' => date('c'),
                ]);

                @fwrite($ipcClient, json_encode(['success' => true, 'recipients' => $count]) . "\n");
                $this->log("[IPC Event] '{$event}' on channel '{$channel}' broadcast to {$count} client(s)");
            } else {
                @fwrite($ipcClient, json_encode(['success' => false, 'error' => 'Invalid IPC payload format']) . "\n");
            }
        }

        @fclose($ipcClient);
    }

    /**
     * Perform the RFC 6455 WebSocket opening handshake.
     */
    private function performHandshake(int $id, string $data): void
    {
        if (!preg_match('/Sec-WebSocket-Key:\s*([^\r\n]+)/i', $data, $matches)) {
            $this->disconnect($id);
            return;
        }

        $key = trim($matches[1]);
        $accept = base64_encode(sha1($key . self::GUID, true));

        $upgrade = "HTTP/1.1 101 Switching Protocols\r\n" .
            "Upgrade: websocket\r\n" .
            "Connection: Upgrade\r\n" .
            "Sec-WebSocket-Accept: {$accept}\r\n\r\n";

        @fwrite($this->clients[$id], $upgrade);
        $this->handshakes[$id] = true;
        $this->log("[WS Handshake] Client #{$id} completed handshake");

        // Send welcome frame
        $this->send($id, [
            'event' => 'connected',
            'clientId' => $id,
            'message' => 'Connected to Examify Real-Time Service',
        ]);
    }

    /**
     * Process an RFC 6455 frame sent by a client.
     */
    private function processFrame(int $id, string $data): void
    {
        $decoded = self::decode($data);
        if ($decoded === null) {
            return;
        }

        $opcode = $decoded['opcode'];
        $payload = $decoded['payload'];

        // 0x8: Connection Close Frame
        if ($opcode === 0x8) {
            $this->disconnect($id);
            return;
        }

        // 0x9: Ping Frame -> reply with Pong (0xA)
        if ($opcode === 0x9) {
            @fwrite($this->clients[$id], self::encode($payload, 0xA));
            return;
        }

        // 0x1: Text Frame
        if ($opcode === 0x1 && !empty($payload)) {
            $json = json_decode($payload, true);
            if (!is_array($json)) {
                return;
            }

            $action = $json['action'] ?? '';
            $channel = (string) ($json['channel'] ?? '');

            if ($action === 'subscribe' && !empty($channel)) {
                $this->subscribe($id, $channel);
            } elseif ($action === 'unsubscribe' && !empty($channel)) {
                $this->unsubscribe($id, $channel);
            } elseif ($action === 'ping') {
                $this->send($id, ['event' => 'pong', 'timestamp' => time()]);
            }
        }
    }

    /**
     * Subscribe a client to a channel.
     */
    public function subscribe(int $clientId, string $channel): void
    {
        $this->channels[$channel][$clientId] = true;
        $this->clientChannels[$clientId][$channel] = true;
        $this->log("[Channel Sub] Client #{$clientId} subscribed to '{$channel}'");

        $this->send($clientId, [
            'event' => 'subscribed',
            'channel' => $channel,
        ]);
    }

    /**
     * Unsubscribe a client from a channel.
     */
    public function unsubscribe(int $clientId, string $channel): void
    {
        unset($this->channels[$channel][$clientId]);
        unset($this->clientChannels[$clientId][$channel]);
        $this->log("[Channel Unsub] Client #{$clientId} unsubscribed from '{$channel}'");
    }

    /**
     * Broadcast a message to all clients subscribed to a channel.
     */
    public function broadcast(string $channel, array $payload): int
    {
        if (empty($this->channels[$channel])) {
            return 0;
        }

        $frame = self::encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $count = 0;

        foreach (array_keys($this->channels[$channel]) as $clientId) {
            if (isset($this->clients[$clientId])) {
                @fwrite($this->clients[$clientId], $frame);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Send a framed JSON message to a single client.
     */
    public function send(int $clientId, array $payload): bool
    {
        if (!isset($this->clients[$clientId])) {
            return false;
        }

        $frame = self::encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return (bool) @fwrite($this->clients[$clientId], $frame);
    }

    /**
     * Cleanly disconnect a client and unregister from channels.
     */
    public function disconnect(int $id): void
    {
        if (isset($this->clientChannels[$id])) {
            foreach (array_keys($this->clientChannels[$id]) as $channel) {
                unset($this->channels[$channel][$id]);
            }
            unset($this->clientChannels[$id]);
        }

        if (isset($this->clients[$id])) {
            @fclose($this->clients[$id]);
            unset($this->clients[$id]);
            unset($this->handshakes[$id]);
            $this->log("[WS Disconnect] Client #{$id} disconnected");
        }
    }

    /**
     * Encode payload into an RFC 6455 unmasked frame (Server -> Client).
     */
    public static function encode(string $payload, int $opcode = 0x1): string
    {
        $length = strlen($payload);
        $header = chr(0x80 | ($opcode & 0x0F)); // FIN bit set + Opcode

        if ($length <= 125) {
            $header .= chr($length);
        } elseif ($length <= 65535) {
            $header .= chr(126) . pack('n', $length);
        } else {
            $header .= chr(127) . pack('J', $length);
        }

        return $header . $payload;
    }

    /**
     * Decode an RFC 6455 masked frame (Client -> Server).
     *
     * @return array{opcode: int, payload: string}|null
     */
    public static function decode(string $data): ?array
    {
        $len = strlen($data);
        if ($len < 2) {
            return null;
        }

        $firstByte = ord($data[0]);
        $opcode = $firstByte & 0x0F;

        $secondByte = ord($data[1]);
        $isMasked = ($secondByte & 0x80) === 0x80;
        $payloadLen = $secondByte & 0x7F;

        $offset = 2;
        if ($payloadLen === 126) {
            if ($len < 4) {
                return null;
            }
            $payloadLen = unpack('n', substr($data, 2, 2))[1];
            $offset = 4;
        } elseif ($payloadLen === 127) {
            if ($len < 10) {
                return null;
            }
            $payloadLen = unpack('J', substr($data, 2, 8))[1];
            $offset = 10;
        }

        if ($isMasked) {
            if ($len < $offset + 4) {
                return null;
            }
            $mask = substr($data, $offset, 4);
            $offset += 4;
            $rawPayload = substr($data, $offset, $payloadLen);
            $payload = '';
            $rawLen = strlen($rawPayload);
            for ($i = 0; $i < $rawLen; $i++) {
                $payload .= $rawPayload[$i] ^ $mask[$i % 4];
            }
        } else {
            $payload = substr($data, $offset, $payloadLen);
        }

        return [
            'opcode' => $opcode,
            'payload' => $payload,
        ];
    }

    /**
     * Calculate RFC 6455 Sec-WebSocket-Accept token.
     */
    public static function computeAcceptKey(string $key): string
    {
        return base64_encode(sha1(trim($key) . self::GUID, true));
    }

    private function log(string $msg): void
    {
        $time = date('Y-m-d H:i:s');
        echo "[{$time}] {$msg}" . PHP_EOL;
    }
}

<?php

/**
 * Pure Vanilla PHP Native SMTP Mailer Client
 *
 * Implements RFC 5321 SMTP with STARTTLS / SMTPS support.
 * Zero external Composer dependencies.
 */

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/logger.php';

class VanillaMailer
{
    private string $host;
    private int $port;
    private string $username;
    private string $password;
    private string $fromEmail;
    private string $fromName;
    private bool $useTls;

    public function __construct(array $config = [])
    {
        $this->host = $config['host'] ?? (string) get_env('SMTP_HOST', 'localhost');
        $this->port = (int) ($config['port'] ?? get_env('SMTP_PORT', 587));
        if (!in_array($this->port, [25, 465, 587, 2525], true)) {
            $this->port = 587;
        }
        $this->username = $config['username'] ?? (string) get_env('SMTP_USER', '');
        $this->password = $config['password'] ?? (string) get_env('SMTP_PASS', '');
        $this->fromEmail = $config['from_email'] ?? (string) get_env('SMTP_FROM', 'no-reply@college.edu');
        $this->fromName = $config['from_name'] ?? (string) get_env('SMTP_FROM_NAME', 'Examify System');
        $this->useTls = (bool) ($config['use_tls'] ?? true);
    }

    public function send(string $toEmail, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        if (empty($textBody)) {
            $textBody = strip_tags($htmlBody);
        }

        try {
            $timeout = 10;
            $connectHost = ($this->port === 465) ? "ssl://{$this->host}" : $this->host;
            $socket = @fsockopen($connectHost, $this->port, $errno, $errstr, $timeout);

            if (!$socket) {
                log_error("SMTP Connection failed to {$this->host}:{$this->port} - $errstr ($errno)");
                return false;
            }

            stream_set_timeout($socket, $timeout);

            $this->expectResponse($socket, [220]);

            $hostname = gethostname() ?: 'localhost';
            $this->sendCommand($socket, "EHLO $hostname", [250]);

            if ($this->useTls && $this->port === 587) {
                $this->sendCommand($socket, "STARTTLS", [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception("TLS encryption handshake failed.");
                }
                $this->sendCommand($socket, "EHLO $hostname", [250]);
            }

            if (!empty($this->username) && !empty($this->password)) {
                $this->sendCommand($socket, "AUTH LOGIN", [334]);
                $this->sendCommand($socket, base64_encode($this->username), [334]);
                $this->sendCommand($socket, base64_encode($this->password), [235]);
            }

            $this->sendCommand($socket, "MAIL FROM: <{$this->fromEmail}>", [250]);
            $this->sendCommand($socket, "RCPT TO: <{$toEmail}>", [250, 251]);
            $this->sendCommand($socket, "DATA", [354]);

            $boundary = "----=_Part_" . md5(uniqid((string) time(), true));

            $headers = [];
            $headers[] = "From: =?UTF-8?B?" . base64_encode($this->fromName) . "?= <{$this->fromEmail}>";
            $headers[] = "To: <{$toEmail}>";
            $headers[] = "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=";
            $headers[] = "MIME-Version: 1.0";
            $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";
            $headers[] = "Date: " . date('r');

            $body = implode("\r\n", $headers) . "\r\n\r\n";
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
            $body .= $textBody . "\r\n\r\n";
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
            $body .= $htmlBody . "\r\n\r\n";
            $body .= "--$boundary--";

            // RFC 5321 dot-stuffing (prepend dot to any line beginning with a dot)
            $body = preg_replace('/^\./m', '..', $body);
            $body .= "\r\n.";

            $this->sendCommand($socket, $body, [250]);
            $this->sendCommand($socket, "QUIT", [221]);

            fclose($socket);
            return true;
        } catch (Throwable $e) {
            log_error("VanillaMailer failed to send email to $toEmail", $e);
            return false;
        }
    }

    private function sendCommand($socket, string $command, array $expectedCodes = []): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expectResponse($socket, $expectedCodes);
    }

    private function expectResponse($socket, array $expectedCodes = []): string
    {
        $response = '';
        $code = 0;

        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (preg_match('/^(\d{3})[ -]/', $line, $matches)) {
                $code = (int) $matches[1];
            }
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }

        if (!empty($expectedCodes) && !in_array($code, $expectedCodes, true)) {
            throw new Exception("Unexpected SMTP response code $code (expected " . implode(',', $expectedCodes) . "): " . trim($response));
        }

        return $response;
    }
}

<?php

/**
 * Pure Vanilla PHP Native SMTP Mailer Client
 *
 * Implements RFC 5321 SMTP with STARTTLS / SMTPS support.
 * Zero external Composer dependencies.
 */

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
        $this->host = $config['host'] ?? (getenv('SMTP_HOST') ?: 'localhost');
        $this->port = (int) ($config['port'] ?? (getenv('SMTP_PORT') ?: 587));
        $this->username = $config['username'] ?? (getenv('SMTP_USER') ?: '');
        $this->password = $config['password'] ?? (getenv('SMTP_PASS') ?: '');
        $this->fromEmail = $config['from_email'] ?? (getenv('SMTP_FROM') ?: 'no-reply@college.edu');
        $this->fromName = $config['from_name'] ?? (getenv('SMTP_FROM_NAME') ?: 'Examify System');
        $this->useTls = (bool) ($config['use_tls'] ?? true);
    }

    public function send(string $toEmail, string $subject, string $htmlBody, string $textBody = ''): bool
    {
        if (empty($textBody)) {
            $textBody = strip_tags($htmlBody);
        }

        try {
            $timeout = 10;
            $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $timeout);

            if (!$socket) {
                log_error("SMTP Connection failed: $errstr ($errno)");
                return false;
            }

            $this->readResponse($socket);

            $this->sendCommand($socket, "EHLO " . gethostname());

            if ($this->useTls && $this->port === 587) {
                $this->sendCommand($socket, "STARTTLS");
                stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->sendCommand($socket, "EHLO " . gethostname());
            }

            if (!empty($this->username) && !empty($this->password)) {
                $this->sendCommand($socket, "AUTH LOGIN");
                $this->sendCommand($socket, base64_encode($this->username));
                $this->sendCommand($socket, base64_encode($this->password));
            }

            $this->sendCommand($socket, "MAIL FROM: <{$this->fromEmail}>");
            $this->sendCommand($socket, "RCPT TO: <{$toEmail}>");
            $this->sendCommand($socket, "DATA");

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
            $body .= "--$boundary--\r\n.";

            $this->sendCommand($socket, $body);
            $this->sendCommand($socket, "QUIT");

            fclose($socket);
            return true;
        } catch (Throwable $e) {
            log_error("VanillaMailer failed to send email to $toEmail", $e);
            return false;
        }
    }

    private function sendCommand($socket, string $command): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->readResponse($socket);
    }

    private function readResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') {
                break;
            }
        }
        return $response;
    }
}

#!/bin/sh
set -e

# ==============================================================================
# Examify Production WebSocket Daemon Entrypoint
# ==============================================================================

# Ensure .env exists
if [ ! -f /var/www/html/.env ]; then
    if [ -f /var/www/html/.env.example ]; then
        cp /var/www/html/.env.example /var/www/html/.env
    else
        touch /var/www/html/.env
    fi
fi

# Synchronize runtime environment variables into /var/www/html/.env
php -r '
    $envFile = "/var/www/html/.env";
    $content = file_exists($envFile) ? file_get_contents($envFile) : "";
    $keys = [
        "APP_ENV", "APP_NAME", "APP_URL", "APP_TIMEZONE",
        "DB_HOST", "DB_PORT", "DB_DATABASE", "DB_USERNAME", "DB_PASSWORD", "DB_CHARSET", "DB_SSL", "DB_SSL_CA",
        "SESSION_LIFETIME", "CSRF_SECRET",
        "WS_ENABLED", "WS_HOST", "WS_PORT", "WS_IPC_HOST", "WS_IPC_PORT", "WS_PUBLIC_URL"
    ];
    foreach ($keys as $key) {
        $val = getenv($key);
        if ($val !== false && $val !== "") {
            $escapedKey = preg_quote($key, "/");
            if (preg_match("/^{$escapedKey}=.*/m", $content)) {
                $content = preg_replace("/^{$escapedKey}=.*/m", "{$key}={$val}", $content);
            } else {
                $content .= "\n{$key}={$val}";
            }
        }
    }
    file_put_contents($envFile, trim($content) . "\n");
'

# Wait for database readiness if requested
if [ "${DB_WAIT:-true}" = "true" ]; then
    DB_H="${DB_HOST:-db}"
    DB_P="${DB_PORT:-3306}"
    echo "[Examify WebSocket] Waiting for database readiness at ${DB_H}:${DB_P}..."
    php -r '
        $host = getenv("DB_HOST") ?: "db";
        $port = (int) (getenv("DB_PORT") ?: 3306);
        for ($i = 0; $i < 30; $i++) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 2);
            if ($socket) {
                fclose($socket);
                exit(0);
            }
            sleep(2);
        }
        echo "[Examify WebSocket] WARNING: Database unreachable after 60s; launching WebSocket daemon anyway.\n";
    '
fi

exec "$@"

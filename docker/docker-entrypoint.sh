#!/bin/sh
set -e

# ==============================================================================
# Examify Production Web Entrypoint
# ==============================================================================

# Default backend host and port for Apache WebSocket proxy if not defined
export WS_BACKEND_HOST="${WS_BACKEND_HOST:-websocket}"
export WS_BACKEND_PORT="${WS_BACKEND_PORT:-8085}"

# SSL Certificate Management (Self-Signed / Production Fallback)
export SSL_CERT_FILE="${SSL_CERT_FILE:-/etc/ssl/certs/examify.crt}"
export SSL_KEY_FILE="${SSL_KEY_FILE:-/etc/ssl/private/examify.key}"
export SSL_SAN="${SSL_SAN:-DNS:localhost,DNS:*.localhost,DNS:examify.local,IP:127.0.0.1,IP:::1}"

if [ ! -f "$SSL_CERT_FILE" ] || [ ! -f "$SSL_KEY_FILE" ]; then
    echo "[Examify Entrypoint] Generating self-signed SSL certificate for HTTPS..."
    mkdir -p "$(dirname "$SSL_CERT_FILE")" "$(dirname "$SSL_KEY_FILE")"
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$SSL_KEY_FILE" \
        -out "$SSL_CERT_FILE" \
        -subj "/C=IN/ST=State/L=City/O=Examify/CN=localhost" \
        -addext "subjectAltName=${SSL_SAN}" 2>/dev/null || \
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout "$SSL_KEY_FILE" \
        -out "$SSL_CERT_FILE" \
        -subj "/C=IN/ST=State/L=City/O=Examify/CN=localhost"
    chmod 600 "$SSL_KEY_FILE"
    chmod 644 "$SSL_CERT_FILE"
    echo "[Examify Entrypoint] SSL certificate generated at $SSL_CERT_FILE"
fi

# Ensure .env exists (Examify config/database.php requires .env file in project root)
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

# Ensure logs directory exists and has correct www-data permissions
mkdir -p /var/www/html/logs
chown -R www-data:www-data /var/www/html/logs
chmod -R 775 /var/www/html/logs

# Database connection readiness check
if [ "${DB_WAIT:-true}" = "true" ]; then
    DB_H="${DB_HOST:-db}"
    DB_P="${DB_PORT:-3306}"
    echo "[Examify Entrypoint] Checking database connectivity at ${DB_H}:${DB_P}..."
    php -r '
        $host = getenv("DB_HOST") ?: "db";
        $port = (int) (getenv("DB_PORT") ?: 3306);
        $connected = false;
        for ($i = 0; $i < 30; $i++) {
            $socket = @fsockopen($host, $port, $errno, $errstr, 2);
            if ($socket) {
                fclose($socket);
                $connected = true;
                break;
            }
            sleep(2);
        }
        if (!$connected) {
            echo "[Examify Entrypoint] WARNING: Database is still unreachable after 60s; continuing...\n";
            exit(0);
        }
        echo "[Examify Entrypoint] Database connection verified successfully.\n";
    '
fi

# Automatic schema migration
if [ "${DB_AUTO_MIGRATE:-true}" = "true" ]; then
    echo "[Examify Entrypoint] Verifying and applying database schema..."
    if [ "${DB_SEED_DEMO:-false}" = "true" ]; then
        php init-db.php || echo "[Examify Entrypoint] Database seeding finished or skipped."
    else
        php init-db.php --schema-only || echo "[Examify Entrypoint] Database schema verification finished or skipped."
    fi
fi

exec "$@"

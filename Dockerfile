# ==============================================================================
# Examify - Production Multi-Stage Dockerfile
# Architected strictly according to .github/workflows/release.yml
#
# Targets:
#   - web       : Apache 2.4 + PHP 8.2 Production Web Server (default)
#   - websocket : Standalone CLI RFC 6455 WebSocket & IPC Daemon
# ==============================================================================

# ------------------------------------------------------------------------------
# Stage 1: Asset Builder (Node.js 20 per release.yml)
# ------------------------------------------------------------------------------
FROM node:20-alpine AS asset-builder

WORKDIR /build

# Install asset minification tools specified in release.yml
RUN npm install -g clean-css-cli terser

# Copy static assets and JavaScript utilities for optimization
COPY assets/ ./assets/
COPY utils/ ./utils/

# Restore symlinks if checked out on Windows as pointer files, then minify CSS and JS files
RUN for f in utils/*.js; do \
      if [ -f "$f" ] && [ ! -L "$f" ]; then \
        target=$(cat "$f"); \
        case "$target" in \
          ../*) rm -f "$f" && ln -s "$target" "$f" ;; \
        esac; \
      fi; \
    done \
    && for f in assets/css/*.css; do \
      if [ -f "$f" ]; then cleancss -o "$f" "$f"; fi; \
    done \
    && for f in assets/js/*.js; do \
      if [ -f "$f" ]; then terser "$f" -o "$f" --compress --mangle; fi; \
    done \
    && for f in utils/*.js; do \
      if [ -f "$f" ] && [ ! -L "$f" ]; then terser "$f" -o "$f" --compress --mangle; fi; \
    done

# ------------------------------------------------------------------------------
# Stage 2: Production PHP Apache Runtime (PHP 8.2 per release.yml)
# ------------------------------------------------------------------------------
FROM php:8.2-apache-bookworm AS web

LABEL maintainer="Bibekananda Mudi" \
      description="Examify Online Examination System - Production Web Server"

# Install production PHP extensions: pdo, pdo_mysql, mbstring, gd, opcache
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring gd opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable essential Apache modules for .htaccess, proxying, SSL, and client IP resolution
RUN a2enmod rewrite headers expires deflate remoteip proxy proxy_http proxy_wstunnel ssl socache_shmcb

# Apply production VirtualHost and PHP configurations
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini $PHP_INI_DIR/conf.d/examify-production.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

# Copy application directories matching release.yml assembly
COPY admin/ ./admin/
COPY bin/ ./bin/
COPY components/ ./components/
COPY config/ ./config/
COPY docs/ ./docs/
COPY lib/ ./lib/
COPY services/ ./services/
COPY student/ ./student/
COPY archive/ ./archive/
COPY init-db.php index.php server.php developers.php .htaccess .env.example README.md LICENSE ./

# Copy optimized assets and utils from Stage 1
COPY --from=asset-builder /build/assets/ ./assets/
COPY --from=asset-builder /build/utils/ ./utils/

# Ensure logs directory exists with appropriate permissions for www-data
RUN mkdir -p logs && chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html && chmod -R 775 logs

# Run PHP syntax verification across all production PHP files (per release.yml)
RUN find . -type f -name "*.php" -print0 | xargs -0 -n1 -P4 php -l

# Healthcheck verifying HTTPS service
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r "\$c = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false], 'http' => ['timeout' => 3]]); if (@file_get_contents('https://127.0.0.1/index.php', false, \$c) === false) exit(1);"

# Install production entrypoint script
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80 443

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]

# ------------------------------------------------------------------------------
# Stage 3: Production WebSocket Daemon (CLI PHP 8.2 per release.yml)
# ------------------------------------------------------------------------------
FROM php:8.2-cli-bookworm AS websocket

LABEL maintainer="Bibekananda Mudi" \
      description="Examify Online Examination System - Real-Time WebSocket Daemon"

# Install production PHP extensions for WebSocket daemon
RUN apt-get update && apt-get install -y --no-install-recommends \
    libonig-dev \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

COPY docker/php.ini $PHP_INI_DIR/conf.d/examify-production.ini
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

WORKDIR /var/www/html

# Copy application runtime files
COPY bin/ ./bin/
COPY config/ ./config/
COPY lib/ ./lib/
COPY services/ ./services/
COPY archive/ ./archive/
COPY init-db.php server.php .env.example LICENSE ./

# Copy optimized assets and utils from Stage 1
COPY --from=asset-builder /build/assets/ ./assets/
COPY --from=asset-builder /build/utils/ ./utils/

# Prepare directories and permissions
RUN mkdir -p logs && chown -R www-data:www-data /var/www/html && chmod -R 755 /var/www/html && chmod -R 775 logs

# Run PHP syntax check
RUN find . -type f -name "*.php" -print0 | xargs -0 -n1 -P4 php -l

# Install websocket entrypoint script
COPY docker/websocket-entrypoint.sh /usr/local/bin/websocket-entrypoint.sh
RUN chmod +x /usr/local/bin/websocket-entrypoint.sh

USER www-data

EXPOSE 8085 8086

# Healthcheck verifying WebSocket/IPC port is listening
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD php -r "if (!@fsockopen('127.0.0.1', (int)(getenv('WS_IPC_PORT') ?: 8086), \$errno, \$errstr, 2)) exit(1);"

ENTRYPOINT ["websocket-entrypoint.sh"]
CMD ["php", "bin/websocket-server.php"]

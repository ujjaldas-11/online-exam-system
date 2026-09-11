# Examify Production Docker Deployment Guide

This guide documents the production Docker architecture for Examify. The build pipeline directly implements the optimization and packaging steps established in [`.github/workflows/release.yml`](.github/workflows/release.yml).

---

## 1. Architectural Overview

Examify uses a **multi-stage Docker build** process to guarantee 100% offline air-gapped compliance and optimal runtime performance:

1. **Stage 1 (Asset Builder — `node:20-alpine`)**:
   - Compiles and minifies all production stylesheets in `assets/css/` via `clean-css-cli`.
   - Compresses and mangles JavaScript files in `assets/js/` and `utils/` via `terser`.
   - Does not leave Node.js, npm, or build tools in the final production runtime image.

2. **Stage 2 (Production Web Server — `php:8.2-apache-bookworm` or `php:8.2-fpm-bookworm`)**:
   - Pure Vanilla PHP 8.2 runtime with native PDO MySQL, mbstring, GD, and OPcache.
   - Enforces Apache `.htaccess` security policies, HTTP headers, and compression.
   - Validates PHP syntax across the entire codebase (`php -l`) during image build.
   - Embeds zero CDN links, zero remote fonts, and zero tracking dependencies.

3. **Stage 3 (Real-Time WebSocket Daemon — `php:8.2-cli-bookworm`)**:
   - Zero-dependency RFC 6455 WebSocket & IPC daemon (`bin/websocket-server.php`).
   - Runs as unprivileged user `www-data`.
   - Listens on port `8085` (client WebSockets) and `8086` (internal loopback IPC).

---

## 2. Dockerfile Inventory

| File | Purpose | Base Image | Default Command |
|---|---|---|---|
| [`Dockerfile`](Dockerfile) | Primary multi-stage build; defaults to Apache web server (target `web`). Also includes target `websocket`. | `php:8.2-apache-bookworm` | `apache2-foreground` |
| [`Dockerfile.websocket`](Dockerfile.websocket) | Standalone WebSocket and IPC daemon for live proctoring. | `php:8.2-cli-bookworm` | `php bin/websocket-server.php` |
| [`Dockerfile.fpm`](Dockerfile.fpm) | Production PHP-FPM service for Nginx or Kubernetes Ingress deployments. | `php:8.2-fpm-bookworm` | `php-fpm` |
| [`.dockerignore`](.dockerignore) | Excludes dev files, tests, temporary logs, and secrets from build context. | N/A | N/A |

---

## 3. Quickstart with Docker Compose

The simplest and recommended method to deploy Examify in production is using `docker-compose.yml`:

```bash
# 1. Clone or extract the repository
git clone https://github.com/ujjaldas-11/online-exam-system.git
cd online-exam-system

# 2. Review and adjust environment variables if needed
cp .env.example .env

# 3. Build and launch the complete stack in detached mode
docker compose up -d --build
```

The stack automatically launches:
- **`examify_web`**: Apache web server on `http://localhost:8080`.
- **`examify_websocket`**: WebSocket daemon on port `8085`.
- **`examify_db`**: MySQL 8.0 server with automated health checks.

The entrypoint automatically waits for the database to become healthy and runs schema initialization (`php init-db.php --schema-only`).

To complete the initial system setup, open:
`http://localhost:8080/admin/setup.php`

---

## 4. Environment Variables Reference

| Variable | Default | Description |
|---|---|---|
| `APP_ENV` | `production` | Environment mode (`production` forces strict session cookies and security headers). |
| `APP_NAME` | `Examify` | Institutional platform name. |
| `APP_URL` | `http://localhost:8080` | Canonical application URL. |
| `APP_TIMEZONE` | `Asia/Kolkata` | Application default timezone. |
| `DB_HOST` | `db` | Database hostname or container service name. |
| `DB_PORT` | `3306` | MySQL port. |
| `DB_DATABASE` | `examify` | Database name. |
| `DB_USERNAME` | `examify_prod` | Database user account. |
| `DB_PASSWORD` | *(required)* | Database password. |
| `DB_SSL` | `false` | Enable TLS/SSL connection to MySQL. |
| `DB_AUTO_MIGRATE` | `true` | Automatically runs `php init-db.php --schema-only` on container startup. |
| `DB_SEED_DEMO` | `false` | Set to `true` to populate demo accounts and sample exams. |
| `WS_ENABLED` | `true` | Enable real-time WebSocket proctoring. |
| `WS_HOST` | `0.0.0.0` | Bind address for WebSocket daemon. |
| `WS_PORT` | `8085` | Public WebSocket port. |
| `WS_IPC_HOST` | `websocket` | IPC hostname for web container push events. |
| `WS_IPC_PORT` | `8086` | IPC loopback port. |
| `WS_PUBLIC_URL` | `ws://localhost:8085` | Browser connection string for client WebSocket (`wss://...` when using SSL). |
| `SESSION_LIFETIME` | `1800` | Inactivity timeout in seconds (30 minutes). |
| `CSRF_SECRET` | *(required)* | 64-character random hex string for CSRF token generation. |

---

## 5. Building Individual Images

### A. Web Application (Apache)
```bash
docker build -t examify-web:latest -f Dockerfile --target web .
```

### B. WebSocket Daemon
```bash
docker build -t examify-ws:latest -f Dockerfile.websocket .
# Or using the multi-stage target:
docker build -t examify-ws:latest --target websocket .
```

### C. PHP-FPM Service
```bash
docker build -t examify-fpm:latest -f Dockerfile.fpm .
```

---

## 6. Nginx + PHP-FPM Deployment Variant

For deployments using Nginx and PHP-FPM:

```bash
docker compose -f docker-compose.fpm.yml up -d --build
```

This stack uses:
- `docker/nginx.conf`: Routes static assets, proxies `/ws/` to the WebSocket container, and forwards PHP requests to `examify_app:9000`.

---

## 7. Production SSL/TLS & Air-Gapped College LAN Setup

As detailed in [`production.md`](production.md), Examify enforces `session.cookie_secure = true` in production mode. Modern browsers require HTTPS to transmit secure cookies.

### Terminating SSL at Host or Reverse Proxy

When terminating SSL at an external Nginx or Traefik reverse proxy:

1. Forward HTTPS requests to `http://localhost:8080`.
2. Ensure the following proxy headers are passed:
   ```nginx
   proxy_set_header X-Forwarded-Proto https;
   proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
   proxy_set_header Host $host;
   ```
3. Proxy WebSocket connections to port 8085:
   ```nginx
   location /ws/ {
       proxy_pass http://127.0.0.1:8085/;
       proxy_http_version 1.1;
       proxy_set_header Upgrade $http_upgrade;
       proxy_set_header Connection "Upgrade";
       proxy_set_header Host $host;
   }
   ```
4. Update `WS_PUBLIC_URL` in your `.env` or `docker-compose.yml`:
   ```env
   WS_PUBLIC_URL=wss://examify.college.edu/ws/
   ```

---

## 8. Common Maintenance Operations

### Running Database Migrations Manually
```bash
docker compose exec web php init-db.php --schema-only
```

### Seeding Demo Data (for Testing / Staging)
```bash
docker compose exec web php init-db.php
```

### Viewing Container Logs
```bash
# Web server logs
docker compose logs -f web

# WebSocket daemon logs
docker compose logs -f websocket

# Database logs
docker compose logs -f db
```

### Creating Database Backup
```bash
docker compose exec db mysqldump -u examify_prod -p examify > backup.sql
```

### Restoring Database Backup
```bash
docker compose exec -T db mysql -u examify_prod -p examify < backup.sql
```

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

### A. Development Mode (Database-Only in Docker)
To use Docker purely for database provisioning while running `php -S localhost:8000` locally for live file changes:

```bash
# Launch only the MySQL 8.0 container (port 3306 mapped to localhost)
docker compose up -d

# Start your local PHP development server
php -S localhost:8000
```

The database is immediately reachable on host at `127.0.0.1:3306`. Ensure your `.env` has:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=examify
DB_USERNAME=examify_prod
DB_PASSWORD=examify_secure_production_password_change_me
```

---

### B. Full Production Stack (Web + WebSocket + MySQL)
To build and launch the complete production environment:

```bash
# 1. Review and adjust environment variables if needed
cp .env.example .env

# 2. Build and launch all services with the 'full' profile
docker compose --profile full up -d --build
```

The stack launches:
- **`examify_web`**: Apache web server on `http://localhost:8080` and `https://localhost:8443`.
- **`examify_websocket`**: WebSocket daemon on port `8085`.
- **`examify_db`**: MySQL 8.0 server with automated health checks on port `3306`.

The entrypoint automatically waits for the database to become healthy and runs schema initialization (`php init-db.php --schema-only`).

To complete the initial system setup, open:
`https://localhost:8443/admin/setup.php` (or `http://localhost:8080/admin/setup.php`)

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

---

## 9. Troubleshooting & Setup Caveats

### A. MySQL Container Crash Loop: `MYSQL_USER="root"` Error
- **Symptom**: The `examify_db` container fails to start, repeatedly restarting with:
  ```text
  [ERROR] [Entrypoint]: MYSQL_USER="root", MYSQL_USER and MYSQL_PASSWORD are for configuring a regular user and cannot be used for the root user
  ```
- **Cause**: Docker Compose automatically loads the `.env` file in the project root. If `.env` was copied directly from `.env.example` without modification, it contains `DB_USERNAME=root`. Docker Compose injects this into the MySQL container as `MYSQL_USER=root`, which the official MySQL 8.0 entrypoint strictly rejects.
- **Solution**: Update `.env` to specify a non-root user and credentials:
  ```env
  DB_USERNAME=examify_prod
  DB_PASSWORD=your_secure_password
  DB_ROOT_PASSWORD=your_secure_root_password
  ```
  Then recreate the stack:
  ```bash
  docker compose down -v
  docker compose up -d --build
  ```

### B. Docker Buildx Cloud Concurrency Limit (`concurrent build limit reached`)
- **Symptom**: The build aborts immediately during multi-stage image generation with:
  ```text
  target websocket: failed to solve: build cannot proceed concurrent build limit of 0 reached
  ```
- **Cause**: The active Docker Buildx context is pointing to an external Docker Build Cloud endpoint (e.g. `cloud-*-tests*`) rather than the local Docker daemon, and has exceeded concurrency quotas.
- **Solution**: List available builders and switch to the local engine:
  ```bash
  # Check active builder (marked with an asterisk *)
  docker buildx ls

  # Switch to the local Docker engine
  docker buildx use desktop-linux
  # Or use default:
  docker buildx use default
  ```
  Then rerun `docker compose up -d --build`.

### C. Running Host Test Suites against Dockerized MySQL
- **Symptom**: Running PHP test suites directly from the host machine (`php tests/...`) fails with:
  ```text
  Database connection failed: SQLSTATE[HY000] [2002] php_network_getaddresses: getaddrinfo for db failed
  ```
- **Cause**: By default, `docker-compose.yml` does not bind port `3306` to the host network to maintain network isolation and prevent port conflicts with local MySQL/MariaDB instances.
- **Solution**:
  1. **Option 1 (Recommended)**: Run the automated tests inside the running web container where network services are directly resolvable:
     ```bash
     docker compose exec web php tests/phase4_remediation_test.php
     ```
  2. **Option 2 (Host Testing)**: If you need to run tests directly from your host, expose port `3306` in `docker-compose.yml` under `services.db.ports`:
     ```yaml
     ports:
       - "3306:3306"
     ```
     And set `DB_HOST=127.0.0.1` in your host `.env`.


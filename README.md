# Examify

[![CI](https://github.com/ujjaldas-11/online-exam-system/actions/workflows/mega-linter.yml/badge.svg?branch=main)](https://github.com/ujjaldas-11/online-exam-system/actions/workflows/mega-linter.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.1%20--%208.5%2B-777bb4.svg)](https://www.php.net/)

Examify is an institutional web-based examination platform designed specifically for college computer laboratories and local area networks (LAN). It delivers timed assessments, semester examinations, and quizzes with real-time proctoring and automated evaluation—operating with zero external internet dependencies.

---

## Architectural Overview

Examify is engineered to run in physically isolated network environments where internet access is unavailable or deliberately restricted during examinations.

- **Air-Gapped Offline LAN Guarantee**: Zero external CDN links, remote fonts, or third-party tracking scripts. All stylesheets, JavaScript runtime files, and Material Symbols font files are self-hosted locally in `assets/`.
- **Pure Vanilla Stack**: Built with native PHP 8, PDO MySQL, and Vanilla JavaScript. It requires no Composer packages, npm runtime dependencies, or external framework runtimes.
- **Event-Driven Proctoring Daemon**: A standalone RFC 6455 WebSocket server (`bin/websocket-server.php` / `server.php`) handles real-time proctoring alerts, student connectivity telemetry, and live broadcast announcements, backed by a non-blocking IPC loopback and transparent HTTP polling fallback.
- **High-Concurrency Exam Engine**: Evaluates attempts inside atomic database transactions (`services/ExamEngine.php`). Features include deterministic option permutation per candidate to prevent shoulder surfing, configurable negative marking with an automated floor at zero, and lock contention defense under concurrent laboratory traffic.

---

## Core Capabilities

### Examination and Integrity Controls
- **Singleton Sessions**: Enforces a single active session per student or instructor account. Simultaneous logins from other devices immediately terminate the preceding session.
- **Hardware and Device Gating**: Exam rooms require desktop or laptop environments. Mobile devices and tablets are locked out, and touchscreen inputs on convertibles are suppressed in favor of physical pointer input.
- **Client Integrity Monitoring**: Detects fullscreen exits, tab switches, document visibility changes, and window focus loss. Automated submission triggers if the configurable violation threshold (default: 3) is exceeded.
- **Deterministic Option Shuffling**: Questions seed pseudo-random option permutations per candidate (`options_order`), preventing adjacent candidates from copying identical option letters while preserving deterministic grading.
- **Server-Synchronized Timer**: Authoritative server-side expiration checks prevent client-side timer manipulation. Instructors can extend emergency time (+5m / +10m) in real time without refreshing the page.

### Faculty and Administration Tools
- **Role-Based Access Control**: Granular separation between Superadmin and Teacher accounts, with departmental and ownership scoping across curriculum subjects and question banks.
- **Question Bank Management**: Teachers can author questions manually or import multiple-choice questions in bulk via CSV (`admin/manage-questions.php`).
- **Student Cohort Management**: Administrators can filter, verify, suspend, or perform bulk cohort promotions across academic semesters.
- **Pure-PHP PDF Generation**: Exports complete institutional exam score sheets and individual student result cards using an embedded FPDF engine without requiring external command-line binaries.

---

## System Requirements

- **PHP**: Version 8.1 or higher (tested across PHP 8.1, 8.2, 8.3, 8.4, and 8.5)
  - Required extensions: `pdo_mysql`, `mbstring`, `json`, `session`
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ (with `mod_rewrite` and `mod_headers`) or Nginx 1.18+
- **Browser**: Any modern browser (Chrome, Edge, Firefox, Safari) running in desktop view

---

## Quick Start

### 1. Clone the Repository
```bash
git clone https://github.com/ujjaldas-11/online-exam-system.git
cd online-exam-system
```

### 2. Configure Environment
Copy the example environment file and customize database credentials:
```bash
cp .env.example .env
```

Edit `.env` to match your local database configuration:
```ini
APP_ENV=development
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=examify
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
```

### 3. Initialize the Database
Run the consolidated database initializer to create tables and seed demo accounts:
```bash
php init-db.php
```

Advanced CLI Options:
- `php init-db.php --fresh` — Drops existing tables, recreates schema, and reseeds test data.
- `php init-db.php --schema-only` — Creates empty database schema without seeding demo accounts (recommended for production).

### 4. Run the Application

#### Option A: Local Development Server
Start PHP's built-in server from the project root:
```bash
php -S 127.0.0.1:8000
```
Open `http://127.0.0.1:8000` in your web browser.

#### Option B: Apache or Nginx
Deploy the project directory to your web server root (for example, `/var/www/html/examify` or `htdocs/examify`). Ensure Apache `mod_rewrite` is enabled to support `.htaccess` security rules.

### 5. Start the WebSocket Daemon (Optional)
To enable real-time proctoring updates and live announcements:
```bash
php server.php
```
*(If the daemon is not running, the application automatically falls back to background HTTP polling).*

---

## Default Seeded Accounts

The initial database seeder configures the following test accounts:

| Role | Email | Password | Scope |
|---|---|---|---|
| **Superadmin** | `admin@college.edu` | `Admin@123` | Full system control, settings, backups, and teacher management |
| **Teacher** | `teacher@college.edu` | `Teacher@123` | Question bank authoring, exam scheduling, and live proctoring |
| **Student** | `student@college.edu` | `Student@123` | Assessment participation, scorecard access, and test review |

*A demo quiz (OS Surprise Quiz, PIN: `4821`) is pre-seeded for testing.*

---

## Directory Structure

```text
online-exam-system/
├── admin/          # Administrative and instructor portal (exams, questions, proctoring)
├── assets/         # Self-hosted assets (CSS, JavaScript, local WOFF2 fonts, images)
├── bin/            # CLI utilities and daemon entrypoints (websocket-server.php)
├── components/     # Reusable layout partials (sidebar, header, footer, modals)
├── config/         # Database connection and environment bootstrap
├── docs/           # Technical manuals and user guides in Simplified Technical English
├── lib/            # Standalone zero-dependency libraries (FPDF, WebSocket engine)
├── services/       # Core business logic (ExamEngine, CurriculumService, CsvService)
├── student/        # Candidate examination room and results interface
├── tests/          # Automated security, unit, and concurrency test suites
├── utils/          # Security helpers (CSRF, auth, sanitization, device gating)
├── init-db.php     # CLI database schema and migration utility
├── production.md   # Production deployment, SSL/TLS, and process supervision guide
└── server.php      # Root CLI entrypoint for WebSocket daemon
```

---

## Automated Test Suite

Examify includes 15 automated test suites covering security, authorization, anti-cheat gating, concurrency, and air-gapped compliance.

Run individual test suites:
```bash
# Security, CSRF, and unit validation
php tests/security_and_unit_tests.php

# Zero-CDN and air-gapped asset compliance
php tests/offline_zero_cdn_test.php

# High-concurrency engine and race-condition simulation
php tests/concurrency_test.php

# Device lockout and platform gating verification
php tests/device_gating_test.php

# Singleton session enforcement test
php tests/singleton_login_test.php
```

Run the complete test suite:
```bash
for t in tests/*.php; do
    [ -f "$t" ] && php "$t" > /dev/null || { echo "Failed: $t"; exit 1; }
done
echo "All test suites passed successfully."
```

---

## Production Deployment and Security

Refer to [**production.md**](production.md) for production deployment instructions, including:
- Web server HTTPS configuration for air-gapped LAN environments (internal CA setup) and internet domains.
- Warnings regarding production session cookie flags (`Secure`).
- Enforcing SSL/TLS encryption on database connections (`DB_SSL=true`).
- `systemd` process supervision for the WebSocket daemon.

---

## Contributing and Governance

Please consult the project guidelines before proposing or committing changes:
- [**CONTRIBUTING.md**](CONTRIBUTING.md) — Code style conventions, formatting rules, and pull request checklist.
- [**AGENTS.md**](AGENTS.md) — Architectural invariants, zero-CDN constraints, security protocols, and feature preservation rules for contributors and AI agents.

---

## Documentation

Full documentation is available in the [`docs/`](docs/README.md) directory:
- [**User Documentation**](docs/user/README.md) — Instructions for students, instructors, and system administrators.
- [**Developer Documentation**](docs/dev/README.md) — Architecture specifications, database schema, security mechanisms, and APIs.

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

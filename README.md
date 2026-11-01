# Examify

[![CI](https://github.com/ujjaldas-11/online-exam-system/actions/workflows/mega-linter.yml/badge.svg?branch=main)](https://github.com/ujjaldas-11/online-exam-system/actions/workflows/mega-linter.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP](https://img.shields.io/badge/PHP-8.1_--_8.5%2B-777bb4.svg?style=flat)](https://www.php.net/)

Examify is an air-gapped online examination platform for college computer labs and local area networks. It operates with zero internet dependencies.

---

## Features

- **100% Offline LAN**: Zero external CDNs, web fonts, or tracking scripts.
- **Pure Vanilla Stack**: Native PHP 8, PDO MySQL, and Vanilla JS without npm or Composer runtime dependencies.
- **Anti-Cheat Controls**: Enforces fullscreen mode, monitors tab switches, and randomizes question options deterministically.
- **Live Proctoring**: Real-time WebSocket daemon delivers connectivity telemetry, announcements, and proctoring alerts.
- **Role-Based Portals**: Granular portals for Superadmins, Teachers, and Students with pure-PHP PDF report card generation.

---

## Quick Start

### 1. Configure Environment
```bash
cp .env.example .env
```
Update `.env` with your MySQL database credentials.

### 2. Initialize Database
```bash
php init-db.php
```

### 3. Start Development Server
```bash
php -S 127.0.0.1:8000
```
Open `http://127.0.0.1:8000` in your browser.

### 4. Start WebSocket Daemon (Optional)
```bash
php server.php
```
*The app automatically falls back to HTTP polling if the WebSocket daemon is offline.*

---

## Default Accounts

| Role | Email | Password | Scope |
|---|---|---|---|
| **Superadmin** | `admin@college.edu` | `Admin@123` | Full system control and teacher management |
| **Teacher** | `teacher@college.edu` | `Teacher@123` | Question bank authoring and exam proctoring |
| **Student** | `student@college.edu` | `Student@123` | Exam participation and scorecard access |

*Demo quiz access PIN: `4821`.*

---

## Testing

Run all 17 automated test suites:
```bash
php tests/run_all.php
```

---

## Documentation

- [User Documentation](docs/user/README.md) — Guides for students, instructors, and administrators.
- [Developer Documentation](docs/dev/README.md) — Architecture, database schema, and APIs.
- [Production Deployment](production.md) — SSL/TLS, web server, and service configuration.
- [Agent Governance](AGENTS.md) — Contribution rules and architectural invariants.

---

## License

This project is licensed under the [MIT License](LICENSE).

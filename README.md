# Examify — Online Examination System

**Examify** is a high-performance, secure, and lightweight web-based online examination platform built with **Vanilla PHP 8.x, MySQL, HTML5, CSS3, and Vanilla JavaScript**. Designed specifically for educational institutions and college computer labs, Examify provides complete classroom control for **surprise quizzes, semester tests, and scheduled online exams** over local area networks (LAN).

---
[![CI](https://github.com/ujjaldas-11/online-exam-system/actions/workflows/lint.yml/badge.svg?branch=main)](https://github.com/ujjaldas-11/online-exam-system/actions/workflows/lint.yml)


## 🎯 Primary Use Case & Architecture

- **Deployment Model**: College local area network (LAN) server (Apache/Nginx on Ubuntu/Debian or local XAMPP/WampServer).
- **Classroom Scenarios**: Lab surprise tests, scheduled examinations, and department assessments.
- **Core Technology**: 100% pure Vanilla PHP (zero heavy runtime framework dependencies), native PDO, native session management, and single-hit cached CSS.

```text
┌────────────────────────────────────────────────────────────────────────┐
│                          EXAMIFY ARCHITECTURE                          │
├─────────────────┬─────────────────┬─────────────────┬──────────────────┤
│  Admin Portal   │ Student Portal  │ Live Proctoring │ Security & Timer │
│  (Exams & Bank) │  (Test Room)    │ (Real-time LAN) │   (Anti-Cheat)   │
└─────────────────┴─────────────────┴─────────────────┴──────────────────┘
```

---

## ✨ Key Features & Capabilities

### 🛡️ Security & Integrity
- **CSRF Defense**: Cryptographic token protection across all student and admin state-changing endpoints (`verify_csrf()`).
- **Session Hardening**: Secure cookie flags (`HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS) and post-login session regeneration (`session_regenerate_id(true)`).
- **Server-Side Timer Enforcement**: Tamper-proof validation on submission preventing client clock manipulation.
- **Anti-Cheat Audit Trail**: Real-time logging of tab switches, full-screen exits, and DevTools attempts stored in `exam_violations` table.
- **Safe Error Disclosure**: Centralized exception logging to `logs/app_errors.log` preventing SQL error leaks.

### 🎓 Classroom & Surprise Test Features
- **Classroom Exam PIN**: Instructors can set an optional 4-to-6 digit PIN on exams. Students enter the whiteboard PIN to start the quiz.
- **Live Instructor Proctoring Panel**: Real-time dashboard showing every enrolled student's live status, current question progress, and cheat flags.
- **Emergency PC Crash Recovery**: Instructors can unlock and resume attempts or grant $+5$ / $+10$ minutes if lab hardware crashes.
- **Batch CSV Student Roster Import**: Enroll an entire classroom in seconds by uploading a `.csv` file.
- **Offline LAN Password Reset**: Instructors can reset forgotten student passwords directly in the admin panel with one click.

### 🎨 Modern Vanilla CSS Design System
- **Single Cached Network Hit (`assets/css/app.css`)**: Combines unified design tokens (`variables.css`), global resets (`base.css`), and reusable components (`components.css`).
- **55% CSS Payload Reduction**: Replaced redundant inline style blocks with a consolidated ~12 KB stylesheet cached on first lab visit.
- **Modular Styles**: Dedicated `exam.css` for the exam room, `landing.css` for landing components, and `style.css` for page layout.

---

## 📁 Project Directory Structure

```text
online-exam-system/
├── .github/
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug_report.md        # GitHub bug report template
│   │   └── feature_request.md   # GitHub feature request template
│   └── workflows/
│       ├── archive.yml          # Production release packager workflow
│       └── lint.yml             # Automated GitHub Actions CI (EditorConfig, PHP Syntax & Style)
├── admin/
│   ├── admin-dashboard.php      # System overview and quick action center
│   ├── admin-guard.php          # Session authorization gate for administrators
│   ├── admin-login.php          # Secure admin authentication
│   ├── admin-logout.php         # Session destruction and logout
│   ├── control-exams.php        # Start/stop exams, add emergency time, view PINs
│   ├── import-students.php      # Bulk CSV classroom enrollment tool
│   ├── manage-exam.php          # Configure exams (duration, question count, PIN)
│   ├── manage-questions.php     # Bulk upload MCQ questions with JSON
│   ├── manage-requests.php      # Profile updates and emergency password resets
│   ├── manage-subjects.php      # Curriculum subjects by department/semester
│   ├── proctor-exam.php         # Live classroom proctoring dashboard
│   ├── results.php              # Department results overview
│   ├── view-questions.php       # Question bank browser and management
│   └── view-results.php         # Top 3 podium, leaderboard, and printable report
├── archive/
│   ├── 001_add_indexes.sql      # Database query performance optimization indexes
│   ├── 002_security_and_surprise_test.sql # Security & proctoring migration
│   └── schema.sql               # Master MySQL schema
├── assets/
│   ├── css/
│   │   ├── app.css              # Consolidated master application stylesheet
│   │   ├── base.css             # Resets and base element defaults
│   │   ├── components.css       # Buttons, cards, tables, badges, and alerts
│   │   ├── exam.css             # Isolated exam room and question palette
│   │   ├── landing.css          # Isolated marketing landing styles
│   │   ├── style.css            # Legacy & landing page styles
│   │   └── variables.css        # Unified CSS custom property design tokens
│   └── images/                  # Logos and branding assets
├── components/
│   ├── footer.php               # Shared footer layout partial
│   ├── header.php               # Shared HTML head and stylesheet partial
│   ├── navbar.php               # Sticky navigation bar
│   └── searchbar.php            # Instant table search component
├── config/
│   └── database.php             # Native .env parser and PDO connection
├── student/
│   ├── check-exams.php          # Non-blocking polling endpoint for live exams
│   ├── dashboard.php            # Active student examinations
│   ├── edit-profile.php         # Request academic info change
│   ├── exam.php                 # Exam taking interface with timer and AntiCheat
│   ├── log-violation.php        # Real-time cheating violation audit endpoint
│   ├── login.php                # Student authentication
│   ├── logout.php               # Student logout
│   ├── profile.php              # Academic credentials and past exam history
│   ├── question.php             # Real-time question fetching and auto-save
│   ├── register.php             # Student registration
│   ├── result.php               # Instant score evaluation and performance breakdown
│   └── student-guard.php        # Student authorization gate
├── tests/
│   ├── create-credentials.php   # Test credential generator
│   ├── daa-questions.json       # Question bank for Algorithms
│   ├── networking-questions.json# Question bank for Computer Networks
│   ├── os-questions.json        # Question bank for Operating Systems
│   └── prepare-question.php     # CLI question importer from JSON
├── tools/
│   ├── check-editorconfig.ps1   # PowerShell EditorConfig runner
│   ├── check-editorconfig.sh    # Bash EditorConfig runner
│   ├── reset-and-seed.php       # Complete database reset & full demo seeder
│   └── setup-db.php             # One-command database installer and health check
├── utils/
│   ├── anti-cheat.js            # Fullscreen, tab-switch, and DevTools detection
│   ├── auth.php                 # Native role verification helpers
│   ├── csrf.php                 # Native CSRF token generation and validation
│   ├── env.php                  # Environment variables & browser cache control helper
│   ├── funny_quotes.json        # Anti-cheat violation notice quotes
│   ├── logger.php               # Safe exception logging
│   ├── mailer.php               # Zero-dependency Vanilla PHP socket SMTP client
│   ├── response.php             # JSON response and redirect helpers
│   ├── sanitize.php             # HTML escaping and parameter sanitization
│   ├── session.php              # Hardened session initialization and flash messages
│   └── timer.js                 # Synchronized exam timer countdown
├── .editorconfig                # Universal indentation and whitespace rules
├── .editorconfig-checker.json   # EditorConfig automated checker config
├── .htaccess                    # Apache LAN caching and security headers
├── .php-cs-fixer.dist.php       # PHP PSR-12 code style config
├── CONTRIBUTING.md              # Guidelines for developers and contributors
├── LICENSE                      # Project license
├── PRODUCTION.md                # Production build and deployment guide
├── README.md                    # System documentation
└── index.php                    # Landing page and portal gateway
```

---

## 🚀 Installation & Quick Start

### 1. Requirements
- **PHP**: 8.1 or higher (with `pdo_mysql` enabled)
- **Database**: MySQL 5.7+ / MariaDB 10.3+
- **Web Server**: Apache / Nginx or built-in PHP server

### 2. Setup Configuration
1. Clone the repository into your web server directory (`htdocs` or `/var/www/html`):
  ```bash
  git clone https://github.com/ujjaldas-11/online-exam-system.git
  cd online-exam-system
  ```
2. Create a `.env` file in the root directory:
  ```env
  # Set to 'development' (disables browser cache) or 'production' (enables caching)
  APP_ENV=development

  DB_HOST=localhost
  DB_DATABASE=examify
  DB_USERNAME=root
  DB_PASSWORD=
  DB_CHARSET=utf8mb4
  ```

### 3. Initialize Database

#### Option A: Quick Full Demo with Sample Data (Recommended for Development)
```bash
php tools/reset-and-seed.php
```
*This automatically creates the database, loads the schema, seeds subjects, imports 50+ test questions, and sets up sample exams with default accounts:*
- **Admin Email**: `admin@college.edu` | **Password**: `Admin@123`
- **Student Email**: `student@college.edu` | **Password**: `Student@123` *(BCA, Semester 4)*

#### Option B: Clean Setup (Production & Clean Installs)
```bash
php tools/setup-db.php
```
*Executes `archive/schema.sql`, applies pending migrations, and initializes the default administrator account:*
- **Admin Email**: `admin@college.edu`
- **Default Password**: `Admin@123`

### 4. Launch Application
- **Via Local Web Server (XAMPP / WampServer / Apache)**:
  Navigate to: `http://localhost/online-exam-system/`
- **Via Built-in PHP Server**:
  ```bash
  php -S localhost:8000
  ```
  Navigate to: `http://localhost:8000/`

---

## 🛠️ Developer & CI Verification

Examify uses strict PSR-12 and EditorConfig standards enforced by GitHub Actions.

### Windows (PowerShell)
```powershell
# 1. Verify text format & EditorConfig
.\tools\check-editorconfig.ps1

# 2. Verify PHP syntax across all files
Get-ChildItem -Filter *.php -Recurse | Where-Object { $_.FullName -notmatch '[\\/]vendor[\\/]' } | ForEach-Object { php -l $_.FullName }
```

### Linux / macOS (Bash)
```bash
# 1. Verify text format & EditorConfig
./tools/check-editorconfig.sh

# 2. Verify PHP syntax across all files
find . -type f -name "*.php" ! -path "./vendor/*" -exec php -l {} +
```

### PHP Code Formatting (PHP-CS-Fixer)
```bash
# 3. Check PSR-12 style standards
php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php

# Or using downloaded phar
# php php-cs-fixer.phar fix --dry-run --diff --config=.php-cs-fixer.dist.php
```

---

## 🤝 Contributing

Contributions and improvements are welcome! Please read [**CONTRIBUTING.md**](CONTRIBUTING.md) for code style guidelines and pull request instructions.


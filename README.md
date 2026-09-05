# Examify — Online Examination System

Examify is a secure web-based online examination platform.
The system uses Vanilla PHP 8.x, MySQL, HTML5, CSS3, and Vanilla JavaScript.
Institutions deploy Examify on local area networks (LAN) for quizzes, semester tests, and scheduled examinations.

---

[![CI](https://github.com/ujjaldas-11/online-exam-system/actions/workflows/mega-linter.yml/badge.svg?branch=main)](https://github.com/ujjaldas-11/online-exam-system/actions/workflows/mega-linter.yml)

## 🎯 Primary Use Case & Architecture

- **Deployment Model**: College local area network (LAN) server with Apache or Nginx.
- **Classroom Scenarios**: Surprise quizzes, semester examinations, and departmental assessments.
- **Core Technology**: Pure Vanilla PHP, native PDO database connections, native session management, and single-hit cached CSS.

```text
┌────────────────────────────────────────────────────────────────────────┐
│                          EXAMIFY ARCHITECTURE                          │
├─────────────────┬─────────────────┬─────────────────┬──────────────────┤
│  Admin Portal   │ Student Portal  │ Live Proctoring │ Security & Timer │
│ (Exams/Students)│  (Test Room)    │ (Real-time LAN) │   (Anti-Cheat)   │
└─────────────────┴─────────────────┴─────────────────┴──────────────────┘
```

---

## ✨ Key Features & Capabilities

### 🛡️ Security & Session Integrity
- **Singleton Login Enforcement**: The system permits only one active session per account. A new login immediately invalidates the previous session.
- **Device & Platform Gating**: The system restricts active examinations to desktop and laptop computers. Mobile phones and tablets cannot take examinations.
- **Touchscreen Suppression**: The system suppresses touchscreen inputs on laptops. Students must use a physical mouse or touchpad.
- **In-DOM Submission Confirmation**: A custom modal replaces native browser popups. The modal displays answered, marked, and unanswered question counts.
- **Graceful Proctoring Teardown**: The anti-cheat monitor stops before form submission. This action prevents false window blur violations.
- **CSRF Defense**: Cryptographic tokens protect all state-changing endpoints via `verify_csrf()`.
- **Session Hardening**: Secure cookie parameters (`HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS) and post-login session regeneration prevent session fixation.
- **Centralized Audit Trail**: The system records administrative actions and anti-cheat infractions in dedicated database tables.

### 🎓 Classroom & Administration Features
- **Student Management Panel**: Administrators search, filter, enroll, edit, suspend, and delete student accounts.
- **Bulk Student Promotion**: Administrators promote student cohorts by department or promote selected students (+1 semester).
- **Universal Password Visibility**: Every password input field includes an interactive eye toggle button.
- **Automated Registration Redirect**: Successful student registration shows a 30-second progress bar and redirects to the homepage.
- **Classroom Access PIN**: Instructors can set an optional PIN on examinations.
- **Live Classroom Proctoring**: Instructors monitor student progress and violation counts in real time.
- **Hardware Crash Recovery**: Instructors can unlock student attempts and grant emergency time (+5 or +10 minutes).
- **Pure-PHP PDF Reports**: The system generates institutional result summaries and individual student scorecards without external operating system tools.

### 🎨 Design System
- **Single Cached Network Hit (`assets/css/app.css`)**: Combines design tokens, global resets, and reusable components.
- **Responsive Layout Containment**: Tables and containers prevent horizontal page overflow across screen sizes.
- **Gold Navigation Indicator**: The active tab in the admin sidebar displays text and icons in gold (`#ffd700`).

---

## 📁 Project Directory Structure

```text
online-exam-system/
├── .github/
│   ├── ISSUE_TEMPLATE/
│   │   ├── bug_report.md        # Bug report template
│   │   └── feature_request.md   # Feature request template
│   └── workflows/
│       ├── lint.yml             # Automated CI workflow
│       └── release.yml          # Automated release packaging workflow
├── admin/
│   ├── admin-dashboard.php      # Admin overview and quick action center
│   ├── admin-guard.php          # Session authorization gate for administrators
│   ├── admin-login.php          # Administrator authentication
│   ├── admin-logout.php         # Session termination and logout
│   ├── control-exams.php        # Start/stop exams, add emergency time, view PINs
│   ├── export-pdf.php           # Institutional exam results PDF download
│   ├── manage-exam.php          # Configure exams (duration, marks, PIN)
│   ├── manage-questions.php     # Upload multiple-choice questions via CSV
│   ├── manage-students.php      # Student management and bulk promotion panel
│   ├── manage-teachers.php      # Teacher account management
│   ├── proctor-exam.php         # Live classroom proctoring dashboard
│   ├── results.php              # Department results overview
│   ├── setup.php                # Master superadmin initial setup wizard
│   ├── view-questions.php       # Question bank browser
│   └── view-results.php         # Exam leaderboard and PDF export trigger
├── archive/
│   └── schema.sql               # Master MySQL relational schema
├── assets/
│   ├── css/
│   │   ├── admin-sidebar.css    # Admin sidebar styles and active theme
│   │   ├── app.css              # Master combined application stylesheet
│   │   ├── base.css             # HTML resets and base defaults
│   │   ├── components.css       # Cards, tables, modals, badges, password toggles
│   │   ├── exam.css             # Isolated examination room layout
│   │   ├── landing.css          # Landing page styles
│   │   ├── material-symbols.css # Self-hosted Material Symbols font stylesheet
│   │   └── variables.css        # CSS custom properties and color tokens
│   ├── fonts/                   # Self-hosted woff2 font files
│   └── images/                  # Application logos and graphics
├── components/
│   ├── admin-sidebar.php        # Admin sidebar navigation partial
│   ├── desktop-required.php     # Mobile lockout notification screen
│   ├── footer.php               # Shared footer and password toggle handler
│   ├── header.php               # Shared HTML head partial
│   ├── navbar.php               # Navigation bar component
│   └── searchbar.php            # Search input component
├── config/
│   └── database.php             # Environment loader and PDO connection
├── docs/
│   ├── dev/
│   │   └── README.md            # Developer documentation (ASD-STE100)
│   ├── user/
│   │   └── README.md            # User guide documentation (ASD-STE100)
│   └── README.md                # Documentation index
├── lib/
│   └── fpdf/                    # Pure-PHP PDF generation engine
├── services/
│   ├── ExamEngine.php           # High-concurrency exam delivery and grading engine
│   └── PdfService.php           # PDF report layout service
├── student/
│   ├── check-exams.php          # Live examination polling endpoint
│   ├── dashboard.php            # Student dashboard and active tests
│   ├── download-card.php        # Student scorecard PDF download endpoint
│   ├── exam.php                 # Exam taking room with in-DOM confirmation modal
│   ├── log-violation.php        # Anti-cheat violation logging endpoint
│   ├── login.php                # Student authentication
│   ├── logout.php               # Student session termination
│   ├── question.php             # Question fetch and auto-save endpoint
│   ├── register.php             # Student registration with 30s timeout bar
│   ├── result.php               # Score evaluation and metrics breakdown
│   └── student-guard.php        # Student session authorization gate
├── tests/                       # Automated unit, security, and concurrency test suites
├── utils/
│   ├── anti-cheat.js            # Client-side integrity, fullscreen, and touch suppression
│   ├── auth.php                 # Role verification and singleton session helpers
│   ├── csrf.php                 # CSRF token generator and validator
│   ├── device.php               # Device detection and desktop requirement helpers
│   ├── logger.php               # Safe exception logging
│   ├── sanitize.php             # HTML escaping and CSV sanitization
│   └── timer.js                 # Synchronized exam timer countdown
├── init-db.php                  # Database initializer and seeder tool
├── index.php                    # Application landing page
├── LICENSE                      # Project license
├── PRODUCTION.md                # Production build and deployment guide
└── README.md                    # Main system documentation
```

---

## 🚀 Installation & Quick Start

### 1. Requirements
- **PHP**: Version 8.1 or higher with `pdo_mysql`, `mbstring`, and `json` extensions.
- **Database**: MySQL 5.7+ or MariaDB 10.3+.
- **Web Server**: Apache 2.4+, Nginx, or the built-in PHP development server.

### 2. Setup Configuration
1. Clone the repository into your web server directory:
   ```bash
   git clone https://github.com/ujjaldas-11/online-exam-system.git
   cd online-exam-system
   ```
2. Create a `.env` file in the project root directory:
   ```env
   APP_ENV=development
   DB_HOST=127.0.0.1
   DB_DATABASE=examify
   DB_USERNAME=root
   DB_PASSWORD=
   DB_CHARSET=utf8mb4
   ```

### 3. Initialize Database
Execute the consolidated database initialization tool:

```bash
php init-db.php
```

The script creates the database schema and seeds initial accounts:
- **Superadmin**: `admin@college.edu` | Password: `Admin@123`
- **Active Teacher**: `teacher@college.edu` | Password: `Teacher@123`
- **Student**: `student@college.edu` | Password: `Student@123`
- **Demo Quiz**: *OS Surprise Quiz* (PIN: `4821`)

#### Advanced CLI Options
- `php init-db.php --fresh`: Drops existing database tables and seeds new demo data.
- `php init-db.php --schema-only`: Applies database tables without seeding demo data.

### 4. Launch Application
- **Via Local Web Server (Apache/XAMPP)**:
  Open `http://localhost/online-exam-system/` in your browser.
- **Via Built-in PHP Server**:
  ```bash
  php -S 127.0.0.1:8000
  ```
  Open `http://127.0.0.1:8000/` in your browser.

---

## 🛠️ Verification & Test Suites

Run the automated test suites to verify system functionality:

```bash
# Security, CSRF, and unit tests
php tests/security_and_unit_tests.php

# Singleton concurrent login test
php tests/singleton_login_test.php

# Device and touchscreen gating test
php tests/device_gating_test.php

# Universal password visibility toggle test
php tests/password_visibility_test.php

# Bulk student promotion test
php tests/bulk_promote_test.php

# High-concurrency exam engine benchmark
php tests/concurrency_test.php
```

---

## 📚 Documentation

The [`docs/`](docs/README.md) directory contains complete documentation written in **ASD-STE100 Simplified Technical English**:

- [**User Documentation (`docs/user/README.md`)**](docs/user/README.md): Instructions for students, instructors, and administrators.
- [**Developer Documentation (`docs/dev/README.md`)**](docs/dev/README.md): Specifications for architecture, database schema, security modules, and APIs.

---

## 🤝 Contributing

Read [**CONTRIBUTING.md**](CONTRIBUTING.md) for code style guidelines and pull request instructions.

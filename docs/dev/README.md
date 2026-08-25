# Developer Documentation for Examify

This document gives technical information for developers who maintain or extend Examify.
Examify is an online examination system built with Vanilla PHP, MySQL, and Vanilla JavaScript.

---

## 1. System Architecture and Design Philosophy

Examify uses a lightweight architecture with zero runtime package dependencies.
The system runs on local area network (LAN) servers and college computer laboratories.

```text
┌────────────────────────────────────────────────────────────────────────┐
│                          EXAMIFY ARCHITECTURE                          │
├─────────────────┬─────────────────┬─────────────────┬──────────────────┤
│  Admin Portal   │ Student Portal  │ Live Proctoring │ Security & Timer │
│  (Exams & Bank) │  (Test Room)    │ (Real-time LAN) │   (Anti-Cheat)   │
└─────────────────┴─────────────────┴─────────────────┴──────────────────┘
```

### Core Technologies

- **Backend**: Pure PHP 8.1+ with native PHP Data Objects (PDO).
- **Database**: MySQL 5.7+ or MariaDB 10.3+.
- **Frontend**: HTML5, CSS custom properties, and Vanilla JavaScript (ES6+).
- **Icons**: Google Material Symbols Outlined font.
- **Dependencies**: No Composer or Node.js runtime packages required.

---

## 2. System Requirements and Environment Setup

### 2.1 Server Requirements

- PHP 8.1 or higher with these extensions enabled:
  - `pdo_mysql`
  - `session`
  - `json`
  - `mbstring`
- MySQL 5.7+ or MariaDB 10.3+.
- Apache 2.4+ (with `mod_rewrite` and `mod_headers`) or Nginx.

### 2.2 Environment Configuration File (`.env`)

Examify uses a root `.env` file for configuration.
The file `config/database.php` reads this file with native PHP code.

Create a `.env` file in the project root:

```env
# Application environment: 'development' or 'production'
APP_ENV=development

# Database connection parameters
DB_HOST=localhost
DB_DATABASE=examify
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
```

#### Configuration Keys

- `APP_ENV`: Set to `development` to disable browser caching. Set to `production` to enable caching.
- `DB_HOST`: Hostname or IP address of the database server.
- `DB_DATABASE`: Name of the MySQL database.
- `DB_USERNAME`: Database user account name.
- `DB_PASSWORD`: Database user account password.
- `DB_CHARSET`: Character encoding for the database connection (default: `utf8mb4`).

---

## 3. Database Architecture and Data Model

Examify stores all application data in 9 relational tables.

```text
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│   subjects   │──────<│  questions   │       │   students   │
└──────────────┘       └──────────────┘       └──────────────┘
        │                                             │
        │                                             │
        ▼                                             ▼
┌──────────────┐                              ┌──────────────┐
│    exams     │─────────────────────────────<│exam_attempts │
└──────────────┘                              └──────────────┘
                                                      │
                                                      ├──────< student_answers
                                                      │
                                                      └──────< exam_violations
```

### 3.1 Table Specifications

#### 1. `admins`
Stores instructor and administrator user accounts.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `name` (VARCHAR(100))
- `email` (VARCHAR(150), UNIQUE)
- `password` (VARCHAR(255))
- `created_at` (TIMESTAMP)

#### 2. `students`
Stores registered student user accounts.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `name` (VARCHAR(100))
- `email` (VARCHAR(150), UNIQUE)
- `password` (VARCHAR(255))
- `roll_number` (VARCHAR(50), UNIQUE)
- `department` (VARCHAR(50))
- `semester` (INT)
- `status` (ENUM('active', 'blocked'))
- `created_at` (TIMESTAMP)

#### 3. `subjects`
Stores academic curriculum subjects organized by department and semester.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `name` (VARCHAR(100))
- `department` (VARCHAR(50))
- `semester` (INT)
- `created_at` (TIMESTAMP)

#### 4. `questions`
Stores multiple-choice questions belonging to subjects.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `subject_id` (INT, FOREIGN KEY -> `subjects.id`)
- `question_text` (TEXT)
- `option_a` (TEXT)
- `option_b` (TEXT)
- `option_c` (TEXT)
- `option_d` (TEXT)
- `correct_option` (CHAR(1))

#### 5. `exams`
Stores examination configurations and live status.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `title` (VARCHAR(150))
- `subject_id` (INT, FOREIGN KEY -> `subjects.id`)
- `duration_minutes` (INT)
- `total_marks` (INT)
- `total_questions_to_ask` (INT)
- `access_pin` (VARCHAR(10), NULLABLE)
- `status` (ENUM('inactive', 'active', 'ended'))
- `start_time` (DATETIME, NULLABLE)
- `created_at` (TIMESTAMP)

#### 6. `exam_attempts`
Stores student test attempts and total scores.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `student_id` (INT, FOREIGN KEY -> `students.id`)
- `exam_id` (INT, FOREIGN KEY -> `exams.id`)
- `total_questions` (INT)
- `score` (DECIMAL(5,2), DEFAULT 0.00)
- `status` (ENUM('in_progress', 'completed'))
- `started_at` (TIMESTAMP)
- `submitted_at` (DATETIME, NULLABLE)

#### 7. `student_answers`
Stores individual question assignments and student responses per attempt.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `attempt_id` (INT, FOREIGN KEY -> `exam_attempts.id`)
- `question_id` (INT, FOREIGN KEY -> `questions.id`)
- `selected_option` (CHAR(1), NULLABLE)
- `is_correct` (TINYINT(1), DEFAULT 0)

#### 8. `exam_violations`
Stores real-time anti-cheat infraction events.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `attempt_id` (INT, FOREIGN KEY -> `exam_attempts.id`)
- `violation_type` (VARCHAR(100))
- `details` (TEXT, NULLABLE)
- `timestamp` (TIMESTAMP)

#### 9. `profile_requests`
Stores pending student profile change requests.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `student_id` (INT, FOREIGN KEY -> `students.id`)
- `new_name` (VARCHAR(100))
- `new_roll_no` (VARCHAR(50))
- `new_department` (VARCHAR(50))
- `new_semester` (INT)
- `status` (ENUM('pending', 'approved', 'rejected'))
- `request_date` (TIMESTAMP)

### 3.2 Database Initialization Tools

The `tools/` directory contains two setup scripts:

- `tools/reset-and-seed.php`:
  Drops existing tables, recreates the schema, and seeds demo subjects, questions, and accounts.
  Run with:
  ```bash
  php tools/reset-and-seed.php
  ```

- `tools/setup-db.php`:
  Runs `archive/schema.sql` on production systems.
  Run with:
  ```bash
  php tools/setup-db.php
  ```

---

## 4. Code Structure and Organization

```text
online-exam-system/
├── admin/               # Administrator views and backend control endpoints
├── assets/              # CSS styles, images, and brand assets
│   ├── css/             # Consolidated and modular stylesheets
│   └── images/          # Logo and icon graphics
├── components/          # Shared PHP UI partials (header, navbar, footer, searchbar)
├── config/              # Database connection and environment loader
├── docs/                # User and developer documentation
│   ├── dev/             # Developer reference documentation
│   └── user/            # User guide documentation
├── student/             # Student portal views and examination endpoints
├── tests/               # Test credential generators and JSON question banks
├── tools/               # Database installers, seeders, and format checkers
├── utils/               # Helper modules (auth, CSRF, logger, mailer, sanitize, timer)
├── .editorconfig        # Indentation and line ending specification
├── .htaccess            # Apache security headers and browser caching rules
├── .php-cs-fixer.dist.php # PSR-12 code style fixer configuration
└── index.php            # Application landing page
```

---

## 5. Core Utilities and Security Architecture

### 5.1 CSRF Defense (`utils/csrf.php`)

All state-changing POST endpoints require CSRF token validation.

- `csrf_token()`: Generates a cryptographically secure token and saves it to the session.
- `csrf_field()`: Outputs a hidden HTML form input element with the token:
  ```html
  <input type="hidden" name="csrf_token" value="...">
  ```
- `verify_csrf()`: Validates incoming POST requests against `$_SESSION['csrf_token']`.
  If the token is invalid, the function stops execution with a `403 Forbidden` response.

### 5.2 Session Hardening (`utils/session.php`)

The `init_secure_session()` function configures hardened cookie parameters:

- `cookie_httponly = 1`: Prevents client-side scripts from reading session cookies.
- `cookie_samesite = 'Lax'`: Mitigates Cross-Site Request Forgery attacks.
- `use_only_cookies = 1`: Disallows session ID transmission through URL parameters.
- `cookie_secure`: Automatically set to `1` when the server detects HTTPS.

On successful login, scripts call `session_regenerate_id(true)` to prevent session fixation.

### 5.3 Input Sanitization and XSS Prevention (`utils/sanitize.php`)

- `e(string $value)`: Escapes output with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- `clean_input(string $data)`: Trims whitespace and strips dangerous characters.
- `int_param(mixed $val, int $default = 0)`: Sanitizes numeric query and form inputs.

### 5.4 Centralized Error Logging (`utils/logger.php`)

- `log_error(string $message, ?Throwable $exception = null)`:
  Appends timestamped error messages and stack traces to `logs/app_errors.log`.
- `safe_db_error(PDOException $e, string $userMessage)`:
  Logs the real SQL error to the log file and returns a safe, generic message to the user.

### 5.5 Authentication and Authorization (`utils/auth.php`)

- `is_admin_logged_in()`: Returns `true` if the session contains a valid `admin_id`.
- `is_student_logged_in()`: Returns `true` if the session contains a valid `student_id`.
- `admin/admin-guard.php`: Guard file included at the top of all admin pages.
- `student/student-guard.php`: Guard file included at the top of all student pages.

---

## 6. Examination Engine and Anti-Cheat Module

### 6.1 Real-Time Question Fetching and Auto-Save (`student/question.php`)

`student/question.php` handles question delivery and student response recording:

- **GET Request**:
  Fetches a single question by index for the active attempt.
  Returns question text, options, current index, total count, and saved answer state as JSON.
- **POST Request**:
  Receives `selected_option` and `marked_for_review`.
  Saves the answer to the PHP session and executes a direct database backup query to `student_answers`.

### 6.2 Client-Side Anti-Cheat Detection (`utils/anti-cheat.js`)

The `AntiCheat` JavaScript module monitors examination integrity:

```javascript
AntiCheat.init({
  attemptId: attemptId,
  onViolation: (count, reason) => console.warn(reason),
  onTerminate: () => document.getElementById('examForm').submit()
});
```

#### Monitored Events

1. **Full-Screen Enforcement**:
  Calls `requestFullscreen()` when the student begins the test.
  Listens for `fullscreenchange` events. If the student exits full-screen, the module triggers a violation.
2. **Tab Switching and Minimization**:
  Listens for `visibilitychange` events (`document.hidden`).
3. **Window Focus Loss**:
  Listens for `window.blur` events.
4. **Developer Tools Shortcuts**:
  Blocks the `F12` key and keyboard combinations (`Ctrl+Shift+I`, `Ctrl+Shift+J`, `Ctrl+Shift+C`, `Ctrl+U`).

When a violation occurs, the module sends an asynchronous POST request to `student/log-violation.php`.
If the student reaches 3 violations, the module submits the examination automatically.

### 6.3 Synchronized Exam Countdown Timer (`utils/timer.js`)

The timer reads the remaining duration from `data-time-left` on the `#timerDisplay` element.
The script counts down each second and updates the timer display.
When the remaining time reaches 0, the script submits the form `#examForm`.

---

## 7. CSS Design System and Assets

Examify uses a consolidated CSS architecture under `assets/css/`:

- `variables.css`: Defines colors, fonts, spacing, shadows, and border-radius tokens.
- `base.css`: Global HTML resets, typography defaults, and form field baselines.
- `components.css`: Buttons, cards, tables, badges, alert boxes, and Material Symbols styling.
- `app.css`: Master stylesheet that imports `variables.css`, `base.css`, and `components.css`.
- `exam.css`: Isolated stylesheet for the examination room, split-view layout, and question palette.
- `landing.css` & `style.css`: Styles for the portal landing page.

### Material Symbols Icons

The project loads the `Material Symbols Outlined` web font in `components/header.php`.
Use icons in HTML with this syntax:

```html
<span class="material-symbols-outlined icon-sm">icon_name</span>
```

Available size modifier classes:
- `.icon-xs` (16px)
- `.icon-sm` (18px)
- `.icon-md` (24px)
- `.icon-lg` (28px)
- `.icon-xl` (32px)
- `.icon-2xl` (48px)

---

## 8. Code Quality, Standards, and CI/CD

### 8.1 Code Style Standards

Examify follows the PSR-12 coding standard for PHP and strict EditorConfig rules.

- **PHP files**: 4 spaces indentation, Unix LF line endings, strict typing where applicable.
- **CSS / JS / JSON / SQL / Markdown / YAML files**: 2 spaces indentation, Unix LF line endings.

### 8.2 Local Quality Verification

#### Windows (PowerShell):

```powershell
# 1. Verify EditorConfig compliance
.\tools\check-editorconfig.ps1

# 2. Verify PHP syntax across all project files
Get-ChildItem -Filter *.php -Recurse | Where-Object { $_.FullName -notmatch '[\\/]vendor[\\/]' } | ForEach-Object { php -l $_.FullName }
```

#### Linux / macOS (Bash):

```bash
# 1. Verify EditorConfig compliance
./tools/check-editorconfig.sh

# 2. Verify PHP syntax across all project files
find . -type f -name "*.php" ! -path "./vendor/*" -exec php -l {} +
```

#### Code Formatting with PHP-CS-Fixer:

```bash
php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.dist.php
```

### 8.3 GitHub Actions CI/CD Pipeline

The repository includes two automated workflows under `.github/workflows/`:

1. `lint.yml`:
  Runs on every push and pull request.
  Checks EditorConfig rules, tests PHP syntax across PHP 8.1, 8.2, and 8.3, and runs PHP-CS-Fixer.

2. `archive.yml`:
  Runs on release tags.
  Minifies all CSS files with `clean-css-cli` and JavaScript files with `terser`.
  Packages runtime files into a clean `examify-release.zip` distribution artifact.

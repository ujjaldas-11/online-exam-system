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

Examify stores all application data in 10 relational tables.

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
Stores instructor and administrator accounts.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `name` (VARCHAR(100))
- `email` (VARCHAR(100), UNIQUE)
- `password` (VARCHAR(255))
- `role` (ENUM('superadmin', 'teacher'), DEFAULT 'teacher')
- `status` (ENUM('active', 'retired'), DEFAULT 'active')
- `department` (VARCHAR(50), NULLABLE)
- `active_session_id` (VARCHAR(128), NULLABLE): Tracks current active session token for singleton login enforcement.
- `created_by` (INT, NULLABLE, FOREIGN KEY -> `admins.id` ON DELETE SET NULL)
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

#### 2. `students`
Stores student accounts across the complete enrollment lifecycle.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `name` (VARCHAR(100))
- `email` (VARCHAR(100), UNIQUE)
- `password` (VARCHAR(255))
- `roll_number` (VARCHAR(50), UNIQUE)
- `department` (VARCHAR(50))
- `semester` (TINYINT UNSIGNED)
- `phone_number` (VARCHAR(20), NULLABLE)
- `gender` (ENUM('male', 'female', 'others'), NULLABLE)
- `status` (ENUM('pending', 'active', 'rejected', 'blocked'), DEFAULT 'pending')
- `reviewed_by` (INT, NULLABLE, FOREIGN KEY -> `admins.id` ON DELETE SET NULL)
- `reviewed_at` (DATETIME, NULLABLE)
- `active_session_id` (VARCHAR(128), NULLABLE): Enforces single-device login constraints.
- `created_at` (TIMESTAMP)
- `updated_at` (TIMESTAMP)

#### 3. `subjects`
Stores curriculum subjects organized by department and semester.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `name` (VARCHAR(200))
- `department` (VARCHAR(50))
- `semester` (TINYINT UNSIGNED)
- `created_by` (INT, NULLABLE, FOREIGN KEY -> `admins.id` ON DELETE SET NULL)
- `created_at` (TIMESTAMP)

#### 4. `questions`
Stores multiple-choice questions belonging to subjects.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `subject_id` (INT, FOREIGN KEY -> `subjects.id` ON DELETE CASCADE)
- `question_text` (TEXT)
- `unit_number` (INT, DEFAULT 1)
- `option_a` (TEXT)
- `option_b` (TEXT)
- `option_c` (TEXT, NULLABLE)
- `option_d` (TEXT, NULLABLE)
- `correct_option` (ENUM('A', 'B', 'C', 'D'))
- `marks` (INT, DEFAULT 1)
- `created_by` (INT, NULLABLE, FOREIGN KEY -> `admins.id` ON DELETE SET NULL)
- `created_at` (TIMESTAMP)

#### 5. `exams`
Stores examination configurations and lifecycle states.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `subject_id` (INT, FOREIGN KEY -> `subjects.id` ON DELETE CASCADE)
- `title` (VARCHAR(200))
- `description` (TEXT, NULLABLE)
- `duration_minutes` (INT)
- `total_questions_to_ask` (INT, DEFAULT 10)
- `total_marks` (INT, DEFAULT 0)
- `status` (ENUM('inactive', 'scheduled', 'active', 'ended'), DEFAULT 'inactive')
- `results_published` (TINYINT(1), DEFAULT 0): Controls student score and question answer key release after the exam concludes.
- `access_pin` (VARCHAR(10), NULLABLE)
- `target_units` (VARCHAR(50), DEFAULT 'all')
- `start_time` (DATETIME, NULLABLE)
- `created_by` (INT, NULLABLE, FOREIGN KEY -> `admins.id` ON DELETE SET NULL)
- `created_at` (TIMESTAMP)

#### 6. `exam_attempts`
Stores student examination sessions and computed scores.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `student_id` (INT, FOREIGN KEY -> `students.id` ON DELETE CASCADE)
- `exam_id` (INT, FOREIGN KEY -> `exams.id` ON DELETE CASCADE)
- `started_at` (TIMESTAMP)
- `submitted_at` (TIMESTAMP, NULLABLE)
- `score` (DECIMAL(6,2), DEFAULT 0.00): Decimal precision supports fractional marking.
- `total_questions` (INT, DEFAULT 0)
- `status` (ENUM('in_progress', 'completed', 'disqualified'), DEFAULT 'in_progress')

#### 7. `student_answers`
Stores question assignments and saved responses per attempt.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `attempt_id` (INT, FOREIGN KEY -> `exam_attempts.id` ON DELETE CASCADE)
- `question_id` (INT, FOREIGN KEY -> `questions.id` ON DELETE CASCADE)
- `selected_option` (ENUM('A', 'B', 'C', 'D'), NULLABLE)
- `marked_for_review` (TINYINT(1), DEFAULT 0): Persists review flags across page reloads.
- `is_correct` (TINYINT(1), DEFAULT 0)
- `answered_at` (TIMESTAMP, NULLABLE)

#### 8. `exam_violations`
Stores real-time proctoring infraction events.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `attempt_id` (INT, FOREIGN KEY -> `exam_attempts.id` ON DELETE CASCADE)
- `violation_type` (VARCHAR(50))
- `details` (TEXT, NULLABLE)
- `occurred_at` (TIMESTAMP)

#### 9. `profile_requests`
Stores student requests for academic profile updates.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `student_id` (INT, FOREIGN KEY -> `students.id` ON DELETE CASCADE)
- `new_name` (VARCHAR(100))
- `new_roll_no` (VARCHAR(50))
- `new_department` (VARCHAR(50))
- `new_semester` (TINYINT UNSIGNED)
- `status` (ENUM('pending', 'approved', 'rejected'), DEFAULT 'pending')
- `reviewed_by` (INT, NULLABLE, FOREIGN KEY -> `admins.id` ON DELETE SET NULL)
- `request_date` (TIMESTAMP)

#### 10. `admin_audit_logs`
Stores immutable records of all administrative operations.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `admin_id` (INT, NULLABLE, FOREIGN KEY -> `admins.id` ON DELETE SET NULL)
- `admin_name` (VARCHAR(100))
- `admin_role` (VARCHAR(50))
- `action` (VARCHAR(100))
- `entity_type` (VARCHAR(50), NULLABLE)
- `entity_id` (INT, NULLABLE)
- `details` (TEXT, NULLABLE)
- `ip_address` (VARCHAR(45), NULLABLE)
- `created_at` (TIMESTAMP)

### 3.2 Database Initialization Tool (`init-db.php`)

Examify uses a consolidated database script in the repository root:

- `init-db.php`:
  The script initializes MySQL tables, applies `archive/schema.sql`, and seeds initial accounts.
  ```bash
  # Standard setup with demo data
  php init-db.php

  # Recreate database and apply demo data
  php init-db.php --fresh

  # Apply schema without demo data
  php init-db.php --schema-only
  ```

---

## 4. Code Structure and Organization

```text
online-exam-system/
├── admin/               # Administrator views and backend endpoints
├── assets/              # CSS stylesheets, web fonts, and images
│   ├── css/             # Consolidated and modular stylesheets
│   ├── fonts/           # Self-hosted web fonts for offline campus use
│   └── images/          # Application logos and icons
├── components/          # Shared PHP UI partials and modal components
├── config/              # Database connection and environment loader
├── docs/                # Technical documentation library
│   ├── dev/             # Developer specifications (ASD-STE100)
│   └── user/            # User guide documentation (ASD-STE100)
├── lib/                 # Third-party libraries (FPDF engine)
├── services/            # Core business logic services (ExamEngine, PdfService)
├── student/             # Student portal views and examination endpoints
├── tests/               # Automated unit, security, and concurrency test suites
├── tools/               # Local development and format checkers
├── utils/               # Core utility modules (auth, CSRF, device, logger, sanitize, timer)
├── .editorconfig        # Indentation and line ending specification
├── .htaccess            # Apache security headers and browser caching rules
├── .php-cs-fixer.dist.php # PSR-12 code style fixer configuration
└── index.php            # Application landing page
```

---

## 5. Core Utilities and Security Architecture

### 5.1 CSRF Defense (`utils/csrf.php`)

All state-changing POST endpoints require CSRF token validation.

- `csrf_token()`: Generates a cryptographically secure token and stores the token in the session.
- `csrf_field()`: Outputs a hidden HTML form input element with the token:
  ```html
  <input type="hidden" name="csrf_token" value="...">
  ```
- `verify_csrf()`: Validates incoming POST requests against `$_SESSION['csrf_token']`.
  If the token fails validation, the function halts execution with a `403 Forbidden` response.

### 5.2 Session Hardening (`utils/session.php`)

The `init_secure_session()` function configures hardened cookie parameters:

- `cookie_httponly = 1`: Prevents client-side scripts from reading session cookies.
- `cookie_samesite = 'Lax'`: Mitigates Cross-Site Request Forgery attacks.
- `use_only_cookies = 1`: Disallows session ID transmission through URL query parameters.
- `cookie_secure`: Automatically activates when the server detects HTTPS connections.

On successful login, the application calls `session_regenerate_id(true)` to prevent session fixation attacks.

### 5.3 Singleton Login Architecture (`utils/auth.php`)

Examify enforces a strict singleton session model for students and administrators.
Only one active browser session can access an account at a given time.

1. Upon successful login, the system generates a random 64-character cryptographic token.
2. The system stores this token in the user session and in the `active_session_id` database column.
3. Every request handler invokes `validate_singleton_session()`.
4. If the database token does not match the session token, the system destroys the session immediately.
5. Logging in from a second device immediately invalidates the first session.

### 5.4 Device and Platform Gating (`utils/device.php`)

The system separates desktop examination access from mobile portal viewing.

- **Mobile and Tablet Access**: Students can check announcements, dashboards, and profile history on mobile devices.
- **Desktop Examination Lockout**: The system blocks mobile phones and tablets from entering the examination interface (`student/exam.php`).
- **Lockout Screen (`components/desktop-required.php`)**: Mobile devices display an instructive notification requesting a desktop or laptop computer.
- **Touchscreen Suppression**: Examination rooms suppress touchscreen tap events on laptops to prevent accidental screen touches.

### 5.5 Input Sanitization and XSS Prevention (`utils/sanitize.php`)

- `e(string $value)`: Escapes output with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- `clean_input(string $data)`: Trims whitespace and strips dangerous characters.
- `int_param(mixed $val, int $default = 0)`: Sanitizes numeric query and form parameters.
- `sanitize_csv_value(string $val)`: Prepends a single quote to strings starting with `=`, `+`, `-`, or `@` to prevent CSV formula injection.

### 5.6 Centralized Error Logging (`utils/logger.php`)

- `log_error(string $message, ?Throwable $exception = null)`:
  Appends timestamped error messages and stack traces to `logs/app_errors.log`.
- `safe_db_error(PDOException $e, string $userMessage)`:
  Logs the raw SQL error to the log file and returns a safe generic message to the user.

### 5.7 Authentication and Authorization (`utils/auth.php`)

- `is_admin_logged_in()`: Returns `true` if the session contains an authenticated `admin_id`.
- `is_superadmin()`: Returns `true` if the administrator holds the `superadmin` role.
- `is_teacher()`: Returns `true` if the administrator holds the `teacher` role.
- `require_admin()`: Redirects unauthenticated visitors to `admin/admin-login.php`.
- `require_superadmin()`: Restricts access to Superadmin-only management modules.
- `admin/admin-guard.php`: Guard file included at the top of all admin pages.
- `student/student-guard.php`: Guard file included at the top of all student pages.

### 5.8 Master Superadmin Setup Wizard (`admin/setup.php`)

When an administrator deploys Examify on a new server:
1. The helper `is_system_initialized($pdo)` evaluates to `false` because zero admin accounts exist.
2. Visits to administrative routes automatically redirect to `admin/setup.php`.
3. The setup wizard captures the Superadmin name, email, and master password.
4. The system provisions the account using `PASSWORD_BCRYPT` hashing and logs the event in `admin_audit_logs`.
5. Once initialized, `admin/setup.php` locks permanently and redirects subsequent visits to `admin-login.php`.

### 5.9 Universal Password Visibility Toggle (`components/footer.php`)

The application provides an interactive eye icon for every password input field:
- The helper wraps password inputs in a relative `.password-wrapper` container.
- An absolute-positioned `.password-toggle-btn` displays a Material Symbols eye icon.
- Clicking the toggle button switches the input attribute between `type="password"` and `type="text"`.
- A centralized listener in `components/footer.php` initializes all toggles automatically across the entire application.

---

## 6. High-Concurrency Examination Engine & Proctoring

### 6.1 Concurrency Engine (`services/ExamEngine.php`)

`ExamEngine` manages examination attempts under high concurrent laboratory loads:

- **Idempotent Attempt Initialization**: The engine creates an attempt record inside a database transaction.
  If an attempt already exists, the engine returns the existing record without duplicate queries.
- **Bulk Answer Seeding**: The engine generates all question rows for the attempt using a single multi-row `INSERT` statement.
- **Atomic Answer Persistence**: Question responses save directly with an indexed atomic `UPDATE` query.
- **Decimal Scoring Precision**: Scores calculate as exact decimal values (`DECIMAL(6,2)`), supporting fractional grading schemes.

### 6.2 Client-Side Anti-Cheat Detection (`utils/anti-cheat.js`)

The `AntiCheat` JavaScript module monitors examination room integrity:

```javascript
AntiCheat.init({
  attemptId: attemptId,
  maxViolations: 3,
  onViolation: (count, reason) => showViolationModal(count, reason),
  onTerminate: () => document.getElementById('examForm').submit()
});
```

#### Monitored Events
1. **Fullscreen Enforcement**: Requests full-screen mode on start and logs violations if the student exits.
2. **Tab Switching**: Listens for document visibility changes (`document.hidden`).
3. **Window Focus Loss**: Detects window blur events.
4. **Developer Tools Shortcuts**: Blocks the `F12` key, `Ctrl+Shift+I`, `Ctrl+Shift+J`, `Ctrl+Shift+C`, and `Ctrl+U`.
5. **Touchscreen Suppression**: Suppresses touch taps on touchscreen laptops to prevent accidental touches.

#### In-DOM Confirmation & Proctoring Teardown
- The examination room replaces native browser `confirm()` popups with an in-DOM modal (`#submit-confirm-modal`).
- The modal displays dynamic live counts for answered, marked, and unanswered questions.
- When the student clicks the final submit button, the script calls `AntiCheat.stop()` immediately.
- This action halts event listeners before form submission, preventing false blur infractions.

### 6.3 Synchronized Countdown Timer (`utils/timer.js`)

The timer reads remaining seconds from `data-time-left` on the `#timerDisplay` element.
The script updates the countdown display every second.
When the countdown reaches zero, the timer stops the proctoring monitor and submits `#examForm` automatically.

---

## 7. Reporting & PDF Generation Architecture

### 7.1 Pure-PHP PDF Generation Service (`services/PdfService.php`)

Examify generates official academic records using the bundled pure-PHP FPDF library (`lib/fpdf/`):

- **No Operating System Binaries**: The service operates without `wkhtmltopdf`, headless Chrome, or Node.js.
- **Institutional Results Report (`generateExamResultsPdf`)**:
  - Generates comprehensive departmental examination summaries.
  - Formats KPI cards for total candidates, pass counts, fail counts, highest scores, and class averages.
  - Measures candidate names using `$pdf->GetStringWidth()` to prevent cell clipping.
  - Positions institutional endorsement lines symmetrically across the printable page width (`X = 20..75mm` and `X = 135..190mm`).
- **Student Scorecard Report (`generateStudentScorecardPdf`)**:
  - Generates official student assessment grade sheets.
  - Positions exam title across a full-width header box to prevent text margin overflow.
  - Implements a balanced two-column grid for candidate details.
  - Renders a complete performance breakdown table and centered official signature blocks.
  - Appends official timestamps and dynamic page numbers (`Page X of Y`).

---

## 8. CSS Design System and Tokens

Examify organizes styles modularly under `assets/css/`:

- `variables.css`: Defines color palettes, typography tokens, border radii, and shadows.
- `base.css`: Global HTML resets and form control baselines.
- `components.css`: Buttons, cards, modals, badges, password wrappers, and `.table-responsive` containers.
- `admin-sidebar.css`: Administrative sidebar navigation styles with gold active state indicator (`#ffd700`).
- `exam.css`: Isolated layout rules for the split-screen examination room and question palette.
- `landing.css`: Landing page hero sections and feature presentation styles.
- `material-symbols.css`: Offline `@font-face` definitions for self-hosted Material Symbols web fonts.
- `app.css`: Master stylesheet that imports core tokens, base rules, and components into one cached file.

---

## 9. Code Quality Standards and CI/CD

### 9.1 Coding Standards
- **PHP**: Strictly adheres to PSR-12 code style with 4-space indentation and Unix LF line endings.
- **Assets**: CSS, JavaScript, JSON, SQL, and Markdown use 2-space indentation with Unix LF line endings.

### 9.2 Automated Verification Commands
```powershell
# Windows PowerShell
.\tools\check-editorconfig.ps1
Get-ChildItem -Filter *.php -Recurse | ForEach-Object { php -l $_.FullName }
php tests/security_and_unit_tests.php
```

```bash
# Linux / macOS Bash
./tools/check-editorconfig.sh
find . -type f -name "*.php" -exec php -l {} +
php tests/security_and_unit_tests.php
```

### 9.3 GitHub Actions Workflows
- `lint.yml`: Checks EditorConfig rules, validates PHP syntax across PHP 8.1, 8.2, and 8.3, and tests code style.
- `release.yml`: Minifies CSS and JavaScript assets, excludes development files, and packages production releases (`examify-release.zip`).

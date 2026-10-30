# Developer Documentation for Examify

This document gives technical information for developers who maintain or extend Examify.
Examify is an online examination system built with Vanilla PHP, MySQL, and Vanilla JavaScript.

---

## 1. System Architecture and Design Philosophy

Examify uses a lightweight architecture with zero external runtime package dependencies.
The system runs on air-gapped local area network (LAN) servers and college computer laboratories.

```text
┌────────────────────────────────────────────────────────────────────────┐
│                          EXAMIFY ARCHITECTURE                          │
├─────────────────┬─────────────────┬─────────────────┬──────────────────┤
│  Admin Portal   │ Student Portal  │ Live Proctoring │ Security & Timer │
│  (Exams & Bank) │  (Test Room)    │ (WebSocket LAN) │   (Anti-Cheat)   │
├─────────────────┴─────────────────┴─────────────────┴──────────────────┤
│                           Core Engine Layer                            │
│   (ExamEngine, PdfService, CsvService, BackupService, CurriculumService)│
├────────────────────────────────────────────────────────────────────────┤
│                       Relational Data Storage                          │
│               (MySQL 5.7+ / MariaDB 10.3+ with PDO)                    │
└────────────────────────────────────────────────────────────────────────┘
```

### Core Technologies

- **Backend**: Pure PHP 8.1+ with native PHP Data Objects (PDO) and native sessions.
- **Database**: MySQL 5.7+ or MariaDB 10.3+.
- **Frontend**: HTML5, CSS custom properties, and Vanilla JavaScript (ES6+).
- **Icons & Fonts**: Self-hosted Google Material Symbols Outlined font.
- **PDF Generation**: Bundled pure-PHP FPDF engine with zero operating system binaries.
- **Spreadsheet Generation**: Bundled zero-dependency `SimpleXLSXGen` engine.
- **Real-Time Communication**: Standalone RFC 6455 WebSocket daemon with native socket IPC.
- **Dependencies**: No Composer, Node.js, or external CDN dependencies required.

---

## 2. System Requirements and Environment Setup

### 2.1 Server Requirements

- PHP 8.1 or higher (fully compatible through PHP 8.5+) with these extensions enabled:
  - `pdo_mysql`
  - `session`
  - `json`
  - `mbstring`
  - `sockets` (recommended for WebSocket daemon)
  - `zlib` (optional, for gzipped database backups)
- MySQL 5.7+ or MariaDB 10.3+.
- Apache 2.4+ (with `mod_rewrite` and `mod_headers`) or Nginx.

### 2.2 Environment Configuration File (`.env`)

Examify uses a root `.env` file for application configuration.
The loader `utils/env.php` reads this file using native PHP code.

Create a `.env` file in the project root:

```env
# Application environment: 'development' or 'production'
APP_ENV=development

# Database connection parameters
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=examify
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4

# Database SSL configuration (optional)
DB_SSL=false
DB_SSL_CA=

# Explicit path to mysqldump or mariadb-dump binary (optional)
DB_DUMP_PATH=

# Real-time WebSocket daemon configuration
WS_ENABLED=true
WS_HOST=0.0.0.0
WS_PORT=8085
WS_IPC_HOST=127.0.0.1
WS_IPC_PORT=8086
```

#### Configuration Keys

- `APP_ENV`: Set to `development` to disable strict HTTPS enforcement. Set to `production` to enforce HTTPS redirects and HSTS headers.
- `DB_HOST`: Hostname or IP address of the database server.
- `DB_PORT`: Port number of the database server (default: `3306`).
- `DB_DATABASE`: Name of the MySQL database.
- `DB_USERNAME`: Database user account name.
- `DB_PASSWORD`: Database user account password.
- `DB_CHARSET`: Character encoding for the database connection (default: `utf8mb4`).
- `DB_SSL`: Set to `true` to enable encrypted SSL database connections.
- `DB_SSL_CA`: File path to the custom Certificate Authority certificate.
- `DB_DUMP_PATH`: Absolute path to `mysqldump` or `mariadb-dump` executable.
- `WS_ENABLED`: Set to `true` to enable real-time WebSocket event dispatching.
- `WS_HOST`: Network interface for the WebSocket server (default: `0.0.0.0`).
- `WS_PORT`: TCP port for browser WebSocket connections (default: `8085`).
- `WS_IPC_HOST`: Internal host address for request IPC bridge (default: `127.0.0.1`).
- `WS_IPC_PORT`: Internal TCP port for request IPC bridge (default: `8086`).

---

## 3. Database Architecture and Data Model

Examify stores all application data in 11 relational tables.

```text
┌──────────────┐       ┌──────────────┐       ┌──────────────┐
│   subjects   │──────<│  questions   │       │   students   │
└──────────────┘       └──────────────┘       └──────────────┘
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
- `gender` (ENUM('male', 'female', 'others'), DEFAULT 'male')
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
- `question_type` (VARCHAR(32), DEFAULT 'single'): Archetypes include `single`, `multiple`, `case_study`, `assertion_reason`, and `matching`.
- `option_a` (TEXT)
- `option_b` (TEXT)
- `option_c` (TEXT, NULLABLE)
- `option_d` (TEXT, NULLABLE)
- `correct_option` (VARCHAR(32)): Stores a single option key (`A`) or comma-separated keys (`A,C`) for multi-select questions.
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
- `negative_marks_per_question` (DECIMAL(4,2), DEFAULT 0.00): Configurable penalty per wrong answer.
- `status` (ENUM('inactive', 'scheduled', 'active', 'ended'), DEFAULT 'inactive')
- `results_published` (TINYINT(1), DEFAULT 0): Controls student score release after examination conclusion.
- `access_pin` (VARCHAR(10), NULLABLE)
- `target_units` (VARCHAR(50), DEFAULT 'all')
- `start_time` (DATETIME, NULLABLE)
- `end_time` (DATETIME, NULLABLE)
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
- `options_order` (JSON, NULLABLE): Stores per-student randomized option ordering.
- `status` (ENUM('in_progress', 'completed', 'disqualified'), DEFAULT 'in_progress')

#### 7. `student_answers`
Stores question assignments and saved responses per attempt.
- `id` (INT, PRIMARY KEY, AUTO_INCREMENT)
- `attempt_id` (INT, FOREIGN KEY -> `exam_attempts.id` ON DELETE CASCADE)
- `question_id` (INT, FOREIGN KEY -> `questions.id` ON DELETE CASCADE)
- `selected_option` (VARCHAR(32), NULLABLE): Stores single option (`A`) or comma-separated selections (`A,C`).
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
- `new_gender` (ENUM('male', 'female', 'others'), DEFAULT 'male')
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

#### 11. `rate_limits`
Stores high-throughput sliding window throttle keys and expiration timestamps.
- `rate_key` (VARCHAR(128), PRIMARY KEY): Scoped key identifier (for example `login:admin:ip:127.0.0.1`).
- `hits` (INT, DEFAULT 1): Count of requests within the active window.
- `expires_at` (DATETIME): Expiration timestamp for automatic cooldown and cleanup.

### 3.2 Database Initialization Tool (`init-db.php`)

Examify uses a consolidated database script in the repository root:

```bash
# Standard setup with demo seed data
php init-db.php

# Recreate database and apply demo seed data
php init-db.php --fresh

# Apply schema without demo seed data (production deployment)
php init-db.php --schema-only
```

---

## 4. Code Structure and Organization

```text
online-exam-system/
├── admin/               # Administrator views and management endpoints
├── assets/              # CSS stylesheets, web fonts, and images
│   ├── css/             # Modular and bundled CSS stylesheets
│   ├── fonts/           # Self-hosted Material Symbols web fonts
│   ├── images/          # Application logos and icons
│   └── js/              # Client-side WebSocket handlers and scripts
├── bin/                 # CLI entrypoints (websocket-server.php, generate-ssl.sh)
├── components/          # Shared PHP UI partials and accessible dialogs
├── config/              # Database connection and environment loader
├── docs/                # Technical documentation library
│   ├── dev/             # Developer specifications (ASD-STE100)
│   └── user/            # User guide and interactive manuals (ASD-STE100)
├── lib/                 # Zero-dependency engines (FPDF, SimpleXLSXGen, WebSocket)
│   ├── fpdf/            # Bundled pure-PHP PDF generation engine
│   ├── simplexlsxgen/   # Zero-dependency native Excel (.xlsx) generator
│   └── websocket/       # Standalone RFC 6455 real-time server daemon
├── services/            # Core business logic services
│   ├── BackupService.php      # Database dump and restore engine
│   ├── CsvService.php         # CSV/XLSX streaming, preview, and validation
│   ├── CurriculumService.php  # Department, semester, and syllabus resolution
│   ├── ExamEngine.php         # High-concurrency attempt lifecycle and grading
│   └── PdfService.php         # Official PDF generation service
├── student/             # Student portal views and examination endpoints
├── tests/               # Automated unit, security, concurrency, and E2E suites
├── utils/               # Core utility modules (auth, CSRF, device, logger, rate-limiter)
├── .htaccess            # Apache security headers and browser caching rules
├── .mega-linter.yml     # Automated multi-language linter configuration
└── index.php            # Application landing page
```

---

## 5. Core Utilities and Security Architecture

### 5.1 CSRF Defense (`utils/csrf.php`)

All state-changing POST endpoints require CSRF token validation.

- `csrf_token()`: Generates a cryptographically secure token and stores it in the active session.
- `csrf_field()`: Outputs a hidden HTML form input element containing the token.
- `verify_csrf()`: Validates incoming POST requests against `$_SESSION['csrf_token']`.
  If validation fails, the function halts execution with an HTTP 403 Forbidden response.

### 5.2 Session Hardening (`utils/session.php`)

The `init_secure_session()` function configures hardened cookie parameters:

- `cookie_httponly = 1`: Prevents client-side scripts from reading session cookies.
- `cookie_samesite = 'Lax'`: Mitigates Cross-Site Request Forgery attacks.
- `use_only_cookies = 1`: Disallows session ID transmission through URL query strings.
- `cookie_secure`: Activates automatically when the server detects HTTPS connections.
- On successful login, the application calls `session_regenerate_id(true)` to prevent session fixation attacks.

### 5.3 Singleton Login Architecture (`utils/auth.php`)

Examify enforces a strict singleton session model for students and administrators:

1. When a user logs in, the system records the current `session_id()` in the user's database record (`active_session_id`).
2. Authentication guards (`admin/admin-guard.php` and `student/student-guard.php`) verify `active_session_id` on every request.
3. If the database session ID does not match the active session ID, another device has logged in.
4. The system destroys the older session immediately and redirects the browser with `error=concurrent_session`.

### 5.4 Device and Platform Gating (`utils/device.php`)

The system separates general portal viewing from active examination rooms:

- **Mobile and Tablet Access**: Students can check dashboards, results, and profiles on mobile devices.
- **Desktop Examination Lockout**: The system blocks mobile phones and tablets from entering `student/exam.php` and `student/question.php`.
- **Lockout Screen (`components/desktop-required.php`)**: Mobile devices display an official institutional notification instructing the student to switch to a computer.
- **Touchscreen Suppression**: Active exam rooms suppress touchscreen tap events on laptops to prevent gestures and accidental touches.

### 5.5 Input Sanitization and XSS Prevention (`utils/sanitize.php`)

- `e(string $value)`: Escapes output with `htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`.
- `clean_input(string $data)`: Trims whitespace and strips dangerous characters.
- `int_param(mixed $val, int $default = 0)`: Sanitizes numeric query and form parameters.
- `sanitize_csv_value(string $val)`: Prepends a single quote to strings starting with `=`, `+`, `-`, or `@` to prevent CSV formula injection.
- `sanitize_asset_name(string $name)`: Prevents directory traversal attacks on asset requests.

### 5.6 Centralized Error Logging (`utils/logger.php`)

- `log_error(string $message, ?Throwable $exception = null)`: Appends timestamped error logs and stack traces to `logs/app_errors.log`.
- `safe_db_error(PDOException $e, string $userMessage)`: Logs raw SQL exceptions safely and returns a generic message to the user.
- `log_admin_action(PDO $pdo, string $action, ?string $type, ?int $id, ?string $details)`: Records immutable entries in `admin_audit_logs`.

### 5.7 Database-Backed Rate Limiting (`utils/rate-limiter.php`)

Examify implements database-backed, sliding window rate limiting using atomic SQL queries:

- **Dual-Key Login Defense**: Throttles failed attempts across both client IP and user account identifier (5 failed attempts per 5 minutes).
- **Exam PIN Brute-Force Defense**: Locks candidates out for 10 minutes upon 5 incorrect classroom PIN attempts.
- **Registration Flood Control**: Restricts device velocity to 5 registrations per 15 minutes per IP.
- **Atomic Operations**: Employs `INSERT ... ON DUPLICATE KEY UPDATE` to avoid race conditions.
- **Opportunistic Pruning**: Automatically purges expired rate limit records on 1% of incoming requests.

### 5.8 Role-Based Access Control (`utils/auth.php`)

- `is_admin_logged_in()`: Returns `true` if the session contains an authenticated `admin_id`.
- `is_superadmin()`: Returns `true` if the administrator holds the `superadmin` role.
- `get_admin_role()`: Returns `'superadmin'`, `'teacher'`, or `''`.
- `can_admin_manage_exam(PDO $pdo, int $examId, ?int $adminId = null)`: Verifies superadmin role or exam creator ownership.
- `can_admin_manage_subject(PDO $pdo, int $subjectId, ?int $adminId = null)`: Verifies superadmin role or subject creator ownership.
- `can_admin_manage_question(PDO $pdo, int $questionId, ?int $adminId = null)`: Verifies superadmin role or question/subject creator ownership.
- `require_admin()`: Redirects unauthenticated visitors to `admin/admin-login.php`.
- `require_superadmin()`: Restricts access to Superadmin-only modules (faculty provisioning, database backups, audit logs).

### 5.9 Master Superadmin Setup Wizard (`admin/setup.php`)

When an administrator deploys Examify on a new server:
1. The helper `is_system_initialized($pdo)` evaluates to `false` because zero admin accounts exist.
2. Visits to administrative routes automatically redirect to `admin/setup.php`.
3. The setup wizard captures the Superadmin name, email, and master password.
4. The system provisions the account using `PASSWORD_BCRYPT` hashing and logs the event in `admin_audit_logs`.
5. Once initialized, `admin/setup.php` locks permanently and redirects subsequent visits to `admin-login.php`.

### 5.10 Universal Password Visibility Toggle (`components/footer.php`)

The application provides an interactive eye icon for every password input field:
- The form markup wraps password inputs in a relative `.password-wrapper` container.
- An absolute-positioned `.password-toggle-btn` displays a Material Symbols eye icon.
- Clicking the toggle button switches the input attribute between `type="password"` and `type="text"`.
- A centralized script in `components/footer.php` initializes all toggles automatically.

---

## 6. Services Architecture

### 6.1 Examination Concurrency Engine (`services/ExamEngine.php`)

`ExamEngine` manages examination attempts under high concurrent laboratory loads:

- **Idempotent Attempt Initialization (`getOrStartAttempt`)**:
  - Initializes attempt records safely inside a transaction.
  - Automatically transitions scheduled examinations to `active` when `start_time` arrives.
  - Rejects attempts if the scheduled `end_time` has elapsed.
  - Blocks disqualified students from entering the exam room.
  - Seeds questions and per-candidate option permutation in a single step.
- **Deterministic Option Permutation (`generateOptionsOrder`)**:
  - Generates a randomized JSON option map per student attempt (`exam_attempts.options_order`).
  - Permutes options A, B, C, and D differently across workstations to prevent neighbor copying.
  - Retains original answer keys for deterministic automated grading.
- **Answer Autosaving (`saveAnswer`)**:
  - Validates active attempt ownership, remaining time, and option tokens.
  - Supports 30-second network latency grace period.
  - Atomically updates selected option and persistent review flags (`marked_for_review`).
- **Atomic Submission & Grading (`submitExam`)**:
  - Uses exclusive row-level locking (`FOR UPDATE`) to serialize submissions.
  - Multi-Type MCQ Evaluation:
    - `multiple`: Sorts student response tokens and correct keys alphabetically, requiring an exact match.
    - Single-choice variants (`single`, `case_study`, `assertion_reason`, `matching`): Evaluates trimmed candidate choices against correct keys directly.
  - Negative Marking: Deducts configured fractional penalties (`negative_marks_per_question`) for incorrect answers.
  - Floors final score at `0.00` to prevent negative totals.
  - Updates attempt status to `completed` and records `submitted_at` timestamp.
- **Automated Lifecycle Synchronization (`syncExamStatuses`)**:
  - Automatically advances scheduled exams to `active` based on wall-clock time.
  - Automatically ends elapsed examinations.

### 6.2 Pure-PHP PDF Generation Service (`services/PdfService.php`)

Examify generates official academic records using the bundled pure-PHP FPDF library (`lib/fpdf/`):

- **Zero External Dependencies**: Operates with zero OS binaries or Node.js packages.
- **Institutional Results Report (`generateExamResultsPdf`)**:
  - Generates comprehensive departmental examination summaries.
  - Formats KPI cards for total candidates, pass counts, fail counts, highest scores, and class averages.
  - Measures candidate names using `$pdf->GetStringWidth()` to prevent cell clipping.
  - Positions institutional endorsement lines symmetrically across the printable page width.
- **Printable Offline Examination Paper (`generateOfflineExamPaperPdf`)**:
  - Generates printable examination papers for pen-and-paper assessments.
  - Includes examination instructions, question statements, option grids, and optional answer keys.
- **Detailed Student Answer Sheet (`generateDetailedAnswerSheetPdf`)**:
  - Renders question-by-question candidate choices, answer keys, and awarded marks.
- **Official Student Scorecard Report (`generateStudentScorecardPdf`)**:
  - Formats official student assessment grade sheets.
  - Renders candidate demographics, KPI metric blocks, scoring breakdown, and official signatures.

### 6.3 Spreadsheet & Data Interchange Service (`services/CsvService.php`)

`CsvService` centralizes spreadsheet import, export, and preview operations:

- **Zero-Dependency Native Excel (`lib/simplexlsxgen/SimpleXLSXGen.php`)**:
  - Generates valid Office Open XML zip archives directly using pure PHP.
  - Formats bold header rows, computes automatic column widths, and enforces UTF-8 encoding.
- **Dual-Format Question Template Downloads**:
  - `downloadSampleQuestionsTemplate('csv')`: Delivers a standard CSV template.
  - `downloadSampleQuestionsTemplate('xlsx')`: Delivers a formatted Excel workbook with sample archetype records.
- **In-Browser Interactive Question Preview (`#previewQuestionsModal`)**:
  - Parses uploaded CSV files and client-side text inputs before database insertion.
  - Highlights validation errors, missing options, and invalid answer tokens.
- **Question Bank Exports (`exportQuestions`)**:
  - Streams complete question inventories to CSV or native `.xlsx` files.
- **Upload Validation (`validateUploadedCsv`)**:
  - Validates file extensions, MIME types, and file size limits.
  - Implements PHP 8.5 finfo garbage-collection compatibility.

### 6.4 Curriculum Resolution Service (`services/CurriculumService.php`)

`CurriculumService` standardizes department and semester querying:

- `getDepartments(PDO $pdo)`: Returns distinct active departments merged with standard defaults (`BCA`, `BBA`).
- `isValidDepartment(PDO $pdo, string $dept)`: Validates department codes.
- `isValidSemester(int $sem)`: Validates that semester values fall within standard undergraduate range (1 to 8).
- `getSemestersByDepartment(PDO $pdo, string $dept)`: Fetches active semester numbers for a given department.
- `getSubjects(PDO $pdo, ?string $dept, ?int $sem)`: Returns curriculum subjects filtered by department and semester.

### 6.5 Database Backup and Restore Service (`services/BackupService.php`)

`BackupService` provides resilient database backups without server memory exhaustion:

- **CLI Dump Binary Detection (`getDumpBinaryInfo`)**:
  - Resolves `mariadb-dump` or `mysqldump` executable from `.env`, system PATH, or common server directories.
- **Streaming Export (`streamDump`)**:
  - Pipes process stdout directly to client HTTP output with zero PHP memory buffering.
  - Automatic memory-safe PHP streaming fallback if native CLI binaries are absent.
  - Supports automatic gzip compression when supported.
- **Database Restoration (`restoreDatabase`)**:
  - Validates and applies SQL or gzipped SQL backup files safely.
  - Temporarily disables foreign key checks to ensure smooth restoration.

---

## 7. Real-Time WebSocket & IPC Architecture

Examify features an event-driven real-time architecture:

```text
┌─────────────────┐       TCP IPC (Port 8086)       ┌──────────────────────┐
│  PHP Web App    │────────────────────────────────>│  WebSocket Daemon    │
│ (HTTP Requests) │   (Ultra-fast 30ms timeout)     │  (RFC 6455 Daemon)   │
└─────────────────┘                                 └──────────────────────┘
                                                               │
                                                     WS Frames │ (Port 8085)
                                                               ▼
                                                    ┌──────────────────────┐
                                                    │ Candidate & Proctor  │
                                                    │   Browser Windows    │
                                                    └──────────────────────┘
```

- **WebSocket Engine (`lib/websocket/Server.php`)**: Standalone RFC 6455 server using native PHP sockets.
- **Daemon CLI Entrypoint (`bin/websocket-server.php`)**: Run via CLI to host real-time services.
- **Client Socket Port (`8085`)**: Accepts browser connections for channels (`exam:{id}`, `admin:notifications`).
- **Internal IPC Bridge (`127.0.0.1:8086`)**: Accepts internal TCP pushes from web requests via `WebSocketPusher::emit()`.
- **Candidate Announcements**: Pushes instant instructor notifications directly onto student screens.
- **Graceful Polling Fallback**: If the daemon is inactive, interfaces fall back to lightweight AJAX polling automatically.

---

## 8. Client-Side Anti-Cheat & Timer Engines

### 8.1 Anti-Cheat Monitoring (`utils/anti-cheat.js`)

The `AntiCheat` JavaScript module monitors examination room integrity:

- **Fullscreen Enforcement**: Requests full-screen mode on launch and logs violations if exited.
- **Tab Switching & Blur**: Tracks document visibility changes and window blur events.
- **Developer Tools Suppression**: Blocks `F12`, `Ctrl+Shift+I`, `Ctrl+Shift+J`, `Ctrl+Shift+C`, and `Ctrl+U`.
- **Touchscreen Tap Suppression**: Blocks tap gestures on touchscreen laptops to prevent accidental touches.
- **Three-Strike Rule**: Triggers automatic test submission upon the third recorded integrity violation.
- **Clean Submission Teardown**: Halts all event listeners before form submission to eliminate false blur flags.

### 8.2 Synchronized Countdown Timer (`assets/js/timer.js`)

- Reads remaining time from `data-time-left` on the `#timerDisplay` element.
- Updates the display every second.
- Dynamically recalculates remaining duration when instructors grant emergency time extensions (+5m / +10m).
- Submits the examination automatically when the countdown reaches zero.

---

## 9. Shared UI Components Inventory (`components/`)

Examify centralizes UI partials in `components/` to avoid duplication:

| Partial | Path | Purpose |
| :--- | :--- | :--- |
| **Header** | `components/header.php` | Global HTML head, meta tags, local CSS bundles. |
| **Footer** | `components/footer.php` | Global footer markup and universal password toggle script. |
| **Admin Sidebar** | `components/admin-sidebar.php` | Navigation sidebar with gold active tab indicator. |
| **Student Navbar** | `components/student-navbar.php` | Student topbar with user session details and theme toggle. |
| **Flash Messages** | `components/flash-messages.php` | Standardized alert banners reading from `get_flash()`. |
| **Confirm Modal** | `components/confirm-modal.php` | Accessible in-DOM confirmation dialog triggered via `data-confirm`. |
| **Desktop Gating** | `components/desktop-required.php` | Lockout screen preventing mobile devices from entering exams. |
| **Filter Bar** | `components/filter-bar.php` | Standardized department, semester, and search inputs. |
| **Hall of Fame** | `components/hall-of-fame.php` | Top 3 podium display component for exam results. |
| **Pagination** | `components/pagination.php` | Pagination link generator for large data tables. |
| **Rank Board** | `components/rank-board.php` | Leaderboard table rendering ranked student performances. |
| **Searchbar** | `components/searchbar.php` | Unified instant search input component. |
| **Status Badge** | `components/status-badge.php` | Color-coded status badge renderer. |

---

## 10. Code Quality Standards and Automated Test Suite

### 10.1 Coding Standards
- **PHP**: Strictly adheres to PSR-12 code style with 4-space indentation and Unix LF line endings.
- **Assets**: CSS, JavaScript, JSON, SQL, and Markdown use 2-space indentation with Unix LF line endings.

### 10.2 Automated Test Suite (All 17 Suites)

Examify maintains 17 automated test suites:

1. `tests/bulk_promote_test.php`: Verifies cohort and selection-based student promotion logic.
2. `tests/concurrency_test.php`: Simulates 40 concurrent students launching, saving, and submitting exams.
3. `tests/device_gating_test.php`: Verifies mobile/tablet lockout and desktop permissions.
4. `tests/disqualified_student_test.php`: Verifies server-side rejection of disqualified student attempts.
5. `tests/doc_access_test.php`: Verifies public student documentation and protected admin documentation access.
6. `tests/e2e_automation.php`: Browser automation verifying student and admin lifecycles.
7. `tests/mcq_types_test.php`: Validates grading and formatting for all 5 question archetypes.
8. `tests/offline_zero_cdn_test.php`: Verifies 100% self-hosted assets and zero CDN dependencies.
9. `tests/password_visibility_test.php`: Verifies universal password visibility toggles.
10. `tests/phase4_remediation_test.php`: Validates scheduling, question editing, subject deletion, and backup tools.
11. `tests/rate_limiter_test.php`: Tests database rate-limiting and throttle cooldowns.
12. `tests/remediation_test.php`: Tests environment security and HTTPS redirect rules.
13. `tests/scheduled_exam_test.php`: Tests scheduled exam auto-activation and timezones.
14. `tests/security_and_unit_tests.php`: Verifies database constraints, sanitization, and PDF generation.
15. `tests/singleton_login_test.php`: Tests single-device session enforcement across roles.
16. `tests/websocket_test.php`: Unit tests for WebSocket framing, encoding, and IPC push bridge.
17. `tests/websocket_e2e_test.php`: End-to-end handshake, channel subscription, and broadcast tests.

### 10.3 Verification Commands

```powershell
# Windows PowerShell: Verify syntax and run all test suites
Get-ChildItem -Filter *.php -Recurse | Where-Object { $_.FullName -notmatch '\\vendor\\' } | ForEach-Object { php -l $_.FullName }

$tests = @(
    "bulk_promote_test.php", "concurrency_test.php", "device_gating_test.php",
    "disqualified_student_test.php", "doc_access_test.php", "e2e_automation.php",
    "mcq_types_test.php", "offline_zero_cdn_test.php", "password_visibility_test.php",
    "phase4_remediation_test.php", "rate_limiter_test.php", "remediation_test.php",
    "scheduled_exam_test.php", "security_and_unit_tests.php", "singleton_login_test.php",
    "websocket_test.php", "websocket_e2e_test.php"
)
foreach ($t in $tests) { php "tests/$t" }
```

```bash
# Linux / macOS Bash: Verify syntax and run all test suites
find . -type f -name "*.php" -not -path "*/vendor/*" -exec php -l {} +

for t in tests/bulk_promote_test.php \
         tests/concurrency_test.php \
         tests/device_gating_test.php \
         tests/disqualified_student_test.php \
         tests/doc_access_test.php \
         tests/e2e_automation.php \
         tests/mcq_types_test.php \
         tests/offline_zero_cdn_test.php \
         tests/password_visibility_test.php \
         tests/phase4_remediation_test.php \
         tests/rate_limiter_test.php \
         tests/remediation_test.php \
         tests/scheduled_exam_test.php \
         tests/security_and_unit_tests.php \
         tests/singleton_login_test.php \
         tests/websocket_test.php \
         tests/websocket_e2e_test.php; do
    php "$t" || exit 1
done
```

---

## 11. CI/CD and Release Automation

- `.mega-linter.yml`: Multi-language linter validating syntax, formatting, and coding standards on push/PR.
- `.github/workflows/release.yml`: Packages production-ready release archives (`examify-release.zip`), excluding development logs and scratch scripts.

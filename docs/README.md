# Examify Documentation Library

This directory contains official documentation for the Examify online examination system.
All documents in this directory follow the **ASD-STE100 Simplified Technical English** standard.

---

## 1. Documentation Index

### [User Documentation](user/README.md)
The user documentation gives procedural instructions for students, instructors, and administrators.
You can also view interactive versions in your browser at `docs/user-doc.php` and `docs/admin-doc.php`.

Topics include:
- Student account registration, demographic details, and approval workflows.
- Universal password visibility toggle operation across all forms.
- Rate-limited authentication and singleton session enforcement.
- Student dashboard navigation, automatic 10-second exam polling, and examination access.
- Classroom PIN unlocking procedures for surprise and scheduled quizzes.
- Online examination interface, option permutation, single-choice and multi-select answer navigation.
- Anti-cheat rules, fullscreen enforcement, tab blur tracking, and touchscreen suppression.
- Real-time proctor broadcast announcement delivery in the exam room.
- In-DOM examination submission confirmation and live metric counters.
- Result confidentiality rules, instant score evaluation, and student scorecard PDF downloads.
- Student profile review, historical attempt tracking, and academic detail change requests.
- Administrator dashboard overview, live KPI cards, and quick actions.
- Curriculum subject management with in-DOM editing modals and relational constraint checks.
- Question bank management with multi-type MCQs (single, multiple, case study, assertion-reason, matching).
- Dual CSV and native Microsoft Excel (`.xlsx`) question template downloads and question bank exports.
- In-browser interactive question template preview modal with validation error highlighting.
- AI Question Generator prompt export tool ("Copy LLM Prompt").
- Examination configuration, question pools, duration settings, negative marking, and scheduling.
- Live examination controls, session reopening, and emergency time extensions (+5 / +10 minutes).
- Offline printable question paper and answer key PDF generation.
- Live Classroom Proctoring Panel, WebSocket updates, candidate status tracking, and hardware crash recovery.
- Student Management Panel: enrollment, profile editing, password resets, and account suspension.
- Cohort and selection-based bulk student promotion (+1 semester) with Semester 8 cap.
- Batch student enrollment using CSV files.
- Student request governance: approval and rejection of registrations and academic profile changes.
- Faculty management, teacher provisioning, superadmin credential resets, and permanent record retention.
- Examination results, podium ranks, leaderboard analytics, and institutional PDF report downloads.
- Database backup creation (CLI and PHP streaming fallback) and database restoration.
- Immutable institutional audit trail inspection and security event filtering.

---

### [Developer Documentation](dev/README.md)
The developer documentation provides technical specifications for software developers and system administrators.

Topics include:
- System architecture, multi-tiered design, and air-gapped zero-CDN philosophy.
- Server requirements, environment setup, and `.env` configuration keys.
- Relational database schema, table definitions, constraints, and migration scripts.
- Question schema supporting `question_type` and multi-option answer keys (`VARCHAR(32)`).
- Codebase organization and directory structure (`bin/`, `components/`, `lib/`, `services/`, `utils/`).
- Core utilities: CSRF defense, hardened sessions, sanitization, rate limiting, and centralized error logging.
- Singleton login architecture and concurrent session enforcement across devices.
- Device detection and route-level gating (`utils/device.php`).
- Client-side anti-cheat detection engine and touchscreen suppression (`utils/anti-cheat.js`).
- Synchronized countdown timer with dynamic duration updates (`assets/js/timer.js`).
- High-concurrency engine (`services/ExamEngine.php`), multi-type grading, and option permutation.
- Pure-PHP PDF generation architecture (`services/PdfService.php` and `lib/fpdf/`).
- Native Excel workbook generation architecture (`lib/simplexlsxgen/SimpleXLSXGen.php`).
- Spreadsheet service with formula injection protection (`services/CsvService.php`).
- Curriculum syllabus resolution service (`services/CurriculumService.php`).
- Resilient database backup and restore service (`services/BackupService.php`).
- Real-time WebSocket daemon and non-blocking IPC socket bridge (`lib/websocket/`, `bin/websocket-server.php`, `utils/websocket-pusher.php`).
- Shared UI components and accessible modal partials (`components/`).
- CSS design system, design tokens, and self-hosted Material Symbols integration.
- Quality standards (PSR-12, EditorConfig), full test suite (17 automated suites), and verification commands.
- Automated release packaging and GitHub Actions CI/CD workflows.

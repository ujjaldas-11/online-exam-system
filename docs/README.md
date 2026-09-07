# Examify Documentation Library

This directory contains official documentation for the Examify online examination system.
All documents in this directory follow the **ASD-STE100 Simplified Technical English** standard.

---

## 1. Documentation Index

### [User Documentation](user/README.md)
The user documentation gives procedural instructions for students, instructors, and administrators.

Topics include:
- Student account registration and automated login redirection.
- Universal password visibility toggle operation.
- Student dashboard navigation and examination access.
- Classroom PIN unlocking procedures.
- Online examination interface, single-choice and multi-select answer navigation.
- Anti-cheat rules, fullscreen enforcement, and touchscreen suppression.
- In-DOM examination submission confirmation and metric counters.
- Instant score evaluation and student scorecard PDF downloads.
- Student profile review and academic detail change requests.
- Administrator dashboard overview and quick actions.
- Curriculum subject management.
- Question bank management with multi-type MCQs (single, multiple, case study, assertion-reason, matching).
- Dual CSV and native Microsoft Excel (`.xlsx`) question template downloads and question bank exports.
- In-browser interactive question template preview modal.
- Examination configuration, question pools, duration settings, and negative marking.
- Live examination controls and emergency time extensions (+5 / +10 minutes).
- Live Classroom Proctoring Panel and hardware crash recovery.
- Student Management Panel: enrollment, profile editing, password resets, and account suspension.
- Cohort and selection-based bulk student promotion (+1 semester).
- Batch student enrollment using CSV files.
- Faculty management, teacher provisioning, superadmin credential resets, and permanent record retention.
- Examination results, leaderboards, and institutional PDF report downloads.

---

### [Developer Documentation](dev/README.md)
The developer documentation provides technical specifications for software developers and system administrators.

Topics include:
- System architecture and core design philosophy.
- Server requirements and `.env` configuration keys.
- Relational database schema, table definitions, and constraints.
- Question schema supporting `question_type` and multi-option answers (`VARCHAR(20)`).
- Codebase organization and directory structure (`lib/simplexlsxgen/`, `services/CsvService.php`).
- Core utilities: CSRF defense, secure sessions, sanitization, and error logging.
- Singleton login and concurrent session enforcement architecture.
- Device detection and route-level gating (`utils/device.php`).
- Client-side anti-cheat detection engine and touchscreen suppression (`utils/anti-cheat.js`).
- Synchronized countdown timer (`utils/timer.js`).
- Concurrency engine (`services/ExamEngine.php`), multi-type grading, and option normalization.
- Pure-PHP PDF generation architecture (`services/PdfService.php` and `lib/fpdf/`).
- Native Excel workbook generation architecture (`lib/simplexlsxgen/SimpleXLSXGen.php`).
- CSS design system, design tokens, and Material Symbols integration.
- Quality standards (PSR-12, EditorConfig), full test suite (16 suites), and verification commands.
- Automated release packaging and GitHub Actions CI/CD workflows.


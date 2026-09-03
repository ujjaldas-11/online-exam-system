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
- Online examination interface and answer navigation.
- Anti-cheat rules, fullscreen enforcement, and touchscreen suppression.
- In-DOM examination submission confirmation and metric counters.
- Instant score evaluation and student scorecard PDF downloads.
- Student profile review and academic detail change requests.
- Administrator dashboard overview and quick actions.
- Curriculum subject management.
- Question bank management with bulk JSON upload and AI prompts.
- Examination configuration, question pools, and duration settings.
- Live examination controls and emergency time extensions (+5 / +10 minutes).
- Live Classroom Proctoring Panel and hardware crash recovery.
- Student Management Panel: enrollment, profile editing, password resets, and account suspension.
- Cohort and selection-based bulk student promotion (+1 semester).
- Batch student enrollment using CSV files.
- Examination results, leaderboards, and institutional PDF report downloads.

---

### [Developer Documentation](dev/README.md)
The developer documentation provides technical specifications for software developers and system administrators.

Topics include:
- System architecture and core design philosophy.
- Server requirements and `.env` configuration keys.
- Relational database schema, table definitions, and constraints.
- Codebase organization and directory structure.
- Core utilities: CSRF defense, secure sessions, sanitization, and error logging.
- Singleton login and concurrent session enforcement architecture.
- Device detection and route-level gating (`utils/device.php`).
- Client-side anti-cheat detection engine and touchscreen suppression (`utils/anti-cheat.js`).
- Synchronized countdown timer (`utils/timer.js`).
- Pure-PHP PDF generation architecture (`services/PdfService.php` and `lib/fpdf/`).
- CSS design system, design tokens, and Material Symbols integration.
- Quality standards (PSR-12, EditorConfig), test suites, and verification commands.
- Automated release packaging and GitHub Actions CI/CD workflows.

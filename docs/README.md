# Examify Documentation Library

Welcome to the Examify documentation.
This directory contains user guides and developer references for the Examify online examination system.

All documentation in this directory strictly follows the **ASD-STE100 Simplified Technical English** standard.

---

## 1. Documentation Index

### [User Documentation](user/README.md)
The user documentation gives instructions for students, instructors, and administrators.

Topics include:
- Student account registration and login procedures
- Student dashboard navigation and examination discovery
- Classroom PIN unlocking procedures
- Taking an online examination and question navigation
- Anti-cheat rules, full-screen mode, and security violations
- Examination submission and score breakdown evaluation
- Student profile review and academic detail change requests
- Administrator dashboard overview and quick actions
- Curriculum subject management
- Question bank management with bulk JSON upload and AI prompts
- Examination creation, random question pools, and duration setup
- Live exam controls and emergency time extension (+5 / +10 min)
- Live Classroom Proctoring Panel and PC crash recovery
- Student credential management and offline password resets
- Batch student enrollment with CSV files
- Examination results, leaderboards, top 3 podiums, and printable PDF reports

---

### [Developer Documentation](dev/README.md)
The developer documentation gives technical specifications for software developers and system administrators.

Topics include:
- System architecture and core design philosophy (pure Vanilla PHP 8.x, native PDO)
- System requirements and environment setup (`.env`)
- Database architecture, relational tables, and schema migrations
- Codebase organization and directory structure
- Core utilities: CSRF protection, secure sessions, sanitization, logging, mailer
- Student examination engine, auto-save protocol, and question fetching
- Client-side anti-cheat detection engine (`anti-cheat.js`) and violation logging
- Synchronized countdown timer (`timer.js`)
- CSS design system, design tokens, and Material Symbols integration
- Code quality standards (PSR-12, EditorConfig), testing, and verification commands
- Production build, minification, and GitHub Actions CI/CD workflows

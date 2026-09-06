# AGENTS.md: AI Agent Contribution & Engineering Governance Guide

> **Target Audience**: Autonomous AI coding agents (Antigravity, Cursor, Copilot, Claude, ChatGPT, etc.), LLM assistants, and human developers contributing to `ujjaldas-11/online-exam-system`.
>
> **Mandate**: This document defines binding architectural invariants, security rules, design-system constraints, and review protocols. **Read and follow every rule in this document without exception before creating, editing, staging, or proposing any changes.**

---

## 1. Core Architectural Invariants (Non-Negotiable)

### A. 100% Air-Gapped Offline LAN Guarantee (Zero-CDN Architecture)
- **Zero External Network Dependencies**: Examify is purpose-built to operate in physically isolated college computer laboratories with **zero internet connectivity**.
- **Strictly Prohibited**:
  - **No external CDN links** (`cdnjs`, `unpkg`, `jsdelivr`, `cdnjs.cloudflare.com`, etc.).
  - **No external web fonts** (Google Fonts, Adobe Fonts, etc.). All fonts (including *Material Symbols*) must be self-hosted locally in `assets/fonts/` and served via `assets/css/material-symbols.css`.
  - **No remote avatar or image URLs**: Avatar images (such as GitHub avatar URLs) must have offline local fallbacks (e.g. `$localDevAvatar` / local SVGs) and `onerror="this.style.display='none';"` to prevent network timeouts or broken layouts on disconnected LANs.
  - **No external analytics, tracking scripts, or unhosted third-party SDKs**.
- **Verification Requirement**: Any change that introduces an external network request will immediately fail the automated test `tests/offline_zero_cdn_test.php` and be rejected.

### B. Pure Vanilla PHP & Runtime Compatibility (PHP 8.1 – PHP 8.5+)
- **Vanilla PHP Only**: Native PHP with native PDO and sessions. Do **not** introduce Composer packages, npm runtime dependencies, or heavy frameworks (Laravel, Symfony, React, Vue, etc.).
- **PHP Modernization & Deprecation Safeguards**:
  - Target compatibility across **PHP 8.1, 8.2, 8.3, 8.4, and 8.5+**.
  - **Never use deprecated PHP functions**:
    - `finfo_close()` is deprecated since PHP 8.5 (the `finfo` object is automatically garbage-collected).
    - Never use implicit nullable parameter types like `function foo(string $arg = null)`. Always write explicitly: `?string $arg = null`.
    - Never create dynamic properties on classes without the `#[AllowDynamicProperties]` attribute.
    - Avoid deprecated string manipulation or character encoding functions (`utf8_encode`, `utf8_decode`).

---

## 2. Design System & Anti-"Vibe-Coding" Policy

### A. Absolute Prohibition of Unapproved "Vibe-Coding" & Reskins
- **Preserve Established Aesthetic**: The Examify visual identity is a clean, intentional **Deep Olive Green / Institutional Academic** theme.
- **Strictly Prohibited**:
  - **No unsolicited design overhaul**: Do not replace clean functional styles with trendy glassmorphism, glowing borders, or cyberpunk UI.
  - **No radial gradients or gradient text fills**: Backgrounds and headings must remain solid, crisp, and high-contrast.
  - **No unapproved color changes**: All styles must strictly draw from existing CSS tokens in `assets/css/variables.css`:
    - Primary: `--color-primary` (deep olive green `#2d5a27` / dark mode counterpart).
    - Active Nav Indicator: `--color-accent-gold` (`#ffd700` in `assets/css/admin-sidebar.css`).
    - Base backgrounds: `--bg-body`, `--bg-surface`, `--border-color`.
  - **No destructive edits to bespoke pages**: Do not discard or vandalize custom page designs (such as the simple, clean card layout of `developers.php`).

### B. Layout Containment & Responsive Design Standards
- **Table Containment**: Every data table must be enclosed inside a `<div class="table-wrap">` with horizontal overflow containment (`overflow-x: auto; -webkit-overflow-scrolling: touch;`) to guarantee responsiveness on tablets and laptops without breaking page containers.
- **Form Controls**: Form inputs, dropdowns, and buttons must use `width: 100%; box-sizing: border-box;` in grid or flex containers.
- **Adaptive Breakpoints**: Administrative and examination views must degrade gracefully down to mobile screens (using standard breakpoints: `1024px`, `768px`, `640px`, `480px`).

---

## 3. Security, Authorization & Concurrency Invariants

### A. SQL Injection Prevention
- **100% Prepared Statements**: Every SQL query that incorporates variable input **must** use PDO prepared statements with positional (`?`) or named (`:param`) parameters:
  ```php
  // CORRECT
  $stmt = $pdo->prepare("SELECT * FROM exams WHERE subject_id = ? AND status = ?");
  $stmt->execute([$subject_id, 'active']);

  // STRICTLY FORBIDDEN
  $pdo->query("SELECT * FROM exams WHERE subject_id = $subject_id");
  ```
- **Order & Limit Clauses**: Dynamic column names in `ORDER BY` or pagination `LIMIT` must be strictly whitelisted against an array of allowed column names before query concatenation.

### B. Cross-Site Scripting (XSS) Defense
- **100% Contextual Output Escaping**: Any dynamic data (from `$_GET`, `$_POST`, `$_SESSION`, database queries, or logs) echoed into HTML **must** be escaped using `e($val)` or `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.
- **Attribute Escaping**: Pay special attention to HTML attributes (`value="..."`, `title="..."`, `href="..."`, `data-*="..."`).
- **Inline JavaScript Escaping**: When passing PHP variables into inline `<script>` blocks, always use `json_encode($var, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)` rather than string interpolation.

### C. CSRF Protection
- **Mandatory CSRF Tokens**: Every state-changing HTML form (`<form method="POST">`) must include `<?= csrf_field() ?>`.
- **Mandatory Handler Verification**: Every POST handler must call `verify_csrf()` at the beginning of the request lifecycle before executing any business logic.

### D. Authorization & Broken Access Control (RBAC & IDOR)
- **Admin Guarding**: Every administrative page must require `admin/admin-guard.php` and verify specific capabilities:
  - `can_admin_manage_exam($pdo, $admin_id, $exam_id)`
  - `can_admin_manage_subject($pdo, $admin_id, $subject_id)`
  - `can_admin_manage_question($pdo, $admin_id, $question_id)`
- **Superadmin Only Restrictions**: Teacher provisioning (`admin/manage-teachers.php`), system database backup/restore (`admin/settings.php`), and bulk cohort promotion (`admin/manage-students.php`) must be strictly locked to `$_SESSION['admin_role'] === 'superadmin'`.
- **Student Guarding**: Every student page must require `student/student-guard.php`.
- **IDOR Defense**: Students must never be able to view or submit answers for another student's exam attempt or view exams they are not eligible for.

### E. Database Transactions & Concurrency Safety
- **Atomic Operations**: Final exam submission (`ExamEngine::submitExam`), answer auto-saving (`ExamEngine::saveAnswer`), attempt creation, and emergency time adjustments must execute inside database transactions (`$pdo->beginTransaction()`, `$pdo->commit()`, `$pdo->rollBack()`).
- **Idempotency & Race Conditions**: State updates on exam attempts must use status condition checks (e.g. `WHERE id = ? AND status = 'in_progress'`) to guarantee that rapid double-clicks or concurrent requests do not corrupt scores or duplicate attempt records.
- **Session Lock Release**: Endpoints that perform long-polling or real-time event checks must call `release_session_lock()` to avoid serializing concurrent requests from the same user session.

---

## 4. Component Synchronization & Shared Partials

Avoid duplicating layout blocks, headers, or modal logic. Leverage the centralized components in `components/`:

| Component | Path | Description |
|---|---|---|
| **Header** | `components/header.php` | Global `<head>`, meta tags, and `app.css` bundle inclusion. |
| **Footer** | `components/footer.php` | Global footer markup and universal password visibility toggle script. |
| **Admin Sidebar** | `components/admin-sidebar.php` | Navigation sidebar with gold active indicator (`--color-accent-gold`). |
| **Student Navbar** | `components/student-navbar.php` | Student top navigation with session info and timer indicator. |
| **Flash Messages** | `components/flash-messages.php` | Standardized alert banners reading from `get_flash()`. |
| **Confirm Modal** | `components/confirm-modal.php` | Accessible in-DOM dialog triggered via `data-confirm="..."`. |
| **Searchbar** | `components/searchbar.php` | Unified instant search input component. |
| **Pagination** | `components/pagination.php` | Clean pagination link builder for large dataset tables. |
| **Desktop Gating** | `components/desktop-required.php`| Lockout view preventing smartphones/tablets from taking exams. |

---

## 5. Forbidden Files & Git Cleanliness Rules

The following files and patterns are **strictly forbidden** from being committed or staged to git:

- ❌ `config/ca.crt` (offline local SSL certificate)
- ❌ `count_lines.py` or any temporary utility/scratch scripts
- ❌ `changes.md` or any unreviewed diff logs / scratch notes
- ❌ `.env` or any file containing live credentials
- ❌ Database backups (`*.sql.bak`, `*.dump`, `backups/`)
- ❌ Temporary logs (`logs/*.log`, `report/`, `php_errors.log`)

> **Pre-Commit Check**: Before staging files, always inspect `git status` to ensure none of these files are tracked or staged.

---

## 6. Mandatory Pre-Commit Verification Suite

Before proposing or committing any code, agents and contributors **must** execute the following verification steps:

```bash
# 1. PHP Syntax Check across all PHP files
find . -type f -name "*.php" -not -path "*/vendor/*" -exec php -l {} +

# 2. Run the Full Automated Test Suite (All 15 Suites Must Pass)
for t in tests/bulk_promote_test.php \
         tests/concurrency_test.php \
         tests/device_gating_test.php \
         tests/doc_access_test.php \
         tests/e2e_automation.php \
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
    echo "Running $t..."
    php "$t" > /dev/null || { echo "TEST FAILED: $t"; exit 1; }
done
echo "ALL TESTS PASSED!"
```

---

## 7. Git Workflow & Review Protocols

1. **No Direct Pushes to `main`**: All features, bug fixes, and design updates must be developed on a dedicated branch (e.g. `feat/<name>`, `fix/<name>`, `refactor/<name>`).
2. **Conventional Commits**: Commit messages must follow the Conventional Commits specification:
   - `feat(admin): make manage-subjects panel responsive`
   - `fix(security): prevent session hijacking during password reset`
   - `refactor(engine): use atomic status updates in submitExam`
   - `test(audit): add automated verification for offline font loading`
3. **Pull Request Requirements**:
   - Provide a clear, technical summary of changes.
   - Confirm adherence to Zero-CDN offline LAN architecture.
   - Include test execution output confirming 100% pass rate.
   - Never combine unrelated visual refactorings with security or bug fixes.

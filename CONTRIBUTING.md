# Contributing to Examify

Follow these guidelines to contribute to the Examify codebase.
These rules keep the software secure, fast, and consistent.

---

## 🏛️ Architectural Principles

1. **Pure Vanilla PHP**:
   - Write backend code using pure PHP 8.1+ with native PDO and sessions.
   - Do not add runtime frameworks or npm build tools.
2. **Offline Local Area Network Design**:
   - Design all features for isolated college computer laboratories.
   - Store all fonts, styles, and assets locally in the `assets/` directory.
   - Do not depend on external CDNs.
3. **Defense in Depth**:
   - Validate CSRF tokens on every state-changing POST request using `verify_csrf()`.
   - Escape all HTML output using `e($string)`.
   - Use prepared SQL statements with bound parameters for all database queries.

---

## 🎨 Code Style and Formatting

The automated CI workflow enforces strict formatting rules.

### 1. EditorConfig Rules
- **Line Endings**: Unix LF (`\n`) for all files.
- **Trailing Whitespace**: Remove trailing whitespace on save.
- **Final Newline**: Insert a final newline at the end of every file.
- **Indentation**:
  - `*.php`, `*.ps1`, `*.sh`: Use **4 spaces**.
  - `*.css`, `*.js`, `*.json`, `*.sql`, `*.yml`, `*.md`: Use **2 spaces**.

### 2. PHP Formatting (PSR-12)
- Enclose all control structures in braces `{ ... }`.
- Place opening braces for classes and methods on the next line.
- Place opening braces for control structures on the same line.
- Omit closing PHP tags (`?>`) at the end of pure PHP files.

---

## 🛠️ Verification Commands

Run these verification commands before you commit code:

### 1. Verification on Windows (PowerShell)
```powershell
# Verify PHP syntax across all project files
Get-ChildItem -Filter *.php -Recurse | ForEach-Object { php -l $_.FullName }

# Optional: Run MegaLinter locally (requires Docker & Node.js)
npx mega-linter-runner --flavor php
```

### 2. Verification on Linux or macOS (Bash)
```bash
# Verify PHP syntax across all project files
find . -type f -name "*.php" -exec php -l {} +

# Optional: Run MegaLinter locally (requires Docker & Node.js)
npx mega-linter-runner --flavor php
```

### 3. Run Automated Test Suite
```bash
php tests/security_and_unit_tests.php
php tests/singleton_login_test.php
php tests/device_gating_test.php
php tests/password_visibility_test.php
php tests/bulk_promote_test.php
php tests/concurrency_test.php
```

---

## 🚀 Pull Request Checklist

Verify these items before you submit a pull request:

- [ ] Automated MegaLinter CI checks pass with zero errors.
- [ ] All modified PHP files pass `php -l` syntax validation.
- [ ] You did not commit credentials, `.env` files, or local logs (`logs/*.log`).
- [ ] Every form contains `<?= csrf_field() ?>`.
- [ ] Every POST request handler calls `verify_csrf()`.
- [ ] Database queries use prepared statements with bound parameters.
- [ ] All automated tests pass with zero failures.


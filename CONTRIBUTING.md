# 🤝 Contributing to Examify

Thank you for contributing to **Examify**! To maintain a secure, high-performance, and consistent codebase, please review and adhere to the guidelines below before submitting pull requests.

---

## 🏛️ Architectural Principles

1. **Strict Vanilla PHP**:
  - The runtime project uses **pure Vanilla PHP 8.x** with native PDO and native session management.
  - Do **NOT** introduce heavy runtime framework dependencies (e.g., Laravel, Symfony) or npm frontend build chains (Webpack, Vite).
2. **Offline / College LAN First**:
  - All features must operate reliably in isolated local area network environments (e.g., college computer labs).
  - External CDN dependencies must have local fallbacks or be included directly in `assets/`.
3. **Defense in Depth**:
  - Verify CSRF tokens on every state-changing `POST` request using `verify_csrf()`.
  - Always escape HTML output with `e($string)` or `htmlspecialchars()`.
  - Use prepared statements (`PDO::prepare`) with parameter binding for all database queries.

---

## 🎨 Code Style & Formatting Standards

Our automated CI pipeline enforces strict formatting across all files.

### 1. EditorConfig Standards
- **Line Endings**: Unix LF (`\n`) across all files on all operating systems.
- **Trailing Whitespace**: Stripped automatically on save.
- **Final Newline**: Required at the end of every file.
- **Indentation**:
  - `*.php`, `*.sql`: **4 spaces**
  - `*.css`, `*.js`, `*.json`, `*.yml`, `*.md`: **2 spaces**
  - `*.{sh,bash,ps1}`: **2 spaces**

### 2. PHP Formatting (PSR-12)
- All control structures must use braces `{ ... }` (no single-line `if ($x) do();`).
- Opening braces for classes and methods on the next line; control structures on the same line.
- Do not let closing PHP tags (`?>`) appear at the end of pure PHP files.

---

## 🛠️ Developer Verification Tools

Before committing your code, run the local verification suite:

### 1. Check EditorConfig & Text Formatting
```powershell
# Windows (PowerShell)
.\tools\check-editorconfig.ps1

# Linux / macOS (Bash)
./tools/check-editorconfig.sh
```

### 2. Check PHP Syntax
```bash
# Check all PHP files for syntax errors
find . -name "*.php" -not -path "./vendor/*" -exec php -l {} +
```

### 3. Check PHP Code Style (PHP-CS-Fixer)
```bash
php php-cs-fixer.phar fix --dry-run --diff --config=.php-cs-fixer.dist.php
```

---

## 🚀 Pull Request Checklist

- [ ] My code passes `tools/check-editorconfig.ps1` with 0 errors.
- [ ] All modified PHP files pass `php -l` without syntax errors.
- [ ] No credentials, `.env` files, or local logs (`logs/*.log`) are committed.
- [ ] Forms include `<?= csrf_field() ?>` and handlers call `verify_csrf()`.
- [ ] Database queries use prepared statements with bound parameters.

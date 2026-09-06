# Production Build and Deployment Guide

Examify uses GitHub Actions to package production-ready release archives automatically.
The production archive contains only runtime files and assets.

---

## 1. Downloading the Release Package

Follow these steps to download the production build:

1. Open the repository on GitHub.
2. Click the **Releases** section on the right sidebar.
3. Select the latest version tag (for example, `v1.2.0`).
4. Download `examify-release.zip` or `examify-release.tar.gz`.
5. (Optional) Download `SHA256SUMS.txt` to verify archive integrity.
6. Extract the downloaded archive on your local computer.

---

## 2. Web Server Deployment

Follow these steps to deploy the extracted application:

1. Upload all extracted files to your web root directory (for example, `/var/www/html/` or `htdocs/`).
2. Create a `.env` file in the root directory:
   ```env
   APP_ENV=production
   DB_HOST=127.0.0.1
   DB_DATABASE=examify
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_secure_password
   DB_CHARSET=utf8mb4
   ```
3. Initialize the database schema:
   ```bash
   php init-db.php --schema-only
   ```
4. Open the application in your browser and complete the Superadmin setup wizard (`admin/setup.php`).
5. (Optional) Start the Real-Time WebSocket Daemon for live classroom proctoring:
   ```bash
   php bin/websocket-server.php &
   ```
   *(If not started, the application automatically falls back to in-place background HTTP polling with zero functionality loss).*

---

## 3. Automated Release Optimizations

The release packaging workflow performs these automated optimizations:

- **Static Asset Caching**: The setting `APP_ENV=production` enables browser caching headers.
- **Enforced SSL / HTTPS**: Setting `APP_ENV=production` automatically forces 301 redirects to HTTPS, sets `Strict-Transport-Security` (HSTS) headers, and marks session cookies as `Secure`.
- **CSS Minification**: The workflow minifies all files in `assets/css/` using `clean-css-cli`.
- **JavaScript Minification**: The workflow minifies and mangles client scripts in `assets/js/` (and `utils/`) using `terser`.
- **Zero Runtime Dependencies**: The workflow produces a pure Vanilla PHP deployment without build tools.

---

## 4. Excluded Files and Directories

The production archive excludes these development and test resources:

- Version control files (`.git/`, `.gitignore`, `.gitattributes`)
- Test suites and mock question banks (`tests/`)
- GitHub Actions workflows and issue templates (`.github/`)
- Linter configurations (`.mega-linter.yml`)
- Local secrets and environment files (`.env`)
- Local error logs (`logs/*.log`)

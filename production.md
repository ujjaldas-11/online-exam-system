# 📦 Production Build

Examify uses GitHub Actions CI/CD to automatically build and package production-ready artifacts.

## Downloading the Latest Build

To get the final build without any unnecessary files or development scripts:

1. Navigate to the **[Actions](https://github.com/ujjaldas-11/online-exam-system/actions)** tab on the GitHub repository.
2. Click on the latest successful workflow run (look for the green checkmark ✔️).
3. Scroll down to the **Artifacts** section.
4. Click on **examify-release** to download the `examify-release.zip` file.
5. Extract the `.zip` file. The extracted folder contains only the production-ready code.

## Deployment

Once you have extracted the production build:

1. Upload the contents of the extracted folder directly to your web server (e.g., inside `htdocs`, `www`, or `/var/www/html/`).
2. Configure your `.env` file with the live database credentials.
3. Initialize the database on your MySQL server using the database schema (`schema.sql` from the repository) as described in [Database Setup](README.md#3-initialize-database).

## ⚡ Production Optimizations

The CI pipeline applies automated performance optimizations before creating the release archive:
- **CSS Minification**: All stylesheets in `assets/css/` are processed with `clean-css-cli` to strip comments and collapse whitespace, reducing asset payload size.
- **JavaScript Minification & Mangling**: All client scripts in `utils/` are optimized and minified with `terser` (`--compress --mangle`).
- **Zero Build Tooling at Runtime**: Minification happens during GitHub Actions packaging, leaving the deployed application completely dependency-free Vanilla PHP.

## What is excluded?

The production artifact specifically excludes development, setup scripts, testing, and CI configuration files to ensure a clean deployment containing only runtime assets:
- `archive/` (raw SQL migrations and development schemas)
- `tools/` (local development utilities, format checkers, and seeders)
- `tests/` directory
- GitHub Actions workflows and issue templates (`.github/`)
- Version control and development dotfiles (`.git/`, `.gitignore`, `.gitattributes`)
- Linter configs (`.editorconfig`, `.editorconfig-checker.json`, `.php-cs-fixer.dist.php`)




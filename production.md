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
3. Set up the database as described in the [Database Setup](README.md#3-initialize-database) instructions (`php tools/setup-db.php` or importing `archive/schema.sql`).

## What is excluded?

The production artifact specifically excludes development, testing, and CI configuration files to ensure a clean deployment. For example, it excludes:
- `tests/` directory
- GitHub Actions workflows and issue templates (`.github/`)
- Version control and development dotfiles (`.git/`, `.gitignore`, `.gitattributes`)
- Linter configs (`.editorconfig`, `.editorconfig-checker.json`, `.php-cs-fixer.dist.php`)


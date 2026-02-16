# GRACe Local CI/Test Suite

This repository includes a manual CI testing suite designed to be run locally (WSL/Linux) to verify critical functionality and compliance requirements.

## ⚠️ Prerequisites

To run the full suite, you must have the following installed in your local environment:

*   **PHP 8.1+** (CLI)
*   **PHP SQLite3 Extension** (`php-sqlite3` or similar)
*   **Bash** (for the runner script)

**Note:** If you see `Database error: could not find driver`, you are missing the PHP SQLite3 extension.
Install it via: `sudo apt-get install php8.1-sqlite3` (adjust for your PHP version).

## How to Run

Execute the master runner script from the repository root:

```bash
bash tests/run_ci.sh
```

## What It Tests

### 1. Database Migrations (`tests/test_db_migration.php`)
*   **Fresh Install:** Verifies `init_db.php` can create a fresh database with all tables.
*   **In-Place Upgrade:** Simulates an older database (missing `upload_date`, `expiry_date` columns) and verifies `performMigrations()` correctly adds them without data loss.
*   **Schema Check:** Ensures `init_db.php` creates the expected modern schema.

### 2. Permission Logic (`tests/test_permissions.php`)
*   **Directory Creation:** Simulates `ensureUploadDirectories()` in a temporary folder.
*   **Verification:** Asserts that all required subdirectories (`offtakes`, `sops`, etc.) are created and writable.
*   **Logic Check:** Verifies the script can handle creating parent and child directories permissions.

### 3. Static Code Analysis (`tests/static_checks.sh`)
*   **Critical Paths:**
    *   Verifies Database path is `/data/grace.db`
    *   Verifies Upload path is `/data/uploads/`
*   **Limits:** checks for `1024 * 1024` (1MB) logic in `image-compress.js`.
*   **Timezone:** Verifies `Pacific/Auckland` is set.
*   **Security:** Scans for dangerous relative path usage (`__DIR__ . '/uploads'`).
*   **Duplicates:** Scans for duplicate `<script src="...">` tags in PHP files (prevent redeclaration errors).

### 4. Version Consistency (`tests/test_version_consistency.php`)
*   **Why:** Ensures the version number is identical across:
    *   `config.yaml` (Home Assistant)
    *   `nav.php` (UI Display)
    *   `CHANGELOG.md` (Release Notes)

### 5. PHP Syntax Check (`tests/syntax_check.sh`)
*   **Linting:** Runs `php -l` on all PHP files in `grace_addon/files/general/www/public/` to catch syntax errors before runtime.

## Manual Verification Checklist

In addition to running the automated suite, perform these manual checks:

*   [ ] **1MB Upload Limit**: Try uploading a >1MB file (after disabling JS compression) to verify server-side rejection.
*   [ ] **Image Compression**: Upload a large image and inspect the server for the compressed version.
*   [ ] **Persistent Data**: Verify `/data` contains `grace.db` and `uploads/` after a restart (in Home Assistant).

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

### 3. Annual Stocktake Logic (`tests/test_annual_stocktake.php`)
*   **Opening balance:** Plants that left stock in any earlier year (destroyed / sent / legacy-harvested) must not appear in a later year's Start Amount.
*   **Year boundaries:** Activity timestamped on 31 December still counts in that year's columns.
*   **Status rules:** "Harvested - Drying" counts as active stock; flower balances reconcile start + in - out - destroyed = end.

### 4. Agency Report Reminders (`tests/test_report_reminders.php`)
*   **Windows, not queues:** the monthly reminder only shows on days 1-7 (and only if last month shipped materials); the annual reminder only in January (and only with prior-year data). At most two banners, ever.
*   **Dismissals:** dismissing or drafting a period keeps it silent across reloads; fresh installs are never flooded.

### 5. Company Editing (`tests/test_company_editing.php`)
*   **Annual license renewal:** updating a verified company's license number, address, or contact persists correctly.
*   **Uniqueness:** a license number or contact email belonging to a *different* company is rejected; re-saving a company's own values always succeeds.
*   **Design rule:** asserts `company_lib.php` contains no delete operation. Verified companies can be edited but never deleted.

### 6. License Alerts (`tests/test_license_alerts.php`)
*   **Shared windows:** the nav banner (3 days) and Dashboard list (30 days) use one helper; each returns the right licenses for its window.
*   **Acknowledgment:** acknowledged licenses disappear from both surfaces; other document categories and licenses without expiry dates are never alerted.

### 7. Download Filenames (`tests/test_download_names.php`)
*   **Original names:** uploaded documents download under the name they were uploaded as; generated manifests lose their `uniqid()` prefix.
*   **Header safety:** the `Content-Disposition` value is a single quoted filename with no trailing semicolon, and matches the parser Android's download manager (used by the Home Assistant app) relies on. A malformed header made phones save licenses as `download-2.php`.
*   **MIME types:** correct types for PDFs and images.

### 8. Static Code Analysis (`tests/static_checks.sh`)
*   **Critical Paths:**
    *   Verifies Database path is `/data/grace.db`
    *   Verifies Upload path is `/data/uploads/`
*   **Limits:** checks for `1024 * 1024` (1MB) logic in `image-compress.js`.
*   **Timezone:** Verifies `Pacific/Auckland` is set.
*   **Security:** Scans for dangerous relative path usage (`__DIR__ . '/uploads'`).
*   **Duplicates:** Scans for duplicate `<script src="...">` tags in PHP files (prevent redeclaration errors).

### 9. Version Consistency (`tests/test_version_consistency.php`)
*   **Why:** Ensures the version number is identical across:
    *   `config.yaml` (Home Assistant)
    *   `nav.php` (UI Display)
    *   `CHANGELOG.md` (Release Notes)

### 10. PHP Syntax Check (`tests/syntax_check.sh`)
*   **Linting:** Runs `php -l` on all PHP files in `grace_addon/files/general/www/public/` to catch syntax errors before runtime.

## Demo / Development Helpers (not part of CI)

*   `tests/seed_demo_data.php [--force]` fills a dev database with realistic content (plants, ledger entries, companies, documents, manifests). See DEVELOPMENT.md.
*   `tests/demo_report_reminders.php [from] [to]` replays the dashboard reminder decisions for any date range against the dev database.

## Manual Verification Checklist

The CI suite is PHP-only, so browser behaviours still need a manual pass
(spin up a dev server per DEVELOPMENT.md and use the seeded demo data):

*   [ ] **1MB Upload Limit**: Try uploading a >1MB file (after disabling JS compression) to verify server-side rejection.
*   [ ] **Image Compression**: Upload a large image and inspect the server for the compressed version.
*   [ ] **Persistent Data**: Verify `/data` contains `grace.db` and `uploads/` after a restart (in Home Assistant).
*   [ ] **Quick select** (Harvest / Destroy / Send): selecting N of a genetics ticks the oldest/youngest N including drying plants, re-running replaces that genetics' selection, and manual ticks still work.
*   [ ] **Dashboard banners**: report reminder banners appear in their windows (use `dashboard.php?demo_date=YYYY-MM-DD`), Dismiss persists, and acknowledged licenses stay out of "License Renewals Due".
*   [ ] **Draft email buttons**: open a pre-filled email in a new tab (webmail-safe) on the monthly and annual report pages.

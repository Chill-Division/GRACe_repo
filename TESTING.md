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

### 2. Genetics Upgrade (`tests/test_genetics_upgrade.php`)
*   **Fresh installs:** the `Genetics` table has only `id` and `name` (Breeder and Genetic Lineage were removed in 1.1.0).
*   **Upgrading 1.0.x databases:** builds a database with the old schema, with the two fields filled in on some genetics and blank, empty or whitespace-only on others, then runs `performMigrations()`. The columns must be dropped while every genetics keeps its id and name, and every plant and flower entry keeps its genetics link (a careless table rebuild would wipe them through `ON DELETE SET NULL`). Foreign key and integrity checks must pass.
*   **Nothing typed is lost:** filled-in values are kept in `LegacyGeneticsDetails`; blanks are not.
*   **Safe to repeat:** a second run changes nothing, adding genetics and the stocktake reports keep working, and on SQLite older than 3.35 the columns are left in place instead.

### 3. Permission Logic (`tests/test_permissions.php`)
*   **Directory Creation:** Simulates `ensureUploadDirectories()` in a temporary folder.
*   **Verification:** Asserts that all required subdirectories (`offtakes`, `sops`, etc.) are created and writable.
*   **Logic Check:** Verifies the script can handle creating parent and child directories permissions.

### 4. Harvest Safeguards (`tests/test_harvest_safeguards.php`)
*   **History can't be rewritten:** plants that have already left (sent, destroyed) can't be processed again, for example from a stale browser tab. They keep their status, date and company.
*   **All or nothing:** if any ticked plant can't take the action, the whole batch is refused and nothing changes. Drying plants can't be harvested again (that used to reset their harvest date).
*   **Nothing is guessed:** a mistyped action (it used to be recorded as Sent), a send without a company or to an unknown company, and plant ids that aren't numbers are all refused.
*   **Clear messages:** "Marked 1 plant as sent to Buyer Ltd." (it used to say "sended"), with the right singular or plural.

### 5. Annual Stocktake Logic (`tests/test_annual_stocktake.php`)
*   **Opening balance:** Plants that left stock in any earlier year (destroyed / sent / legacy-harvested) must not appear in a later year's Start Amount.
*   **Year boundaries:** Activity timestamped on 31 December still counts in that year's columns.
*   **Status rules:** "Harvested - Drying" counts as active stock; flower balances reconcile start + in - out - destroyed = end.

### 6. NZ Time (`tests/test_nz_time.php`)
*   **Upgrading a 1.0.x install:** builds a database with times stored the old way (UTC, from SQLite's `DATETIME('now')`), runs `performMigrations()`, and checks each one now reads NZ time: summer and winter time, both sides of a daylight saving change, and entries that move month or year (a plant sent on the morning of 1 March leaves February's report and joins March's).
*   **Left alone:** flower deducted by a shipping manifest (always NZ time), dates without a time, blank dates and anything that isn't a date.
*   **Exactly once:** a second run changes nothing, fresh installs have nothing to convert, entries written after the upgrade are never shifted, and a conversion that has to be retried only touches the rows that existed before the upgrade.
*   **No UTC clock:** no page or handler uses SQLite's `'now'` or `CURRENT_TIMESTAMP`; handlers stamp entries with `ledgerTimestamp()`, and report dates are shown as stored instead of being re-read in the browser's time zone.

### 7. Agency Report Reminders (`tests/test_report_reminders.php`)
*   **Windows, not queues:** the monthly reminder only shows on days 1-7 (and only if last month shipped materials); the annual reminder only in January (and only with prior-year data). At most two banners, ever.
*   **Dismissals:** dismissing or drafting a period keeps it silent across reloads; fresh installs are never flooded.

### 8. Monthly Report Periods (`tests/test_report_periods.php`)
*   **Last month is the previous calendar month:** on the 29th, 30th and 31st, "Last month's materials out" used to show this month's figures under last month's heading, because `strtotime('-1 month')` on 31 July gives "31 June", which PHP rolls over to 1 July. Checked on month ends, across the new year and in a leap year.
*   **Right rows:** the report holds only sends and lab samples dated inside the month, including its first and last second.
*   **Heading and figures agree:** the page passes its month to the data endpoint, and anything other than a real `YYYY-MM` in the URL is ignored.

### 9. Company Editing (`tests/test_company_editing.php`)
*   **Annual license renewal:** updating a verified company's license number, address, or contact persists correctly.
*   **Uniqueness:** a license number or contact email belonging to a *different* company is rejected; re-saving a company's own values always succeeds.
*   **Design rule:** asserts `company_lib.php` contains no delete operation. Verified companies can be edited but never deleted.

### 10. License Alerts (`tests/test_license_alerts.php`)
*   **Shared windows:** the nav banner (3 days) and Dashboard list (30 days) use one helper; each returns the right licenses for its window.
*   **Acknowledgment:** acknowledged licenses disappear from both surfaces; other document categories and licenses without expiry dates are never alerted.

### 11. License Expiry Limit (`tests/test_license_expiry.php`)
*   **The limit:** a license upload may expire up to 15 months from today, because licenses are annual and a renewal can be issued up to 3 months early (it used to be 12 months, which blocked early renewals). Exactly on the limit is accepted, one day past it is refused, and month ends clamp (15 months from 30 November is 29 February).
*   **Bad input:** text, impossible dates and wrong formats are refused; already-expired licenses can still be uploaded for the record.
*   **One rule everywhere:** `upload.php` and the Company Licenses date picker both use the shared helper.

### 12. Download Filenames (`tests/test_download_names.php`)
*   **Original names:** uploaded documents download under the name they were uploaded as; generated manifests lose their `uniqid()` prefix.
*   **Header safety:** the `Content-Disposition` value is a single quoted filename with no trailing semicolon, and matches the parser Android's download manager (used by the Home Assistant app) relies on. A malformed header made phones save licenses as `download-2.php`.
*   **MIME types:** correct types for PDFs and images.

### 13. Static Code Analysis (`tests/static_checks.sh`)
*   **Critical Paths:**
    *   Verifies Database path is `/data/grace.db`
    *   Verifies Upload path is `/data/uploads/`
*   **Limits:** checks for `1024 * 1024` (1MB) logic in `image-compress.js`.
*   **Timezone:** Verifies `Pacific/Auckland` is set.
*   **Security:** Scans for dangerous relative path usage (`__DIR__ . '/uploads'`).
*   **Duplicates:** Scans for duplicate `<script src="...">` tags in PHP files (prevent redeclaration errors).
*   **PHP extensions:** Fails on `ctype_*` or `mb_*` functions. The add-on image only loads `pdo`, `pdo_sqlite` and `session` on top of PHP's core, so they would crash in production even though they work on a dev PC.

### 14. Version Consistency (`tests/test_version_consistency.php`)
*   **Why:** Ensures the version number is identical across:
    *   `config.yaml` (Home Assistant)
    *   `nav.php` (UI Display)
    *   `CHANGELOG.md` (Release Notes)

### 15. PHP Syntax Check (`tests/syntax_check.sh`)
*   **Linting:** Runs `php -l` on all PHP files in `grace_addon/files/general/www/public/` to catch syntax errors before runtime.

## Demo / Development Helpers (not part of CI)

*   `tests/seed_demo_data.php [--force] [--legacy]` fills a dev database with realistic content (plants, ledger entries, companies, documents, manifests). `--legacy` shapes it like a 1.0.x install (old Genetics columns, UTC times) so you can watch the 1.1 upgrade run on the next page load. See DEVELOPMENT.md.
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
*   [ ] **License date picker**: on Company Licenses, the date picker stops at 15 months from today.

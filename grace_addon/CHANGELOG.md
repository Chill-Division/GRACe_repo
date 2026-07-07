## [0.18.0] - 2026-07-06
### Features
- **Edit Verified Companies**: Administration now has an "Edit Verified Companies" page. Pick any company you've added and update its license number (they change every year), address, or staff contact. New manifests and Chain of Custody paperwork use the updated details straight away, while your existing ledger history stays exactly as it was recorded. Companies can be edited but never deleted, because your records reference them.
- **Quick select on Harvest / Destroy / Send**: got 90 plants of one genetics but only sending 88? Choose the genetics, type 88, and GRACe ticks them for you, starting from the oldest (or youngest, your choice). You can still tick or untick individual plants afterwards, for example to hold back a mother plant, and nothing is written until you confirm as usual.

### Fixes
- Licenses you've already acknowledged no longer keep showing in the Dashboard's "License Renewals Due" list. Acknowledge an expired license once on the Company Licenses page and it disappears from the Dashboard too.

## [0.17.2] - 2026-06-13
One big update. Everything from 0.15 through 0.17.2 rolled together.

### Features
- **Fresh new look**: GRACe has been redesigned from top to bottom, with a new Dashboard home page, tap-friendly menus and cards, dark & light themes, and a layout that works great on phones, tablets, and PCs.
- **Dashboard**: your grow at a glance, showing plants growing and drying, dried flower on hand, what went out this month, manifests awaiting paperwork, and license renewal reminders.
- **Agency report reminders**: in the first week of each month, the Dashboard reminds you to send last month's materials-out report, but only if you actually sent materials out, so quiet months stay quiet. In January it also reminds you about the annual stocktake. Reminders can be dismissed and never pile up.
- **One-tap report emails**: a "Draft this in an email" button on the monthly materials-out and annual stocktake reports opens a ready-to-send email to the Medicinal Cannabis Agency, with the subject line, your company details, and the full report already filled in. No more copy/paste.
- **Smarter shipping manifests**: a manifest now stays "In Progress" until the signed Chain of Custody is attached, shipped flower is automatically deducted from your inventory, and every exchange has its own summary page with the PDF and CoC.
- **Annual report fix**: plants destroyed or sent in past years no longer show up as stock in later years' annual stocktake, so the report's totals now match your real live stock.

### Improvements
- A double-check screen before harvesting, destroying, or sending plants, because the ledger can't be edited afterwards.
- Friendlier in-app messages instead of browser pop-ups.
- "Download backup" saves your whole ledger as a single file.
- Works on Home Assistant boxes with no internet connection.
- New leaf icon and logo for the addon.

### Fixes
- "List all plants" page was showing an empty table.
- Menu items overlapped on phones, and the menu icon sat off-centre in the Home Assistant app.
- Activity recorded on 31 December was missed by the annual report.
- Manifests between two external companies recorded the wrong sender.
- Refreshing the manifest result page could create a duplicate.
- "Draft this in an email" now opens in a new tab, so webmail (like Gmail) works from inside the Home Assistant app.
- Tidied up the menus: removed the empty "Recalls" placeholder from Plant Tracking, and put Record Management at the top of the Administration page in a more sensible order.

### Under the hood
- Removed old unused login code and bundled demo files; added a developer guide (`DEVELOPMENT.md`), a demo-data seeder, and more automated tests.

## [0.14.1] - 2026-02-16
### Added
- **Administration**: Added a tool to migrate legacy "Harvested" plants to "Harvested - Destroyed" status.

## [0.14] - 2026-02-16
### Added
- **Annual Stocktake**: Updated logic to treat "Harvested - Drying" plants as active stock in annual reports.
- **Database**: Added automatic migration for `Plants` table to support "Harvested - Drying" and "Harvested - Destroyed" statuses.
- **UI**: Added a floating "Selection Counter" to the Harvest Plants page.
- **UI**: Standardized status labels in harvest dropdowns to match database values.

### Refactor
- **JavaScript**: Moved inline JavaScript from 11 PHP files into 5 modular JS files in `js/` directory.
    - `js/reports.js`: Handles `current_dried_flower.php`.
    - `js/transactions.js`: Handles `this_months...` and `last_months...` listings.
    - `js/documents.js`: Handles `company_licenses.php`, `sops.php`, `offtake_agreements.php`, `police_vet_check_records.php`, `chain_of_custody_documents.php`.
    - `js/genetics.js`: Handles `receive_genetics.php`.
    - `js/transaction_form.js`: Handles `record_dry_weight.php`.
- **Performance**: Enabled browser caching for static JS resources (previously inline).
- **Maintenance**: Improved code organization and reduced code duplication in document management pages.

## [0.13.2] - 2026-02-07
### Fixed
- **Transactions UI**: Fixed `ReferenceError: totalWeight is not defined` on flower transaction pages.

## [0.13.1] - 2026-02-07
### Added
- **Local CI/Test Suite**: Added comprehensive manual testing suite (`tests/`) for local development.
- **Documentation**: Added `TESTING.md` with instructions for running local tests.
- **CI Scripts**: Added database migration verification, static code analysis, and version consistency checks.
- **Dependencies**: Added checks for `php-sqlite3` requirement in test suite.

## [0.13] - 2026-02-07
### Added
- **Image Compression**: Added client-side image compression (`image-compress.js`) to automatically compress images >1MB.
- **Upload Improvements**: Enforced 1MB file size limit (post-compression) and improved error handling for uploads.
- **UX**: Added alphabetic filtering to multiple record management pages.
- **Backend**: Added `upload_date` tracking for all documents and duplicate prevention for Companies/Genetics.

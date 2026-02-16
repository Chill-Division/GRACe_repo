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

## [0.12.1] - 2026-02-05
### Added
- **UI**: Added version number display to top navigation.

## [0.12] - 2026-02-05
### Added
- **Company License Expiry Feature**: Alerts for expiring licenses and acknowledgement workflow.
- **Database Schema**: Added `expiry_date` and `acknowledged` fields to `Documents` table.
- **Storage**: Implemented permanent storage for uploads in `/data/uploads`.
- **Secure File Download**: Implemented `download.php` for serving files.
- **Reporting**: Added column totals to "Materials Out" and "Annual Stocktake" reports.

### Fixed
- "Fixed tracking.php invalid downloads" by moving to permanent storage.

## [0.11.1] - 2025-02-04 
- Added "Hide rows with zero values" to some of the reports so we don't see empty rows

## 0.11

- Added Chain of Custody (CoC) document upload page
- Fixes for Shipping Manifest generation

## 0.10

- Added file upload for SOPs, Offtakes, Licenses, CoCs etc
- Fix shipping manifest generation
- Fix Pacific/Auckland timezone

## [0.13.2] - 2026-02-07
### Fixed
- **Transactions UI**: Fixed `ReferenceError: totalWeight is not defined` on flower transaction pages.
- **JavaScript**: Fixed `SyntaxError: Identifier 'isLight' has already been declared` caused by duplicate script inclusion in transaction pages.

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

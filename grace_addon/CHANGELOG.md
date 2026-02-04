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

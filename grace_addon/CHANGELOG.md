## [0.15.1] - 2026-06-12
### Added
- **Dashboard**: New landing page with at-a-glance stats (plants growing/drying, dried flower on hand, materials out this month), license renewal warnings, and quick actions. The portal now opens here instead of Plant Tracking.
- **Confirmation step**: Harvest / Destroy / Send now shows a review modal summarising exactly which plants are affected before writing to the ledger (records cannot be edited afterwards).
- **Toasts**: All browser `alert()` / `confirm()` popups replaced with inline toast notifications and styled confirmation modals; success messages now survive the page reload.
- **PWA**: Web app manifest + home-screen icons so GRACe can be pinned to a phone like an app.
- **Branding**: Home Assistant addon icon and logo regenerated with the new leaf brand mark.

### Changed
- **Offline support**: Pico CSS and jQuery are now self-hosted (no CDN), so the portal renders correctly on offline / air-gapped Home Assistant boxes. Font Awesome CDN already removed in 0.15.
- **Auth**: Removed the vestigial `auth.php` (and all references) — authentication is handled by Home Assistant itself. It only started a session; the login redirect had been disabled for some time.
- **Backup**: "Dump database" is now "Download backup" — the full ledger downloads as a timestamped JSON file instead of rendering raw JSON in the browser.
- **Developer experience**: New `DEVELOPMENT.md` (local dev server, demo data, release checklist, notes for AI assistants) and `tests/seed_demo_data.php` to fill a dev database with realistic demo data including downloadable documents.

### Removed
- **Dead auth code**: `login.php`, `logout.php`, `config.php`, `get_users.php` (legacy MySQL login flow — `login.php` contained injectable string-interpolated SQL and `config.php` held unused MySQL credentials), plus the orphaned `get_rooms.php`, `phpinfo.php` (information disclosure), and a stale `public/README.md` documenting the pre-addon setup.
- **TCPDF bloat**: pruned `examples/`, `tests/`, `tools/`, and `scripts/` from the vendored TCPDF — hundreds of web-executable demo PHP files are gone from the public web root.

### Security / Fixed (additional)
- **Shipping manifest PDF**: fixed `require_once 'tcpdf/tcpdf.php'` (lowercase) to match the actual `TCPDF/` directory — on the case-sensitive container filesystem this include could not resolve.

### Fixed
- **Navigation**: Nav links no longer overlap (horizontally on desktop, vertically in the mobile menu) — Pico's negative link margins are now neutralised. Mobile menu panel is fully opaque, the hamburger icon is positioned reliably, and the menu state resets after back/forward navigation.

## [0.15] - 2026-06-12
### Changed (UI Revamp)
- **Design**: New design system layered on Pico CSS — green brand palette, refreshed dark and light themes, sticky translucent header, and consistent cards/tables/forms across every page.
- **Navigation**: Redesigned nav bar with brand mark, active-section highlighting, and a cleaner mobile slide-down menu.
- **Hub pages**: Plant Tracking, Reporting, and Administration landing pages now use tappable icon cards with descriptions instead of bullet lists. "Coming soon" items are shown as disabled cards instead of links to missing pages.
- **Theme**: Light/dark preference is now persisted (localStorage) and follows the device preference on first visit; no more flash of the wrong theme or focus-stealing tooltip on load.
- **Tables**: All report tables are wrapped in horizontally scrollable cards for phones/tablets, with styled headers and zebra striping.
- **Status badges**: Plant statuses (Growing / Drying / Destroyed / Sent) render as colour-coded badges on the plant list and harvest pages.
- **Transaction reports**: "Total weight sent out" now displays as a summary stat card.
- **License alerts**: Expiry banner restyled via stylesheet instead of inline styles.

### Fixed
- **List All Plants**: Restored the table population logic that was lost in the v0.14 JavaScript refactor (the page previously rendered an empty table).

### Refactor (presentation layer only)
- **Shared layout**: New `header.php`/`footer.php` partials replace per-page duplicated `<head>`/boilerplate; page titles and jQuery loading are driven by variables. CSS/JS now cache-busted by addon version.
- **Dependencies**: Removed the Font Awesome CDN (download icon replaced with an inline SVG); document pages now load one less stylesheet.

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

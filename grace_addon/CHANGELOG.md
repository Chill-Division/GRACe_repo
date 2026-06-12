## [0.16.1.1] - 2026-06-12
### Fixed
- **Mobile hamburger icon**: the icon could render in the top-left corner of its tap target (visibly misaligned from the highlighted outline when tapped) in some mobile WebViews, e.g. the Home Assistant companion app. The bars are now pinned to the exact centre of the tap target with absolute offsets instead of flex centring, which those WebViews failed to apply.

## [0.16.1] - 2026-06-12
### Fixed
- **Annual Stocktake opening balance**: plants that left stock more than one year before the report year (destroyed / sent / legacy-harvested) kept appearing in the Start Amount and End columns forever. The old query only subtracted departures dated within the *immediately previous* year, so e.g. a plant destroyed in 2024 still showed as stock-on-hand in the 2026 report. The opening balance is now "created before 1 Jan and not departed before 1 Jan", regardless of which year the departure happened. The report's End total now reconciles exactly with live stock (Growing + Drying).
- **Annual Stocktake 31-December boundary**: plant and flower activity timestamped on 31 December after midnight (e.g. `2026-12-31 14:00`) was silently dropped from the year's In/Out/Destroyed columns because datetimes were string-compared against a plain `YYYY-12-31` upper bound. All date comparisons now use `DATE(...)` with an exclusive 1-January upper bound.
- 'Harvested - Drying' plants harvested in an earlier year are now consistently treated as active stock in the opening balance (matching how the End column has treated them since 0.14).

### Refactor
- Stocktake calculations extracted to `annual_stocktake_lib.php`, shared by both report endpoints and covered by a new regression test (`tests/test_annual_stocktake.php`, wired into `tests/run_ci.sh`).

## [0.16.0] - 2026-06-12
### Added
- **Manifest lifecycle**: Generating a shipping manifest now records it in the ledger as **In Progress**. It stays In Progress until the signed Chain of Custody (photo or PDF) is attached — a manifest cannot be completed without one (enforced server-side).
- **Automatic flower deduction**: Flower shipped from us to an external company is deducted from the dried-flower ledger at the moment the manifest is generated (reason `Send external`, so monthly Agency reports and dashboard totals pick it up exactly like a manual entry). The generate page now explains this up front and shows the recorded stock for the selected genetics; generation is blocked if the manifest weight exceeds recorded stock.
- **Complete Manifest page** (`complete_manifest.php`): lists every manifest awaiting completion (date, destination, what was shipped) and lets you pick one to complete by uploading the CoC or attaching one already uploaded on the Chain of Custody page. Replaces the "Amend / Complete Manifest — coming soon" card — manifests in transit are completed, not amended.
- **Exchange summary page** (`manifest_summary.php`): source / destination / shipment details, inventory deduction, CoC download, and the manifest PDF for each exchange. Linked from the Chain of Custody page and the Complete Manifest page.
- **Chain of Custody page**: now also shows all shipment exchanges with their status and CoC state. Manual standalone CoC uploads still work exactly as before.
- **Dashboard**: "Manifests awaiting CoC" stat linking to the Complete Manifest page.
- **Manifest PDFs are persisted** to `/data/uploads/manifests/` and re-downloadable from the summary page; the PDF now also carries the manifest number, genetics, and a note that it must be completed in GRACe.
- **Database**: `ShippingManifests` gains `status`, party-name snapshots, genetics, quantity, destination, flower-transaction link, CoC link, and completion date. Existing installations are migrated in place automatically on first page load — no data loss, no manual steps.

### Fixed
- **Shipping manifest form**: sending and receiving parties no longer share form field names, so an external→external manifest records both companies correctly (previously the receiving company silently overwrote the sending one). The PDF no longer prints the literal text "us"/"external" as the preparing staff name.
- **Shipping manifest processing**: generating a manifest is now a single POST/redirect flow — refreshing the result page can no longer re-run generation, and the stale session-replay code is gone.

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

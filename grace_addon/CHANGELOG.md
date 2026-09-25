## [1.1.1] - 2026-09-25
### Important fixes
- **Current Plants now includes drying plants.** They were left out before, even though they're still on your site and your annual stocktake counts them. Each genetics now shows growing plus drying.

### Changes
- The user guide now explains how to record a sample cut from a plant, and that drying plants stay in stock until you send or destroy them.

## [1.1.0] - 2026-09-24
Quicker data entry, more safety nets, and some important report fixes.

### What's new
- **Add a genetics or a company without leaving the page.** Missing from the list? Choose "+ Add new genetics…" or "+ Add new company…" at the bottom of it. It's added and selected straight away, and nothing you've already typed is lost. Works on Receive plants, Record dry weight change, Harvest / Destroy / Send and Generate Shipping Manifest.
- **See your stock while you enter.** Pick a genetics and GRACe shows how many are growing, or how much dried flower you have and what's left after this entry.
- **Check before you save.** Receive plants and Record dry weight change now show a summary to confirm first, like Harvest / Destroy / Send already did. The ledger can't be edited afterwards, so this is your chance to catch a typo.
- **Catch the extra zero.** Anything bigger than usual needs an extra tick before it's saved. You set what "big" means in Administration → Entry warning limits. It starts at 100 plants and 5,000 g, and we suggest about half of what one of your flower rooms holds.
- **Recent entries.** Your last 10 entries are listed under each form, so you can check what you just did and spot anything entered twice.
- **Clear confirmations.** After saving, GRACe tells you exactly what was recorded, and how many plants are now growing or how much flower is left.
- **Early license renewals.** A license can now be uploaded with an expiry date up to 15 months away, so a renewal issued up to 3 months early goes straight in.

### Important fixes
- **Last month's report shows the right month.** On the 29th, 30th and 31st it used to show this month's figures under last month's heading, and "Draft this in an email" would have sent them.
- **Times are now NZ time.** GRACe used to save times in UTC, 12 or 13 hours behind NZ, so anything recorded before about 1pm on the 1st of a month counted towards the month before. Updating corrects your older entries too. A few may move into the month they really happened in, so an older monthly report you open again can differ slightly from the one you sent.
- **Flower can't go below zero.** Record dry weight change won't subtract more flower than you have on record. Shipping manifests already worked this way.
- **Plants can't be processed twice.** If some plants were already processed, for example in another tab or on another device, Harvest / Destroy / Send changes nothing and shows you the current list, so old entries can't be overwritten.
- **Company names with apostrophes or quotes**, like Joe's Farm, are saved and shown properly, including in Agency emails. Names saved by older versions are repaired automatically.

### Changes
- Plant counts are whole numbers, and one entry can add up to 1,000 plants. Split anything bigger into a few entries.
- Weights are grams to one decimal place, like 612.5 g.
- Adding a genetics spots near-duplicates, so the same name typed with different capitals or extra spaces isn't added twice.
- On shipping manifests, an external company is no longer picked for you. Choose it from the list.
- The unused Breeder and Genetic Lineage fields are gone. Anything you had filled in is kept in your records.
- The user guide now explains setting up Home Assistant backups, which are your real backup. "Download backup" is only for an extra copy, for example for an auditor.

### Minor bug fixes and reliability improvements
- A double tap can't save an entry twice, and plants are received all at once or not at all.
- Messages about problems stay on screen until you've read them, and forms keep what you typed.
- Clearer wording, like "Marked 5 plants as sent to …" on Harvest / Destroy / Send, plus other small fixes.

## [1.0.1] - 2026-09-08
### Fixes
- Downloads on your phone now have the right file name. A license (or any other document) downloaded through the Home Assistant app used to show up as "download.php". It now saves with its proper name, like "cultivation-license-2026.pdf".

## [1.0.0] - 2026-07-10
GRACe's first stable release!

### Features
- **Edit Verified Companies**: Administration now has an "Edit Verified Companies" page. Pick any company you've added and update its license number (they change every year), address, or staff contact. New manifests and Chain of Custody paperwork use the updated details straight away, while your existing ledger history stays exactly as it was recorded. Companies can be edited but never deleted, because your records reference them.
- **Quick select on Harvest / Destroy / Send**: got 90 plants of one genetics but only sending 88? Choose the genetics, type 88, and GRACe ticks them for you, starting from the oldest (or youngest, your choice). You can still tick or untick individual plants afterwards, for example to hold back a mother plant, and nothing is written until you confirm as usual.

### Changes
- **Light theme is now the default.** The first visit opens in light mode; the sun/moon toggle still remembers whichever theme you pick.

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

# AGENTS.md

Rules for AI coding assistants and human contributors working on GRACe.

AI tools pick this file up automatically (Claude Code through `CLAUDE.md`;
Codex, Cursor, GitHub Copilot and others read it directly). People should
read it too before opening a pull request. How-to guides live elsewhere:
[DEVELOPMENT.md](DEVELOPMENT.md) covers running GRACe locally with demo data
and the release checklist, and [TESTING.md](TESTING.md) covers the CI suite.

## 1. Persistent storage (absolute rule)

GRACe runs as a Home Assistant add-on inside a Docker container. Only
`/data` is a persistent volume. Everything else in the container is wiped
on every add-on update and restart.

| Resource | Required path | Notes |
|----------|---------------|-------|
| Database | `/data/grace.db` | SQLite database file |
| Uploads | `/data/uploads/` | Every uploaded document and generated manifest PDF |

- These paths must never change. Reject any pull request that changes them.
- `init_db.php` must keep `initializeDatabase($dbPath = '/data/grace.db')`.
- `upload.php` and `download.php` must keep `$uploadDir = '/data/uploads/';`.
- Never store data under a relative path such as `__DIR__ . '/uploads/'`.
  It would be lost on the next update.
- `tests/static_checks.sh` checks all of this in CI.

This rule takes precedence over every other consideration.

## 2. Database changes upgrade in place

Every install upgrades its own `/data/grace.db` on the first page load after
an update: `performMigrations()` in `init_db.php` runs on every request.

- Schema changes must upgrade existing databases automatically, with no data
  loss and no manual steps, and must be safe to run again on every request.
- Add a regression test that builds a database with the old schema, runs
  `performMigrations()`, and proves the data survived. See
  `tests/test_db_migration.php` and `tests/test_genetics_upgrade.php`.
- Foreign keys are on (`PRAGMA foreign_keys = ON`). `Plants` and `Flower`
  link to `Genetics` and `Companies`, and `ShippingManifests` links to
  `Companies`, all with `ON DELETE SET NULL`. Never rebuild a parent table
  with `DROP TABLE` plus a rename while foreign keys are on: SQLite would
  null out every link to it. Use `ALTER TABLE ... ADD COLUMN` or
  `DROP COLUMN`, which change the table in place.
- Never destroy data people typed in. When a field is retired, keep its old
  values somewhere (see `LegacyGeneticsDetails` in `init_db.php`).
- A one-off data fix (not a schema change) must run exactly once per
  install. Record it in the `DataMigrations` table, the way
  `convertLedgerTimesToNzTime()` does.
- Every stored time is NZ time (Pacific/Auckland), because the Agency
  reports split months and years in NZ time. Stamp entries with
  `ledgerTimestamp()`. Never use SQLite's `DATETIME('now')`,
  `CURRENT_TIMESTAMP` or `julianday('now')`: they are UTC, 12 or 13 hours
  behind NZ (`tests/test_nz_time.php` checks for them).

## 3. Product rules

- The ledger is append-only. No UI or endpoint for editing or deleting
  historical plant or flower records. Mistakes are corrected with a
  compensating entry.
- Verified companies can be edited but never deleted. The ledger, manifests
  and Chain of Custody history reference them by id. Don't add a delete
  button, endpoint or cascade (`tests/test_company_editing.php` checks
  `company_lib.php` has no `DELETE`).
- Authentication is Home Assistant's job. There is intentionally no login
  system (the old `auth.php` and `login.php` were removed in 0.15.1).
- GRACe is served through Home Assistant ingress, under a deep path. Always
  use relative URLs for links, assets and fetch calls, never paths that
  start with `/`.
- No service worker or offline-first support. Users always reach GRACe over
  their LAN through their Home Assistant server. Pico CSS and jQuery are
  vendored (`css/vendor/`, `js/vendor/`) only because the Home Assistant
  server itself may have no internet access.
- The add-on image runs PHP 8.3 with only `pdo`, `pdo_sqlite` and `session`
  on top of PHP's core (see `grace_addon/Dockerfile`). Functions from other
  extensions, such as `ctype_digit()`, `mb_strtolower()` or `iconv()`, work
  on a dev PC but crash in production. Use `preg_match()` and plain string
  functions, or guard with `function_exists()`.
- Home Assistant backups are the backup plan: they include `/data`, so the
  database and every uploaded document. The "Download backup" JSON export is
  an ad-hoc extra copy for audits. It leaves out the uploaded files and
  GRACe can't restore from it, so never present it as the main backup.
- Flower weights are grams to one decimal place (0.1 g), in every form and
  every server check. GRACe's users aren't GMP facilities, and neither their
  work nor NZ's Medicinal Cannabis Agency needs hundredths of a gram, so
  don't bring back 0.01 g inputs. Older installs may hold entries with
  hundredths: show stored values as they are, and compare stock at 0.1 g so
  such a balance can still be cleared (see `recordFlowerTransaction()`).
- Plant counts are whole numbers, and one Receive plants entry adds at most
  1,000 plants (`GRACE_MAX_PLANTS_PER_ENTRY` in `settings_lib.php`). NZ
  cultivators are small: only the largest handles more than 1,000 plants,
  and they spread intakes over several days and people. The cap stops a
  typo like 10000 flooding the append-only ledger, so don't raise it
  without a real grower who needs it.
- Large-entry warnings are settings, not rules: Administration → Entry
  warning limits (default 100 plants and 5,000 g, recommended about half of
  what one flower room holds). Above them the confirm step needs an extra
  tick. They warn, they never block, and the plant limit can't exceed the
  per-entry cap.

## 4. Before you open a pull request

- `bash tests/run_ci.sh` must pass.
- New behaviour gets a regression test wired into `tests/run_ci.sh`, and
  `TESTING.md` stays in step with the suite.
- Releases bump the version in `config.yaml`, `nav.php`, the top
  `CHANGELOG.md` heading, and `GRACE_ASSET_VERSION` in `header.php`, and add
  a changelog entry written the way section 6 describes. See the release
  checklist in DEVELOPMENT.md.

## 5. Writing for users

- Changelogs, the user guide (`grace_addon/DOCS.md`) and on-screen text are
  read by growers, not developers. Keep it short and plain: say what changed
  for them and skip the technical detail.
- Don't use em-dashes anywhere. Rephrase so the sentence doesn't need one.

## 6. The changelog

`grace_addon/CHANGELOG.md` is read by growers in two places: Home
Assistant's add-on changelog, and GRACe's own "What's new" pop-up, which
shows the newest entry the first time anyone opens the updated add-on
(`whats_new_lib.php`). **The 1.1.0 entry is the standard.** Match its tone
and layout:

- Heading `## [x.y.z] - YYYY-MM-DD`, newest entry at the top.
- One plain sentence summing up the release. Leave it out when the entry
  is so short that the sentence would only repeat it, as in 1.1.1.
- Then these sections, in this order, leaving out any that would be empty:
  - `### What's new`: each feature is a bullet that starts with a bold
    sentence saying what the grower gets, followed by a line or two on how
    to use it and where to find it.
  - `### Important fixes`: bold lead-in, then what used to go wrong and what
    it means for them now. Mention anything that changes their existing
    records or reports.
  - `### Changes`: behaviour that's different now, one plain line each.
  - `### Minor bug fixes and reliability improvements`: small things,
    grouped into two or three short bullets. Only say "performance" if
    something really got faster.
- What's new shows about the first 10 lines before "Show more…", so put
  what matters most to growers first.
- Write for growers: what changed for them, not how. No file names,
  functions or tables. Use neutral examples ("Joe's Farm"), never strain
  names or real customer data.
- What's new only renders `### ` headings, `- ` bullets, `**bold**` and
  `` `code` ``. Everything else shows as plain text.

Two things that are easy to get wrong:

- The file lives at `grace_addon/CHANGELOG.md`, not the repository root.
- The add-on image only gets `files/general` and `files/php83` copied in,
  so the Dockerfile copies the changelog separately
  (`COPY CHANGELOG.md /www/CHANGELOG.md`). Without that line What's new
  silently shows nothing. `whats_new_lib.php` looks in the image first, then
  in the repository (for development and tests), and
  `tests/test_whats_new.php` checks both.

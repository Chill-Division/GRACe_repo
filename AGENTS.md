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
- Home Assistant backups are the backup plan: they include `/data`, so the
  database and every uploaded document. The "Download backup" JSON export is
  an ad-hoc extra copy for audits. It leaves out the uploaded files and
  GRACe can't restore from it, so never present it as the main backup.

## 4. Before you open a pull request

- `bash tests/run_ci.sh` must pass.
- New behaviour gets a regression test wired into `tests/run_ci.sh`, and
  `TESTING.md` stays in step with the suite.
- Releases bump the version in `config.yaml`, `nav.php`, the top
  `CHANGELOG.md` heading, and `GRACE_ASSET_VERSION` in `header.php`. See the
  release checklist in DEVELOPMENT.md.

## 5. Writing for users

- Changelogs, the user guide (`grace_addon/DOCS.md`) and on-screen text are
  read by growers, not developers. Keep it short and plain: say what changed
  for them and skip the technical detail.
- Don't use em-dashes anywhere. Rephrase so the sentence doesn't need one.

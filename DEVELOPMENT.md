# GRACe Portal: Local Development Guide

How to run the portal on your own machine, fill it with demo data, and check
your changes before committing, no Home Assistant install required.

## Prerequisites

- PHP 8.1+ CLI with the SQLite extension
  (`sudo apt install php-cli php-sqlite3` on Debian/Ubuntu, WSL works fine)
- The app **hard-codes** its persistent paths (see the warning in
  [README.md](README.md)), so you need a local `/data` directory:

```bash
sudo mkdir -p /data/uploads
sudo chown -R "$(whoami)" /data
```

> In production, `/data` is the Home Assistant addon's persistent volume.
> Never change the `/data/grace.db` or `/data/uploads/` paths in code.

## Spin up a dev server

PHP's built-in server is all you need, the addon's nginx/php-fpm stack is
only for production packaging:

```bash
cd grace_addon/files/general/www/public
php -S 127.0.0.1:8420
```

Open <http://127.0.0.1:8420/> in a browser. The database schema (and any
pending migrations) are created automatically on first page load.

On the first visit you'll be redirected to the company-info form; once company
details exist, the portal opens on the Dashboard.

Tips:

- Test mobile/tablet layouts with your browser dev tools' device toolbar
  (the UI is responsive down to ~375px wide).
- Both themes matter: use the sun/moon toggle in the nav. The choice persists
  in `localStorage` under `grace-theme`.
- Assets are cache-busted by addon version (`?v=…`). If a CSS/JS change
  doesn't show up, hard-refresh (Ctrl+Shift+R).

## Seed demo data

To explore the UI with realistic content (plants in every status, flower
ledger entries this month and last, companies, and downloadable demo
documents including a license that's about to expire, which exercises the
red banner and the dashboard warning):

```bash
# from the repo root
php tests/seed_demo_data.php           # seeds an empty database
php tests/seed_demo_data.php --force   # wipe /data/grace.db and reseed
```

The script refuses to touch a database that already contains company data
unless you pass `--force`, so it can't clobber real records by accident.

To start over completely:

```bash
rm /data/grace.db && rm -rf /data/uploads/*
```

## Demoing the Agency report reminders

The dashboard reminders (monthly materials-out in the first 7 days of a
month, annual stocktake in January) are date-windowed, so out of the box you
can only see them at the right time of month. Two demo aids:

```bash
# What would the dashboard show on any given day (or range)? Runs against
# your dev database, perfect for screen recordings:
php tests/demo_report_reminders.php 2027-01-01 2027-01-10

# Or render the real dashboard as if it were another day:
# http://127.0.0.1:8420/dashboard.php?demo_date=2027-01-03
```

`tests/seed_demo_data.php` always seeds an outbound shipment in the middle
of last month, so the monthly banner has data to point at whatever month you
demo in. The reminder logic itself is covered by
`tests/test_report_reminders.php`, whose output reads as a step-by-step
story of the rules (window open/closed, quiet months, dismissals, the
January double-up, fresh installs staying silent).

## Run the test suite

Always run this before committing:

```bash
bash tests/run_ci.sh
```

It covers DB migrations, upload-path permissions, static checks (persistent
paths, duplicate script tags), version consistency, and PHP syntax across
every file.

## Release checklist

The version number lives in **three places** and the CI suite fails if they
disagree:

1. `grace_addon/config.yaml` → `version: "x.y.z"`
2. `grace_addon/files/general/www/public/nav.php` → `<small>vx.y.z</small>`
3. `grace_addon/CHANGELOG.md` → topmost `## [x.y.z]` heading

Also bump `GRACE_ASSET_VERSION` in
`grace_addon/files/general/www/public/header.php` so browsers pick up new
CSS/JS.

## Notes for AI assistants & future developers

- **No service worker / offline-first support is wanted.** GRACe runs as a
  Home Assistant addon; users always reach it over their LAN through their
  HA server, so "device is offline" is not a scenario we design for. The
  reason Pico CSS and jQuery are vendored locally (`css/vendor/`,
  `js/vendor/`) is that the *HA server itself* may be air-gapped from the
  internet, that's an offline-server concern, not an offline-client one.
  Don't add a service worker, app-state caching, or background sync.
- The portal is normally served through **Home Assistant ingress**, which
  mounts it under a deep path. Always use **relative URLs** for links,
  assets, and fetch calls, never absolute paths starting with `/`.
- Authentication is Home Assistant's job. There is intentionally no login
  system in the app (the old `auth.php`/`login.php` were removed in 0.15.1).
- The ledger is intentionally **append-only**: no UI for editing or deleting
  historical plant/flower records should be added. Corrections happen via
  compensating entries.
- Persistent-path rules (`/data/grace.db`, `/data/uploads/`) are absolute.
  See the "CRITICAL DEVELOPER NOTES" section in [README.md](README.md).

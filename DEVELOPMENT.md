# GRACe Portal: Local Development Guide

How to run the portal on your own machine, fill it with demo data, and check
your changes before committing, no Home Assistant install required.

## Prerequisites

- PHP 8.1+ CLI with the SQLite extension
  (`sudo apt install php-cli php-sqlite3` on Debian/Ubuntu, WSL works fine)
- The app **hard-codes** its persistent paths (see rule 1 in
  [AGENTS.md](AGENTS.md)), so you need a local `/data` directory:

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
- On a phone, a table with more columns than fit scrolls sideways. Give it
  `class="wide-table"` so each value stays on one line instead of being
  squeezed (a date broken over three lines), and give a cell with long text,
  like an address, `class="cell-long"` so it wraps at a readable width.
  Leave tables that fit a phone alone. `tests/test_phone_tables.php` lists
  the tables that have it.
- Both themes matter: use the sun/moon toggle in the nav. The choice persists
  in `localStorage` under `grace-theme`. Each theme's colours live in the
  `:root[data-theme=...]` blocks at the top of `css/growcart.css`. Keep the
  `:root`, or Pico's own light theme wins. `tests/test_theme_overrides.php`
  checks that, and the contrast of the main colour pairs.
- Text is Inter, the Chill Division font, served from `fonts/inter/` (the
  same files as the Visitors add-on). It's free under the SIL Open Font
  License, and `OFL.txt` has to stay with the files. Don't load fonts, or
  anything else, from Google Fonts or a CDN: a Home Assistant box may have
  no internet. `tests/test_fonts.php` checks both.
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

To try an upgrade the way an existing install experiences it, add
`--legacy`. That seeds a database shaped like GRACe 1.0.x, with the old
Breeder and Genetic Lineage columns filled in on some genetics, and plant
and flower times stored in UTC the way 1.0.x stored them:

```bash
php tests/seed_demo_data.php --force --legacy
```

Load any page and GRACe upgrades it: the two columns are removed from
`Genetics`, and the filled-in values are kept in `LegacyGeneticsDetails`
(which also shows up in Administration, "Download backup"). The times are
converted to NZ time, and the `DataMigrations` table records that it's done.

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

## Trying report reminders

Administration → "Report reminders" sends notifications through Home
Assistant, which a dev machine doesn't have, so `tests/ha_standin.php`
stands in for it with two pretend phones:

```bash
# from the repo root, in one terminal
php -S 127.0.0.1:8899 tests/ha_standin.php

# and the dev server in another, pointed at it
cd grace_addon/files/general/www/public
GRACE_SUPERVISOR_URL=http://127.0.0.1:8899 SUPERVISOR_TOKEN=dev-token php -S 127.0.0.1:8420
```

Everything GRACe sends lands in `grace-ha-standin.log` in the system temp
folder (`/tmp` on Linux). In the add-on, `report_reminder_notify.php` runs
from the command line every few minutes to send the monthly reminder. Run
it by hand with the same two variables set to try it, but it only sends
from 9am on the first weekday of a month; `tests/test_report_notify.php`
covers the timing.

## Run the test suite

Always run this before committing:

```bash
bash tests/run_ci.sh
```

It covers DB migrations and in-place upgrades, the reporting and reminder
logic, company editing, license alerts and upload limits, download names,
static checks (persistent paths, duplicate script tags), version
consistency, and PHP syntax across every file. [TESTING.md](TESTING.md)
lists every stage.

GitHub runs the same suite on every pull request and every push to `main`,
inside the add-on's own image, so each PR shows whether it passes with the
exact PHP the add-on has.

## Release checklist

The version number lives in **three places** and the CI suite fails if they
disagree:

1. `grace_addon/config.yaml` → `version: "x.y.z"`
2. `grace_addon/files/general/www/public/nav.php` → `<small>vx.y.z</small>`
3. `grace_addon/CHANGELOG.md` → topmost `## [x.y.z]` heading

Also bump `GRACE_ASSET_VERSION` in
`grace_addon/files/general/www/public/header.php` so browsers pick up new
CSS/JS.

Write the changelog entry first, then let the helper do the rest:

```bash
php tests/bump_version.php 1.2.0
```

It refuses to change anything until `grace_addon/CHANGELOG.md` starts with
`## [1.2.0] - YYYY-MM-DD`, then sets the version in `config.yaml`, `nav.php`
and `GRACE_ASSET_VERSION`, and checks all four agree.

The new `grace_addon/CHANGELOG.md` section is what growers see in the
"What's new" pop-up the first time anyone opens the updated app, so write
it for them, modelled on the 1.2.0 entry (AGENTS.md section 6 has the
standard). To see the pop-up locally, seed with `--legacy` (it behaves like
an upgraded install) and open any page. A normal seed behaves like a new
install, which never shows it. To see notes for skipped versions stack up,
set `whatsNewSeenVersion` in the `Settings` table to an older version first.

If the release changes how pages look, refresh the screenshots in
`docs/screenshots/` too: the user guide and README use them, and every one
shows the version number in the menu.

## Refresh the screenshots

`tests/capture_screenshots.ps1` retakes every screenshot the user guide and
README use, on Windows with headless Microsoft Edge, against the dev server
in WSL. Do it after the version bump and the new changelog entry, so the
menu and the "What's new" shots show the new version.

```bash
# In WSL, from the repo root: a new-install demo, then the dev server
php tests/seed_demo_data.php --force
cd grace_addon/files/general/www/public && php -S 127.0.0.1:8420
```

Then, in PowerShell from the repo root:

```powershell
.\tests\capture_screenshots.ps1
```

Pass `-Only desktop-dashboard,mobile-dashboard` to retake just some. If
PowerShell won't run scripts from the WSL folder, run it as
`powershell -ExecutionPolicy Bypass -File .\tests\capture_screenshots.ps1`.
The script fills in the forms so the guide shows realistic entries, and it
takes the two "What's new" shots last, marking the notes unseen for those
only. Look through the new PNGs before committing them. To add a shot, add a
line to `$shots` in the script and show the PNG in `grace_addon/DOCS.md`.

The script marks the notes in `/data/grace.db`, the usual dev database. If
yours is somewhere else, such as a scratch copy that keeps your real `/data`
out of it, pass its path in WSL with `-Database /path/to/grace.db`.

## The manifest PDF's fonts

The shipping manifest PDF is set in Inter, from font files TCPDF makes:
`TCPDF/fonts/inter.*` (regular) and `interb.*` (semibold), with Inter's licence
beside them as `inter-OFL.txt`. They're cut down to plain Latin, macrons and
common punctuation, about 25 KB each. To make them again, say for a newer
Inter, start from Inter's static TTF files (from rsms.me/inter, not the WOFF2
files the pages use) and Python's fontTools:

```bash
U="U+0020-007E,U+00A0-00FF,U+0100-017F,U+0180-024F,U+2010-2015,U+2018-201E,U+2022,U+2026,U+2032-2033,U+20AC,U+2122,U+2190-2193"
pyftsubset Inter-Regular.ttf --unicodes="$U" --layout-features='' --no-hinting --desubroutinize --output-file=inter.ttf
pyftsubset Inter-SemiBold.ttf --unicodes="$U" --layout-features='' --no-hinting --desubroutinize --output-file=interb.ttf
php tests/build_pdf_fonts.php inter.ttf interb.ttf
```

Keep those file names: TCPDF names a font after its file, and
`SetFont('inter')` then finds `inter.php` for plain text and `interb.php` for
bold. `tests/test_manifest_pdf.php` checks a manifest prints macrons.

The logo on the manifest, `icons/chill-division-logo-print.png`, is a grey PNG
with no alpha channel. TCPDF reads those itself, while one with alpha needs GD
or Imagick, which the add-on image doesn't have.

## Rules

The rules every change must follow live in [AGENTS.md](AGENTS.md), where AI
assistants pick them up automatically: the persistent `/data` paths,
in-place database upgrades, the append-only ledger, never deleting verified
companies, relative URLs under Home Assistant ingress, no service worker,
Home Assistant backups as the backup plan, and plain writing for users.
Read them before your first change.

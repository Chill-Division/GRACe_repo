## [1.3.0] - 2026-10-11

### What's new
- **Retire genetics you no longer use.** Added one with a typo, or stopped growing it? Administration → Retire genetics takes it off the lists for adding plants, recording flower and making a manifest. You can retire it once none of it is left on site, and your reports and history don't change. Changed your mind? Press "Bring back", or add its name again.

## [1.2.0] - 2026-10-11
Report reminders on your phone, plants marked as sent straight from a manifest, and a fix to the annual stocktake.

### What's new
- **Report reminders on your phone.** Administration → Report reminders: switch them on, choose your phone (it needs the Home Assistant app) and send a test. From 9am on the first weekday of each month, if a report is due, GRACe sends you one notification.
- **Mark plants as sent straight from a manifest.** On a manifest for plants going to another company, leave "Then mark these plants as sent" on. Harvest / Destroy / Send opens with those plants ticked and the company chosen, so you check them, confirm, and you're back at the manifest.

### Important fixes
- **Harvested plants count as harvested on the annual stocktake.** Plants marked Harvested - Destroyed used to be counted under Destroyed, which left the Harvested column at 0. They count as Harvested now. The totals and closing stock don't change, but if you open an older year's stocktake again, those plants move columns.

### Changes
- "Plants growing" and "Plants drying" on the Dashboard open a list of just those plants.
- Quick select on Harvest / Destroy / Send can now start with the drying plants.
- GRACe can now send notifications through Home Assistant. It only does if you switch report reminders on.

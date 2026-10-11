## [1.3.0] - 2026-10-11

### What's new
- **Retire genetics you no longer use.** Added one with a typo, or stopped growing it? Administration → Retire genetics takes it off the lists for adding plants, recording flower and making a manifest. You can retire it once none of it is left on site, and your reports and history don't change. Changed your mind? Press "Bring back", or add its name again.

### What's new in 1.2
- **Report reminders on your phone.** Administration → Report reminders: switch them on, choose your phone (it needs the Home Assistant app) and send a test. From 9am on the first weekday of each month, if a report is due, GRACe sends you one notification.
- **Mark plants as sent straight from a manifest.** On a manifest for plants going to another company, leave "Then mark these plants as sent" on. Harvest / Destroy / Send opens with those plants ticked and the company chosen, so you check them, confirm, and you're back at the manifest.

### Important fixes in 1.2
- **Harvested plants count as harvested on the annual stocktake.** Plants marked Harvested - Destroyed used to be counted under Destroyed, which left the Harvested column at 0. They count as Harvested now. The totals and closing stock don't change, but if you open an older year's stocktake again, those plants move columns.

### Changes in 1.2
- "Plants growing" and "Plants drying" on the Dashboard open a list of just those plants.
- Quick select on Harvest / Destroy / Send can now start with the drying plants.
- GRACe can now send notifications through Home Assistant. It only does if you switch report reminders on.

## [1.2.0] - 2026-10-11
Report reminders on your phone, plants marked as sent straight from a manifest, and a fix to the annual stocktake. 1.1 is still recent, so its changes are listed underneath too.

### What's new
- **Report reminders on your phone.** Administration → Report reminders: switch them on, choose your phone (it needs the Home Assistant app) and send a test. From 9am on the first weekday of each month, if a report is due, GRACe sends you one notification.
- **Mark plants as sent straight from a manifest.** On a manifest for plants going to another company, leave "Then mark these plants as sent" on. Harvest / Destroy / Send opens with those plants ticked and the company chosen, so you check them, confirm, and you're back at the manifest.

### Important fixes
- **Harvested plants count as harvested on the annual stocktake.** Plants marked Harvested - Destroyed used to be counted under Destroyed, which left the Harvested column at 0. They count as Harvested now. The totals and closing stock don't change, but if you open an older year's stocktake again, those plants move columns.

### Changes
- "Plants growing" and "Plants drying" on the Dashboard open a list of just those plants.
- Quick select on Harvest / Destroy / Send can now start with the drying plants.
- GRACe can now send notifications through Home Assistant. It only does if you switch report reminders on.

### What's new in 1.1
- **Add a genetics or a company without leaving the page.** Missing from the list? Choose "+ Add new genetics…" or "+ Add new company…" at the bottom of it. It's added and selected straight away, and nothing you've already typed is lost. Works on Receive plants, Record dry weight change, Harvest / Destroy / Send and Generate Shipping Manifest.
- **See your stock while you enter.** Pick a genetics and GRACe shows how many are growing, or how much dried flower you have and what's left after this entry.
- **Check before you save.** Receive plants and Record dry weight change now show a summary to confirm first, like Harvest / Destroy / Send already did. The ledger can't be edited afterwards, so this is your chance to catch a typo.
- **Catch the extra zero.** Anything bigger than usual needs an extra tick before it's saved. You set what "big" means in Administration → Entry warning limits. It starts at 100 plants and 5,000 g, and we suggest about half of what one of your flower rooms holds.
- **Recent entries.** Your last 10 entries are listed under each form, so you can check what you just did and spot anything entered twice.
- **Clear confirmations.** After saving, GRACe tells you exactly what was recorded, and how many plants are now growing or how much flower is left.
- **Early license renewals.** A license can now be uploaded with an expiry date up to 15 months away, so a renewal issued up to 3 months early goes straight in.

### Important fixes in 1.1
- **Last month's report shows the right month.** On the 29th, 30th and 31st it used to show this month's figures under last month's heading, and "Draft this in an email" would have sent them.
- **Times are now NZ time.** GRACe used to save times in UTC, 12 or 13 hours behind NZ, so anything recorded before about 1pm on the 1st of a month counted towards the month before. Updating corrects your older entries too. A few may move into the month they really happened in, so an older monthly report you open again can differ slightly from the one you sent.
- **Current Plants now includes drying plants.** They were left out before, even though they're still on your site and your annual stocktake counts them. Each genetics now shows growing plus drying.
- **Flower can't go below zero.** Record dry weight change won't subtract more flower than you have on record. Shipping manifests already worked this way.
- **Plants can't be processed twice.** If some plants were already processed, for example in another tab or on another device, Harvest / Destroy / Send changes nothing and shows you the current list, so old entries can't be overwritten.
- **Company names with apostrophes or quotes**, like Joe's Farm, are saved and shown properly, including in Agency emails. Names saved by older versions are repaired automatically.

### Changes in 1.1
- Plant counts are whole numbers, and one entry can add up to 1,000 plants. Split anything bigger into a few entries.
- Weights are grams to one decimal place, like 612.5 g.
- Adding a genetics spots near-duplicates, so the same name typed with different capitals or extra spaces isn't added twice.
- On shipping manifests, an external company is no longer picked for you. Choose it from the list.
- The unused Breeder and Genetic Lineage fields are gone. Anything you had filled in is kept in your records.
- The user guide now explains setting up Home Assistant backups, which are your real backup. "Download backup" is only for an extra copy, for example for an auditor.
- The user guide also explains how to record a sample cut from a plant, and that drying plants stay in stock until you send or destroy them.

### Minor bug fixes and reliability improvements in 1.1
- A double tap can't save an entry twice, and plants are received all at once or not at all.
- Messages about problems stay on screen until you've read them, and forms keep what you typed.
- Clearer wording, like "Marked 5 plants as sent to …" on Harvest / Destroy / Send, plus other small fixes.

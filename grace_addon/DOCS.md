# GRACe Portal: first-time user guide

GRACe is a ledger for your plants and your dried flower. When something comes in (clones taken, plants received, flower harvested) you **add** it. When something goes out (harvested, destroyed, sent away, tested) you **subtract** it. That's the whole idea. Everything you report to the Medicinal Cannabis Agency is worked out from that ledger, so a few seconds of data entry when things happen replaces the spreadsheet at the end of the month.

One thing to know up front: history can't be edited. Once a plant is recorded as destroyed, or 200 g is recorded as sent, that line stays. This is deliberate, because your ledger is your compliance record. If you make a mistake, you fix it with another entry (there's a section on that at the end), so take a second to check before you confirm.

GRACe works the same on a PC, a tablet, or a phone through the Home Assistant app. This guide shows both.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-dashboard.png" alt="The GRACe dashboard on a PC" width="900">

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/mobile-dashboard.png" alt="The GRACe dashboard on a phone" width="300"> <img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/mobile-menu-open.png" alt="The phone menu" width="300">

# Installation

- Install the add-on
- Select "Show in sidebar" for easier access
- Ensure "Start on boot" is ticked, as well as "Auto update"
- Tap "Start" to start the add-on
- Tap "Open WebUI" to go to the GRACe Portal

# First-run setup (about five minutes)

**1. Your company details.** The first time GRACe opens it asks for your company name, license number, address and contact email. These are printed on every Chain of Custody document and go in the subject line of your Agency emails, so get them right. You can change them later under Administration → "Update company information".

**2. Your genetics.** Go to Administration → "Add New Genetics" and add every strain you have as plants or flower, or expect to have. You can't record a plant until its genetics exist. Don't worry about typing a name twice: GRACe spots names that differ only by capitals or spaces, so "white widow" won't become a second White Widow.

**3. Your verified companies.** Administration → "Add Verified Company" is where you add the testing labs and buyers you send product to. They then show up in a drop-down whenever you send something. Their license numbers change every year, so when that happens use "Edit Verified Companies" to update them (companies can be edited but never deleted, because your records refer to them). Forgot one? Choose **"+ Add new company…"** at the bottom of any company list, on Harvest / Destroy / Send, Record dry weight change or Generate Shipping Manifest, and add it there without losing what you've filled in.

**4. Your entry warning limits.** Administration → "Entry warning limits". Anything bigger than these, such as receiving 1,000 plants when you meant 100, gets an extra "are you sure?" that you have to tick before it's saved. Set each to about half of what one of your flower rooms usually holds. They start at 100 plants and 5,000 g. Plant counts are always whole numbers, and one entry can add at most 1,000 plants, so split anything bigger into a few entries.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-administration.png" alt="The Administration page" width="900">

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-verified-companies.png" alt="Verified companies list" width="900">

# The Dashboard

The Dashboard is your home page. At a glance it shows plants growing, plants drying, dried flower on hand, what has gone out this month, any manifests still waiting for their Chain of Custody, and any licenses coming up for renewal.

It also tells you when a report is due:

- In the **first week of each month**, if you sent anything out last month, a banner reminds you to send the monthly materials-out report. Quiet months get no banner.
- In **January**, a banner reminds you about the annual stocktake.

"Open report" takes you straight to the right page. "Dismiss" hides that reminder until the next one is due. Reminders never pile up.

# Adding product

## Plants coming in

Plant Tracking → **"Receive plants or take clones"**. Use this for clones you take from a mother plant, plants received from another license holder, and plants declared under Form D. Enter how many and which genetics, then "Add plants", check the summary GRACe shows you and confirm. Each one becomes a plant in your ledger with today's date, and you'll see it counted under "Plants growing" on the Dashboard.

New genetics that isn't in the list yet? Choose **"+ Add new genetics…"** at the bottom of the list, type the name and it's added and selected, without leaving the page. The same works on "Record dry weight change".

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-receive-plants.png" alt="Receive plants form" width="900">

## Flower coming in

Once harvested plants have dried, weigh the flower and record it: Plant Tracking → **"Record dry weight change"**, choose the genetics, enter the weight in grams (to one decimal place, like 612.5), choose **Add**, and pick the reason **Harvest**. That weight is now in your dried-flower inventory.

While you fill in the form, GRACe shows how much of that genetics is on hand and what it will be after this entry. Before anything is saved it shows a summary for you to confirm, and afterwards it tells you exactly what was recorded. Receive plants does the same with how many are growing.

If flower arrives from someone else (a sample from another grower, say), use Add with the reason **Other**.

# Subtracting product

## Whole plants going out

Plant Tracking → **"Harvest / Destroy / Send plants"** lists every live plant. Tick the plants, choose what happened to them, and press "Process Selected":

- **Harvested - Drying**: the plants have been cut and are drying. They still count as stock until you record the dry weight.
- **Harvested - Destroyed** (or destroyed for any other reason): the plants are gone.
- **Send External**: the plants went to another license holder. You'll be asked which company.

Ticking 88 boxes out of 90 is tedious, so use **Quick select**: choose the genetics, type how many, and choose whether to start from the oldest or the youngest plants. GRACe ticks them for you. You can still untick a mother plant you want to keep, or tick extra ones, before you process.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-harvest-quick-select.png" alt="Quick select on the harvest page" width="900">

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/mobile-harvest.png" alt="Quick select on a phone" width="300">

Before anything is written, GRACe shows you exactly what is about to happen and asks you to confirm. This is your chance to catch a mistake.

If some of the ticked plants have already been processed, for example on another device or in another tab, GRACe changes nothing and reloads the list, so old entries can never be overwritten by accident.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-harvest-confirm.png" alt="The confirmation step before plants are processed" width="900">

## Flower going out

Plant Tracking → **"Record dry weight change"** again, but this time choose **Subtract** and a reason:

- **Testing**: sent to a lab. You'll be asked which lab.
- **Send external**: sold or sent to another license holder. You'll be asked which company.
- **Destroy**: destroyed on site.
- **Other**: anything else, with a note.

Testing and Send external are what appear in your monthly materials-out report.

You can't subtract more flower than GRACe has on record for that genetics. If you really do have more than it thinks (say a harvest was never entered), record the missing amount with **Add** first.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-record-dry-weight.png" alt="Recording flower sent out" width="900">

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/mobile-record-dry-weight.png" alt="Recording flower sent out on a phone" width="300">

## Shipping with a manifest

When product physically leaves your site, Plant Tracking → **"Generate Shipping Manifest"** produces the manifest PDF to print and send with it. Pick the sending and receiving parties, the product type, the genetics and the quantity.

If you're sending flower to an external company, the shipped weight is **deducted from your dried-flower inventory automatically** when the manifest is generated, so you don't need a separate "Record dry weight change" entry. It shows in your monthly report like any other send.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-generate-manifest.png" alt="Generating a shipping manifest" width="900">

The manifest stays "In Progress" while the product is in transit. When you have the signed Chain of Custody back (a photo of the signed paperwork is fine), go to **"Complete Manifest"**, pick the shipment, attach the Chain of Custody, and complete it. GRACe won't let a manifest be closed without one. Every shipment has its own summary page showing where it went, what was deducted, and the paperwork.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-complete-manifest.png" alt="Completing a manifest" width="900">

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-manifest-summary.png" alt="A shipment summary" width="900">

# Checking your numbers

Plant Tracking → **"List all plants"** shows every plant you've ever recorded with its age and what happened to it, colour-coded so you can see at a glance what's growing, drying, destroyed or sent. Filter by status to narrow it down.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-list-all-plants.png" alt="List of all plants with status badges" width="900">

Reporting → **"Current Plants"** and **"Current Dried Flower"** give you today's totals by genetics, which is what to check against what's actually in the room.

# Reporting to the Agency

## Monthly materials out

Reporting → **"Last month's materials out"** is the monthly report, laid out the way the Agency wants it: flower sent out (including testing), plants sent out, and the total weight. Press **"Draft this in an email"** and GRACe opens a new email to the Agency with the subject line and the whole report already filled in. Check it and send.

Use this in the first week of the new month. "This month's materials out" is only a preview of the month so far; never email a partial month.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-last-months-report.png" alt="Last month's materials out report" width="900">

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/mobile-last-months-report.png" alt="The monthly report on a phone" width="300">

## Annual stocktake

Every January the Agency wants a stocktake as at midnight on 31 December. Reporting → **"Annual Stocktake"**, pick the year, and GRACe works out the opening stock, everything in and out, and the closing stock for plants and for flower. Tick "Hide rows with all zero values" to keep it tidy, then "Draft this in an email" as above. If you arrive here from the January reminder on the Dashboard, the report is generated for you already.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-annual-stocktake.png" alt="Annual stocktake report" width="900">

# Keeping your records

Administration → **"Record Management"** holds your paperwork: Chain of Custody documents, company licenses, SOPs, police vet checks and offtake agreements. Upload a photo or PDF and it's stored with your Home Assistant backups.

Give each **license** its expiry date when you upload it. Licenses are yearly, but a renewal can be issued up to 3 months early, so the date can be up to 15 months from today and an early renewal goes straight in. GRACe warns you on the Dashboard a month before a license expires and shows a red banner on every page in the last three days. Once you've renewed it, press "Acknowledge Alert" and the warning goes away.

<img src="https://raw.githubusercontent.com/Chill-Division/GRACe_repo/main/docs/screenshots/desktop-company-licenses.png" alt="Company licenses with expiry tracking" width="900">

# Backups

Your ledger and every document you've uploaded live inside the GRACe add-on, so **Home Assistant's own backups are your backup plan**. They save everything, and they're what you'd restore from if your Home Assistant box ever died. Set them up once:

1. In Home Assistant, go to **Settings → System → Backups**.
2. Turn on **automatic backups** (daily is a good choice) and make sure the **GRACe Portal** add-on is included.
3. Add at least one **off-site location**, such as Home Assistant Cloud, Google Drive, or a network share. A backup that only lives on the same box won't help if that box fails.
4. Every so often, check that the latest backup finished.

Administration → **"Download backup"** is only for an extra, ad-hoc copy, for example to hand to an auditor. It contains your ledger but not the uploaded documents themselves (licenses, Chain of Custody photos and so on), and GRACe can't restore from it. Never use it as your main backup.

# Good to know

- **Recent entries.** Receive plants, Record dry weight change and Harvest / Destroy / Send each list your last 10 entries under the form, newest first. Check there if you think something went in twice.
- **Fixing a mistake.** History can't be edited, so correct it with another entry. Recorded 10 clones but only took 8? Process the 2 extras as destroyed. Typed 250 g instead of 25 g? Subtract 225 g with the reason Other and a note saying why. The correction is part of your record too.
- **Light or dark.** The sun/moon button in the top right switches themes, and GRACe remembers your choice.
- **Phone tip.** Everything in this guide works from the Home Assistant app on your phone, so record harvests and sends while you're standing in the room.
- **Times are NZ time.** Before version 1.1, GRACe saved plant and flower times in UTC, which is 12 or 13 hours behind NZ, so anything recorded before about 1pm on the 1st of a month counted towards the month before. Updating to 1.1 corrects the old entries as well. A few of them move into the month they really happened in, so an older monthly report you open again can differ slightly from the one you sent at the time.

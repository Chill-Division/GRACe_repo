# GRACe_repo
Repository for the GRACe Portal app for Home Assistant

Take back your time, focus on your garden, while GRACe looks after your regulatory compliance

GRACe is a simple ledger for your plants and dried flower. If you take a clone, you add to it. If you harvest a plant, you subtract it. This is the whole basis of GRACe, coupled with the intentional inability to historically edit records.

* Annual reporting / stock-take
* Monthly summaries for the Medicinal Cannabis Agency
* Minimal effort for plant tracking
* Easy Chain of Custody creation
* Report reminders on the Dashboard, and one-tap email drafts to the Agency

## First-time user guide

New to GRACe? Read the [first-time user guide](grace_addon/DOCS.md) (it's also shown on the app's Documentation tab inside Home Assistant). It walks through setup, adding and subtracting product, shipping, and reporting, with screenshots for PC and phone.

<img src="docs/screenshots/desktop-dashboard.png" alt="The GRACe dashboard on a PC" width="720"> <img src="docs/screenshots/mobile-dashboard.png" alt="The GRACe dashboard on a phone" width="220">

## Installation and getting started video

Check out the video on YouTube for a walkthrough of installation / getting started:
[![Walkthrough Video](https://github.com/user-attachments/assets/50b24411-3776-49c4-8978-2b2e9cbd7cf0)](https://youtu.be/2Re8jBMEAvw)

[https://youtu.be/KMqnaY6NRiY](https://youtu.be/2Re8jBMEAvw)

## Installation instructions

Home Assistant has called add-ons "apps" since 2026.2, so on an older Home Assistant read "Add-ons" wherever these steps say "Apps".

The quickest way is [this link](https://my.home-assistant.io/redirect/supervisor_add_addon_repository/?repository_url=https%3A%2F%2Fgithub.com%2FChill-Division%2FGRACe_repo), which opens your Home Assistant with the GRACe repository filled in, ready to add. Or add it by hand:

Go to Home Assistant Settings -> Apps and open the app store

Tap on the 3-dot menu in the upper-right and choose "Repositories"

In the "Add" box, enter the URL of this github repo: https://github.com/Chill-Division/GRACe_repo

Click on "Add" then click on "Close".

You may need to tap the 3-dot menu in the upper-right and choose "Check for updates". You should now see the new repository with the "GRACe" app showing underneath it.

![image](https://github.com/user-attachments/assets/ba8b20de-f414-4e8d-834c-eba6a62f817d)

Tap the new app, select "Install", then tap "Show in sidebar", "Auto update" and tap "Start".

![image](https://github.com/user-attachments/assets/72cde961-1459-4805-ae29-f1a4f6ef1b47)

## Your data and backups

Everything GRACe records (your ledger and every document you upload) is stored inside the app, so it survives updates and is included in your Home Assistant backups. Make sure automatic Home Assistant backups are turned on and copied somewhere off the box. The [user guide](grace_addon/DOCS.md#backups) shows how.

## For developers

Working on GRACe itself? [DEVELOPMENT.md](DEVELOPMENT.md) covers running it locally with demo data, and [TESTING.md](TESTING.md) covers the test suite. The rules every change must follow, for people and AI assistants alike, are in [AGENTS.md](AGENTS.md).

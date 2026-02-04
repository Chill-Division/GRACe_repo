# GRACe_repo
Repository for the GRACe Portal addon for Home Assistant

Take back your time, focus on your garden, while GRACe looks after your regulatory compliance

* Annual reporting / stock-take
* Monthly summaries for the Medicinal Cannabis Agency
* Minimal effort for plant tracking
* Easy Chain of Custody creation
* Automatic emails (coming soon)

## Storage and Upgrades

Files are stored in `/data/uploads`, which is a permanent storage volume in Home Assistant. This ensures uploaded documents persist across addon updates.

**Note on Upgrades (v0.12+):**
- **Database:** Schema changes (new `expiry_date` and `acknowledged` columns) are handled automatically by the application upon start.
- **File Storage:** Uploads now go to `/data/uploads`. Existing files in the container's ephemeral storage are *not* automatically migrated. They must be moved manually to `/data/uploads` if needed.
- **Permissions:** The application automatically attempts to create necessary directories in `/data` and set permissions.

## Release Process

For future edits, note that the version number is maintained in three places:
1.  **`config.yaml`**: The source of truth for the Home Assistant Addon version.
2.  **`grace_addon/files/general/www/public/nav.php`**: Displayed to the user in the top navigation bar.
3.  **`CHANGELOG.md`**: Documenting changes for each version.

## Installation and getting started video

Check out the video on YouTube for a walkthrough of installation / getting started:
[![image](https://github.com/user-attachments/assets/c7a64972-cfdb-4253-bb63-a615c5614f20)](https://youtu.be/KMqnaY6NRiY)

[https://youtu.be/KMqnaY6NRiY](https://youtu.be/KMqnaY6NRiY)

## Installation instructions

Go to Home Assistant Settings -> Add-ons -> Add-on Store

Tap on the 3-dot menu in the upper-right and choose "Repositories"

In the "Add" box, enter the URL of this github repo: https://github.com/Chill-Division/GRACe_repo

Click on "Add" then click on "Close".

You may need to tap the 3-button menu in the upper-right and choose "Check for updates". You should now see the new repository with the "GRACe" add-on showing underneath it.

![image](https://github.com/user-attachments/assets/ba8b20de-f414-4e8d-834c-eba6a62f817d)

Tap the new add-on, select "Install", then tap "Show in sidebar", "Auto update" and tap "Start".

![image](https://github.com/user-attachments/assets/72cde961-1459-4805-ae29-f1a4f6ef1b47)


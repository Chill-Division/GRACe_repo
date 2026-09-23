/**
 * whats_new.js
 *
 * Called from:
 * - footer.php, only on a page load where there are update notes nobody on
 *   this system has seen yet.
 *
 * Why:
 * Opens the "What's new" pop-up after an update. Long notes start folded
 * with "Show more…", which opens them into a scrollable area, so it fits a
 * phone screen and nobody is swamped. Closing it in any way (Got it, Escape,
 * tapping outside) marks the notes seen for everyone via
 * handle_whats_new.php, so it appears once per update, not once per device.
 */

document.addEventListener('DOMContentLoaded', () => {
    const dialog = document.getElementById('whatsNewDialog');
    if (!dialog || typeof dialog.showModal !== 'function') return;

    const notes = dialog.querySelector('.whats-new-notes');
    const more = dialog.querySelector('[data-whats-new-more]');
    const gotIt = dialog.querySelector('[data-whats-new-close]');

    dialog.addEventListener('close', () => {
        const body = new URLSearchParams({ version: dialog.dataset.version });
        fetch('handle_whats_new.php', { method: 'POST', body: body })
            .catch(() => { /* not recorded: it simply shows again next time */ });
        dialog.remove();
    });
    gotIt.addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) dialog.close(); // tapped outside
    });

    more.addEventListener('click', () => {
        notes.classList.remove('whats-new-notes--folded');
        notes.classList.add('whats-new-notes--open');
        more.hidden = true;
        notes.focus();
    });

    dialog.showModal();

    // Fold only notes that don't fit (about 10 lines)
    if (notes.scrollHeight > notes.clientHeight + 8) {
        notes.classList.add('whats-new-notes--folded');
        notes.tabIndex = -1;
        more.hidden = false;
    }
    gotIt.focus();
});

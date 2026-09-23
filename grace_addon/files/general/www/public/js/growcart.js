/**
 * growcart.js
 *
 * Shared UI behaviour for every page:
 * - Light/dark theme toggle, persisted in localStorage (key: 'grace-theme').
 *   The current theme is applied before first paint by an inline script in
 *   header.php; this file only handles the toggle button.
 * - statusBadge(): renders a plant status as a coloured badge element,
 *   used by genetics.js and the harvest page.
 */

(function () {
    const html = document.documentElement;
    const sun = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><path fill="currentColor" d="M8 11a3 3 0 1 1 0-6a3 3 0 0 1 0 6zm0 1a4 4 0 1 0 0-8a4 4 0 0 0 0 8zM8 0a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 0zm0 13a.5.5 0 0 1 .5.5v2a.5.5 0 0 1-1 0v-2A.5.5 0 0 1 8 13zm8-5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2a.5.5 0 0 1 .5.5zM3 8a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1 0-1h2A.5.5 0 0 1 3 8zm10.657-5.657a.5.5 0 0 1 0 .707l-1.414 1.415a.5.5 0 1 1-.707-.708l1.414-1.414a.5.5 0 0 1 .707 0zm-9.193 9.193a.5.5 0 0 1 0 .707L3.05 13.657a.5.5 0 0 1-.707-.707l1.414-1.414a.5.5 0 0 1 .707 0zm9.193 2.121a.5.5 0 0 1-.707 0l-1.414-1.414a.5.5 0 0 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .707zM4.464 4.465a.5.5 0 0 1-.707 0L2.343 3.05a.5.5 0 1 1 .707-.707l1.414 1.414a.5.5 0 0 1 0 .708z"/></svg>';
    const moon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16"><g fill="currentColor"><path d="M6 .278a.768.768 0 0 1 .08.858a7.208 7.208 0 0 0-.878 3.46c0 4.021 3.278 7.277 7.318 7.277c.527 0 1.04-.055 1.533-.16a.787.787 0 0 1 .81.316a.733.733 0 0 1-.031.893A8.349 8.349 0 0 1 8.344 16C3.734 16 0 12.286 0 7.71C0 4.266 2.114 1.312 5.124.06A.752.752 0 0 1 6 .278zM4.858 1.311A7.269 7.269 0 0 0 1.025 7.71c0 4.02 3.279 7.276 7.319 7.276a7.316 7.316 0 0 0 5.205-2.162c-.337.042-.68.063-1.029.063c-4.61 0-8.343-3.714-8.343-8.29c0-1.167.242-2.278.681-3.286z"/><path d="M10.794 3.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387a1.734 1.734 0 0 0-1.097 1.097l-.387 1.162a.217.217 0 0 1-.412 0l-.387-1.162A1.734 1.734 0 0 0 9.31 6.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387a1.734 1.734 0 0 0 1.097-1.097l.387-1.162zM13.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.156 1.156 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.156 1.156 0 0 0-.732-.732l-.774-.258a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732L13.863.1z"/></g></svg>';

    function currentTheme() {
        // Light is the default: anything other than an explicit 'dark' is light
        return html.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    }

    function renderIcon(button) {
        // Show the icon for the theme you would switch TO
        button.innerHTML = currentTheme() === 'light' ? moon : sun;
    }

    // Make sure the mobile menu is closed after back/forward navigation
    // the checkbox state can otherwise be restored as "open" by the bfcache
    window.addEventListener('pageshow', () => {
        const navToggle = document.getElementById('nav-toggle');
        if (navToggle) navToggle.checked = false;
    });

    document.addEventListener('DOMContentLoaded', () => {
        const switchTheme = document.getElementById('theme_switcher');
        if (!switchTheme) return;

        renderIcon(switchTheme);

        switchTheme.addEventListener('click', (e) => {
            e.preventDefault();
            const next = currentTheme() === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', next);
            try {
                localStorage.setItem('grace-theme', next);
            } catch (err) { /* private browsing etc., toggle still works for this page */ }
            renderIcon(switchTheme);
        });
    });
})();

/**
 * Escape a string for safe insertion into innerHTML, as text or inside a
 * quoted attribute value (quotes are escaped too, so a company called
 * Joe's "Farm" can't break out of value="...").
 */
function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * Format a ledger timestamp ('2026-03-01 09:30:00', stored in NZ time) as an
 * NZ date ('01/03/2026'), straight from the text. Parsing it with new Date()
 * would read it in the browser's own time zone, which can shift the day.
 * @param {string} value
 * @returns {string}
 */
function formatLedgerDate(value) {
    const text = String(value ?? '');
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(text);
    return match ? `${match[3]}/${match[2]}/${match[1]}` : text;
}

/**
 * Format grams for people: 1400 -> '1,400', 395.5 -> '395.5', at most 2 decimals.
 * @param {number} grams
 * @returns {string}
 */
function formatGrams(grams) {
    return Number(grams || 0).toLocaleString('en-NZ', { maximumFractionDigits: 2 });
}

/**
 * Show a transient toast notification.
 * @param {string} message
 * @param {string} type 'success' | 'error' | 'info'
 * @param {number} duration ms before auto-dismiss
 */
function showToast(message, type = 'info', duration = 4500) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        document.body.appendChild(container);
    }
    const toast = document.createElement('div');
    toast.className = 'toast toast--' + type;
    toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
    toast.textContent = message;
    container.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('toast--visible'));
    setTimeout(() => {
        toast.classList.remove('toast--visible');
        setTimeout(() => toast.remove(), 400);
    }, duration);
}

/**
 * Queue a toast to be shown after the next page load, use before
 * location.reload() / navigation so the message isn't lost.
 */
function flashToast(message, type = 'success', duration = 4500) {
    try {
        sessionStorage.setItem('grace-flash', JSON.stringify({ message: message, type: type, duration: duration }));
    } catch (e) { /* sessionStorage unavailable, message is lost, not fatal */ }
}

document.addEventListener('DOMContentLoaded', () => {
    try {
        const flash = sessionStorage.getItem('grace-flash');
        if (flash) {
            sessionStorage.removeItem('grace-flash');
            const data = JSON.parse(flash);
            showToast(data.message, data.type || 'success', data.duration || 4500);
        }
    } catch (e) { /* ignore malformed flash data */ }
});

/**
 * Double taps can't save twice: once a form is on its way to the server,
 * further submits of it are ignored until a new page loads. Forms that
 * send via fetch() cancel the submit event, so they're not affected.
 */
function markFormSubmitting(form) {
    form.dataset.submitting = '1';
    // Disable the buttons only after the browser has read the form, so a
    // named submit button (e.g. the legacy harvest migration) still sends
    // its value
    setTimeout(() => {
        form.querySelectorAll('button[type="submit"], button:not([type])').forEach(button => {
            button.disabled = true;
            button.setAttribute('aria-busy', 'true');
        });
    }, 0);
}

/**
 * Submit a form after a confirm step, at most once. Use instead of
 * form.submit(), which skips the submit event and so the guard above.
 * @param {HTMLFormElement} form
 */
function submitOnce(form) {
    if (form.dataset.submitting) return;
    markFormSubmitting(form);
    form.submit();
}

// Capture phase: runs before any page's own submit handler, so a second tap
// doesn't even open a second confirm pop-up
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form.dataset.submitting) {
        event.preventDefault();
        event.stopImmediatePropagation();
    }
}, true);

// Bubble phase: after the page's handlers. If none of them cancelled the
// submit, it's really on its way.
document.addEventListener('submit', (event) => {
    if (!event.defaultPrevented) markFormSubmitting(event.target);
});

// Coming back with the Back button can restore a page from the cache as it
// was mid-submit; make its forms usable again
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) return;
    document.querySelectorAll('form[data-submitting]').forEach(form => {
        delete form.dataset.submitting;
        form.querySelectorAll('button[aria-busy]').forEach(button => {
            button.disabled = false;
            button.removeAttribute('aria-busy');
        });
    });
});

/**
 * Show a confirmation modal. Resolves true (confirm) or false (cancel).
 * @param {Object} options
 * @param {string} options.title
 * @param {string} [options.message]
 * @param {string[]} [options.items]  bullet list shown in the modal (e.g. affected plants)
 * @param {string} [options.confirmLabel]
 * @param {string} [options.cancelLabel]
 * @param {boolean} [options.danger]  style the confirm button red
 * @param {string} [options.warning]  a highlighted warning (e.g. an unusually
 *        large entry); the confirm button stays disabled until the tick box
 *        underneath is ticked
 * @param {string} [options.warningTick]  label for that tick box
 * @returns {Promise<boolean>}
 */
function confirmAction(options) {
    const opts = options || {};
    const title = opts.title || 'Are you sure?';
    const message = opts.message || '';
    const items = opts.items || [];
    const confirmLabel = opts.confirmLabel || 'Confirm';
    const cancelLabel = opts.cancelLabel || 'Cancel';
    const danger = !!opts.danger;
    const warning = opts.warning || '';
    const warningTick = opts.warningTick || 'Yes, this is right';

    if (typeof HTMLDialogElement === 'undefined') {
        // Very old browser, fall back to the native dialog
        return Promise.resolve(window.confirm(title + (message ? '\n\n' + message : '') + (warning ? '\n\n' + warning : '')));
    }

    return new Promise((resolve) => {
        const dialog = document.createElement('dialog');
        dialog.className = 'grace-modal';
        const list = items.length
            ? '<ul class="grace-modal-list">' + items.map(i => '<li>' + escapeHtml(i) + '</li>').join('') + '</ul>'
            : '';
        dialog.innerHTML = `
            <article>
                <h3>${escapeHtml(title)}</h3>
                ${message ? '<p>' + escapeHtml(message) + '</p>' : ''}
                ${list}
                ${warning ? `<p class="grace-modal-warning" role="alert">${escapeHtml(warning)}</p>
                <label class="grace-modal-tick"><input type="checkbox" data-modal-tick> ${escapeHtml(warningTick)}</label>` : ''}
                <footer>
                    <button type="button" class="secondary" data-modal-cancel>${escapeHtml(cancelLabel)}</button>
                    <button type="button"${danger ? ' class="modal-danger"' : ''} data-modal-confirm>${escapeHtml(confirmLabel)}</button>
                </footer>
            </article>`;
        document.body.appendChild(dialog);

        const close = (result) => {
            dialog.close();
            dialog.remove();
            resolve(result);
        };
        dialog.querySelector('[data-modal-cancel]').addEventListener('click', () => close(false));
        const confirmButton = dialog.querySelector('[data-modal-confirm]');
        confirmButton.addEventListener('click', () => close(true));
        const tick = dialog.querySelector('[data-modal-tick]');
        if (tick) {
            // A large entry has to be ticked as right before it can be saved
            confirmButton.disabled = true;
            tick.addEventListener('change', () => { confirmButton.disabled = !tick.checked; });
        }
        dialog.addEventListener('cancel', (e) => { e.preventDefault(); close(false); });
        dialog.addEventListener('click', (e) => { if (e.target === dialog) close(false); });
        dialog.showModal();
    });
}

/**
 * Render a plant/transaction status as a coloured badge element.
 * @param {string} status e.g. 'Growing', 'Harvested - Drying', 'Sent'
 * @returns {HTMLSpanElement}
 */
function statusBadge(status) {
    const span = document.createElement('span');
    span.className = 'badge';
    span.textContent = status;

    const s = String(status || '').toLowerCase();
    if (s === 'growing') {
        span.classList.add('badge--growing');
    } else if (s.includes('drying')) {
        span.classList.add('badge--drying');
    } else if (s.includes('destroyed')) {
        span.classList.add('badge--destroyed');
    } else if (s === 'sent') {
        span.classList.add('badge--sent');
    } else {
        span.classList.add('badge--neutral');
    }
    return span;
}

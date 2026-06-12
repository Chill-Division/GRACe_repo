/**
 * report_email.js
 *
 * Called from:
 * - last_months_flower_transactions.php: "Draft this in an email" for the monthly materials-out report.
 * - annual_stocktake.php: "Draft this in an email" for the annual stocktake.
 *
 * Why:
 * Opens the user's own mail app via a mailto: link addressed to the
 * Medicinal Cannabis Agency, pre-filled with the report shown on screen.
 * Mail clients truncate very long mailto bodies, so oversized reports are
 * copied to the clipboard instead, with a note in the email body to paste.
 * Drafting also records the matching dashboard reminder as 'drafted' so the
 * banner clears itself.
 */

const GRACE_AGENCY_EMAIL = 'medicinal_cannabis@health.govt.nz';

// Conservative limit for mailto body length — beyond this some mail clients
// silently cut the message off, so we switch to the clipboard instead
const GRACE_MAILTO_BODY_LIMIT = 1800;

/**
 * Open a pre-filled email to the Agency and clear the matching reminder.
 * @param {Object} options
 * @param {string} options.subject
 * @param {string} options.body          plain-text report body
 * @param {string} options.reminderType  'monthly' | 'annual'
 * @param {string} options.reminderPeriod e.g. '2026-05' or '2025'
 */
function draftReportEmail(options) {
    const { subject, body, reminderType, reminderPeriod } = options;

    const openMailto = (mailBody) => {
        const url = 'mailto:' + GRACE_AGENCY_EMAIL
            + '?subject=' + encodeURIComponent(subject)
            + '&body=' + encodeURIComponent(mailBody);

        // GRACe usually runs inside Home Assistant's ingress iframe.
        // Navigating the iframe itself to a mailto: breaks when the
        // browser's mail handler is webmail (e.g. Chrome rewrites it to a
        // mail.google.com compose URL, and Gmail refuses to load inside a
        // frame). Opening in a new browsing context works in both worlds:
        // webmail handlers get their own tab, native mail apps just open.
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        document.body.appendChild(link);
        link.click();
        link.remove();
    };

    if (body.length > GRACE_MAILTO_BODY_LIMIT && navigator.clipboard && navigator.clipboard.writeText) {
        // Too long for a reliable mailto body — clipboard fallback
        navigator.clipboard.writeText(body).then(() => {
            openMailto('(The report was too long to pre-fill — it has been copied to your clipboard. Paste it here.)');
            showToast('Report copied to your clipboard — paste it into the email body.', 'info', 8000);
        }).catch(() => {
            // Clipboard unavailable — best effort with the full body anyway
            openMailto(body);
            showToast('Heads up: long reports can be cut off by some mail apps. Double-check the email body.', 'info', 8000);
        });
    } else {
        openMailto(body);
        showToast('Email draft opened in your mail app.', 'success');
    }

    // Mark the dashboard reminder as drafted (fire and forget — drafting
    // the email is the action we wanted, even if this recording fails)
    if (reminderType && reminderPeriod) {
        const params = new URLSearchParams({
            report_type: reminderType,
            period: reminderPeriod,
            status: 'drafted'
        });
        fetch('handle_report_reminder.php', { method: 'POST', body: params })
            .catch(() => { /* non-fatal */ });
    }
}

/**
 * Collect the visible rows of a report table as plain-text lines.
 * @param {HTMLTableElement} table
 * @param {function(string[]): string} formatRow turns one row's cell texts into a line
 * @returns {string[]}
 */
function reportTableLines(table, formatRow) {
    const lines = [];
    table.querySelectorAll('tbody tr').forEach(row => {
        if (row.style.display === 'none') return; // respect active filters
        const cells = Array.from(row.cells).map(cell => cell.textContent.trim());
        if (cells.length < 2) return; // skip "Nothing to report" placeholder rows
        lines.push(formatRow(cells));
    });
    return lines;
}

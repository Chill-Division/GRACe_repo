<?php
/**
 * Agency report reminders (added in 0.17.0).
 *
 * Decides which reporting reminders are due on a given day. The key design
 * rule is that reminders are WINDOWS, not queues: we only ever evaluate the
 * current window (the first 7 days of this month for last month's materials
 * out, or any day in January for the annual stocktake). We never scan
 * history for "unreported" periods, so a user can see at most two banners
 * and a fresh install is never flooded with reminders about the past.
 *
 * Dismissals/drafts are stored per period in the ReportReminders table, so
 * they stick across every device and browser.
 */

// How many days into the month the materials-out reminder stays visible
const GRACE_MONTHLY_REMINDER_WINDOW_DAYS = 7;

/**
 * Which reminders should the dashboard show today?
 *
 * @param PDO $pdo
 * @param string|null $today 'Y-m-d' (defaults to today, Pacific/Auckland);
 *                           injectable for tests and demos.
 * @return array[] each: ['type' => 'monthly'|'annual', 'period' => '2026-05'|'2025', 'label' => 'May 2026'|'2025']
 */
function getDueReportReminders(PDO $pdo, $today = null)
{
    $now = new DateTimeImmutable($today ?? 'now');
    $due = [];

    // Reminders only make sense once company details exist (set during first
    // run), the report email needs the company name and license number.
    $hasCompany = (int) $pdo->query("SELECT COUNT(*) FROM OwnCompany")->fetchColumn() > 0;
    if (!$hasCompany) {
        return $due;
    }

    // --- Monthly materials out: first N days of the month, only if last
    // --- month actually had outbound materials, and not already actioned.
    if ((int) $now->format('j') <= GRACE_MONTHLY_REMINDER_WINDOW_DAYS) {
        $lastMonth = $now->modify('first day of last month');
        $period = $lastMonth->format('Y-m');

        if (!reportReminderActioned($pdo, 'monthly', $period)
            && monthHadOutboundMaterials($pdo, $lastMonth)) {
            $due[] = [
                'type' => 'monthly',
                'period' => $period,
                'label' => $lastMonth->format('F Y'),
            ];
        }
    }

    // --- Annual stocktake: any day in January, as long as the ledger has
    // --- any activity from before 1 Jan (so brand-new installs stay quiet).
    if ((int) $now->format('n') === 1) {
        $year = (string) ((int) $now->format('Y') - 1);
        $janFirst = $now->format('Y') . '-01-01';

        if (!reportReminderActioned($pdo, 'annual', $year)
            && ledgerHasActivityBefore($pdo, $janFirst)) {
            $due[] = [
                'type' => 'annual',
                'period' => $year,
                'label' => $year,
            ];
        }
    }

    return $due;
}

/** Has this reminder already been dismissed or drafted? */
function reportReminderActioned(PDO $pdo, $type, $period)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM ReportReminders WHERE report_type = ? AND period = ?");
    $stmt->execute([$type, $period]);
    return (int) $stmt->fetchColumn() > 0;
}

/** Record a reminder as dismissed or drafted (idempotent). */
function actionReportReminder(PDO $pdo, $type, $period, $status)
{
    $stmt = $pdo->prepare("INSERT INTO ReportReminders (report_type, period, status, actioned_at)
                           VALUES (?, ?, ?, datetime('now', 'localtime'))
                           ON CONFLICT(report_type, period) DO UPDATE SET status = excluded.status, actioned_at = excluded.actioned_at");
    $stmt->execute([$type, $period, $status]);
}

/**
 * Did the given month have any outbound materials? Uses the same
 * definitions as the monthly Agency report: flower subtracted for
 * 'Send external'/'Testing', or plants with status 'Sent'.
 */
function monthHadOutboundMaterials(PDO $pdo, DateTimeImmutable $monthStart)
{
    $start = $monthStart->format('Y-m-01');
    $nextStart = $monthStart->modify('first day of next month')->format('Y-m-01');

    $stmt = $pdo->prepare(
        "SELECT EXISTS (
            SELECT 1 FROM Flower
            WHERE transaction_type = 'Subtract'
              AND reason IN ('Send external', 'Testing')
              AND DATE(transaction_date) >= :start AND DATE(transaction_date) < :nextStart
         ) OR EXISTS (
            SELECT 1 FROM Plants
            WHERE status = 'Sent'
              AND date_harvested IS NOT NULL
              AND DATE(date_harvested) >= :start AND DATE(date_harvested) < :nextStart
         )"
    );
    $stmt->execute([':start' => $start, ':nextStart' => $nextStart]);
    return (bool) $stmt->fetchColumn();
}

/** Any plants or flower transactions dated before the given day? */
function ledgerHasActivityBefore(PDO $pdo, $date)
{
    $stmt = $pdo->prepare(
        "SELECT EXISTS (SELECT 1 FROM Plants WHERE DATE(date_created) < :d)
             OR EXISTS (SELECT 1 FROM Flower WHERE DATE(transaction_date) < :d)"
    );
    $stmt->execute([':d' => $date]);
    return (bool) $stmt->fetchColumn();
}

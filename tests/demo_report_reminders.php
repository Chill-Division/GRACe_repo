<?php
/**
 * demo_report_reminders.php, show what the dashboard reminders would do on
 * any date, against your local dev database (/data/grace.db). Great for
 * demos and videos: walk through a month without touching the system clock.
 *
 * Usage (from the repo root):
 *   php tests/demo_report_reminders.php                # today
 *   php tests/demo_report_reminders.php 2026-07-03     # a specific day
 *   php tests/demo_report_reminders.php 2027-01-01 2027-01-10   # a date range, day by day
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/report_reminders_lib.php';

$pdo = initializeDatabase();

$from = $argv[1] ?? date('Y-m-d');
$to = $argv[2] ?? $from;

$day = new DateTimeImmutable($from);
$end = new DateTimeImmutable($to);

echo "Dashboard reminders per day (database: /data/grace.db)\n";
echo str_repeat('-', 64) . "\n";

while ($day <= $end) {
    $due = getDueReportReminders($pdo, $day->format('Y-m-d'));
    $descriptions = array_map(function ($r) {
        return $r['type'] === 'monthly'
            ? "Monthly materials out for {$r['label']}"
            : "Annual stocktake for {$r['label']}";
    }, $due);

    printf("%s (%s): %s\n",
        $day->format('Y-m-d'),
        $day->format('D'),
        $descriptions ? implode(' + ', $descriptions) : '(no reminders)');

    $day = $day->modify('+1 day');
}

<?php
/**
 * Regression tests for license expiry alerts (0.18.0),
 * grace_addon/files/general/www/public/license_alerts_lib.php.
 *
 * The same helper drives the nav banner (3-day window) and the Dashboard's
 * "License Renewals Due" list (30-day window). Acknowledged licenses must
 * disappear from both.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/license_alerts_lib.php';

$failures = 0;
function check($story, $actual, $expected)
{
    global $failures;
    if ($actual === $expected) {
        echo "[PASS] $story\n";
    } else {
        echo "[FAIL] $story, expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        $failures++;
    }
}

/** The returned filenames as one string, for easy comparison. */
function names($rows)
{
    return implode(' ', array_column($rows, 'original_filename'));
}

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_alerts_') . '.db';
$pdo = initializeDatabase($tmpDb);
// Documents gains expiry_date/acknowledged via migration on real installs
performMigrations($pdo);

$stmt = $pdo->prepare("INSERT INTO Documents (category, original_filename, unique_filename, expiry_date, acknowledged)
                       VALUES (?, ?, ?, ?, ?)");
$stmt->execute(['licenses', 'expired-unacknowledged.pdf', 'a.pdf', date('Y-m-d', strtotime('-80 days')), 0]);
$stmt->execute(['licenses', 'expired-acknowledged.pdf', 'b.pdf', date('Y-m-d', strtotime('-60 days')), 1]);
$stmt->execute(['licenses', 'expiring-in-10-days.pdf', 'c.pdf', date('Y-m-d', strtotime('+10 days')), 0]);
$stmt->execute(['licenses', 'expiring-in-10-days-acknowledged.pdf', 'd.pdf', date('Y-m-d', strtotime('+10 days')), 1]);
$stmt->execute(['licenses', 'renewed-next-year.pdf', 'e.pdf', date('Y-m-d', strtotime('+300 days')), 0]);
$stmt->execute(['licenses', 'no-expiry-recorded.pdf', 'f.pdf', null, 0]);
$stmt->execute(['sops', 'not-a-license.pdf', 'g.pdf', date('Y-m-d', strtotime('-10 days')), 0]);

// Dashboard window (30 days): expired + soon-expiring, but never acknowledged
// ones, other categories, or licenses without an expiry date. Soonest first.
check('Dashboard (30 days) lists the unacknowledged expired + expiring licenses only',
    names(getUnacknowledgedExpiringLicenses($pdo, 30)),
    'expired-unacknowledged.pdf expiring-in-10-days.pdf');

// Nav banner window (3 days): the 10-day one is not urgent yet
check('Nav banner (3 days) lists only the unacknowledged expired license',
    names(getUnacknowledgedExpiringLicenses($pdo, 3)),
    'expired-unacknowledged.pdf');

// Acknowledging clears the alert everywhere (the 0.18.0 fix)
$pdo->exec("UPDATE Documents SET acknowledged = 1 WHERE original_filename = 'expired-unacknowledged.pdf'");
check('After acknowledging: gone from the dashboard list',
    names(getUnacknowledgedExpiringLicenses($pdo, 30)),
    'expiring-in-10-days.pdf');
check('After acknowledging: gone from the nav banner too',
    names(getUnacknowledgedExpiringLicenses($pdo, 3)),
    '');

unlink($tmpDb);

if ($failures > 0) {
    echo "[FAIL] License alert tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] License Alerts Test Completed Successfully\n";
exit(0);

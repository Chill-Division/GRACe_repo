<?php
/**
 * Regression tests for the Agency report reminder logic
 * (grace_addon/files/general/www/public/report_reminders_lib.php).
 *
 * The date is injected, so every scenario below is deterministic. Each case
 * prints a one-line story of what it proves, handy for demos.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/report_reminders_lib.php';

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

/** Summarise due reminders as e.g. 'monthly:2026-05 annual:2026' for easy comparison. */
function summarise($due)
{
    return implode(' ', array_map(fn($r) => $r['type'] . ':' . $r['period'], $due));
}

// ---------------------------------------------------------------------------
// Scenario A: a brand-new install, empty database, no company yet
// ---------------------------------------------------------------------------
$tmpA = tempnam(sys_get_temp_dir(), 'grace_rem_a_') . '.db';
$pdoA = initializeDatabase($tmpA);

check('New install, no company: silent on 3 June', summarise(getDueReportReminders($pdoA, '2026-06-03')), '');
check('New install, no company: silent in January too', summarise(getDueReportReminders($pdoA, '2026-01-15')), '');

// ---------------------------------------------------------------------------
// Scenario B: an established cultivator
//   - shipped flower + a plant in May 2026, nothing in June 2026
//   - shipped flower in December 2026
//   - ledger activity going back to 2025
// ---------------------------------------------------------------------------
$tmpB = tempnam(sys_get_temp_dir(), 'grace_rem_b_') . '.db';
$pdoB = initializeDatabase($tmpB);
$pdoB->exec("INSERT INTO OwnCompany (company_name, company_license_number) VALUES ('Test Co', 'MCA-1')");
$pdoB->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");
$pdoB->exec("INSERT INTO Plants (genetics_id, status, date_created, date_harvested) VALUES
    (1, 'Growing', '2025-03-01 10:00:00', NULL),
    (1, 'Sent',    '2025-04-01 10:00:00', '2026-05-20 10:00:00')");
$pdoB->exec("INSERT INTO Flower (genetics_id, weight, transaction_type, reason, transaction_date) VALUES
    (1,  500.00, 'Add',      'Harvest',       '2026-04-15 10:00:00'),
    (1, -120.00, 'Subtract', 'Send external', '2026-05-10 10:00:00'),
    (1,  -30.00, 'Subtract', 'Testing',       '2026-12-29 10:00:00')");

// Monthly window behaviour
check('Day 3 of June: May shipped materials -> monthly reminder due', summarise(getDueReportReminders($pdoB, '2026-06-03')), 'monthly:2026-05');
check('Day 7 of June: still inside the window', summarise(getDueReportReminders($pdoB, '2026-06-07')), 'monthly:2026-05');
check('Day 8 of June: window closed, reminder gone (no nagging)', summarise(getDueReportReminders($pdoB, '2026-06-08')), '');
check('Day 3 of July: June shipped nothing -> no reminder', summarise(getDueReportReminders($pdoB, '2026-07-03')), '');

// Dismissal behaviour
actionReportReminder($pdoB, 'monthly', '2026-05', 'dismissed');
check('After dismissing May: day 3 of June is silent', summarise(getDueReportReminders($pdoB, '2026-06-03')), '');

// January: annual + monthly (December shipped) can both be due, the maximum
check('3 January: December report AND annual stocktake both due (the max of two)', summarise(getDueReportReminders($pdoB, '2027-01-03')), 'monthly:2026-12 annual:2026');
check('20 January: monthly window closed, annual still due all month', summarise(getDueReportReminders($pdoB, '2027-01-20')), 'annual:2026');

// Drafting the email clears the annual reminder
actionReportReminder($pdoB, 'annual', '2026', 'drafted');
check('After drafting the annual email: January is silent', summarise(getDueReportReminders($pdoB, '2027-01-20')), '');

// Next year's cycle starts fresh
check('Next June (May 2027 shipped nothing): silent', summarise(getDueReportReminders($pdoB, '2027-06-03')), '');

// ---------------------------------------------------------------------------
// Scenario C: company exists but the ledger only starts THIS year
// no annual reminder for a year GRACe wasn't tracking
// ---------------------------------------------------------------------------
$tmpC = tempnam(sys_get_temp_dir(), 'grace_rem_c_') . '.db';
$pdoC = initializeDatabase($tmpC);
$pdoC->exec("INSERT INTO OwnCompany (company_name, company_license_number) VALUES ('Fresh Co', 'MCA-2')");
$pdoC->exec("INSERT INTO Genetics (name) VALUES ('Bravo')");
$pdoC->exec("INSERT INTO Plants (genetics_id, status, date_created) VALUES (1, 'Growing', '2027-01-10 10:00:00')");

check('Installed in January with no prior-year data: no annual reminder', summarise(getDueReportReminders($pdoC, '2027-01-15')), '');

unlink($tmpA);
unlink($tmpB);
unlink($tmpC);

if ($failures > 0) {
    echo "[FAIL] Report reminder tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Report Reminder Test Completed Successfully\n";
exit(0);

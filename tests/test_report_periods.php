<?php
/**
 * Regression tests for the monthly materials-out report periods (1.1.0),
 * grace_addon/files/general/www/public/materials_out_lib.php.
 *
 * The bug: "Last month's materials out" worked out last month with
 * strtotime('-1 month'). On 31 July that gives "31 June", which PHP rolls
 * over to 1 July, so on the 29th-31st the report showed THIS month's figures
 * under LAST month's heading (and "Draft this in an email" sent them).
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
if (file_exists($publicDir . '/materials_out_lib.php')) {
    require_once $publicDir . '/materials_out_lib.php';
}

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

if (!function_exists('previousReportMonth') || !function_exists('getMaterialsOut')) {
    check('materials_out_lib.php provides previousReportMonth() and getMaterialsOut()', false, true);
    echo "[FAIL] Report period tests: $failures failure(s)\n";
    exit(1);
}

// --- "Last month" is always the previous calendar month ------------------------
$cases = [
    // today         last month   heading
    ['2026-07-31', '2026-06', 'June 2026'],      // the reported bug
    ['2026-10-31', '2026-09', 'September 2026'],
    ['2026-03-30', '2026-02', 'February 2026'],  // 30 February doesn't exist either
    ['2026-03-31', '2026-02', 'February 2026'],
    ['2026-05-31', '2026-04', 'April 2026'],
    ['2026-12-31', '2026-11', 'November 2026'],
    ['2026-01-15', '2025-12', 'December 2025'],  // across the new year
    ['2026-08-01', '2026-07', 'July 2026'],      // first of the month (reminder window)
];
foreach ($cases as [$today, $period, $label]) {
    $month = previousReportMonth($today);
    check("On $today, last month is $label", [$month['period'], $month['label']], [$period, $label]);
}

$leap = previousReportMonth('2028-03-31');
check('In a leap year, February runs to the 29th', [$leap['start'], $leap['nextStart']], ['2028-02-01', '2028-03-01']);

$current = currentReportMonth('2026-07-31');
check('"This month" on 31 July is July', $current['period'], '2026-07');

// --- The report itself only contains last month's sends ------------------------
$tmpDb = tempnam(sys_get_temp_dir(), 'grace_periods_') . '.db';
$pdo = initializeDatabase($tmpDb);
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");
$pdo->exec("INSERT INTO Companies (name, license_number, address) VALUES ('Test Lab', 'LAB-1', '1 Lab Street')");

$pdo->exec("INSERT INTO Flower (genetics_id, weight, transaction_type, transaction_date, reason, company_id) VALUES
    (1, -3,  'Subtract', '2026-05-31 23:59:59', 'Testing',       1),  -- May: not June
    (1, -10, 'Subtract', '2026-06-01 00:00:00', 'Testing',       1),  -- first second of June
    (1, -9,  'Subtract', '2026-06-15 10:00:00', 'Destroy',       NULL), -- destroyed, not sent out
    (1, 100, 'Add',      '2026-06-16 10:00:00', 'Harvest',       NULL), -- flower in, not out
    (1, -20, 'Subtract', '2026-06-30 23:59:59', 'Send external', 1),  -- last second of June
    (1, -5,  'Subtract', '2026-07-01 00:00:00', 'Testing',       1),  -- July
    (1, -7,  'Subtract', '2026-07-31 10:00:00', 'Send external', 1)   -- July, the day the bug showed");
$pdo->exec("INSERT INTO Plants (genetics_id, status, date_created, date_harvested, company_id) VALUES
    (1, 'Sent',                  '2026-01-01 10:00:00', '2026-06-10 09:00:00', 1),
    (1, 'Sent',                  '2026-01-01 10:00:00', '2026-06-10 09:00:00', 1),
    (1, 'Harvested - Destroyed', '2026-01-01 10:00:00', '2026-06-11 09:00:00', NULL),
    (1, 'Sent',                  '2026-01-01 10:00:00', '2026-07-02 09:00:00', 1)");

$june = getMaterialsOut($pdo, previousReportMonth('2026-07-31'));
check('On 31 July, last month\'s report lists June\'s two flower sends, newest first',
    array_map(fn($f) => [$f['transaction_date'], (float) $f['weight']], $june['flowers']),
    [['2026-06-30 23:59:59', 20.0], ['2026-06-01 00:00:00', 10.0]]);
check('On 31 July, last month\'s report lists June\'s plants sent (2 in one go)',
    array_map(fn($p) => [$p['transaction_date'], (int) $p['plantCount']], $june['plants']),
    [['2026-06-10 09:00:00', 2]]);
check('Company name and address are shown for each send',
    $june['flowers'][0]['companyNameAddress'] ?? null, 'Test Lab, 1 Lab Street');

$july = getMaterialsOut($pdo, currentReportMonth('2026-07-31'));
check('This month\'s report on 31 July lists July\'s sends only',
    array_map(fn($f) => (float) $f['weight'], $july['flowers']), [7.0, 5.0]);

@unlink($tmpDb);

// --- The page tells the data endpoint which month to load ----------------------
check('A month in the URL is accepted', (parseReportMonth('2026-06') ?? [])['period'] ?? null, '2026-06');
check('An impossible month is ignored', parseReportMonth('2026-13'), null);
check('A badly formatted month is ignored', parseReportMonth('26-06'), null);
check('Anything else in the URL is ignored', parseReportMonth("2026-06' OR 1=1"), null);
check('A missing month is ignored', parseReportMonth(null), null);

$endpoint = file_get_contents($publicDir . '/get_last_months_flower_transactions.php');
$page = file_get_contents($publicDir . '/last_months_flower_transactions.php');
check('The data endpoint no longer uses strtotime(\'-1 month\')', strpos($endpoint, "-1 month") === false, true);
check('The data endpoint uses the shared helpers',
    strpos($endpoint, 'previousReportMonth(') !== false && strpos($endpoint, 'parseReportMonth(') !== false, true);
check('The page passes its month to the data endpoint, so heading and figures always match',
    strpos($page, 'previousReportMonth(') !== false && strpos($page, '?period=') !== false, true);

if ($failures > 0) {
    echo "[FAIL] Report period tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Report Period Test Completed Successfully\n";
exit(0);

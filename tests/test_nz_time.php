<?php
/**
 * Regression tests for storing ledger times in NZ time (1.1.0).
 *
 * The bug: plants and manual flower entries were stamped with SQLite's
 * DATETIME('now'), which is UTC, 12 or 13 hours behind NZ. The reports split
 * months and years in NZ time, so anything recorded before about 1pm on the
 * 1st of a month was counted in the previous month's report (and anything
 * recorded on the morning of 1 January in the previous year's stocktake).
 *
 * From 1.1.0 every ledger time is NZ time, and convertLedgerTimesToNzTime()
 * in init_db.php converts the old UTC times once, on the first page load
 * after the update.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/materials_out_lib.php';

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

function freshDb()
{
    $path = tempnam(sys_get_temp_dir(), 'grace_nztime_') . '.db';
    return [$path, initializeDatabase($path)];
}

function value(PDO $pdo, $sql)
{
    try {
        return $pdo->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        return 'ERROR: ' . $e->getMessage();
    }
}

function migrationRow(PDO $pdo)
{
    try {
        $row = $pdo->query("SELECT status, details FROM DataMigrations WHERE name = 'ledger-times-to-nz-time'")->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return null;
    }
    if (!$row) {
        return null;
    }
    $row['details'] = json_decode($row['details'] ?? '', true);
    return $row;
}

if (!function_exists('convertLedgerTimesToNzTime') || !function_exists('ledgerTimestamp')) {
    check('init_db.php provides convertLedgerTimesToNzTime() and ledgerTimestamp()', false, true);
}

// === A. Fresh install ==========================================================
[$pathA, $pdo] = freshDb();
performMigrations($pdo);
$row = migrationRow($pdo);
check('A fresh install records the conversion as done straight away (nothing to convert)',
    [$row['status'] ?? null, $row['details']['plantsUpToId'] ?? null, $row['details']['flowerUpToId'] ?? null],
    ['done', 0, 0]);

$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");
$pdo->exec("INSERT INTO Plants (genetics_id, status, date_created) VALUES (1, 'Growing', '2026-09-23 10:00:00')");
performMigrations($pdo);
check('Entries made after that are never shifted',
    value($pdo, "SELECT date_created FROM Plants WHERE id = 1"), '2026-09-23 10:00:00');
@unlink($pathA);

// === B. Upgrading a 1.0.x install =============================================
// Times exactly as 1.0.x stored them: UTC
[$pathB, $pdo] = freshDb();
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");
$pdo->exec("INSERT INTO Companies (name, license_number, address) VALUES ('Buyer Ltd', 'LIC-1', '1 Buyer Road')");
$pdo->exec("INSERT INTO Plants (id, genetics_id, status, date_created, date_harvested, company_id) VALUES
    -- 1. received at 9:30am on 1 March 2025 NZ time (NZDT, UTC+13)
    (1, 1, 'Growing', '2025-02-28 20:30:00', '2025-02-28 20:30:00', NULL),
    -- 2. sent at 9am on 1 March 2025 NZ time: 1.0.x reported it in February
    (2, 1, 'Sent',    '2025-01-10 01:00:00', '2025-02-28 20:00:00', 1),
    -- 3. received at 1am on 1 July 2025 NZ time (NZST, UTC+12)
    (3, 1, 'Growing', '2025-06-30 13:00:00', NULL, NULL),
    -- 4. an old date without a time: nothing to convert
    (4, 1, 'Growing', '2024-05-05', NULL, NULL),
    -- 5. something that isn't a date at all: left alone, never an error
    (5, 1, 'Growing', 'not a date', NULL, NULL)");
$pdo->exec("INSERT INTO Flower (id, genetics_id, weight, transaction_type, transaction_date, reason, company_id) VALUES
    -- 1. sample sent to a lab at 1:30am on 1 January 2026 NZ time
    (1, 1, -5,  'Subtract', '2025-12-31 12:30:00', 'Testing', 1),
    -- 2. deducted by a shipping manifest: those were always NZ time
    (2, 1, -20, 'Subtract', '2026-06-12 10:00:00', 'Send external', 1),
    -- 3 and 4. half an hour either side of daylight saving ending (3am NZDT on
    --    6 April 2025 became 2am NZST), both 2:30am NZ time
    (3, 1, 100, 'Add', '2025-04-05 13:30:00', 'Harvest', NULL),
    (4, 1, 100, 'Add', '2025-04-05 14:30:00', 'Harvest', NULL)");
$pdo->exec("INSERT INTO ShippingManifests (recipient_id, shipment_date, product_type, status, genetics_id, quantity, flower_transaction_id)
            VALUES (1, '2026-06-12 10:00:00', 'flower', 'In Progress', 1, 20, 2)");

$februaryBefore = getMaterialsOut($pdo, reportMonth('2025-02'));
check('Before the upgrade, the plant sent on 1 March shows in the February report (the bug)',
    count($februaryBefore['plants']), 1);

performMigrations($pdo);

$plants = $pdo->query("SELECT id, date_created, date_harvested FROM Plants ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
check('Plant received on 1 March 2025 (summer time) now reads 9:30am on 1 March',
    [$plants[0]['date_created'], $plants[0]['date_harvested']], ['2025-03-01 09:30:00', '2025-03-01 09:30:00']);
check('Plant sent on 1 March 2025 now reads 9am on 1 March',
    [$plants[1]['date_created'], $plants[1]['date_harvested']], ['2025-01-10 14:00:00', '2025-03-01 09:00:00']);
check('Plant received on 1 July 2025 (winter time, 12 hours) now reads 1am on 1 July',
    $plants[2]['date_created'], '2025-07-01 01:00:00');
check('A date with no time is left as it was', $plants[3]['date_created'], '2024-05-05');
check('A value that is not a date is left as it was', $plants[4]['date_created'], 'not a date');
check('Blank harvest dates stay blank', $plants[2]['date_harvested'], null);

$flower = $pdo->query("SELECT id, transaction_date FROM Flower ORDER BY id")->fetchAll(PDO::FETCH_KEY_PAIR);
check('Lab sample sent on 1 January 2026 now reads 1:30am on 1 January 2026', $flower[1], '2026-01-01 01:30:00');
check('Flower deducted by a shipping manifest is not shifted (it was already NZ time)', $flower[2], '2026-06-12 10:00:00');
check('Daylight saving is handled: both sides of the change read 2:30am on 6 April',
    [$flower[3], $flower[4]], ['2025-04-06 02:30:00', '2025-04-06 02:30:00']);

check('The plant sent on 1 March 2025 is no longer in the February report',
    count(getMaterialsOut($pdo, reportMonth('2025-02'))['plants']), 0);
$march = getMaterialsOut($pdo, reportMonth('2025-03'))['plants'];
check('It is in the March report instead', [count($march), $march[0]['transaction_date'] ?? null], [1, '2025-03-01 09:00:00']);
check('The lab sample from the morning of 1 January now counts in 2026, not 2025',
    value($pdo, "SELECT strftime('%Y', transaction_date) FROM Flower WHERE id = 1"), '2026');

$row = migrationRow($pdo);
check('The conversion is recorded as done, with the rows it covered',
    [$row['status'] ?? null, $row['details']['plantsUpToId'] ?? null, $row['details']['flowerUpToId'] ?? null],
    ['done', 5, 4]);
// 5 plant times (plants 1-3) and 3 flower times (not the manifest one);
// 5 of them land in a different month (plant 1 twice, plant 2's send,
// plant 3, and the lab sample)
check('It records how many times it converted and how many moved month',
    [$row['details']['convertedTimes'] ?? null, $row['details']['movedMonth'] ?? null], [8, 5]);

performMigrations($pdo);
check('Running it again changes nothing',
    [value($pdo, "SELECT date_harvested FROM Plants WHERE id = 2"), value($pdo, "SELECT transaction_date FROM Flower WHERE id = 1")],
    ['2025-03-01 09:00:00', '2026-01-01 01:30:00']);

$pdo->exec("INSERT INTO Flower (id, genetics_id, weight, transaction_type, transaction_date, reason) VALUES (5, 1, 10, 'Add', '2026-09-23 10:00:00', 'Harvest')");
performMigrations($pdo);
check('Entries made after the upgrade are never shifted',
    value($pdo, "SELECT transaction_date FROM Flower WHERE id = 5"), '2026-09-23 10:00:00');
check('Integrity check still passes', value($pdo, 'PRAGMA integrity_check'), 'ok');
@unlink($pathB);

// === C. A conversion that has to be retried never touches newer entries ========
// The first page load records which rows are old before converting anything.
// If the conversion itself fails and is retried later, entries written in the
// meantime (already NZ time) must be left alone.
[$pathC, $pdo] = freshDb();
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");
$pdo->exec("INSERT INTO Plants (id, genetics_id, status, date_created) VALUES (1, 1, 'Growing', '2025-02-28 20:30:00')");
$pdo->exec("CREATE TABLE IF NOT EXISTS DataMigrations (name TEXT PRIMARY KEY, status TEXT NOT NULL, details TEXT, updated_at DATETIME)");
$pdo->exec("INSERT INTO DataMigrations (name, status, details) VALUES ('ledger-times-to-nz-time', 'pending', '{\"plantsUpToId\":1,\"flowerUpToId\":0}')");
$pdo->exec("INSERT INTO Plants (id, genetics_id, status, date_created) VALUES (2, 1, 'Growing', '2026-09-23 10:00:00')");
if (function_exists('convertLedgerTimesToNzTime')) {
    convertLedgerTimesToNzTime($pdo);
}
check('On a retry, only the rows that existed before the upgrade are converted',
    $pdo->query("SELECT date_created FROM Plants ORDER BY id")->fetchAll(PDO::FETCH_COLUMN),
    ['2025-03-01 09:30:00', '2026-09-23 10:00:00']);
check('The retry finishes the job', migrationRow($pdo)['status'] ?? null, 'done');
@unlink($pathC);

// === D. New entries are written in NZ time =====================================
if (function_exists('ledgerTimestamp')) {
    $stamp = ledgerTimestamp();
    $nzNow = new DateTimeImmutable('now', new DateTimeZone('Pacific/Auckland'));
    $parsed = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $stamp, new DateTimeZone('Pacific/Auckland'));
    check('ledgerTimestamp() is the current NZ time, as Y-m-d H:i:s',
        $parsed !== false && abs($parsed->getTimestamp() - $nzNow->getTimestamp()) <= 5, true);
}

// Nothing may write the database clock (UTC) any more
function codeWithoutComments($file)
{
    $code = '';
    foreach (token_get_all(file_get_contents($file)) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $code .= is_array($token) ? $token[1] : $token;
    }
    return $code;
}

$offenders = [];
foreach (glob($publicDir . '/*.php') as $file) {
    $name = basename($file);
    $code = codeWithoutComments($file);
    if ($name === 'init_db.php') {
        // CREATE TABLE defaults stay (SQLite can't change them in place);
        // nothing relies on them because every write sets the time itself
        $code = preg_replace('/CREATE TABLE.*?\);/s', '', $code);
    }
    if (preg_match("/(date|datetime|julianday|strftime)\\s*\\(\\s*'now'|CURRENT_TIMESTAMP|'localtime'/i", $code)) {
        $offenders[] = $name;
    }
}
check('No page or handler uses the database clock (UTC) for times', $offenders, []);

// (Harvest / Destroy / Send does its writing in harvest_lib.php)
foreach (['handle_receive_genetics.php', 'harvest_lib.php', 'record_flower_transaction.php'] as $handler) {
    check("$handler stamps entries with ledgerTimestamp()",
        strpos(file_get_contents($publicDir . '/' . $handler), 'ledgerTimestamp()') !== false, true);
}
check('Receiving plants leaves the harvest date blank (it used to default to the UTC time)',
    preg_match('/INSERT INTO Plants \(genetics_id, status, date_created, date_harvested\)/', file_get_contents($publicDir . '/handle_receive_genetics.php')) === 1, true);

$transactionsJs = file_get_contents($publicDir . '/js/transactions.js');
check('Report dates are shown as stored (NZ time), not re-read in the browser\'s time zone',
    strpos($transactionsJs, 'formatLedgerDate(') !== false && strpos($transactionsJs, 'new Date(') === false, true);
check('formatLedgerDate() lives in the shared growcart.js',
    strpos(file_get_contents($publicDir . '/js/growcart.js'), 'function formatLedgerDate(') !== false, true);

if ($failures > 0) {
    echo "[FAIL] NZ time tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] NZ Time Test Completed Successfully\n";
exit(0);

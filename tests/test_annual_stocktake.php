<?php
/**
 * Regression tests for the annual stocktake calculations
 * (grace_addon/files/general/www/public/annual_stocktake_lib.php).
 *
 * Covers the 0.16.1 bug: plants that left stock MORE than one year before the
 * report year kept appearing in the opening balance forever, because the old
 * query only subtracted departures dated within the immediately-previous year.
 * Also covers the 31-December boundary (datetime vs date string comparison)
 * and "Harvested - Drying counts as active stock".
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/annual_stocktake_lib.php';

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_stocktake_') . '.db';
$pdo = initializeDatabase($tmpDb);

$failures = 0;
function check($label, $actual, $expected)
{
    global $failures;
    if ($actual === $expected) {
        echo "[PASS] $label\n";
    } else {
        echo "[FAIL] $label — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        $failures++;
    }
}

// Report year for all scenarios. Fixed (not relative to today) so results are deterministic.
$year = 2026;

$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha'), ('Bravo')");

// Alpha (genetics_id 1) — plant scenarios
$pdo->exec("INSERT INTO Plants (genetics_id, status, date_created, date_harvested) VALUES
    -- 1. THE BUG: destroyed two years before the report year -> must not be in 2026 at all
    (1, 'Destroyed',             '2024-03-01 10:00:00', '2024-10-20 02:26:58'),
    -- 2. destroyed in the immediately-previous year -> also not in 2026
    (1, 'Harvested - Destroyed', '2025-02-01 10:00:00', '2025-06-15 09:00:00'),
    -- 3. growing since 2023 -> in start and end
    (1, 'Growing',               '2023-05-05 08:00:00', NULL),
    -- 4. created during the report year -> 'in' and end
    (1, 'Growing',               '2026-04-10 12:00:00', NULL),
    -- 5. created 31 Dec of the report year, late in the day -> still 'in' (boundary)
    (1, 'Growing',               '2026-12-31 23:30:00', NULL),
    -- 6. created before, destroyed during the report year -> start + destroyed, not end
    (1, 'Destroyed',             '2025-03-03 10:00:00', '2026-07-01 11:00:00'),
    -- 7. created before, sent during the report year -> start + out, not end
    (1, 'Sent',                  '2025-08-08 10:00:00', '2026-05-05 10:00:00'),
    -- 8. harvested to drying BEFORE the report year -> drying is active stock, stays in start/end
    (1, 'Harvested - Drying',    '2024-06-06 10:00:00', '2025-11-11 10:00:00'),
    -- 9. legacy 'Harvested' before the report year -> left stock, not in start
    (1, 'Harvested',             '2023-01-01 10:00:00', '2023-09-09 10:00:00')");

// Bravo (genetics_id 2) — entirely departed before the report year (the 'Medicine Girl' case)
$pdo->exec("INSERT INTO Plants (genetics_id, status, date_created, date_harvested) VALUES
    (2, 'Destroyed', '2023-02-02 10:00:00', '2024-01-15 10:00:00'),
    (2, 'Sent',      '2023-02-02 10:00:00', '2024-02-20 10:00:00')");

$rows = computeAnnualPlantStocktake($pdo, $year);
$byName = [];
foreach ($rows as $r) {
    $byName[$r['geneticsName']] = $r;
}

$a = $byName['Alpha'];
// In stock at 2026-01-01: #3 growing, #6 destroyed-during-2026, #7 sent-during-2026, #8 drying => 4
check('Alpha start excludes plants departed in ANY earlier year', $a['startAmount'], 4);
check('Alpha in counts 2026 creations incl. 31 Dec evening', $a['in'], 2);
check('Alpha out (sent during 2026)', $a['out'], 1);
check('Alpha destroyed during 2026', $a['destroyed'], 1);
check('Alpha legacy harvested in 2026 is zero', $a['harvested'], 0);
// end = 4 + 2 - 1 - 0 - 1 = 4 (#3, #4, #5, #8)
check('Alpha end balance', $a['end'], 4);

$b = $byName['Bravo'];
check('Bravo (all departed before 2026) start is zero', $b['startAmount'], 0);
check('Bravo end is zero', $b['end'], 0);
check('Bravo shows zero activity in 2026', $b['in'] + $b['out'] + $b['harvested'] + $b['destroyed'], 0);

// --- Flower ledger scenarios -------------------------------------------------
$pdo->exec("INSERT INTO Flower (genetics_id, weight, transaction_type, reason, transaction_date) VALUES
    (1,  500.00, 'Add',      'Harvest',       '2025-09-01 10:00:00'),  -- before the year -> start
    (1, -100.00, 'Subtract', 'Send external', '2025-10-01 10:00:00'),  -- before the year -> start
    (1,  250.00, 'Add',      'Harvest',       '2026-03-01 10:00:00'),  -- in
    (1,  -50.00, 'Subtract', 'Testing',       '2026-06-01 10:00:00'),  -- out
    (1,  -20.00, 'Subtract', 'Destroy',       '2026-12-31 18:00:00'),  -- destroyed, 31 Dec boundary
    (1,   10.00, 'Add',      'Harvest',       '2027-01-02 10:00:00')   -- next year -> ignored");

$rows = computeAnnualFlowerStocktake($pdo, $year);
$f = null;
foreach ($rows as $r) {
    if ($r['geneticsName'] === 'Alpha') {
        $f = $r;
    }
}

check('Flower start is the running balance before 1 Jan', $f['startWeight'], 400.0);
check('Flower in during the year', $f['in'], 250.0);
check('Flower out during the year', $f['out'], 50.0);
check('Flower destroyed incl. 31 Dec evening', $f['destroyed'], 20.0);
check('Flower end balance', $f['end'], 580.0);

unlink($tmpDb);

if ($failures > 0) {
    echo "[FAIL] Annual stocktake tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Annual Stocktake Test Completed Successfully\n";
exit(0);

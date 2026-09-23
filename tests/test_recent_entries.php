<?php
/**
 * Regression tests for "Recent entries" (1.1.0), recent_lib.php.
 *
 * Receive plants, Record dry weight and Harvest / Destroy / Send each list
 * the latest entries under the form, newest first, so you can see what you
 * just did and spot something entered twice.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
if (file_exists($publicDir . '/recent_lib.php')) {
    require_once $publicDir . '/recent_lib.php';
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

function source($publicDir, $file)
{
    return file_exists($publicDir . '/' . $file) ? file_get_contents($publicDir . '/' . $file) : '';
}

if (!function_exists('recentReceipts') || !function_exists('recentFlowerEntries') || !function_exists('recentPlantMoves')) {
    check('recent_lib.php provides recentReceipts(), recentFlowerEntries() and recentPlantMoves()', false, true);
    echo "[FAIL] Recent entry tests: $failures failure(s)\n";
    exit(1);
}

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_recent_') . '.db';
$pdo = initializeDatabase($tmpDb);
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha'), ('Bravo')");
$pdo->exec("INSERT INTO Companies (name, license_number) VALUES ('Buyer Ltd', 'LIC-1')");

// Receipts: 3 Alpha at 09:00, 2 Bravo at 10:00 (one since sent), 12 Alpha at 11:00,
// and the same 12 Alpha again two minutes later (the double entry to spot)
$receipt = $pdo->prepare("INSERT INTO Plants (genetics_id, status, date_created, date_harvested, company_id) VALUES (?, ?, ?, ?, ?)");
foreach ([[1, 3, '2026-09-20 09:00:00'], [2, 2, '2026-09-21 10:00:00'], [1, 12, '2026-09-22 11:00:00'], [1, 12, '2026-09-22 11:02:00']] as [$genetics, $count, $when]) {
    for ($i = 0; $i < $count; $i++) {
        $receipt->execute([$genetics, 'Growing', $when, null, null]);
    }
}
$pdo->exec("UPDATE Plants SET status = 'Sent', date_harvested = '2026-09-23 08:00:00', company_id = 1 WHERE genetics_id = 2 AND id = (SELECT MIN(id) FROM Plants WHERE genetics_id = 2)");
$pdo->exec("UPDATE Plants SET status = 'Harvested - Drying', date_harvested = '2026-09-23 09:30:00' WHERE id IN (1, 2)");

check('Receipts are listed newest first, one line per entry, whatever happened to the plants since',
    array_map(fn($r) => [$r['when'], $r['genetics'], $r['count']], recentReceipts($pdo, 10)), [
        ['2026-09-22 11:02:00', 'Alpha', 12],
        ['2026-09-22 11:00:00', 'Alpha', 12],
        ['2026-09-21 10:00:00', 'Bravo', 2],
        ['2026-09-20 09:00:00', 'Alpha', 3],
    ]);
check('Only the requested number are listed', count(recentReceipts($pdo, 2)), 2);

check('Harvests, destroys and sends are listed newest first, with the company',
    array_map(fn($r) => [$r['when'], $r['status'], $r['genetics'], $r['count'], $r['company']], recentPlantMoves($pdo, 10)), [
        ['2026-09-23 09:30:00', 'Harvested - Drying', 'Alpha', 2, null],
        ['2026-09-23 08:00:00', 'Sent', 'Bravo', 1, 'Buyer Ltd'],
    ]);

// Flower: a harvest in, a lab sample out, and a manifest deduction
$pdo->exec("INSERT INTO Flower (id, genetics_id, weight, transaction_type, transaction_date, reason, company_id) VALUES
    (1, 1, 612.5, 'Add', '2026-09-20 12:00:00', 'Harvest', NULL),
    (2, 1, -10, 'Subtract', '2026-09-21 12:00:00', 'Testing', 1),
    (3, 1, -200, 'Subtract', '2026-09-22 12:00:00', 'Send external', 1)");
$pdo->exec("INSERT INTO ShippingManifests (id, recipient_id, shipment_date, product_type, status, genetics_id, quantity, flower_transaction_id)
            VALUES (7, 1, '2026-09-22 12:00:00', 'flower', 'In Progress', 1, 200, 3)");
check('Flower entries are listed newest first, with in/out weight, reason, company and any manifest',
    array_map(fn($r) => [$r['when'], $r['genetics'], $r['grams'], $r['reason'], $r['company'], $r['manifestId']], recentFlowerEntries($pdo, 10)), [
        ['2026-09-22 12:00:00', 'Alpha', -200.0, 'Send external', 'Buyer Ltd', 7],
        ['2026-09-21 12:00:00', 'Alpha', -10.0, 'Testing', 'Buyer Ltd', null],
        ['2026-09-20 12:00:00', 'Alpha', 612.5, 'Harvest', null, null],
    ]);
@unlink($tmpDb);

// --- Dates are shown as stored (NZ time) ----------------------------------------
check('A time is shown as day/month/year hours:minutes', formatLedgerDateTime('2026-09-23 10:32:05'), '23/09/2026 10:32');
check('A date on its own is shown as day/month/year', formatLedgerDateTime('2024-05-05'), '05/05/2024');
check('Anything else is shown as it is', formatLedgerDateTime('unknown'), 'unknown');

// --- The pages show them --------------------------------------------------------------
foreach (['receive_genetics.php' => 'recentReceipts(', 'record_dry_weight.php' => 'recentFlowerEntries(', 'harvest_plants.php' => 'recentPlantMoves('] as $page => $call) {
    $code = source($publicDir, $page);
    check("$page lists recent entries", strpos($code, $call) !== false && strpos($code, 'Recent entries') !== false, true);
    check("$page escapes what it lists", strpos($code, 'htmlspecialchars(') !== false, true);
}

if ($failures > 0) {
    echo "[FAIL] Recent entry tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Recent Entry Test Completed Successfully\n";
exit(0);

<?php
/**
 * Regression tests for Record dry weight change (1.1.0),
 * recordFlowerTransaction() in grace_addon/files/general/www/public/flower_lib.php.
 *
 * The gap: shipping manifests already refused to send more flower than was
 * on hand, but a manual Subtract took stock below zero without a word. The
 * handler also trusted the browser for everything else (reason, company,
 * weight), so a hand-crafted request could record nonsense.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
if (file_exists($publicDir . '/flower_lib.php')) {
    require_once $publicDir . '/flower_lib.php';
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

if (!function_exists('recordFlowerTransaction') || !function_exists('flowerOnHand')) {
    check('flower_lib.php provides recordFlowerTransaction() and flowerOnHand()', false, true);
    echo "[FAIL] Flower stock tests: $failures failure(s)\n";
    exit(1);
}

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_flower_') . '.db';
$pdo = initializeDatabase($tmpDb);
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha'), ('Bravo')");
$pdo->exec("INSERT INTO Companies (name, license_number) VALUES ('Lab Ltd', 'LAB-1')");
$pdo->exec("INSERT INTO Flower (genetics_id, weight, transaction_type, transaction_date, reason) VALUES
    (1, 100,   'Add',      '2026-09-01 10:00:00', 'Harvest'),
    (1, -30,   'Subtract', '2026-09-02 10:00:00', 'Destroy'),
    (2, -5,    'Subtract', '2026-09-03 10:00:00', 'Destroy')");  // an old ledger that already went below zero

$entries = fn() => (int) $pdo->query("SELECT COUNT(*) FROM Flower")->fetchColumn();
$subtract = fn($grams, $extra = []) => recordFlowerTransaction($pdo, $extra + [
    'geneticsName' => '1', 'weight' => (string) $grams, 'transactionType' => 'Subtract', 'reason' => 'Destroy',
]);

check('On hand is everything added minus everything subtracted', flowerOnHand($pdo, 1), 70.0);

// --- Can't subtract more than is on hand ------------------------------------------
$result = $subtract(80);
check('Subtracting more than is on hand is refused', $result['success'], false);
check('...with a message that says how much there is',
    $result['message'], 'Only 70 g of Alpha is recorded, so 80 g can\'t be subtracted. If more is really there, record it with Add first.');
check('...and nothing is written', $entries(), 3);
check('The same goes for Testing and Send external',
    $subtract(70.1, ['reason' => 'Testing', 'companyId' => '1'])['success'], false);
check('A ledger that is already below zero can\'t go further below',
    recordFlowerTransaction($pdo, ['geneticsName' => '2', 'weight' => '1', 'transactionType' => 'Subtract', 'reason' => 'Destroy'])['success'], false);

$result = $subtract(70, ['reason' => 'Testing', 'companyId' => '1']);
check('Subtracting exactly what is on hand works', [$result['success'], $result['onHand']], [true, 0.0]);

$result = recordFlowerTransaction($pdo, ['geneticsName' => '1', 'weight' => '0.1', 'transactionType' => 'Add', 'reason' => 'Harvest']);
recordFlowerTransaction($pdo, ['geneticsName' => '1', 'weight' => '0.2', 'transactionType' => 'Add', 'reason' => 'Harvest']);
check('Adding is never limited', $result['success'], true);
check('Rounding in stored weights never blocks taking the lot (0.1 + 0.2 g)', $subtract(0.3)['success'], true);

// --- Weights are grams to one decimal place -----------------------------------------
// Not a GMP facility, and the Agency doesn't need hundredths of a gram
$pdo->exec("INSERT INTO Flower (genetics_id, weight, transaction_type, transaction_date, reason) VALUES (1, 50, 'Add', '2026-09-03 10:00:00', 'Harvest')");
$result = $subtract(12.25);
check('A weight with 2 decimal places is refused', [$result['success'], $result['message']],
    [false, 'Weights are recorded to one decimal place, like 12.5 g.']);
check('A trailing zero is fine (12.50 g is 12.5 g)', $subtract('12.50')['success'], true);
check('...and whole grams are fine', $subtract('10')['success'], true);

// Older installs could record hundredths. A balance like 27.55 g can still
// be cleared: stock is compared at 0.1 g, the precision weights are entered in.
$pdo->exec("INSERT INTO Flower (genetics_id, weight, transaction_type, transaction_date, reason) VALUES (1, 0.05, 'Add', '2026-09-03 11:00:00', 'Harvest')");
check('An older balance with hundredths can be cleared by rounding to the nearest 0.1 g',
    [flowerOnHand($pdo, 1), $subtract(27.6)['success']], [27.55, true]);

// --- Everything else is checked on the server too -------------------------------
$refused = [
    'An unknown genetics' => ['geneticsName' => '99'],
    'A weight of zero' => ['weight' => '0'],
    'A negative weight' => ['weight' => '-5'],
    'A weight that isn\'t a number' => ['weight' => 'lots'],
    'An unknown transaction type' => ['transactionType' => 'Move'],
    'A reason that doesn\'t go with Add' => ['transactionType' => 'Add', 'reason' => 'Testing'],
    'A reason that doesn\'t go with Subtract' => ['reason' => 'Harvest'],
    'Other with no explanation' => ['reason' => 'Other', 'otherReason' => '   '],
    'Testing with no company' => ['reason' => 'Testing'],
    'Send external to a company that doesn\'t exist' => ['reason' => 'Send external', 'companyId' => '42'],
];
$before = $entries();
foreach ($refused as $story => $input) {
    $result = recordFlowerTransaction($pdo, $input + ['geneticsName' => '1', 'weight' => '0.1', 'transactionType' => 'Subtract', 'reason' => 'Destroy']);
    check("$story is refused", $result['success'], false);
}
check('None of them wrote anything', $entries(), $before);

$pdo->exec("INSERT INTO Flower (genetics_id, weight, transaction_type, transaction_date, reason) VALUES (1, 10, 'Add', '2026-09-04 10:00:00', 'Harvest')");
$result = recordFlowerTransaction($pdo, ['geneticsName' => '1', 'weight' => '2.5', 'transactionType' => 'Subtract', 'reason' => 'Other', 'otherReason' => ' Spilled ']);
check('Other stores the explanation as the reason', [$result['success'],
    $pdo->query("SELECT reason FROM Flower ORDER BY id DESC LIMIT 1")->fetchColumn()], [true, 'Spilled']);
check('A company on a Destroy is ignored rather than stored',
    [$subtract(1, ['companyId' => '1'])['success'], $pdo->query("SELECT company_id FROM Flower ORDER BY id DESC LIMIT 1")->fetchColumn()],
    [true, null]);
@unlink($tmpDb);

// --- The handler uses it -------------------------------------------------------------
$handler = file_get_contents($publicDir . '/record_flower_transaction.php');
check('record_flower_transaction.php saves through recordFlowerTransaction()', strpos($handler, 'recordFlowerTransaction(') !== false, true);
check('A refused entry goes back to the form with what was typed', strpos($handler, '&data=') !== false, true);

if ($failures > 0) {
    echo "[FAIL] Flower stock tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Flower Stock Test Completed Successfully\n";
exit(0);

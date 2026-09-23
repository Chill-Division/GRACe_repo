<?php
/**
 * Regression tests for stock on hand and clear confirmations (1.1.0).
 *
 * Receive plants and Record dry weight now show what's on hand for the
 * chosen genetics while you type (get_stock_on_hand.php), and after saving
 * say exactly what happened ("Added 12 White Widow plants.") instead of
 * "Genetics received successfully".
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/flower_lib.php';
if (file_exists($publicDir . '/receive_lib.php')) {
    require_once $publicDir . '/receive_lib.php';
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

if (!function_exists('receivePlants') || !function_exists('stockByGenetics')) {
    check('receive_lib.php provides receivePlants() and flower_lib.php provides stockByGenetics()', false, true);
    echo "[FAIL] Entry message tests: $failures failure(s)\n";
    exit(1);
}

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_messages_') . '.db';
$pdo = initializeDatabase($tmpDb);
$pdo->exec("INSERT INTO Genetics (name) VALUES ('White Widow'), ('GG4')");
$pdo->exec("INSERT INTO Companies (name, license_number) VALUES ('Buyer Ltd', 'LIC-1'), ('Lab Ltd', 'LIC-2')");
$pdo->exec("INSERT INTO Plants (genetics_id, status, date_created) VALUES
    (1, 'Growing', '2026-09-01 10:00:00'), (1, 'Growing', '2026-09-01 10:00:00'),
    (1, 'Harvested - Drying', '2026-08-01 10:00:00'), (1, 'Sent', '2026-07-01 10:00:00')");

// --- Receiving plants says what happened -----------------------------------------
$result = receivePlants($pdo, ['plantCount' => '12', 'geneticsName' => '1']);
check('Receiving plants names the count and genetics, and the new total growing',
    [$result['success'], $result['message']], [true, 'Added 12 White Widow plants. You now have 14 growing.']);
check('One plant reads naturally',
    receivePlants($pdo, ['plantCount' => '1', 'geneticsName' => '2'])['message'], 'Added 1 GG4 plant. You now have 1 growing.');
check('An unknown genetics is refused', receivePlants($pdo, ['plantCount' => '3', 'geneticsName' => '99'])['success'], false);
check('A missing count is refused', receivePlants($pdo, ['plantCount' => '', 'geneticsName' => '1'])['success'], false);
check('Refusals add nothing', (int) $pdo->query("SELECT COUNT(*) FROM Plants")->fetchColumn(), 17);

// --- Recording flower says what happened ------------------------------------------
$flower = fn($input) => recordFlowerTransaction($pdo, $input + ['geneticsName' => '1'])['message'];
check('A harvest says how much went in and what is on hand now',
    $flower(['weight' => '612.5', 'transactionType' => 'Add', 'reason' => 'Harvest']),
    'Added 612.5 g of White Widow (Harvest). On hand now: 612.5 g.');
check('Testing names the lab',
    $flower(['weight' => '10', 'transactionType' => 'Subtract', 'reason' => 'Testing', 'companyId' => '2']),
    'Subtracted 10 g of White Widow (Testing, Lab Ltd). On hand now: 602.5 g.');
check('Send external names the company',
    $flower(['weight' => '200', 'transactionType' => 'Subtract', 'reason' => 'Send external', 'companyId' => '1']),
    'Subtracted 200 g of White Widow (Send external, Buyer Ltd). On hand now: 402.5 g.');
check('Destroy says so',
    $flower(['weight' => '2.5', 'transactionType' => 'Subtract', 'reason' => 'Destroy']),
    'Subtracted 2.5 g of White Widow (Destroy). On hand now: 400 g.');
check('Other shows the explanation',
    $flower(['weight' => '1000', 'transactionType' => 'Add', 'reason' => 'Other', 'otherReason' => 'Found in the drying room']),
    'Added 1,000 g of White Widow (Found in the drying room). On hand now: 1,400 g.');

// --- Stock on hand for the forms ------------------------------------------------------
check('Stock on hand lists each genetics: growing and drying plants, and grams of flower', stockByGenetics($pdo), [
    1 => ['growing' => 14, 'drying' => 1, 'flower' => 1400.0],
    2 => ['growing' => 1, 'drying' => 0, 'flower' => 0.0],
]);
@unlink($tmpDb);

// --- Wiring ---------------------------------------------------------------------------
$endpoint = source($publicDir, 'get_stock_on_hand.php');
check('get_stock_on_hand.php answers with stockByGenetics() as JSON',
    strpos($endpoint, 'stockByGenetics(') !== false && strpos($endpoint, "header('Content-Type: application/json')") !== false, true);
check('handle_receive_genetics.php saves through receivePlants()',
    strpos(source($publicDir, 'handle_receive_genetics.php'), 'receivePlants(') !== false, true);
check('The old "Genetics received successfully" message is gone',
    strpos(source($publicDir, 'handle_receive_genetics.php') . source($publicDir, 'receive_lib.php'), 'Genetics received successfully') === false, true);
foreach (['receive_genetics.php', 'record_dry_weight.php'] as $page) {
    check("$page has a stock hint under the genetics list", strpos(source($publicDir, $page), 'id="stockHint"') !== false, true);
}
foreach (['js/genetics.js', 'js/transaction_form.js'] as $script) {
    check("$script loads stock on hand", strpos(source($publicDir, $script), "fetch('get_stock_on_hand.php')") !== false, true);
}

if ($failures > 0) {
    echo "[FAIL] Entry message tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Entry Message Test Completed Successfully\n";
exit(0);

<?php
/**
 * Regression tests for saving exactly once (1.1.0).
 *
 * - Receiving plants is all or nothing. It used to insert one plant at a
 *   time, so a failure halfway (disk full, database busy) left part of the
 *   batch in the ledger with an error on screen.
 * - A double tap, or tapping again while a slow connection is still
 *   sending, can't submit a form twice: js/growcart.js ignores further
 *   submits of a form that's already on its way, and the confirm steps
 *   send through submitOnce().
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/receive_lib.php';

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

// --- Receiving plants is all or nothing -------------------------------------------
$tmpDb = tempnam(sys_get_temp_dir(), 'grace_once_') . '.db';
$pdo = initializeDatabase($tmpDb);
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");
// Make the database fail on the third plant, like a full disk would
$pdo->exec("CREATE TRIGGER fail_third_plant BEFORE INSERT ON Plants
            WHEN (SELECT COUNT(*) FROM Plants) >= 2
            BEGIN SELECT RAISE(ABORT, 'simulated failure'); END");

$error = null;
try {
    receivePlants($pdo, ['plantCount' => '5', 'geneticsName' => '1']);
} catch (Exception $e) {
    $error = $e->getMessage();
}
check('A failure partway through is reported', $error !== null && strpos($error, 'simulated failure') !== false, true);
check('...and none of the batch is left behind (it used to keep the first 2)',
    (int) $pdo->query("SELECT COUNT(*) FROM Plants")->fetchColumn(), 0);

$pdo->exec("DROP TRIGGER fail_third_plant");
$result = receivePlants($pdo, ['plantCount' => '5', 'geneticsName' => '1']);
check('With nothing failing, the whole batch goes in', [$result['success'], (int) $pdo->query("SELECT COUNT(*) FROM Plants")->fetchColumn()], [true, 5]);
check('...all with the same time stamp',
    (int) $pdo->query("SELECT COUNT(DISTINCT date_created) FROM Plants")->fetchColumn(), 1);
@unlink($tmpDb);

// --- A form is only ever sent once ---------------------------------------------------
$growcart = source($publicDir, 'js/growcart.js');
check('growcart.js provides submitOnce()', strpos($growcart, 'function submitOnce(') !== false, true);
check('It ignores submits of a form that is already on its way',
    strpos($growcart, 'form.dataset.submitting') !== false && strpos($growcart, "}, true);") !== false, true);
check('Coming back to a page with the Back button makes its forms usable again',
    strpos($growcart, 'event.persisted') !== false, true);
check('Buttons are only disabled after the browser has read the form (named buttons still send their value)',
    strpos($growcart, 'setTimeout(') !== false, true);

foreach (['js/genetics.js', 'js/transaction_form.js', 'generate_shipping_manifest.php'] as $file) {
    $code = source($publicDir, $file);
    check("$file sends through submitOnce() after the confirm step",
        strpos($code, 'submitOnce(') !== false && preg_match('/\bform\.submit\(\)|manifestForm\.submit\(\)/', $code) === 0, true);
}

if ($failures > 0) {
    echo "[FAIL] Double submit tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Double Submit Test Completed Successfully\n";
exit(0);

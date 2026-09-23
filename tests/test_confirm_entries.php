<?php
/**
 * Regression tests for the confirm step on Receive plants and Record dry
 * weight change (1.1.0). The ledger can't be edited afterwards, so like
 * Harvest / Destroy / Send, both forms now show exactly what's about to be
 * recorded and wait for a Confirm before anything is saved.
 *
 * The pop-up is confirmAction() in js/growcart.js. There's no JavaScript
 * test runner in CI, so these checks keep the wiring in place; the manual
 * checklist in TESTING.md covers clicking through it.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';

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

$receive = source($publicDir, 'js/genetics.js');
$flower = source($publicDir, 'js/transaction_form.js');

check('Receive plants asks for confirmation before saving', strpos($receive, 'confirmAction({') !== false, true);
check('Receive plants only submits after Confirm', preg_match('/confirmAction\(\{.*?\}\)\.then\(confirmed => \{\s*if \(!confirmed\) return;\s*form\.submit\(\);/s', $receive), 1);
check('Its summary names the count and genetics', strpos($receive, '× ${geneticsName}') !== false, true);

check('Record dry weight asks for confirmation before saving', strpos($flower, 'confirmAction({') !== false, true);
check('Record dry weight only submits after Confirm', preg_match('/confirmAction\(\{.*?\}\)\.then\(confirmed => \{\s*if \(!confirmed\) return;\s*form\.submit\(\);/s', $flower), 1);
check('Its summary shows what on hand will be afterwards', strpos($flower, 'On hand after') !== false, true);
check('Destroying flower gets the red confirm button', strpos($flower, "danger: reason === 'Destroy'") !== false, true);

check('Both remind you the ledger can\'t be edited afterwards',
    substr_count($receive . $flower, "can't be edited afterwards") >= 2, true);

if ($failures > 0) {
    echo "[FAIL] Confirm entry tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Confirm Entry Test Completed Successfully\n";
exit(0);

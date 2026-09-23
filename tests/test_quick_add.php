<?php
/**
 * Regression tests for adding things without leaving the page (1.1.0):
 * "+ Add new genetics…" at the bottom of the genetics list on Receive plants
 * and Record dry weight. js/quick_add.js opens a small pop-up form, posts it
 * to a JSON endpoint, then adds the new entry to the list and selects it, so
 * nothing already typed on the page is lost.
 *
 * The saving itself is addGenetics(), covered by test_genetics_duplicates.php.
 * These checks make sure the pieces stay wired together.
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

// --- The shared pop-up -----------------------------------------------------------
$js = source($publicDir, 'js/quick_add.js');
check('js/quick_add.js provides enableQuickAdd()', strpos($js, 'function enableQuickAdd(') !== false, true);
check('...and a ready-made setup for genetics', strpos($js, 'function enableQuickAddGenetics(') !== false, true);
check('It posts to a relative URL (GRACe runs under a Home Assistant ingress path)',
    preg_match("/endpoint:\\s*'\\//", $js), 0);
check('Anything shown in the pop-up is escaped', strpos($js, 'escapeHtml(') !== false, true);
check('Cancelling puts the list back to what was selected before', strpos($js, 'previous') !== false, true);

// --- The endpoint ------------------------------------------------------------------
$endpoint = source($publicDir, 'handle_quick_add_genetics.php');
check('handle_quick_add_genetics.php exists', $endpoint !== '', true);
check('It saves through addGenetics(), so near-duplicates are caught',
    strpos($endpoint, "require_once 'genetics_lib.php'") !== false && strpos($endpoint, 'addGenetics(') !== false, true);
check('It answers in JSON', strpos($endpoint, "header('Content-Type: application/json')") !== false, true);
check('It only accepts POST', strpos($endpoint, "REQUEST_METHOD'] !== 'POST'") !== false, true);

// --- The pages use it ----------------------------------------------------------------
foreach (['receive_genetics.php' => 'js/genetics.js', 'record_dry_weight.php' => 'js/transaction_form.js'] as $page => $script) {
    check("$page loads js/quick_add.js", strpos(source($publicDir, $page), 'src="js/quick_add.js') !== false, true);
    check("$script adds \"+ Add new genetics\" to the genetics list once it has loaded",
        strpos(source($publicDir, $script), 'enableQuickAddGenetics(geneticsDropdown)') !== false, true);
}

if ($failures > 0) {
    echo "[FAIL] Quick add tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Quick Add Test Completed Successfully\n";
exit(0);

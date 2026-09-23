<?php
/**
 * Regression tests for adding genetics (1.1.0),
 * addGenetics() in grace_addon/files/general/www/public/genetics_lib.php.
 *
 * The bug: the duplicate check was an exact match, so "white widow " or
 * "WHITE WIDOW" went in as a second genetics next to "White Widow", and the
 * plants and flower recorded against each were split across two names in
 * every report.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
if (file_exists($publicDir . '/genetics_lib.php')) {
    require_once $publicDir . '/genetics_lib.php';
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

if (!function_exists('addGenetics')) {
    check('genetics_lib.php provides addGenetics()', false, true);
    echo "[FAIL] Genetics duplicate tests: $failures failure(s)\n";
    exit(1);
}

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_genetics_') . '.db';
$pdo = initializeDatabase($tmpDb);
$names = fn() => $pdo->query("SELECT name FROM Genetics ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);

// --- Adding ---------------------------------------------------------------------
$result = addGenetics($pdo, 'White Widow');
check('A new genetics is added', [$result['success'], $result['message'], $result['name']],
    [true, 'Added White Widow.', 'White Widow']);
$whiteWidowId = $result['id'];

$result = addGenetics($pdo, '  Blue   Dream ');
check('Extra spaces are tidied before saving', [$result['success'], $result['name']], [true, 'Blue Dream']);

// --- Duplicates that differ only by capitals or spaces are caught ----------------
foreach (['White Widow', 'white widow', '  WHITE WIDOW ', 'White  Widow', 'WhiteWidow', "White\tWidow"] as $typed) {
    $result = addGenetics($pdo, $typed);
    check('"' . addcslashes($typed, "\t") . '" is recognised as White Widow',
        [$result['success'], $result['duplicate'], $result['id'], $result['name']],
        [false, true, $whiteWidowId, 'White Widow']);
}
check('The duplicate message names the existing genetics',
    addGenetics($pdo, 'white widow')['message'], 'White Widow is already in your genetics list.');

check('A genuinely different name is still allowed', addGenetics($pdo, 'White Widow Auto')['success'], true);
check('Accented letters are compared without caring about capitals',
    [addGenetics($pdo, 'Ébène Kush')['success'], addGenetics($pdo, 'ébène kush')['duplicate']], [true, true]);
check('Only one of each ended up in the list',
    $names(), ['White Widow', 'Blue Dream', 'White Widow Auto', 'Ébène Kush']);

// --- Bad input ------------------------------------------------------------------------
check('A blank name is refused', addGenetics($pdo, "   \t ")['message'], 'Please enter a genetics name.');
check('A very long name is refused (almost certainly a paste gone wrong)',
    addGenetics($pdo, str_repeat('x', 101))['message'], 'Genetics names can be at most 100 characters.');
check('Nothing extra was added', count($names()), 4);
@unlink($tmpDb);

// --- The Add New Genetics page uses it --------------------------------------------
$handler = file_get_contents($publicDir . '/handle_add_new_genetics.php');
check('handle_add_new_genetics.php adds through addGenetics()', strpos($handler, 'addGenetics(') !== false, true);
check('The exact-match duplicate query is gone', strpos($handler, 'WHERE name = ?') === false, true);

if ($failures > 0) {
    echo "[FAIL] Genetics duplicate tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Genetics Duplicate Test Completed Successfully\n";
exit(0);

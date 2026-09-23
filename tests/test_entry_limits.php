<?php
/**
 * Regression tests for entry limits (1.1.0).
 *
 * - Plant counts are whole numbers only: receiving plants and plant
 *   shipping manifests refuse 2.5 plants (receiving used to turn "2.5" into
 *   3 plants). One entry can add at most 1,000 plants, so a runaway typo
 *   can't flood the ledger: NZ cultivators are small, and even the largest
 *   spreads bigger intakes over several days and people.
 * - Flower manifests are grams to one decimal place, like every weight.
 * - A large entry gets an extra "are you sure?" in the confirm step. The
 *   thresholds are set in Administration → Entry warning limits (default
 *   100 plants and 5,000 g, recommended: about half of what one flower room
 *   usually holds) and stored in the Settings table.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/receive_lib.php';
require_once $publicDir . '/manifest_lib.php';
if (file_exists($publicDir . '/settings_lib.php')) {
    require_once $publicDir . '/settings_lib.php';
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

if (!function_exists('getEntryWarningLimits') || !function_exists('saveEntryWarningLimits')) {
    check('settings_lib.php provides getEntryWarningLimits() and saveEntryWarningLimits()', false, true);
    echo "[FAIL] Entry limit tests: $failures failure(s)\n";
    exit(1);
}

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_limits_') . '.db';
$pdo = initializeDatabase($tmpDb);
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");

// --- The warning limits ------------------------------------------------------------
check('Out of the box the limits are 100 plants and 5,000 g', getEntryWarningLimits($pdo), ['plants' => 100, 'grams' => 5000]);

$result = saveEntryWarningLimits($pdo, ['largePlantEntry' => '250', 'largeFlowerEntryGrams' => '12000']);
check('New limits can be saved', [$result['success'], $result['message']], [true, 'Entry warning limits saved.']);
check('...and are used from then on', getEntryWarningLimits($pdo), ['plants' => 250, 'grams' => 12000]);

$bad = [
    'zero plants' => ['largePlantEntry' => '0'],
    'part of a plant' => ['largePlantEntry' => '2.5'],
    'text' => ['largeFlowerEntryGrams' => 'lots'],
    'a negative weight' => ['largeFlowerEntryGrams' => '-5'],
    'more plants than one entry can add (1,000)' => ['largePlantEntry' => '1001'],
    'an absurd weight' => ['largeFlowerEntryGrams' => '1000001'],
];
foreach ($bad as $story => $input) {
    check("A limit of $story is refused",
        saveEntryWarningLimits($pdo, $input + ['largePlantEntry' => '250', 'largeFlowerEntryGrams' => '12000'])['success'], false);
}
check('Refused limits change nothing', getEntryWarningLimits($pdo), ['plants' => 250, 'grams' => 12000]);
check('A plant limit above the per-entry cap says why',
    saveEntryWarningLimits($pdo, ['largePlantEntry' => '1001', 'largeFlowerEntryGrams' => '12000'])['message'],
    'Enter the plant limit as a whole number of plants, from 1 to 1,000.');
check('A plant limit right at the cap is fine',
    saveEntryWarningLimits($pdo, ['largePlantEntry' => '1000', 'largeFlowerEntryGrams' => '12000'])['success'], true);
saveEntryWarningLimits($pdo, ['largePlantEntry' => '250', 'largeFlowerEntryGrams' => '12000']);

// --- Plant counts are whole numbers -------------------------------------------------
$receive = fn($count) => receivePlants($pdo, ['plantCount' => $count, 'geneticsName' => '1']);
$plants = fn() => (int) $pdo->query("SELECT COUNT(*) FROM Plants")->fetchColumn();
foreach (['2.5', '1e3', '12 plants', '-3', '0', ''] as $count) {
    check("Receiving \"$count\" plants is refused", $receive($count)['success'], false);
}
check('A part plant gets a clear message', $receive('2.5')['message'], 'Please enter a whole number of plants (1 or more).');
check('Nothing was added by any of those', $plants(), 0);
check('A whole number with stray spaces is fine', [$receive(' 12 ')['success'], $plants()], [true, 12]);
check('One entry can add 1,000 plants', $receive('1000')['success'], true);
check('...but not more, so a runaway typo can\'t flood the ledger', $receive('1001')['message'],
    'One entry can add at most 1,000 plants. Split it up if you really received more.');
check('A typo like 10000 adds nothing', [$receive('10000')['success'], $plants()], [false, 1012]);
@unlink($tmpDb);

// --- Plant manifests are whole numbers too -------------------------------------------
check('A plant manifest for 2.5 plants is refused',
    validateManifestQuantity('plant', '2.5'), 'Plants are counted in whole numbers.');
check('A plant manifest for 5 plants is fine', validateManifestQuantity('plant', '5'), null);
check('A flower manifest can be 12.5 g', validateManifestQuantity('flower', '12.5'), null);
check('...but not 12.25 g: weights are grams to one decimal place',
    validateManifestQuantity('flower', '12.25'), 'Weights are recorded to one decimal place, like 12.5 g.');
check('The manifest form weighs flower in steps of 0.1 g',
    strpos(source($publicDir, 'generate_shipping_manifest.php'), "quantityInput.step = wholePlants ? '1' : '0.1'") !== false, true);
check('A manifest for nothing is refused', validateManifestQuantity('flower', '0'), 'Please enter a quantity above 0.');

// --- Wiring ----------------------------------------------------------------------------
check('The Settings table is created with the rest', strpos(source($publicDir, 'init_db.php'), 'CREATE TABLE IF NOT EXISTS Settings') !== false, true);
check('Administration links to the entry warning limits', strpos(source($publicDir, 'administration.php'), 'href="settings.php"') !== false, true);
$settingsPage = source($publicDir, 'settings.php');
check('The settings page saves through saveEntryWarningLimits()', strpos($settingsPage, 'saveEntryWarningLimits(') !== false, true);
check('...and recommends about half a flower room', stripos($settingsPage, 'half of what one of your flower rooms usually holds') !== false, true);
foreach (['receive_genetics.php' => 'data-large-plants', 'record_dry_weight.php' => 'data-large-grams', 'generate_shipping_manifest.php' => 'data-large-plants'] as $page => $attr) {
    check("$page hands the limit to the page ($attr)", strpos(source($publicDir, $page), $attr . '="<?php echo') !== false, true);
}
check('The confirm pop-up can ask for an extra tick on large entries',
    strpos(source($publicDir, 'js/growcart.js'), 'opts.warning') !== false, true);
check('process_shipping_manifest.php checks the quantity with validateManifestQuantity()',
    strpos(source($publicDir, 'process_shipping_manifest.php'), 'validateManifestQuantity(') !== false, true);

if ($failures > 0) {
    echo "[FAIL] Entry limit tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Entry Limit Test Completed Successfully\n";
exit(0);

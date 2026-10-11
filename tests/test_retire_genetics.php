<?php
/**
 * Regression tests for retiring genetics (1.3.0): Administration → Retire
 * genetics, and retireGenetics() and friends in
 * grace_addon/files/general/www/public/genetics_lib.php.
 *
 * What must hold:
 * - Existing databases get the retired_at column in place on their first
 *   page load after the update, with every genetics still in use and every
 *   plant and flower entry still linked to it. Running it again changes
 *   nothing.
 * - A genetics can only be retired with nothing of it left on site: no
 *   plants growing or drying and no flower (a balance within 0.05 g of zero
 *   counts as none). History alone, or a shipment already sent, doesn't
 *   stop it, even while the manifest is In Progress.
 * - Retired genetics are left out of the lists for new entries (receiving
 *   plants, recording flower, making a manifest), and those entries refuse
 *   one even when the form was opened before it was retired.
 * - Reports never change: the annual stocktake, Current Plants, the monthly
 *   materials-out report, recent entries and stock on hand are identical
 *   before and after retiring.
 * - "Bring back" undoes it, and adding a retired genetics' name again brings
 *   it back instead of making a second one.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/genetics_lib.php';
require_once $publicDir . '/receive_lib.php';
require_once $publicDir . '/flower_lib.php';
require_once $publicDir . '/annual_stocktake_lib.php';
require_once $publicDir . '/current_plants_lib.php';
require_once $publicDir . '/materials_out_lib.php';
require_once $publicDir . '/recent_lib.php';

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

foreach (['retireGenetics', 'unretireGenetics', 'activeGenetics', 'geneticsForNewEntry', 'geneticsOnHand', 'describeOnHand'] as $function) {
    if (!function_exists($function)) {
        check("genetics_lib.php provides $function()", false, true);
    }
}
if ($failures > 0) {
    echo "[FAIL] Retire genetics tests: $failures failure(s)\n";
    exit(1);
}

function columnsOf(PDO $pdo, $table)
{
    return implode(',', array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name'));
}

/** Rows as 'a|b;c|d' text. */
function rowsAsText(PDO $pdo, $sql)
{
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_NUM);
    return implode(';', array_map(fn($r) => implode('|', array_map(fn($v) => $v === null ? '' : (string) $v, $r)), $rows));
}

function scalar(PDO $pdo, $sql, array $params = [])
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchColumn();
}

function addPlants(PDO $pdo, $geneticsId, $status, $count = 1, $companyId = null)
{
    $harvested = $status === 'Growing' ? null : '2026-04-01 10:00:00';
    $stmt = $pdo->prepare("INSERT INTO Plants (genetics_id, status, date_created, date_harvested, company_id) VALUES (?, ?, '2026-03-01 10:00:00', ?, ?)");
    for ($i = 0; $i < $count; $i++) {
        $stmt->execute([$geneticsId, $status, $harvested, $companyId]);
    }
}

function addFlower(PDO $pdo, $geneticsId, $weight, $reason, $companyId = null, $date = '2026-04-10 10:00:00')
{
    $pdo->prepare("INSERT INTO Flower (genetics_id, weight, transaction_type, transaction_date, reason, company_id) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$geneticsId, $weight, $weight < 0 ? 'Subtract' : 'Add', $date, $reason, $companyId]);
    return (int) $pdo->lastInsertId();
}

function isRetired(PDO $pdo, $id)
{
    return scalar($pdo, "SELECT retired_at FROM Genetics WHERE id = ?", [$id]) !== null;
}

// ---------------------------------------------------------------------------
// Upgrading a 1.2 database: Genetics was just id and name
// ---------------------------------------------------------------------------
$oldDb = tempnam(sys_get_temp_dir(), 'grace_retire_old_') . '.db';
$old = initializeDatabase($oldDb);
performMigrations($old);
$old->exec("ALTER TABLE Genetics DROP COLUMN retired_at"); // back to exactly how 1.2 left it
check('Before upgrade: Genetics is just id and name', columnsOf($old, 'Genetics'), 'id,name');
$old->exec("INSERT INTO Companies (id, name, license_number) VALUES (1, 'Test Lab', 'LAB-1')");
$old->exec("INSERT INTO Genetics (id, name) VALUES (1, 'Alpha'), (2, 'Bravo'), (4, 'Delta')");
addPlants($old, 1, 'Growing', 2);
addPlants($old, 2, 'Sent', 1, 1);
addPlants($old, 4, 'Harvested - Drying');
addFlower($old, 1, 500.0, 'Harvest');
addFlower($old, 1, -100.0, 'Testing', 1);
$genBefore = rowsAsText($old, "SELECT id, name FROM Genetics ORDER BY id");
$plantLinksBefore = rowsAsText($old, "SELECT id, genetics_id, company_id FROM Plants ORDER BY id");
$flowerLinksBefore = rowsAsText($old, "SELECT id, genetics_id, company_id FROM Flower ORDER BY id");
unset($old);

// Upgrade exactly as the app does on its first page load after updating
$pdo = initializeDatabase($oldDb);
performMigrations($pdo);
check('Upgrade: Genetics gains retired_at, added in place', columnsOf($pdo, 'Genetics'), 'id,name,retired_at');
check('Upgrade: every genetics keeps its id and name', rowsAsText($pdo, "SELECT id, name FROM Genetics ORDER BY id"), $genBefore);
check('Upgrade: every genetics starts in use', (int) scalar($pdo, "SELECT COUNT(*) FROM Genetics WHERE retired_at IS NOT NULL"), 0);
check('Upgrade: every plant still points at the same genetics and company', rowsAsText($pdo, "SELECT id, genetics_id, company_id FROM Plants ORDER BY id"), $plantLinksBefore);
check('Upgrade: every flower entry still points at the same genetics and company', rowsAsText($pdo, "SELECT id, genetics_id, company_id FROM Flower ORDER BY id"), $flowerLinksBefore);
check('Upgrade: no foreign key problems', count($pdo->query("PRAGMA foreign_key_check")->fetchAll()), 0);
check('Upgrade: the upgraded genetics show in the lists for new entries', array_column(activeGenetics($pdo), 'name'), ['Alpha', 'Bravo', 'Delta']);

// It runs on every page load, so a second run must change nothing
performMigrations($pdo);
check('Second run: Genetics columns are unchanged', columnsOf($pdo, 'Genetics'), 'id,name,retired_at');
check('Second run: genetics are unchanged', rowsAsText($pdo, "SELECT id, name, retired_at FROM Genetics ORDER BY id"), '1|Alpha|;2|Bravo|;4|Delta|');
unset($pdo);
@unlink($oldDb);

// ---------------------------------------------------------------------------
// A site with genetics in every state
// ---------------------------------------------------------------------------
$db = tempnam(sys_get_temp_dir(), 'grace_retire_') . '.db';
$pdo = initializeDatabase($db);
performMigrations($pdo);
check('Fresh install: Genetics has the retired_at column', columnsOf($pdo, 'Genetics'), 'id,name,retired_at');

$pdo->exec("INSERT INTO Companies (id, name, license_number) VALUES (1, 'Test Lab', 'LAB-1'), (2, 'Joe''s Farm', 'MCA-2')");
$id = [];
foreach (['Growing Two', 'One Growing', 'Drying One', 'Flower Only', 'Everything', 'Just Over', 'Negative',
          'History Only', 'Hundredths', 'Half Up', 'Half Down', 'Shipped', 'Never Used'] as $name) {
    $id[$name] = (int) addGenetics($pdo, $name)['id'];
}

addPlants($pdo, $id['Growing Two'], 'Growing', 2);
addPlants($pdo, $id['Growing Two'], 'Sent', 1, 2);
addPlants($pdo, $id['One Growing'], 'Growing');
addPlants($pdo, $id['Drying One'], 'Harvested - Drying');
addFlower($pdo, $id['Flower Only'], 12.5, 'Harvest');
addPlants($pdo, $id['Everything'], 'Growing', 2);
addPlants($pdo, $id['Everything'], 'Harvested - Drying');
addFlower($pdo, $id['Everything'], 1234.5, 'Harvest');
addFlower($pdo, $id['Just Over'], 10.06, 'Harvest');      // hundredths from an older version
addFlower($pdo, $id['Just Over'], -10.0, 'Testing', 1);
addFlower($pdo, $id['Negative'], -3.0, 'Destroy');        // from before GRACe refused to go below zero

// Nothing left on site, only history
addPlants($pdo, $id['History Only'], 'Sent', 1, 2);
addPlants($pdo, $id['History Only'], 'Harvested - Destroyed');
addPlants($pdo, $id['History Only'], 'Destroyed');
addPlants($pdo, $id['History Only'], 'Harvested');        // the old status, before Destroyed and Drying
addFlower($pdo, $id['History Only'], 100.0, 'Harvest', null, '2026-03-05 10:00:00');
addFlower($pdo, $id['History Only'], -60.0, 'Send external', 2, '2026-03-20 10:00:00');
addFlower($pdo, $id['History Only'], -40.0, 'Testing', 1, '2026-04-02 10:00:00');
addFlower($pdo, $id['Hundredths'], 10.04, 'Harvest');     // 0.04 g left, which rounds to 0.0 g
addFlower($pdo, $id['Hundredths'], -10.0, 'Testing', 1);
addFlower($pdo, $id['Half Up'], 10.05, 'Harvest');        // 0.05 g left
addFlower($pdo, $id['Half Up'], -10.0, 'Testing', 1);
addFlower($pdo, $id['Half Down'], 12.45, 'Harvest');      // cleared with 12.5 g, the nearest 0.1 g
addFlower($pdo, $id['Half Down'], -12.5, 'Testing', 1);

// Sent with manifests that are still In Progress (Chain of Custody not back yet)
addFlower($pdo, $id['Shipped'], 100.0, 'Harvest');
$deduction = addFlower($pdo, $id['Shipped'], -100.0, 'Send external', 2, '2026-04-20 10:00:00');
addPlants($pdo, $id['Shipped'], 'Sent', 2, 2);
$manifest = $pdo->prepare("INSERT INTO ShippingManifests (recipient_id, shipment_date, product_type, status, sending_company_name,
                               receiving_company_name, genetics_id, genetics_name, quantity, flower_transaction_id)
                           VALUES (2, '2026-04-20 10:00:00', ?, 'In Progress', 'Us', 'Joe''s Farm', ?, 'Shipped', ?, ?)");
$manifest->execute(['flower', $id['Shipped'], 100, $deduction]);
$manifest->execute(['plant', $id['Shipped'], 2, null]);

// --- What's on site, in words --------------------------------------------------------
$onSite = [];
foreach ($id as $name => $geneticsId) {
    $onSite[$name] = describeOnHand(geneticsOnHand($pdo, $geneticsId));
}
check('On site: plants growing', $onSite['Growing Two'], '2 plants growing');
check('On site: one plant growing', $onSite['One Growing'], '1 plant growing');
check('On site: a plant drying', $onSite['Drying One'], '1 plant drying');
check('On site: flower', $onSite['Flower Only'], '12.5 g of flower');
check('On site: plants growing and drying, and flower', $onSite['Everything'], '2 plants growing, 1 drying and 1,234.5 g of flower');
check('On site: 0.06 g left over from an older version shows as 0.1 g', $onSite['Just Over'], '0.1 g of flower');
check('On site: a negative flower balance', $onSite['Negative'], 'a flower balance of -3 g');
check('On site: nothing when only history is left (sent, harvested, destroyed, flower used up)', $onSite['History Only'], null);
check('On site: nothing when 0.04 g is left over from an older version', $onSite['Hundredths'], null);
check('On site: nothing when 0.05 g is left over', $onSite['Half Up'], null);
check('On site: nothing when a balance was cleared to -0.05 g', $onSite['Half Down'], null);
check('On site: nothing once it has all been sent, though the manifests are still In Progress', $onSite['Shipped'], null);
check('On site: nothing for a genetics that was never used', $onSite['Never Used'], null);
check('Stock on hand behind it', geneticsOnHand($pdo, $id['Everything']), ['growing' => 2, 'drying' => 1, 'flower' => 1234.5]);

// Reports as they are before anything is retired
$reports = function () use ($pdo) {
    return json_encode([
        'plantStocktake' => computeAnnualPlantStocktake($pdo, 2026),
        'flowerStocktake' => computeAnnualFlowerStocktake($pdo, 2026),
        'currentPlants' => currentPlantCounts($pdo),
        'materialsOut' => [getMaterialsOut($pdo, reportMonth('2026-03')), getMaterialsOut($pdo, reportMonth('2026-04'))],
        'recent' => [recentReceipts($pdo), recentPlantMoves($pdo), recentFlowerEntries($pdo)],
        'stock' => stockByGenetics($pdo),
    ]);
};
$reportsBefore = $reports();

// --- Anything still on site stops it ---------------------------------------------------
$refused = [
    'Growing Two' => "Growing Two still has 2 plants growing, so it can't be retired yet.",
    'One Growing' => "One Growing still has 1 plant growing, so it can't be retired yet.",
    'Drying One' => "Drying One still has 1 plant drying, so it can't be retired yet.",
    'Flower Only' => "Flower Only still has 12.5 g of flower, so it can't be retired yet.",
    'Everything' => "Everything still has 2 plants growing, 1 drying and 1,234.5 g of flower, so it can't be retired yet.",
    'Just Over' => "Just Over still has 0.1 g of flower, so it can't be retired yet.",
    'Negative' => "Negative still has a flower balance of -3 g, so it can't be retired yet.",
];
foreach ($refused as $name => $message) {
    $result = retireGenetics($pdo, $id[$name]);
    check("Can't retire $name: it says what's still on site", [$result['success'], $result['message']], [false, $message]);
    check("$name stays in use", isRetired($pdo, $id[$name]), false);
}

// --- Nothing on site: it retires --------------------------------------------------------
$retirable = ['History Only', 'Hundredths', 'Half Up', 'Half Down', 'Shipped', 'Never Used'];
foreach ($retirable as $name) {
    $result = retireGenetics($pdo, $id[$name]);
    check("Retires $name", [$result['success'], $result['message']],
        [true, "Retired $name. It won't show when adding plants, recording flower or making a manifest."]);
}
$retiredAt = scalar($pdo, "SELECT retired_at FROM Genetics WHERE id = ?", [$id['Never Used']]);
check('The retired date is stored like every other GRACe time', preg_match('/^\d{4}-\d\d-\d\d \d\d:\d\d:\d\d$/D', (string) $retiredAt), 1);
check('The retired date is NZ time (not UTC)', abs(strtotime($retiredAt) - strtotime(ledgerTimestamp())) < 300, true);

$result = retireGenetics($pdo, $id['Never Used']);
check('Retiring it again changes nothing', [$result['success'], $result['message']], [true, 'Never Used is already retired.']);
check('... and keeps the date it was first retired', scalar($pdo, "SELECT retired_at FROM Genetics WHERE id = ?", [$id['Never Used']]), $retiredAt);
check('A genetics that no longer exists is reported, not retired', retireGenetics($pdo, 9999),
    ['success' => false, 'message' => 'That genetics could not be found. Reload the page and try again.']);

// --- Hidden from new entries ---------------------------------------------------------
check('The lists for new entries leave out retired genetics, alphabetically',
    array_column(activeGenetics($pdo), 'name'),
    ['Drying One', 'Everything', 'Flower Only', 'Growing Two', 'Just Over', 'Negative', 'One Growing']);
check('A genetics in use can take a new entry', geneticsForNewEntry($pdo, $id['Flower Only']),
    ['genetics' => ['id' => $id['Flower Only'], 'name' => 'Flower Only'], 'error' => null]);
check('A retired one is refused with how to bring it back', geneticsForNewEntry($pdo, $id['Never Used']),
    ['genetics' => null, 'error' => 'Never Used is retired. Bring it back in Administration → Retire genetics first.']);
check('A missing one asks for a genetics', geneticsForNewEntry($pdo, 9999)['error'], 'Please choose a genetics.');

// --- Reports never change ------------------------------------------------------------
check('Reports are identical after retiring (stocktake, current plants, materials out, recent entries, stock)', $reports(), $reportsBefore);
$stocktakeNames = array_column(computeAnnualPlantStocktake($pdo, 2026), 'geneticsName');
check('The annual stocktake still lists every retired genetics', array_values(array_diff($retirable, $stocktakeNames)), []);
$historyRow = array_values(array_filter(computeAnnualFlowerStocktake($pdo, 2026), fn($row) => $row['geneticsName'] === 'History Only'))[0] ?? null;
check("A retired genetics' flower history still adds up in the stocktake", $historyRow ? (float) $historyRow['end'] : null, 0.0);
$march = getMaterialsOut($pdo, reportMonth('2026-03'));
check('The monthly report still shows what a retired genetics sent', in_array('History Only', array_column($march['flowers'], 'geneticsName'), true), true);

// --- Bring back -------------------------------------------------------------------------
$result = unretireGenetics($pdo, $id['Never Used']);
check('Bring back', [$result['success'], $result['message']], [true, 'Brought back Never Used. It shows in your lists again.']);
check('... it is in use again', isRetired($pdo, $id['Never Used']), false);
check('... and back in the lists for new entries', in_array('Never Used', array_column(activeGenetics($pdo), 'name'), true), true);
check('Bringing back one in use changes nothing', unretireGenetics($pdo, $id['Never Used'])['message'], 'Never Used is already in your lists.');
check('Bringing back one that no longer exists is reported', unretireGenetics($pdo, 9999)['success'], false);
check('Reports are still identical after bringing one back', $reports(), $reportsBefore);

// --- Adding a retired genetics' name again brings it back -----------------------------------
$count = (int) scalar($pdo, "SELECT COUNT(*) FROM Genetics");
$result = addGenetics($pdo, '  history   ONLY ');
check('Adding a retired name again (any capitals or spaces) brings that genetics back',
    [$result['success'], $result['duplicate'], $result['id'], $result['name'], $result['message']],
    [true, false, $id['History Only'], 'History Only', "History Only was retired, so it's been brought back into your lists."]);
check('... it is in use again', isRetired($pdo, $id['History Only']), false);
check('... and no second genetics was made', (int) scalar($pdo, "SELECT COUNT(*) FROM Genetics"), $count);
check('Adding it once more is the usual duplicate', [addGenetics($pdo, 'History Only')['duplicate'], addGenetics($pdo, 'History Only')['success']], [true, false]);

// --- Entries refuse a retired genetics, even from a form opened before it was retired --------
$retired = $id['Hundredths'];
$plantsBefore = (int) scalar($pdo, "SELECT COUNT(*) FROM Plants");
$flowerBefore = (int) scalar($pdo, "SELECT COUNT(*) FROM Flower");
$retiredMessage = 'Hundredths is retired. Bring it back in Administration → Retire genetics first.';
$result = receivePlants($pdo, ['geneticsName' => (string) $retired, 'plantCount' => '3']);
check('Receive plants refuses a retired genetics', [$result['success'], $result['message']], [false, $retiredMessage]);
$result = recordFlowerTransaction($pdo, ['geneticsName' => (string) $retired, 'weight' => '5', 'transactionType' => 'Add', 'reason' => 'Harvest']);
check('Recording flower refuses a retired genetics', [$result['success'], $result['message']], [false, $retiredMessage]);
check('... and nothing was recorded', [(int) scalar($pdo, "SELECT COUNT(*) FROM Plants"), (int) scalar($pdo, "SELECT COUNT(*) FROM Flower")], [$plantsBefore, $flowerBefore]);
check('Genetics in use still take entries', receivePlants($pdo, ['geneticsName' => (string) $id['Never Used'], 'plantCount' => '2'])['success'], true);
check('... and then have something on site, so they cannot be retired', retireGenetics($pdo, $id['Never Used'])['success'], false);
unset($pdo);
@unlink($db);

// ---------------------------------------------------------------------------
// The pages
// ---------------------------------------------------------------------------
$source = fn($file) => (string) file_get_contents($publicDir . '/' . $file);

foreach (['get_genetics.php' => 'Receive plants and Record dry weight change', 'generate_shipping_manifest.php' => 'Generate Shipping Manifest'] as $file => $form) {
    check("$form lists genetics through activeGenetics()", strpos($source($file), 'activeGenetics($pdo)') !== false, true);
    check("$form has no genetics query of its own", strpos($source($file), 'FROM Genetics') === false, true);
}
check('Generating a manifest checks the genetics with geneticsForNewEntry()', strpos($source('process_shipping_manifest.php'), 'geneticsForNewEntry(') !== false, true);
check("... instead of its own query, which didn't know about retiring", strpos($source('process_shipping_manifest.php'), 'FROM Genetics WHERE id') === false, true);

// Only these files know about retired genetics, so no report can leave them out
$knowsRetired = [];
$listsActive = [];
foreach (glob($publicDir . '/*.php') as $file) {
    if (strpos(file_get_contents($file), 'retired_at') !== false) {
        $knowsRetired[] = basename($file);
    }
    if (strpos(file_get_contents($file), 'activeGenetics(') !== false) {
        $listsActive[] = basename($file);
    }
}
sort($knowsRetired);
sort($listsActive);
check('Only the genetics library, the database setup and the Retire genetics page use retired_at (reports never do)',
    $knowsRetired, ['genetics_lib.php', 'init_db.php', 'retire_genetics.php']);
check('Only the forms for new entries use the in-use list', $listsActive, ['generate_shipping_manifest.php', 'genetics_lib.php', 'get_genetics.php']);

$page = $source('retire_genetics.php');
check('Retire genetics page: retires through retireGenetics()', strpos($page, 'retireGenetics($pdo, $id)') !== false, true);
check('Retire genetics page: brings back through unretireGenetics()', strpos($page, 'unretireGenetics($pdo, $id)') !== false, true);
check('Retire genetics page: asks before retiring', strpos($page, 'confirmAction(') !== false && strpos($page, 'submitOnce(form)') !== false, true);
check('Retire genetics page: goes back to the page after saving, so a refresh does nothing',
    strpos($page, "header('Location: retire_genetics.php?") !== false, true);
check('Retire genetics page: names are escaped', substr_count($page, "htmlspecialchars(\$genetics['name'])") >= 3, true);
check('Retire genetics page: the button is greyed out while something is on site', strpos($page, "' disabled'") !== false, true);
check('Administration links to Retire genetics under Genetics Management',
    (bool) preg_match('/Genetics Management.*href="retire_genetics\.php"/s', $source('administration.php')), true);
check('The menu highlights Administration on Retire genetics', strpos($source('nav.php'), "'retire_genetics'") !== false, true);
check('The user guide explains retiring genetics', strpos((string) file_get_contents(__DIR__ . '/../grace_addon/DOCS.md'), '# Retiring genetics') !== false, true);

if ($failures > 0) {
    echo "[FAIL] Retire genetics tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Retire Genetics Test Completed Successfully\n";
exit(0);

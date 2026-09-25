<?php
/**
 * Regression tests for the Current Plants report
 * (grace_addon/files/general/www/public/current_plants_lib.php).
 *
 * Drying plants are still plants on site until they're sent or destroyed.
 * The report used to count only growing plants, so a crop that had been cut
 * and was drying disappeared from it, while the annual stocktake (rightly)
 * still counted it.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/current_plants_lib.php';
require_once $publicDir . '/annual_stocktake_lib.php';

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_current_plants_') . '.db';
$pdo = initializeDatabase($tmpDb);

$failures = 0;
function check($label, $actual, $expected)
{
    global $failures;
    if ($actual === $expected) {
        echo "[PASS] $label\n";
    } else {
        echo "[FAIL] $label, expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        $failures++;
    }
}

// Fixed year so the stocktake comparison is deterministic
$year = 2026;

// Added out of alphabetical order, so the report's sorting is checked too
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Charlie'), ('Alpha'), ('Bravo')");
$ids = $pdo->query("SELECT name, id FROM Genetics")->fetchAll(PDO::FETCH_KEY_PAIR);

$insert = $pdo->prepare("INSERT INTO Plants (genetics_id, status, date_created, date_harvested) VALUES (?, ?, ?, ?)");
$addPlants = function ($genetics, $status, $count) use ($insert, $ids, $year) {
    for ($i = 0; $i < $count; $i++) {
        $insert->execute([
            $ids[$genetics],
            $status,
            "$year-03-01 10:00:00",
            $status === 'Growing' ? null : "$year-05-01 10:00:00",
        ]);
    }
};

// Alpha: some of everything
$addPlants('Alpha', 'Growing', 3);
$addPlants('Alpha', 'Harvested - Drying', 2);
$addPlants('Alpha', 'Sent', 1);
$addPlants('Alpha', 'Harvested - Destroyed', 1);
$addPlants('Alpha', 'Destroyed', 1);
$addPlants('Alpha', 'Harvested', 1); // legacy status from before 0.14, already left stock
// Bravo: the whole crop has been cut and is drying (the old report showed 0)
$addPlants('Bravo', 'Harvested - Drying', 4);
// Charlie: no plants at all

$rows = currentPlantCounts($pdo);
check('Every genetics is listed, alphabetically', array_column($rows, 'geneticsName'), ['Alpha', 'Bravo', 'Charlie']);

$byName = array_column($rows, 'plantCount', 'geneticsName');
check('Growing and drying plants both count', $byName['Alpha'], 5);
check('A crop that is all drying still shows', $byName['Bravo'], 4);
check('A genetics with no plants shows 0, for the page to hide', $byName['Charlie'], 0);

// The report and the annual stocktake agree on what's on hand
$stocktakeEnd = array_column(computeAnnualPlantStocktake($pdo, $year), 'end', 'geneticsName');
foreach (['Alpha', 'Bravo', 'Charlie'] as $name) {
    check("$name matches the annual stocktake's end balance", $byName[$name], $stocktakeEnd[$name]);
}

// Every status the database allows is either on hand or departed, never both
$schema = (string) $pdo->query("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'Plants'")->fetchColumn();
preg_match("/status\s+TEXT\s+CHECK\s*\(\s*status\s+IN\s*\(([^)]*)\)/i", $schema, $match);
preg_match_all("/'([^']*)'/", $match[1] ?? '', $allowed);
$allowed = $allowed[1];
sort($allowed);
$covered = array_merge(GRACE_CURRENT_PLANT_STATUSES, GRACE_PLANT_DEPARTED_STATUSES);
sort($covered);
check('Every plant status is on hand or departed, exactly once', $covered, $allowed);

// The page gets its numbers from the shared helper and says what they include
$endpoint = file_get_contents($publicDir . '/get_current_plants.php');
check('get_current_plants.php uses currentPlantCounts()', strpos($endpoint, 'currentPlantCounts($pdo)') !== false, true);
$page = file_get_contents($publicDir . '/current_plants.php');
check('current_plants.php says drying plants are included', stripos($page, 'drying') !== false, true);

unlink($tmpDb);

if ($failures > 0) {
    echo "[FAIL] Current plants tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Current Plants Test Completed Successfully\n";
exit(0);

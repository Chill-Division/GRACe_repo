<?php
/**
 * Regression tests for removing the unused Breeder and Genetic Lineage
 * fields from genetics (1.1.0), and for the in-place upgrade of existing
 * databases that have them.
 *
 * What must hold:
 * - Fresh installs never get the two columns.
 * - Existing installs lose the columns on their first page load after the
 *   update, without losing anything else: every genetics keeps its id and
 *   name, and every plant and flower entry still points at its genetics.
 *   (Plants and Flower link to Genetics with ON DELETE SET NULL, so a
 *   careless table rebuild would wipe those links. This test catches that.)
 * - Anything people had typed into the two fields is kept in the
 *   LegacyGeneticsDetails table, so no entered data is ever thrown away.
 * - Running the upgrade again (it runs on every page load) changes nothing.
 * - On SQLite too old to drop columns, the columns are left in place and
 *   GRACe keeps working.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/annual_stocktake_lib.php';

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

function columnsOf(PDO $pdo, $table)
{
    return implode(',', array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(PDO::FETCH_ASSOC), 'name'));
}

function tableExists(PDO $pdo, $table)
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = ?");
    $stmt->execute([$table]);
    return (int) $stmt->fetchColumn() === 1;
}

/** Rows as 'a|b;c|d' text, or 'ERROR: ...' so a missing table fails a check instead of crashing. */
function rowsAsText(PDO $pdo, $sql)
{
    try {
        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_NUM);
    } catch (PDOException $e) {
        return 'ERROR: ' . $e->getMessage();
    }
    return implode(';', array_map(fn($r) => implode('|', array_map(fn($v) => $v === null ? '' : (string) $v, $r)), $rows));
}

/** A single integer from a query, or 'ERROR: ...'. */
function scalar(PDO $pdo, $sql)
{
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (PDOException $e) {
        return 'ERROR: ' . $e->getMessage();
    }
}

/**
 * A database exactly as GRACe 1.0.x left it: the Genetics table still has
 * breeder and genetic_lineage, and Plants/Flower link to it with foreign keys.
 */
function createLegacyDatabase($path)
{
    $pdo = new PDO('sqlite:' . $path);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON;');
    $pdo->exec("CREATE TABLE Companies (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        license_number TEXT NOT NULL,
        address TEXT,
        primary_contact_name TEXT,
        primary_contact_email TEXT,
        primary_contact_phone TEXT
    )");
    $pdo->exec("CREATE TABLE Genetics (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        breeder TEXT,
        genetic_lineage TEXT
    )");
    $pdo->exec("CREATE TABLE Plants (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        genetics_id INTEGER,
        status TEXT CHECK(status IN ('Growing', 'Harvested', 'Destroyed', 'Sent', 'Harvested - Drying', 'Harvested - Destroyed')),
        date_created DATETIME,
        date_harvested DATETIME DEFAULT CURRENT_TIMESTAMP,
        company_id INTEGER,
        FOREIGN KEY (genetics_id) REFERENCES Genetics(id) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (company_id) REFERENCES Companies(id) ON DELETE SET NULL ON UPDATE CASCADE
    )");
    $pdo->exec("CREATE TABLE Flower (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        genetics_id INTEGER,
        weight DECIMAL(10, 2) NOT NULL,
        transaction_type TEXT CHECK(transaction_type IN ('Add', 'Subtract')) NOT NULL,
        transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        reason TEXT,
        company_id INTEGER,
        FOREIGN KEY (company_id) REFERENCES Companies(id) ON DELETE SET NULL ON UPDATE CASCADE,
        FOREIGN KEY (genetics_id) REFERENCES Genetics(id) ON DELETE SET NULL ON UPDATE CASCADE
    )");
    return $pdo;
}

echo "SQLite version: " . (new PDO('sqlite::memory:'))->query('SELECT sqlite_version()')->fetchColumn() . "\n";

// ---------------------------------------------------------------------------
// Scenario A: a fresh install never has the columns
// ---------------------------------------------------------------------------
$freshDb = tempnam(sys_get_temp_dir(), 'grace_gen_fresh_') . '.db';
$fresh = initializeDatabase($freshDb);
performMigrations($fresh);
check('Fresh install: Genetics has only id and name', columnsOf($fresh, 'Genetics'), 'id,name');
check('Fresh install: no LegacyGeneticsDetails table', tableExists($fresh, 'LegacyGeneticsDetails'), false);
$fresh->prepare("INSERT INTO Genetics (name) VALUES (?)")->execute(['Brand New']);
check('Fresh install: adding a genetics by name works', (int) $fresh->query("SELECT COUNT(*) FROM Genetics")->fetchColumn(), 1);
unset($fresh);

// ---------------------------------------------------------------------------
// Scenario B: upgrading a 1.0.x install that filled the fields in on SOME
// genetics (and left them blank, empty or whitespace-only on others)
// ---------------------------------------------------------------------------
$legacyDb = tempnam(sys_get_temp_dir(), 'grace_gen_legacy_') . '.db';
$legacy = createLegacyDatabase($legacyDb);
$legacy->exec("INSERT INTO Companies (id, name, license_number) VALUES (1, 'Test Lab', 'MCA-LAB-1')");
$legacy->exec("INSERT INTO Genetics (id, name, breeder, genetic_lineage) VALUES
    (1, 'Northern Lights', 'Sensi Seeds', 'Afghani x Thai'),
    (2, 'White Widow', 'Green House Seeds', NULL),
    (3, 'GG4', NULL, 'Chem Sis x Sour Dubb x Chocolate Diesel'),
    (4, 'Wedding Cake', '', ''),
    (5, 'Aotearoa Haze', NULL, NULL),
    (7, 'Jack Herer', '   ', NULL)");
$legacy->exec("INSERT INTO Plants (genetics_id, status, date_created, date_harvested, company_id) VALUES
    (1, 'Growing', '2026-02-01 10:00:00', NULL, NULL),
    (1, 'Growing', '2026-02-01 10:00:00', NULL, NULL),
    (2, 'Sent', '2026-01-05 10:00:00', '2026-03-01 10:00:00', 1),
    (3, 'Harvested - Drying', '2026-01-10 10:00:00', '2026-04-01 10:00:00', NULL),
    (4, 'Growing', '2026-05-01 10:00:00', NULL, NULL),
    (5, 'Destroyed', '2026-01-20 10:00:00', '2026-02-20 10:00:00', NULL),
    (7, 'Growing', '2026-06-01 10:00:00', NULL, NULL)");
$legacy->exec("INSERT INTO Flower (genetics_id, weight, transaction_type, reason, transaction_date, company_id) VALUES
    (1, 500.00, 'Add', 'Harvest', '2026-04-10 10:00:00', NULL),
    (1, -100.00, 'Subtract', 'Testing', '2026-05-10 10:00:00', 1),
    (3, 250.00, 'Add', 'Harvest', '2026-05-12 10:00:00', NULL),
    (7, 40.00, 'Add', 'Harvest', '2026-06-12 10:00:00', NULL)");

check('Before upgrade: the old columns are present', columnsOf($legacy, 'Genetics'), 'id,name,breeder,genetic_lineage');
$genBefore = rowsAsText($legacy, "SELECT id, name FROM Genetics ORDER BY id");
$plantLinksBefore = rowsAsText($legacy, "SELECT id, genetics_id, company_id FROM Plants ORDER BY id");
$flowerLinksBefore = rowsAsText($legacy, "SELECT id, genetics_id, company_id FROM Flower ORDER BY id");
unset($legacy);

// Upgrade exactly as the app does on its first page load after updating
$pdo = initializeDatabase($legacyDb);
performMigrations($pdo);

check('Upgrade: breeder and genetic_lineage are removed from Genetics', columnsOf($pdo, 'Genetics'), 'id,name');
check('Upgrade: every genetics keeps its id and name', rowsAsText($pdo, "SELECT id, name FROM Genetics ORDER BY id"), $genBefore);
check('Upgrade: every plant still points at the same genetics and company', rowsAsText($pdo, "SELECT id, genetics_id, company_id FROM Plants ORDER BY id"), $plantLinksBefore);
check('Upgrade: every flower entry still points at the same genetics and company', rowsAsText($pdo, "SELECT id, genetics_id, company_id FROM Flower ORDER BY id"), $flowerLinksBefore);
check('Upgrade: no plant or flower entry lost its genetics link', (int) $pdo->query("SELECT (SELECT COUNT(*) FROM Plants WHERE genetics_id IS NULL) + (SELECT COUNT(*) FROM Flower WHERE genetics_id IS NULL)")->fetchColumn(), 0);
check('Upgrade: no foreign key problems', count($pdo->query("PRAGMA foreign_key_check")->fetchAll()), 0);
check('Upgrade: database integrity check passes', $pdo->query("PRAGMA integrity_check")->fetchColumn(), 'ok');

check('Upgrade: details people typed in are kept in LegacyGeneticsDetails', tableExists($pdo, 'LegacyGeneticsDetails'), true);
check('Upgrade: exactly the filled-in genetics are kept, with their values',
    rowsAsText($pdo, "SELECT genetics_id, genetics_name, breeder, genetic_lineage FROM LegacyGeneticsDetails ORDER BY genetics_id"),
    '1|Northern Lights|Sensi Seeds|Afghani x Thai;2|White Widow|Green House Seeds|;3|GG4||Chem Sis x Sour Dubb x Chocolate Diesel');
check('Upgrade: blank, empty and whitespace-only values are not kept',
    scalar($pdo, "SELECT COUNT(*) FROM LegacyGeneticsDetails WHERE genetics_id IN (4, 5, 7)"), 0);
check('Upgrade: each kept entry records when it was archived',
    scalar($pdo, "SELECT COUNT(*) FROM LegacyGeneticsDetails WHERE archived_at IS NULL OR archived_at = ''"), 0);

// It runs on every page load, so a second run must change nothing
performMigrations($pdo);
check('Second run: Genetics still has only id and name', columnsOf($pdo, 'Genetics'), 'id,name');
check('Second run: kept details are not duplicated', scalar($pdo, "SELECT COUNT(*) FROM LegacyGeneticsDetails"), 3);

// And GRACe keeps working afterwards
$pdo->prepare("INSERT INTO Genetics (name) VALUES (?)")->execute(['New Strain']);
check('After upgrade: a new genetics can be added by name, continuing the id sequence', (int) $pdo->lastInsertId(), 8);
$pdo->prepare("INSERT INTO Plants (genetics_id, status, date_created) VALUES (?, 'Growing', ?)")->execute([8, '2026-07-01 10:00:00']);

$plantRows = [];
foreach (computeAnnualPlantStocktake($pdo, 2026) as $row) {
    $plantRows[$row['geneticsName']] = $row;
}
check('After upgrade: the annual plant stocktake still sees every genetics', count($plantRows), 7);
check('After upgrade: stocktake counts for an upgraded genetics are intact', $plantRows['Northern Lights']['in'] . '/' . $plantRows['Northern Lights']['end'], '2/2');
check('After upgrade: stocktake counts for the newly added genetics work', $plantRows['New Strain']['in'] . '/' . $plantRows['New Strain']['end'], '1/1');

$flowerRows = [];
foreach (computeAnnualFlowerStocktake($pdo, 2026) as $row) {
    $flowerRows[$row['geneticsName']] = $row;
}
check('After upgrade: the flower ledger balance for an upgraded genetics is intact', $flowerRows['Northern Lights']['end'], 400.0);
unset($pdo);

// ---------------------------------------------------------------------------
// Scenario C: a 1.0.x install where nobody used the fields
// ---------------------------------------------------------------------------
$unusedDb = tempnam(sys_get_temp_dir(), 'grace_gen_unused_') . '.db';
$unused = createLegacyDatabase($unusedDb);
$unused->exec("INSERT INTO Genetics (name, breeder, genetic_lineage) VALUES ('Alpha', '', ''), ('Bravo', NULL, NULL)");
unset($unused);
$pdo = initializeDatabase($unusedDb);
performMigrations($pdo);
check('Unused fields: columns removed', columnsOf($pdo, 'Genetics'), 'id,name');
check('Unused fields: no LegacyGeneticsDetails table is created', tableExists($pdo, 'LegacyGeneticsDetails'), false);
check('Unused fields: genetics kept', rowsAsText($pdo, "SELECT name FROM Genetics ORDER BY id"), 'Alpha;Bravo');
unset($pdo);

// ---------------------------------------------------------------------------
// Scenario D: SQLite too old to drop columns (before 3.35.0)
// ---------------------------------------------------------------------------
$oldDb = tempnam(sys_get_temp_dir(), 'grace_gen_old_') . '.db';
$old = createLegacyDatabase($oldDb);
$old->exec("INSERT INTO Genetics (name, breeder, genetic_lineage) VALUES ('Alpha', 'Someone', 'Something')");
unset($old);
$pdo = initializeDatabase($oldDb);
if (function_exists('removeLegacyGeneticsColumns')) {
    removeLegacyGeneticsColumns($pdo, '3.34.1');
    check('Old SQLite: the columns are left in place rather than risking the data', columnsOf($pdo, 'Genetics'), 'id,name,breeder,genetic_lineage');
    $pdo->prepare("INSERT INTO Genetics (name) VALUES (?)")->execute(['Bravo']);
    check('Old SQLite: adding a genetics by name still works', rowsAsText($pdo, "SELECT name FROM Genetics ORDER BY id"), 'Alpha;Bravo');
} else {
    check('removeLegacyGeneticsColumns() exists in init_db.php', false, true);
}
unset($pdo);

foreach ([$freshDb, $legacyDb, $unusedDb, $oldDb] as $file) {
    @unlink($file);
}

if ($failures > 0) {
    echo "[FAIL] Genetics upgrade tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Genetics Upgrade Test Completed Successfully\n";
exit(0);

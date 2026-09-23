<?php
/**
 * Regression tests for Harvest / Destroy / Send (1.1.0),
 * grace_addon/files/general/www/public/harvest_lib.php.
 *
 * The bugs: the handler updated whatever plant ids it was given. A stale
 * browser tab (or the back button) could re-process plants that had already
 * left, rewriting history (a plant sent months ago became "destroyed" today).
 * Harvesting a drying plant reset its harvest date, a mistyped action was
 * recorded as "Sent", plants could be sent without saying where to, and the
 * message read "5 plants sended successfully".
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
if (file_exists($publicDir . '/harvest_lib.php')) {
    require_once $publicDir . '/harvest_lib.php';
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

if (!function_exists('processPlants')) {
    check('harvest_lib.php provides processPlants()', false, true);
    echo "[FAIL] Harvest safeguard tests: $failures failure(s)\n";
    exit(1);
}

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_harvest_') . '.db';
$pdo = initializeDatabase($tmpDb);
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");
$pdo->exec("INSERT INTO Companies (name, license_number) VALUES ('Buyer Ltd', 'LIC-1'), ('Lab Ltd', 'LIC-2')");
$pdo->exec("INSERT INTO Plants (id, genetics_id, status, date_created, date_harvested, company_id) VALUES
    (1, 1, 'Growing',               '2026-01-01 10:00:00', NULL, NULL),
    (2, 1, 'Growing',               '2026-01-01 10:00:00', NULL, NULL),
    (3, 1, 'Harvested - Drying',    '2026-01-01 10:00:00', '2026-08-01 09:00:00', NULL),
    (4, 1, 'Sent',                  '2026-01-01 10:00:00', '2026-05-05 09:00:00', 2),
    (5, 1, 'Harvested - Destroyed', '2026-01-01 10:00:00', '2026-06-06 09:00:00', NULL),
    (6, 1, 'Destroyed',             '2026-01-01 10:00:00', '2026-06-07 09:00:00', NULL),
    (7, 1, 'Harvested',             '2024-01-01 10:00:00', '2024-06-01 09:00:00', NULL),
    (8, 1, 'Growing',               '2026-01-01 10:00:00', NULL, NULL)");

function plant(PDO $pdo, $id)
{
    return $pdo->query("SELECT status, date_harvested, company_id FROM Plants WHERE id = " . (int) $id)->fetch(PDO::FETCH_NUM);
}
$unchanged = function ($ids) use ($pdo) {
    return array_map(fn($id) => plant($pdo, $id), $ids);
};
$snapshot = $unchanged(range(1, 8));

// --- Stale tabs can't rewrite history -------------------------------------------
$result = processPlants($pdo, [4], 'destroy', null);
check('A plant that was already sent cannot be marked destroyed from a stale tab', $result['success'], false);
check('The message explains why and that nothing changed',
    $result['message'], '1 of the selected plants has already been processed, probably in another tab or window. Nothing was changed. Check the list and try again.');
check('The sent plant keeps its status, date and company', plant($pdo, 4), ['Sent', '2026-05-05 09:00:00', 2]);

$result = processPlants($pdo, [2, 5, 6], 'send', 1);
check('If any selected plant was already processed, the whole batch is refused', $result['success'], false);
check('...and says how many', strpos($result['message'], '2 of the selected plants have already been processed') === 0, true);
check('...and the eligible plant in the batch is untouched too', plant($pdo, 2), ['Growing', null, null]);

$result = processPlants($pdo, [3], 'harvest', null);
check('A drying plant cannot be harvested again (it used to reset the harvest date)', $result['success'], false);
check('Its harvest date is unchanged', plant($pdo, 3), ['Harvested - Drying', '2026-08-01 09:00:00', null]);

$result = processPlants($pdo, [999], 'destroy', null);
check('A plant that does not exist is refused', $result['success'], false);

// --- Bad requests are refused, nothing is guessed ------------------------------
$result = processPlants($pdo, [1], 'harvset', null);
check('A mistyped action is refused (it used to be recorded as Sent)', [$result['success'], $result['message']],
    [false, 'Unknown action. Nothing was changed.']);
check('Send without a company is refused', processPlants($pdo, [1], 'send', null)['message'],
    'Choose which company the plants were sent to.');
check('Send to a company that does not exist is refused', processPlants($pdo, [1], 'send', 999)['success'], false);
check('Plant ids that are not numbers are refused', processPlants($pdo, ['1 OR 1=1'], 'destroy', null)['success'], false);
check('...including a number with a line break after it', processPlants($pdo, ["1\n"], 'destroy', null)['success'], false);
check('An empty selection is refused', processPlants($pdo, [], 'destroy', null)['success'], false);
check('Nothing changed after all the refused requests', $unchanged(range(1, 8)), $snapshot);

// --- The normal paths still work, with clear messages -------------------------
$result = processPlants($pdo, [1], 'harvest', null);
check('Harvesting a growing plant works', [$result['success'], $result['message']],
    [true, 'Marked 1 plant as Harvested - Drying.']);
$row = plant($pdo, 1);
check('It is now drying, with today\'s NZ date', [$row[0], substr((string) $row[1], 0, 10)],
    ['Harvested - Drying', (new DateTimeImmutable('now', new DateTimeZone('Pacific/Auckland')))->format('Y-m-d')]);

$result = processPlants($pdo, [2], 'send', 1);
check('Sending says "Marked as sent" and names the company', [$result['success'], $result['message']],
    [true, 'Marked 1 plant as sent to Buyer Ltd.']);
check('The sent plant records the company', [plant($pdo, 2)[0], plant($pdo, 2)[2]], ['Sent', 1]);

$result = processPlants($pdo, [3, 7], 'send', 2);
check('Drying plants and old "Harvested" plants can still be sent (plural message)', [$result['success'], $result['message']],
    [true, 'Marked 2 plants as sent to Lab Ltd.']);

$result = processPlants($pdo, [8, 8], 'destroy', null);
check('Destroying works, and a plant ticked twice counts once', [$result['success'], $result['message']],
    [true, 'Marked 1 plant as Harvested - Destroyed.']);

check('Integrity check still passes', $pdo->query('PRAGMA integrity_check')->fetchColumn(), 'ok');
@unlink($tmpDb);

// --- The handler and page use it ------------------------------------------------
$handler = file_get_contents($publicDir . '/handle_harvest_plants.php');
check('handle_harvest_plants.php goes through processPlants()', strpos($handler, 'processPlants(') !== false, true);
check('The old "...ed successfully" message is gone', strpos($handler, "'ed") === false, true);
check('The JSON endpoint no longer prints PHP errors into its output', stripos($handler, 'display_errors') === false, true);

if ($failures > 0) {
    echo "[FAIL] Harvest safeguard tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Harvest Safeguard Test Completed Successfully\n";
exit(0);

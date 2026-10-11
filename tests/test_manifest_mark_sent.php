<?php
/**
 * Regression tests for "Then mark these plants as sent" on plant manifests
 * (1.2.0, manifest_lib.php). Flower manifests take the flower off the books,
 * but plant manifests never touched the plants, so a shipment of plants had
 * to be marked as sent separately or it stayed in stock and was missing from
 * the monthly report. With the toggle on, Harvest / Destroy / Send opens
 * next with the plants ticked; nothing is marked until it's confirmed there.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/manifest_lib.php';

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_manifest_sent_') . '.db';
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

// Where the form goes next
check('Plants to another company, toggle on: Harvest / Destroy / Send',
    manifestNextPage('plant', 'us', 'external', true, 12), 'harvest_plants.php?manifest=12');
check('Plants to another company, toggle off: the manifest',
    manifestNextPage('plant', 'us', 'external', false, 12), 'manifest_summary.php?id=12&created=1');
check('Flower: the manifest (its weight is already deducted)',
    manifestNextPage('flower', 'us', 'external', true, 12), 'manifest_summary.php?id=12&created=1');
check('Plants coming in: the manifest',
    manifestNextPage('plant', 'external', 'us', true, 12), 'manifest_summary.php?id=12&created=1');
check('Plants between two other companies: the manifest',
    manifestNextPage('plant', 'external', 'external', true, 12), 'manifest_summary.php?id=12&created=1');

// What Harvest / Destroy / Send fills in
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");
$pdo->prepare("INSERT INTO Companies (name, license_number) VALUES (?, ?), (?, ?)")
    ->execute(["Joe's Farm", 'LIC-1', 'Lab Ltd', 'LIC-2']);
$insert = $pdo->prepare("INSERT INTO ShippingManifests (sending_company_id, recipient_id, product_type, genetics_id, genetics_name, quantity, sending_company_name, receiving_company_name)
                         VALUES (?, ?, ?, 1, 'Alpha', ?, ?, ?)");
$insert->execute([null, 1, 'plant', 12, 'Us', "Joe's Farm"]);   // 1: plants from us to Joe's Farm
$insert->execute([null, 2, 'flower', 250, 'Us', 'Lab Ltd']);    // 2: flower
$insert->execute([1, null, 'plant', 5, "Joe's Farm", 'Us']);    // 3: plants coming in
$insert->execute([1, 2, 'plant', 7, "Joe's Farm", 'Lab Ltd']);  // 4: between two other companies

check('A plant manifest from us fills in genetics, count and company', plantManifestToMarkSent($pdo, '1'), [
    'id' => 1, 'geneticsName' => 'Alpha', 'quantity' => 12, 'companyId' => 1, 'companyName' => "Joe's Farm",
]);
check('A flower manifest fills in nothing', plantManifestToMarkSent($pdo, 2), null);
check('Plants coming in fill in nothing', plantManifestToMarkSent($pdo, 3), null);
check('Plants between two other companies fill in nothing', plantManifestToMarkSent($pdo, 4), null);
check('A manifest that does not exist fills in nothing', plantManifestToMarkSent($pdo, 99), null);
check('Anything that is not an id fills in nothing', plantManifestToMarkSent($pdo, '1 OR 1=1'), null);

// The pages use them
$form = file_get_contents($publicDir . '/generate_shipping_manifest.php');
$handler = file_get_contents($publicDir . '/process_shipping_manifest.php');
$harvest = file_get_contents($publicDir . '/harvest_plants.php');
check('The manifest form has the toggle, on by default',
    preg_match('/<input type="checkbox" role="switch" name="markPlantsSent" value="1" checked>/', $form), 1);
check('The toggle only shows for plants from us to another company',
    strpos($form, "productType.value === 'plant' && sendingChoice.value === 'us' && receivingChoice.value === 'external'") !== false, true);
check('Generating a manifest goes to manifestNextPage()', strpos($handler, "manifestNextPage(\$productType, \$sendingChoice, \$receivingChoice, !empty(\$_POST['markPlantsSent']), \$manifestId)") !== false, true);
check('Harvest / Destroy / Send reads the manifest', strpos($harvest, "plantManifestToMarkSent(\$pdo, \$_GET['manifest'])") !== false, true);
check('Quick select offers drying plants first', strpos($harvest, '<option value="drying">Drying plants first</option>') !== false, true);
check('After marking them sent it returns to the manifest without leaving the ticked page in the history',
    strpos($harvest, "location.replace('manifest_summary.php?id=' + manifestPrefill.id)") !== false, true);

unlink($tmpDb);

if ($failures > 0) {
    echo "[FAIL] Manifest mark-sent tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Manifest Mark Sent Test Completed Successfully\n";
exit(0);

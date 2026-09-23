<?php
/**
 * Regression tests for adding a verified company (1.1.0), both from
 * Administration → Add Verified Company and from "+ Add new company…" in the
 * company lists on Harvest / Destroy / Send, Record dry weight and Generate
 * Shipping Manifest. Both go through addVerifiedCompany() in
 * grace_addon/files/general/www/public/company_lib.php.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/company_lib.php';

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

if (!function_exists('addVerifiedCompany')) {
    check('company_lib.php provides addVerifiedCompany()', false, true);
    echo "[FAIL] Company adding tests: $failures failure(s)\n";
    exit(1);
}

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_company_add_') . '.db';
$pdo = initializeDatabase($tmpDb);
$lab = [
    'companyName' => ' Test Lab Ltd ',
    'licenseNumber' => 'MCA-LAB-1',
    'address' => "8 Science Drive\nWellington",
    'contactName' => 'Tessa',
    'contactEmail' => 'lab@example.co.nz',
    'contactPhone' => '04 555 0117',
];

// --- Adding ----------------------------------------------------------------------
$result = addVerifiedCompany($pdo, $lab);
check('A new company is added', [$result['success'], $result['message'], $result['name']],
    [true, 'Added Test Lab Ltd.', 'Test Lab Ltd']);
check('Its details come back, so a manifest can show them straight away', $result['company'], [
    'id' => $result['id'], 'name' => 'Test Lab Ltd', 'license_number' => 'MCA-LAB-1',
    'address' => "8 Science Drive\nWellington", 'primary_contact_email' => 'lab@example.co.nz',
]);
$labId = $result['id'];

// --- Already there ---------------------------------------------------------------
$result = addVerifiedCompany($pdo, ['licenseNumber' => ' MCA-LAB-1 '] + $lab);
check('The same license number is recognised as the existing company, so it can be selected',
    [$result['success'], $result['duplicate'], $result['id'], $result['message']],
    [false, true, $labId, 'Test Lab Ltd is already a verified company (license MCA-LAB-1).']);

$result = addVerifiedCompany($pdo, ['licenseNumber' => 'MCA-OTHER', 'companyName' => 'Other Co'] + $lab);
check('Another company\'s contact email is refused, as before',
    [$result['success'], $result['duplicate'], $result['message']],
    [false, false, 'Another company already has that contact email.']);

// --- Missing details -------------------------------------------------------------
$labels = [
    'companyName' => 'company name', 'licenseNumber' => 'license number', 'address' => 'address',
    'contactName' => 'contact name', 'contactEmail' => 'contact email', 'contactPhone' => 'contact phone',
];
foreach ($labels as $field => $label) {
    check("A missing $label is refused",
        addVerifiedCompany($pdo, [$field => '  '] + ['licenseNumber' => 'NEW-1', 'contactEmail' => 'new@example.co.nz'] + $lab)['message'],
        "Please enter the $label.");
}
check('An invalid email is refused',
    addVerifiedCompany($pdo, ['licenseNumber' => 'NEW-2', 'contactEmail' => 'not an email'] + $lab)['message'],
    'Please enter a valid contact email.');
check('Only the one company was added', (int) $pdo->query("SELECT COUNT(*) FROM Companies")->fetchColumn(), 1);
@unlink($tmpDb);

// --- Companies are still never deleted ---------------------------------------------
check('company_lib.php still contains no delete', stripos(source($publicDir, 'company_lib.php'), 'DELETE FROM') === false, true);

// --- Wiring ------------------------------------------------------------------------
check('Administration → Add Verified Company saves through addVerifiedCompany()',
    strpos(source($publicDir, 'handle_add_verified_company.php'), 'addVerifiedCompany(') !== false, true);
$endpoint = source($publicDir, 'handle_quick_add_company.php');
check('handle_quick_add_company.php saves through addVerifiedCompany() and answers in JSON',
    strpos($endpoint, 'addVerifiedCompany(') !== false && strpos($endpoint, "header('Content-Type: application/json')") !== false, true);
check('js/quick_add.js has a ready-made setup for companies',
    strpos(source($publicDir, 'js/quick_add.js'), 'function enableQuickAddCompany(') !== false, true);
foreach (['harvest_plants.php', 'js/transaction_form.js', 'generate_shipping_manifest.php'] as $file) {
    check("$file offers \"+ Add new company\" in its company list",
        strpos(source($publicDir, $file), 'enableQuickAddCompany(') !== false, true);
}
foreach (['harvest_plants.php', 'record_dry_weight.php', 'generate_shipping_manifest.php'] as $page) {
    check("$page loads js/quick_add.js", strpos(source($publicDir, $page), 'src="js/quick_add.js') !== false, true);
}
check('The manifest form makes you choose a company rather than quietly picking the first one',
    strpos(source($publicDir, 'generate_shipping_manifest.php'), '<option value="" disabled selected>Select company</option>') !== false, true);

if ($failures > 0) {
    echo "[FAIL] Company adding tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Company Adding Test Completed Successfully\n";
exit(0);

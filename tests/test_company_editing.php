<?php
/**
 * Regression tests for verified company editing (0.18.0),
 * grace_addon/files/general/www/public/company_lib.php.
 *
 * Companies can be edited but never deleted; these tests cover the
 * validation rules around annual license renewals and contact changes.
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

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_company_') . '.db';
$pdo = initializeDatabase($tmpDb);

$pdo->exec("INSERT INTO Companies (name, license_number, address, primary_contact_name, primary_contact_email, primary_contact_phone) VALUES
    ('Alpha Labs',   'MCA-100', '1 First St',  'Alice', 'alice@alpha.example', '01'),
    ('Bravo Buyers', 'MCA-200', '2 Second St', 'Bob',   'bob@bravo.example',   '02')");

function editInput($overrides = [])
{
    return array_merge([
        'companyId' => 1,
        'companyName' => 'Alpha Labs',
        'licenseNumber' => 'MCA-100',
        'address' => '1 First St',
        'contactName' => 'Alice',
        'contactEmail' => 'alice@alpha.example',
        'contactPhone' => '01',
    ], $overrides);
}

// The headline use case: the annual license renewal
check('Renewing the license number succeeds',
    updateVerifiedCompany($pdo, editInput(['licenseNumber' => 'MCA-101'])),
    'Success: Company details updated');
check('The new license number is stored',
    $pdo->query("SELECT license_number FROM Companies WHERE id = 1")->fetchColumn(),
    'MCA-101');

// The other use case: the staff contact moves on
check('Changing the contact person succeeds',
    updateVerifiedCompany($pdo, editInput(['licenseNumber' => 'MCA-101', 'contactName' => 'Anna', 'contactEmail' => 'anna@alpha.example'])),
    'Success: Company details updated');

// Re-saving a company's own values must always work (editing one field
// re-submits all the others unchanged)
check('Re-saving unchanged values succeeds',
    updateVerifiedCompany($pdo, editInput(['licenseNumber' => 'MCA-101', 'contactName' => 'Anna', 'contactEmail' => 'anna@alpha.example'])),
    'Success: Company details updated');

// Uniqueness is enforced against OTHER companies only
check('Taking another company\'s license number is blocked',
    updateVerifiedCompany($pdo, editInput(['licenseNumber' => 'MCA-200'])),
    'Error: Another company already has that license number.');
check('Taking another company\'s contact email is blocked',
    updateVerifiedCompany($pdo, editInput(['licenseNumber' => 'MCA-101', 'contactEmail' => 'bob@bravo.example'])),
    'Error: Another company already has that contact email.');

// Bad input
check('Unknown company id is rejected',
    updateVerifiedCompany($pdo, editInput(['companyId' => 999])),
    'Error: Unknown company.');
check('Missing id is rejected',
    updateVerifiedCompany($pdo, editInput(['companyId' => 0])),
    'Error: Unknown company.');
check('A blank field is rejected',
    updateVerifiedCompany($pdo, editInput(['address' => '  '])),
    'Error: All fields are required.');

// Failed attempts must not have changed anything
check('Blocked edits leave the row untouched',
    $pdo->query("SELECT license_number FROM Companies WHERE id = 1")->fetchColumn(),
    'MCA-101');
check('The other company is untouched throughout',
    $pdo->query("SELECT name || '/' || license_number FROM Companies WHERE id = 2")->fetchColumn(),
    'Bravo Buyers/MCA-200');

// The design rule: editing exists, deletion must not
check('company_lib.php contains no delete operation',
    stripos(file_get_contents($publicDir . '/company_lib.php'), 'DELETE FROM') === false,
    true);

unlink($tmpDb);

if ($failures > 0) {
    echo "[FAIL] Company editing tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Company Editing Test Completed Successfully\n";
exit(0);

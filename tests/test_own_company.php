<?php
/**
 * Regression tests for saving your own company details (1.1.0),
 * saveOwnCompany() in grace_addon/files/general/www/public/company_lib.php.
 *
 * The bug: process_own_company.php ran every field through PHP's
 * FILTER_SANITIZE_STRING, which stores quotes as HTML codes. "Joe's Farm"
 * was saved as "Joe&#39;s Farm" and printed that way in Agency emails and
 * reports. Existing installs get their saved details repaired once.
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

function ownCompany(PDO $pdo)
{
    return $pdo->query("SELECT company_name, company_license_number, company_address, primary_contact_email FROM OwnCompany")->fetchAll(PDO::FETCH_NUM);
}

if (!function_exists('saveOwnCompany')) {
    check('company_lib.php provides saveOwnCompany()', false, true);
    echo "[FAIL] Own company tests: $failures failure(s)\n";
    exit(1);
}

// --- Saving keeps exactly what was typed ---------------------------------------
$tmpDb = tempnam(sys_get_temp_dir(), 'grace_own_') . '.db';
$pdo = initializeDatabase($tmpDb);

$input = [
    'companyName' => '  Joe\'s Farm "Pty" Ltd ',
    'companyLicense' => 'MCA-2026-0042',
    'companyAddress' => "1 O'Brien Street\nNelson 7010",
    'primaryContactEmail' => 'joe@example.co.nz',
];
$result = saveOwnCompany($pdo, $input);
check('Saving succeeds and says so', [$result['success'], $result['message']], [true, 'Company details saved.']);
check('Apostrophes and quotes are stored as typed (spaces trimmed)', ownCompany($pdo),
    [['Joe\'s Farm "Pty" Ltd', 'MCA-2026-0042', "1 O'Brien Street\nNelson 7010", 'joe@example.co.nz']]);

$input['companyName'] = 'Joe & Sons <Growers>';
saveOwnCompany($pdo, $input);
check('Saving again updates the one company row (never adds a second)', count(ownCompany($pdo)), 1);
check('Ampersands and angle brackets are stored as typed too (pages escape them when shown)',
    ownCompany($pdo)[0][0], 'Joe & Sons <Growers>');

// --- Bad input is refused with a clear message, nothing saved ------------------
$before = ownCompany($pdo);
check('A missing company name is refused',
    saveOwnCompany($pdo, ['companyName' => '  '] + $input), ['success' => false, 'message' => 'Please enter your company name.']);
check('A missing license number is refused',
    saveOwnCompany($pdo, ['companyLicense' => ''] + $input)['message'], 'Please enter your license number.');
check('A missing address is refused',
    saveOwnCompany($pdo, ['companyAddress' => ''] + $input)['message'], 'Please enter your address.');
check('An invalid email is refused',
    saveOwnCompany($pdo, ['primaryContactEmail' => 'joe at example'] + $input)['message'], 'Please enter a valid contact email.');
check('Nothing changed after the refused saves', ownCompany($pdo), $before);
@unlink($tmpDb);

// --- Existing installs are repaired once ----------------------------------------
$tmpDb = tempnam(sys_get_temp_dir(), 'grace_own_fix_') . '.db';
$pdo = initializeDatabase($tmpDb);
$pdo->exec("INSERT INTO OwnCompany (company_name, company_license_number, company_address, primary_contact_email)
            VALUES ('Joe&#39;s Farm &#34;Pty&#34; Ltd', 'MCA&#39;42', '1 O&#39;Brien Street', 'joe@example.co.nz')");
performMigrations($pdo);
check('Details saved by 1.0.x get their apostrophes and quotes back', ownCompany($pdo),
    [['Joe\'s Farm "Pty" Ltd', "MCA'42", "1 O'Brien Street", 'joe@example.co.nz']]);
check('The repair is recorded so it only ever runs once',
    $pdo->query("SELECT status FROM DataMigrations WHERE name = 'own-company-quotes'")->fetchColumn(), 'done');
$pdo->exec("UPDATE OwnCompany SET company_name = 'Literally &#39; typed'");
performMigrations($pdo);
check('Later saves are never touched by it', ownCompany($pdo)[0][0], 'Literally &#39; typed');
@unlink($tmpDb);

// --- Every page shows the name safely ----------------------------------------------
$handler = file_get_contents($publicDir . '/process_own_company.php');
check('process_own_company.php no longer uses FILTER_SANITIZE_STRING', strpos($handler, 'FILTER_SANITIZE_STRING') === false, true);
check('process_own_company.php saves through saveOwnCompany()', strpos($handler, 'saveOwnCompany(') !== false, true);
foreach (['last_months_flower_transactions.php', 'this_months_flower_transactions.php'] as $page) {
    check("$page escapes the heading that contains the company name",
        strpos(file_get_contents($publicDir . '/' . $page), '<h1><?php echo htmlspecialchars($reportHeading); ?></h1>') !== false, true);
}
$manifestPage = file_get_contents($publicDir . '/generate_shipping_manifest.php');
check('The manifest form escapes company details before putting them in the page',
    preg_match('/\$\{(ownCompany|selectedCompany|company)\.[a-z_]+\}/', $manifestPage), 0);
check('escapeHtml() escapes quotes, so a name can\'t break out of value="..."',
    strpos(file_get_contents($publicDir . '/js/growcart.js'), ".replace(/\"/g, '&quot;')") !== false, true);

if ($failures > 0) {
    echo "[FAIL] Own company tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Own Company Test Completed Successfully\n";
exit(0);

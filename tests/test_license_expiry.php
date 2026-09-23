<?php
/**
 * Regression tests for the license upload expiry limit (1.1.0),
 * grace_addon/files/general/www/public/license_alerts_lib.php.
 *
 * Licenses are annual, but the Agency can issue a renewal up to 3 months
 * early, before the current one runs out. So a freshly issued renewal can
 * expire up to 15 months from the day it's uploaded. The limit used to be
 * 12 months, which blocked people from uploading early renewals.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/license_alerts_lib.php';

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

$today = '2026-09-23';
$tooFar = 'The expiry date can be at most 15 months from today.';

// --- The limit itself ---------------------------------------------------------
if (function_exists('licenseMaxExpiryDate')) {
    check('The latest allowed expiry is 15 months from today',
        licenseMaxExpiryDate($today), '2027-12-23');
    check('Month ends are handled: 15 months from 30 November is 29 February, not 1 March',
        licenseMaxExpiryDate('2026-11-30'), '2028-02-29');
} else {
    check('licenseMaxExpiryDate() exists in license_alerts_lib.php', false, true);
}

if (function_exists('validateLicenseExpiryDate')) {
    // --- Dates that must be accepted ------------------------------------------
    check('A renewal issued the full 3 months early (exactly on the limit) is accepted',
        validateLicenseExpiryDate('2027-12-23', $today), null);
    check('A renewal issued 2 months early is accepted',
        validateLicenseExpiryDate('2027-11-23', $today), null);
    check('13 months out (blocked by the old 12-month limit) is now accepted',
        validateLicenseExpiryDate('2027-10-23', $today), null);
    check('A license expiring later this year is accepted',
        validateLicenseExpiryDate('2026-12-01', $today), null);
    check('An already-expired license can still be uploaded for the record',
        validateLicenseExpiryDate('2025-01-01', $today), null);

    // --- Dates that must be refused -------------------------------------------
    check('One day past the limit is refused with a clear message',
        validateLicenseExpiryDate('2027-12-24', $today), $tooFar);
    check('Two years out (almost certainly a typo) is refused',
        validateLicenseExpiryDate('2028-09-23', $today), $tooFar);
    check('Text that is not a date is refused',
        validateLicenseExpiryDate('next year', $today), 'Please enter a valid expiry date.');
    check('An impossible date (30 February) is refused',
        validateLicenseExpiryDate('2027-02-30', $today), 'Please enter a valid expiry date.');
    check('A date in the wrong format is refused',
        validateLicenseExpiryDate('23/09/2027', $today), 'Please enter a valid expiry date.');
} else {
    check('validateLicenseExpiryDate() exists in license_alerts_lib.php', false, true);
}

// --- The upload page and the upload handler both use the shared limit --------
$uploadPhp = file_get_contents($publicDir . '/upload.php');
$licensesPhp = file_get_contents($publicDir . '/company_licenses.php');
check('upload.php enforces the limit through the shared helper',
    strpos($uploadPhp, 'validateLicenseExpiryDate(') !== false, true);
check('upload.php no longer has its own hard-coded limit',
    stripos($uploadPhp, '+12 months') === false && stripos($uploadPhp, '+15 months') === false, true);
check('The upload form caps the date picker at the same limit',
    strpos($licensesPhp, 'licenseMaxExpiryDate()') !== false, true);
check('The upload form tells people the limit is 15 months',
    stripos($licensesPhp, '15 months') !== false && stripos($licensesPhp, '12 months') === false, true);

if ($failures > 0) {
    echo "[FAIL] License expiry tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] License Expiry Test Completed Successfully\n";
exit(0);

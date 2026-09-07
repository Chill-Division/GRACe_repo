<?php
/**
 * Regression tests for download filenames (1.0.1),
 * grace_addon/files/general/www/public/download_lib.php.
 *
 * Background: the Home Assistant companion app hands downloads to Android's
 * download manager, which names the file from the Content-Disposition
 * header. A malformed header (we used to send a trailing semicolon) makes it
 * fall back to the URL, so licenses arrived on phones as "download-2.php".
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/download_lib.php';

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

$tmpDb = tempnam(sys_get_temp_dir(), 'grace_dl_') . '.db';
$pdo = initializeDatabase($tmpDb);
$pdo->exec("INSERT INTO Documents (category, original_filename, unique_filename) VALUES
    ('licenses', 'Pause Labs renewed Medicinal Cannabis Licence September 2026.pdf', '6a9605b10f49f-Pause Labs renewed Medicinal Cannabis Licence September 2026.pdf'),
    ('coc',      'signed coc.jpg',                                                  '6a9605b10f4a0-signed coc.jpg')");

// --- Which name the user sees ------------------------------------------------
check('Uploaded document downloads under its original name (the reported bug)',
    resolveDownloadName($pdo, 'licenses', '6a9605b10f49f-Pause Labs renewed Medicinal Cannabis Licence September 2026.pdf'),
    'Pause Labs renewed Medicinal Cannabis Licence September 2026.pdf');
check('Lookup is per category (same stored name, wrong category, falls back to prefix strip)',
    resolveDownloadName($pdo, 'sops', '6a9605b10f4a0-signed coc.jpg'),
    'signed coc.jpg');
check('Generated manifest PDF loses its uniqid() prefix',
    resolveDownloadName($pdo, 'manifests', '6a9605b10f4a1-shipping-manifest-12.pdf'),
    'shipping-manifest-12.pdf');
check('uniqid($prefix, true) form is stripped too (14 hex chars, as the demo seeder produces)',
    resolveDownloadName($pdo, 'manifests', 'demo_6a9f1d3957f854.46754153-shipping-manifest.pdf'),
    'shipping-manifest.pdf');
check('A name without a uniqid prefix is left alone',
    resolveDownloadName($pdo, 'sops', 'plain-name.pdf'),
    'plain-name.pdf');
check('Works with no database connection at all',
    resolveDownloadName(null, 'licenses', '6a9605b10f49f-anything.pdf'),
    'anything.pdf');

// --- Making the name header-safe ---------------------------------------------
check('Spaces are kept (quoted-string safe)',
    sanitizeDownloadName('Pause Labs licence 2026.pdf', 'pdf'),
    'Pause Labs licence 2026.pdf');
check('Quotes, backslashes and path separators are neutralised',
    sanitizeDownloadName('bad "name" \\ with / slashes.pdf', 'pdf'),
    'bad _name_ _ with _ slashes.pdf');
check('Non-ASCII characters are transliterated or dropped',
    preg_match('/^[\x20-\x7E]+$/', sanitizeDownloadName('Aotearoa Māori licence.pdf', 'pdf')),
    1);
check('An empty name still gets a usable filename with the right extension',
    sanitizeDownloadName('', 'pdf'),
    'download.pdf');
check('A missing extension is added from the stored file',
    sanitizeDownloadName('licence', 'pdf'),
    'licence.pdf');

// --- The header itself -------------------------------------------------------
$header = downloadDispositionHeader(sanitizeDownloadName('Pause Labs licence 2026.pdf', 'pdf'));
check('Header is exactly one quoted filename parameter',
    $header,
    'attachment; filename="Pause Labs licence 2026.pdf"');
check('Header has no trailing semicolon (what broke Android downloads)',
    substr($header, -1),
    '"');
// Android's URLUtil.parseContentDisposition pattern, which the HA app relies on
check('Header matches the Android download manager parser',
    preg_match('/^attachment;\s*filename\s*=\s*("?)([^"]*)\1\s*$/i', $header),
    1);

// --- MIME types ---------------------------------------------------------------
check('JPEG uses the correct MIME type (was image/jpg)', contentTypeForExtension('jpg'), 'image/jpeg');
check('PDF MIME type', contentTypeForExtension('PDF'), 'application/pdf');
check('Unknown extensions fall back to octet-stream', contentTypeForExtension('xyz'), 'application/octet-stream');

unlink($tmpDb);

if ($failures > 0) {
    echo "[FAIL] Download filename tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Download Filename Test Completed Successfully\n";
exit(0);

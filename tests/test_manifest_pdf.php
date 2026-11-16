<?php
/**
 * Regression tests for the shipping manifest PDF (1.4.0),
 * renderShippingManifestPdf() in manifest_lib.php.
 *
 * - It's set in Inter, which GRACe ships in TCPDF/fonts with its licence, so
 *   te reo Māori names print with their macrons. TCPDF's own Helvetica has no
 *   macrons, and 1.3.x manifests printed "?taki Wh?nau Growers".
 * - The Chill Division logo is on it, and the labels are in sentence case.
 * - process_shipping_manifest.php builds its PDF through this function.
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/manifest_lib.php';

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

/** Every stream in the PDF, unpacked where it's compressed. */
function pdfStreams($pdf)
{
    preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $m);
    $out = [];
    foreach ($m[1] as $raw) {
        $unpacked = function_exists('gzuncompress') ? @gzuncompress($raw) : false;
        $out[] = $unpacked === false ? $raw : $unpacked;
    }
    return $out;
}

check('manifest_lib.php provides renderShippingManifestPdf()', function_exists('renderShippingManifestPdf'), true);
if ($failures > 0) {
    exit(1);
}

$pdf = renderShippingManifestPdf([
    'id' => 42,
    'sender' => ['name' => 'Ōtaki Whānau Growers Ltd', 'license' => 'MCA-2026-0042', 'email' => 'kia.ora@example.co.nz'],
    'receiver' => ['name' => 'Kiwi Cannabis Labs', 'license' => 'MCA-2025-0101', 'address' => "12 Māori Road\nWhanganui 4500"],
    'genetics' => 'Aotearoa Haze',
    'productType' => 'flower',
    'quantity' => 200,
    'prepared' => '2026-11-16 10:30:00',
]);

check('It is a PDF', substr($pdf, 0, 5), '%PDF-');

// Which font draws each piece of text: map the page's font names (/F1...) to
// the fonts they stand for, then walk the page, noting the font in force at
// every text operator. (TCPDF always lists Helvetica as a resource, unused.)
$fontByName = [];
if (preg_match('#/Font\s*<<(.*?)>>#s', $pdf, $fontDict)) {
    preg_match_all('#/(F\d+)\s+(\d+)\s+0\s+R#', $fontDict[1], $refs, PREG_SET_ORDER);
    foreach ($refs as $ref) {
        if (preg_match('#(?:^|\n)' . $ref[2] . ' 0 obj\s*<<(.*?)>>#s', $pdf, $obj)
            && preg_match('#/BaseFont /([A-Za-z0-9+_-]+)#', $obj[1], $base)) {
            $fontByName[$ref[1]] = $base[1];
        }
    }
}
$streams = pdfStreams($pdf);
$drawnWith = [];
foreach ($streams as $stream) {
    $current = null;
    $size = 0;
    preg_match_all('#/(F\d+)\s+([\d.]+)\s+Tf|\)\s*Tj|\]\s*TJ#', $stream, $ops, PREG_SET_ORDER);
    foreach ($ops as $op) {
        if (!empty($op[1])) {
            $current = $op[1];
            $size = (float) $op[2];
        } elseif ($current !== null && $size >= 2) {
            // (TCPDF also writes an invisible 1 pt marker in Helvetica: not text anyone sees)
            $drawnWith[$fontByName[$current] ?? $current] = true;
        }
    }
}
$drawnWith = array_keys($drawnWith);
sort($drawnWith);
check('All its text is drawn in Inter (regular and semibold), none in Helvetica', count($drawnWith) > 0 && count(array_filter($drawnWith, function ($n) {
    return stripos($n, 'Inter') === false;
})) === 0, true);

// TCPDF writes text in the embedded font as UTF-16 (big-endian) code points
$all = implode("\n", $streams);
$utf16 = function ($text) {
    $out = '';
    foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
        $code = 0;
        $bytes = array_values(unpack('C*', $char));
        if (count($bytes) === 1) {
            $code = $bytes[0];
        } elseif (count($bytes) === 2) {
            $code = (($bytes[0] & 0x1F) << 6) | ($bytes[1] & 0x3F);
        } else {
            $code = (($bytes[0] & 0x0F) << 12) | (($bytes[1] & 0x3F) << 6) | ($bytes[2] & 0x3F);
        }
        $out .= chr($code >> 8) . chr($code & 0xFF);
    }
    return $out;
};
check('Names keep their macrons: Ōtaki Whānau Growers', strpos($all, $utf16('Ōtaki Wh')) !== false && strpos($all, $utf16('ānau')) !== false, true);
check('...and addresses: 12 Māori Road', strpos($all, $utf16('Māori Road')) !== false, true);
check('Nothing printed as "?" in their place (1.3.x printed "?taki Wh?nau")', strpos($all, '?taki') === false && strpos($all, $utf16('?taki')) === false, true);
check('Dates are day/month/year', strpos($all, $utf16('16/11/2026 10:30')) !== false, true);
check('The Chill Division logo is on it', strpos($pdf, '/Subtype /Image') !== false, true);
check('...and the logo file is still there afterwards (TCPDF deletes images in its cache folder, which was all of /tmp)',
    is_readable($publicDir . '/icons/chill-division-logo-print.png'), true);

$plain = renderShippingManifestPdf([
    'id' => 7,
    'sender' => ['name' => "Joe's Farm", 'license' => 'LIC-1', 'email' => 'joe@example.com'],
    'receiver' => ['name' => 'Testing Lab', 'license' => 'LIC-2', 'address' => '1 Main Street'],
    'genetics' => 'Alpha',
    'productType' => 'plants',
    'quantity' => 3,
    'prepared' => '2026-01-05 09:00:00',
]);
check('A manifest with plain names still builds', substr($plain, 0, 5), '%PDF-');

// --- Wiring and files ---------------------------------------------------------------
$process = file_get_contents($publicDir . '/process_shipping_manifest.php');
check('process_shipping_manifest.php builds its PDF with renderShippingManifestPdf()', strpos($process, 'renderShippingManifestPdf(') !== false, true);
foreach (['inter.php', 'inter.z', 'inter.ctg.z', 'interb.php', 'interb.z', 'interb.ctg.z', 'inter-OFL.txt'] as $file) {
    check("TCPDF/fonts/$file ships with the add-on", is_readable($publicDir . '/TCPDF/fonts/' . $file), true);
}
check('Inter\'s licence beside the PDF fonts is the SIL Open Font License',
    strpos((string) @file_get_contents($publicDir . '/TCPDF/fonts/inter-OFL.txt'), 'SIL OPEN FONT LICENSE') !== false, true);
$logo = $publicDir . '/icons/chill-division-logo-print.png';
$info = is_readable($logo) ? getimagesize($logo) : false;
// TCPDF reads a PNG with no alpha channel itself. One with alpha needs GD or
// Imagick, which the add-on image doesn't have.
check('The print logo is a PNG without an alpha channel', $info !== false && $info[2] === IMAGETYPE_PNG && ($info['channels'] ?? 1) < 4, true);

if ($failures > 0) {
    echo "[FAIL] Manifest PDF tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Manifest PDF Test Completed Successfully\n";
exit(0);

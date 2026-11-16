<?php
/**
 * Turns Inter into the font files TCPDF needs for the shipping manifest PDF
 * (1.4.0). Not part of the add-on: run it on a dev PC only when the fonts
 * change. See "The manifest PDF's fonts" in DEVELOPMENT.md.
 *
 *   php tests/build_pdf_fonts.php inter.ttf interb.ttf
 *
 * The two .ttf files are Inter Regular and Inter SemiBold, cut down first to
 * the characters a manifest needs (DEVELOPMENT.md has the pyftsubset line).
 * Their names matter: TCPDF names a font after its file, and SetFont('inter')
 * then finds inter.php for plain text and interb.php for bold. The results go
 * into grace_addon/files/general/www/public/TCPDF/fonts.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
require_once $publicDir . '/TCPDF/tcpdf.php';

$fonts = array_slice($argv, 1);
if (count($fonts) === 0) {
    fwrite(STDERR, "Usage: php tests/build_pdf_fonts.php inter.ttf interb.ttf\n");
    exit(1);
}

$outDir = $publicDir . '/TCPDF/fonts/';
foreach ($fonts as $font) {
    if (!is_readable($font)) {
        fwrite(STDERR, "Can't read $font\n");
        exit(1);
    }
    $name = TCPDF_FONTS::addTTFfont($font, 'TrueTypeUnicode', '', 32, $outDir);
    if ($name === false) {
        fwrite(STDERR, "TCPDF couldn't convert $font\n");
        exit(1);
    }
    echo "$font -> TCPDF/fonts/$name.php\n";
}

<?php
/**
 * GRACe's text is Inter, the Chill Division font, and the add-on serves it
 * itself (css/growcart.css, fonts/inter/). A Home Assistant box may have no
 * internet, and GRACe runs under ingress at a deep path, so:
 *
 * - every url() in growcart.css is a relative path to a file in the add-on
 * - no page or stylesheet loads anything from another site, such as Google
 *   Fonts
 * - Inter's licence, the SIL Open Font License, ships with the font files.
 *   The licence requires that: every copy of the fonts carries it.
 * - Inter comes first in the font list, its files really are fonts, and
 *   between them they cover the letters with macrons used in Māori words
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
$cssDir = $publicDir . '/css';
$fontDir = $publicDir . '/fonts/inter';
$css = preg_replace('#/\*.*?\*/#s', '', file_get_contents($cssDir . '/growcart.css'));

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

/** Every url() in $css. A quoted one can hold brackets, as data: URLs do. */
function cssUrls($css)
{
    preg_match_all('/url\(\s*(?:"([^"]*)"|\'([^\']*)\'|([^"\')\s]+))\s*\)/', $css, $m, PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL);
    $urls = [];
    foreach ($m as $match) {
        $urls[] = $match[1] ?? $match[2] ?? $match[3];
    }
    return $urls;
}

/** True when $url leaves the add-on: a web address, or a path from the site root. */
function leavesAddon($url)
{
    return preg_match('#^(/|[a-z][a-z0-9+.-]*:)#i', $url) === 1 && strpos($url, 'data:') !== 0;
}

/** The code points a unicode-range covers, as [first, last] pairs. */
function unicodeRanges($value)
{
    $ranges = [];
    foreach (explode(',', $value) as $part) {
        if (preg_match('/^\s*U\+([0-9A-F]+)(?:-([0-9A-F]+))?\s*$/i', $part, $m)) {
            $ranges[] = [hexdec($m[1]), hexdec($m[2] ?? $m[1])];
        }
    }
    return $ranges;
}

// The detectors themselves
check('Finding url()s: quoted, unquoted, and a data: URL with brackets inside',
    cssUrls('a{b:url("x.woff2")} c{d:url(y.png)} e{f:url(\'data:image/svg+xml,rgb(1, 2, 3)\')}'),
    ['x.woff2', 'y.png', 'data:image/svg+xml,rgb(1, 2, 3)']);
check('A relative path stays in the add-on', leavesAddon('../fonts/inter/a.woff2'), false);
check('A data: URL stays in the add-on', leavesAddon('data:image/png;base64,AAAA'), false);
check('Google Fonts leaves it', leavesAddon('https://fonts.gstatic.com/s/inter/a.woff2'), true);
check('So does a path from the site root (it breaks under ingress)', leavesAddon('/fonts/inter/a.woff2'), true);
check('...and one without a scheme', leavesAddon('//fonts.gstatic.com/a.woff2'), true);
check('Reading a unicode-range', unicodeRanges('U+0000-00FF, U+0131, U+0152-0153'), [[0, 255], [305, 305], [338, 339]]);

// growcart.css only loads files that ship with the add-on
$urls = cssUrls($css);
check('growcart.css loads its fonts with url()', count($urls) > 0, true);
foreach ($urls as $url) {
    if (strpos($url, 'data:') === 0) {
        continue;
    }
    check("growcart.css: $url is a relative path", leavesAddon($url), false);
    $file = $cssDir . '/' . preg_replace('/[?#].*$/', '', $url);
    check("growcart.css: $url is in the add-on", is_file($file), true);
}
check('growcart.css imports no other stylesheet', preg_match('/@import\b/i', $css), 0);

// No page, script or stylesheet loads anything from another site
$external = [];
$files = array_merge(glob($publicDir . '/*.php'), glob($publicDir . '/js/*.js'), glob($cssDir . '/*.css'));
foreach ($files as $file) {
    $code = file_get_contents($file);
    if (preg_match_all('#<link\b[^>]*\bhref="((?:https?:)?//[^"]+)"|<script\b[^>]*\bsrc="((?:https?:)?//[^"]+)"|url\(\s*["\']?((?:https?:)?//[^"\')\s]+)|(fonts\.googleapis\.com|fonts\.gstatic\.com)#i', $code, $m, PREG_SET_ORDER)) {
        foreach ($m as $match) {
            $external[] = basename($file) . ': ' . end($match);
        }
    }
}
check('No page, script or stylesheet loads anything from another site', $external, []);

// Inter, with its licence
$ofl = is_file($fontDir . '/OFL.txt') ? file_get_contents($fontDir . '/OFL.txt') : '';
check("Inter's licence ships with the font files (fonts/inter/OFL.txt)", $ofl !== '', true);
check('...and it is the SIL Open Font License 1.1', strpos($ofl, 'SIL OPEN FONT LICENSE Version 1.1') !== false, true);
check('...with the copyright notice of the Inter Project Authors', strpos($ofl, 'Copyright 2016 The Inter Project Authors') !== false, true);

preg_match_all('/@font-face\s*\{([^}]*)\}/', $css, $faces);
$interFiles = [];
$covered = [];
foreach ($faces[1] as $face) {
    if (!preg_match('/font-family:\s*"Inter"/', $face)) {
        continue;
    }
    preg_match('/src:\s*url\("([^"]+)"\)/', $face, $src);
    $name = basename($src[1] ?? '');
    $interFiles[] = $name;
    check("Inter ($name) covers every weight (a variable font, 100 to 900)", preg_match('/font-weight:\s*100 900;/', $face), 1);
    check("Inter ($name) shows text straight away while it loads", preg_match('/font-display:\s*swap;/', $face), 1);
    $bytes = is_file($fontDir . '/' . $name) ? file_get_contents($fontDir . '/' . $name) : '';
    check("Inter ($name) is a real WOFF2 font, not an error page", substr($bytes, 0, 4), 'wOF2');
    if (preg_match('/unicode-range:\s*([^;]+);/', $face, $range)) {
        $covered = array_merge($covered, unicodeRanges($range[1]));
    }
}
sort($interFiles);
$shipped = array_map('basename', glob($fontDir . '/*.woff2'));
sort($shipped);
check('growcart.css uses every Inter file that ships, and nothing else', $interFiles, $shipped);
check('Inter has the latin and latin-ext files', $interFiles, ['inter-latin-ext-wght-normal.woff2', 'inter-latin-wght-normal.woff2']);

// The code point of one UTF-8 letter, worked out by hand: CI runs the tests in
// the add-on image, which has no mbstring
$codePoint = function ($char) {
    $bytes = array_values(unpack('C*', $char));
    if (count($bytes) === 1) {
        return $bytes[0];
    }
    if (count($bytes) === 2) {
        return (($bytes[0] & 0x1F) << 6) | ($bytes[1] & 0x3F);
    }
    return (($bytes[0] & 0x0F) << 12) | (($bytes[1] & 0x3F) << 6) | ($bytes[2] & 0x3F);
};
$missing = [];
foreach (['A', 'z', '0', '9', 'ā', 'ē', 'ī', 'ō', 'ū', 'Ā', 'Ē', 'Ī', 'Ō', 'Ū', '…'] as $char) {
    $code = $codePoint($char);
    $hit = false;
    foreach ($covered as [$first, $last]) {
        $hit = $hit || ($code >= $first && $code <= $last);
    }
    if (!$hit) {
        $missing[] = $char;
    }
}
check('Inter covers plain Latin, the macron letters (ā ē ī ō ū and capitals) and the … in "Show more…"', $missing, []);

// Inter comes first, so it's the font people see
check('The font list starts with Inter', preg_match('/--pico-font-family:\s*"Inter",/', $css), 1);

if ($failures > 0) {
    echo "[FAIL] Font tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Font Test Completed Successfully\n";
exit(0);

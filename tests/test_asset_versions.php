<?php
/**
 * Every local script and stylesheet a page loads carries the asset version
 * (?v=<?php echo GRACE_ASSET_VERSION; ?>), which each release bumps, so
 * browsers fetch the new copy after an update instead of keeping an old
 * one. Many page scripts used to load without it (1.2.0 fixed them).
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
const VERSION_SUFFIX = '?v=<?php echo GRACE_ASSET_VERSION; ?>';
// footer.php copies the constant into $assetVersion (with a fallback for
// any page that includes the footer without the header)
const FOOTER_VERSION_SUFFIX = '?v=<?php echo $assetVersion; ?>';

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

/**
 * Local scripts and stylesheets in $code without the version, plus the
 * position of the first versioned one (or null).
 *
 * @return array{missing: string[], firstVersioned: int|null}
 */
function assetTags($code)
{
    preg_match_all('/<script\b[^>]*\bsrc="([^"]+)"|<link\b[^>]*\brel="stylesheet"[^>]*\bhref="([^"]+)"/', $code, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $missing = [];
    $firstVersioned = null;
    foreach ($tags as $tag) {
        [$url, $at] = isset($tag[2]) && $tag[2][1] >= 0 ? $tag[2] : $tag[1];
        if (preg_match('#^((https?:)?//|data:)#', $url)) {
            continue; // not ours
        }
        if (substr($url, -strlen(VERSION_SUFFIX)) === VERSION_SUFFIX
            || substr($url, -strlen(FOOTER_VERSION_SUFFIX)) === FOOTER_VERSION_SUFFIX) {
            $firstVersioned = $firstVersioned ?? $at;
        } else {
            $missing[] = $url;
        }
    }
    return ['missing' => $missing, 'firstVersioned' => $firstVersioned];
}

// The detector itself
check('An unversioned script is found', assetTags('<script src="js/a.js"></script>')['missing'], ['js/a.js']);
check('A versioned script passes', assetTags('<script src="js/a.js' . VERSION_SUFFIX . '"></script>')['missing'], []);
check('An unversioned stylesheet is found', assetTags('<link rel="stylesheet" href="css/b.css">')['missing'], ['css/b.css']);
check('Web addresses and data are left alone', assetTags('<script src="https://x.example/c.js"></script><link rel="stylesheet" href="//x.example/d.css">')['missing'], []);

// Every page
$missing = [];
$beforeHeader = [];
foreach (glob($publicDir . '/*.php') as $file) {
    $name = basename($file);
    $code = file_get_contents($file);
    $found = assetTags($code);
    foreach ($found['missing'] as $url) {
        $missing[] = "$name: $url";
    }
    // The version comes from header.php, so a page has to load it first
    if ($found['firstVersioned'] !== null && !in_array($name, ['header.php', 'footer.php'], true)) {
        $header = preg_match("/require(_once)? 'header\.php';/", $code, $m, PREG_OFFSET_CAPTURE) ? $m[0][1] : null;
        if ($header === null || $header > $found['firstVersioned']) {
            $beforeHeader[] = $name;
        }
    }
}
check('Every local script and stylesheet carries the asset version', $missing, []);
check('...and every page loads header.php (which sets it) first', $beforeHeader, []);
check('header.php sets the asset version', preg_match("/define\('GRACE_ASSET_VERSION', '[^']+'\);/", file_get_contents($publicDir . '/header.php')), 1);
check("footer.php's \$assetVersion is that same version",
    strpos(file_get_contents($publicDir . '/footer.php'), "\$assetVersion = defined('GRACE_ASSET_VERSION') ? GRACE_ASSET_VERSION : '0';") !== false, true);

if ($failures > 0) {
    echo "[FAIL] Asset version tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Asset Version Test Completed Successfully\n";
exit(0);

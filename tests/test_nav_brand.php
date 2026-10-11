<?php
/**
 * Regression tests for the maker's logo in the top menu ("GRACe | by Chill
 * Division", grace_addon/files/general/www/public/nav.php).
 *
 * The logo has to load under Home Assistant ingress (a relative path), stay
 * visible on the dark theme (it's a black wordmark), and not squash the menu:
 * with it, the brand and all four menu items need about 890px to fit on one
 * line, so the menu folds into the hamburger below 960px instead of 768px.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
$nav = file_get_contents($publicDir . '/nav.php');
$css = file_get_contents($publicDir . '/css/growcart.css');

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

// The <img> in the menu (the closing PHP tag of the echo in src doesn't end it)
$found = preg_match('/<img class="nav-maker-logo"(?:\?>|[^>])*>/', $nav, $tag);
check('nav.php shows the maker logo', $found, 1);
$tag = $tag[0] ?? '';
$attr = function ($name) use ($tag) {
    return preg_match('/\s' . $name . '="([^"]*)"/', $tag, $m) ? $m[1] : null;
};
$src = (string) $attr('src');
check('The logo path is relative, so it loads under ingress', preg_match('#^icons/[a-z0-9-]+\.png\?v=<\?php echo GRACE_ASSET_VERSION; \?>$#', $src), 1);
check('The logo has alt text naming the maker', $attr('alt'), 'Chill Division');

// The file it points at
$file = $publicDir . '/' . explode('?', $src)[0];
check('The logo file is in the add-on', is_file($file), true);
$png = is_file($file) ? file_get_contents($file) : '';
check('It is a PNG', substr($png, 0, 8), "\x89PNG\r\n\x1a\n");
$ihdr = strlen($png) >= 26 ? unpack('Nwidth/Nheight/Cdepth/Ctype', substr($png, 16, 10)) : [];
check('It has a transparent background (RGBA)', $ihdr['type'] ?? null, 6);
check('Its width and height match the tag, so the menu does not jump as it loads',
    [(int) $attr('width'), (int) $attr('height')], [$ihdr['width'] ?? 0, $ihdr['height'] ?? 0]);
check('It is small (under 20 KB)', strlen($png) > 0 && strlen($png) < 20 * 1024, true);

// Dark theme, and room in the menu
check('The dark theme turns the black wordmark white',
    preg_match('/\[data-theme="dark"\]\s*\.nav-maker-logo\s*\{[^}]*filter:\s*invert\(1\)/', $css), 1);
check('The menu folds into the hamburger below 960px',
    preg_match('/@media \(max-width: 960px\) \{\s*\.nav-toggle-label \{/', $css), 1);
check('No other breakpoint folds the menu', preg_match_all('/\.nav-toggle-label \{\s*display: block;/', $css), 1);

if ($failures > 0) {
    echo "[FAIL] Nav brand tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Nav Brand Test Completed Successfully\n";
exit(0);

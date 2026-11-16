<?php
/**
 * GRACe's light and dark colours (css/growcart.css) sit on top of Pico's own
 * themes (css/vendor/pico.min.css), so GRACe's theme blocks have to win every
 * colour both of them set.
 *
 * Pico sets its light theme on :root:not([data-theme=dark]), which counts as
 * two classes. growcart.css used a bare [data-theme="light"] (one), so up to
 * 1.3.0 the light theme came out in Pico's colours: blue buttons and links, a
 * white page instead of GRACe's soft green-grey one, and Pico's focus rings.
 * Dark mode only worked because Pico's dark selector is a bare
 * [data-theme=dark] as well. With :root in front, GRACe's blocks tie with
 * Pico's, and growcart.css loads after Pico, so GRACe wins.
 *
 * It also checks the contrast of the colour pairs both themes rely on (text,
 * links, main and outline buttons, and the edges of fields and tick boxes),
 * so a change to the theme colours can't quietly make them hard to read.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
$grace = file_get_contents($publicDir . '/css/growcart.css');
$pico = file_get_contents($publicDir . '/css/vendor/pico.min.css');
$header = file_get_contents($publicDir . '/header.php');

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

/** Split a selector list at its top-level commas. */
function splitSelectors($list)
{
    $parts = [];
    $depth = 0;
    $current = '';
    foreach (str_split($list) as $ch) {
        if ($ch === '(' || $ch === '[') {
            $depth++;
        } elseif ($ch === ')' || $ch === ']') {
            $depth--;
        }
        if ($ch === ',' && $depth === 0) {
            $parts[] = trim($current);
            $current = '';
        } else {
            $current .= $ch;
        }
    }
    if (trim($current) !== '') {
        $parts[] = trim($current);
    }
    return $parts;
}

/**
 * CSS specificity of one selector as [ids, classes, elements]. Attributes and
 * pseudo-classes count as classes. :not(), :is() and :has() count as their
 * most specific argument, and :where() counts as nothing.
 */
function specificity($selector)
{
    $spec = [0, 0, 0];
    $stops = " >+~.#[:(),";
    $len = strlen($selector);
    $i = 0;
    while ($i < $len) {
        $ch = $selector[$i];
        if ($ch === '#' || $ch === '.') {
            $spec[$ch === '#' ? 0 : 1]++;
            $i++;
            $i += strcspn($selector, $stops, $i);
        } elseif ($ch === '[') {
            $spec[1]++;
            $i = strpos($selector, ']', $i) + 1;
        } elseif ($ch === ':') {
            $isElement = substr($selector, $i, 2) === '::';
            $i += $isElement ? 2 : 1;
            $name = substr($selector, $i, strcspn($selector, $stops, $i));
            $i += strlen($name);
            $args = null;
            if ($i < $len && $selector[$i] === '(') {
                $start = $i + 1;
                $depth = 0;
                do {
                    if ($selector[$i] === '(') {
                        $depth++;
                    } elseif ($selector[$i] === ')') {
                        $depth--;
                    }
                    $i++;
                } while ($depth > 0 && $i < $len);
                $args = substr($selector, $start, $i - $start - 1);
            }
            if ($isElement) {
                $spec[2]++;
            } elseif ($args !== null && in_array($name, ['not', 'is', 'has'], true)) {
                $most = [0, 0, 0];
                foreach (splitSelectors($args) as $arg) {
                    $most = max($most, specificity($arg));
                }
                foreach ($most as $k => $v) {
                    $spec[$k] += $v;
                }
            } elseif ($name !== 'where') {
                $spec[1]++;
            }
        } elseif (preg_match('/[a-z]/i', $ch)) {
            $spec[2]++;
            $i += strcspn($selector, $stops, $i);
        } else {
            $i++; // a combinator, a space or *
        }
    }
    return $spec;
}

/**
 * Selectors of every rule that sets the custom property $property, from
 * innermost rule blocks (so rules inside @media count too).
 */
function selectorsSetting($css, $property)
{
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
    $found = [];
    foreach ($rules as $rule) {
        if (preg_match('/(?:^|;)\s*' . preg_quote($property, '/') . '\s*:/', $rule[2])) {
            $found = array_merge($found, splitSelectors(trim($rule[1])));
        }
    }
    return $found;
}

$fmt = function ($spec) {
    return '(' . implode(',', $spec) . ')';
};

// GRACe's theme blocks, found by a colour both themes set
$graceThemes = selectorsSetting($grace, '--pico-primary-background');
sort($graceThemes);
check('growcart.css sets its theme colours on :root[data-theme="dark"] and :root[data-theme="light"]',
    $graceThemes, [':root[data-theme="dark"]', ':root[data-theme="light"]']);

// Pico's theme blocks. Its :host() selectors are for web components, which
// GRACe doesn't use, so they never match a GRACe page.
$picoThemes = array_values(array_filter(selectorsSetting($pico, '--pico-primary-background'), function ($s) {
    return strpos($s, ':host') !== 0;
}));
check("Pico's theme selectors were found", count($picoThemes) > 0, true);
$picoMost = [0, 0, 0];
foreach ($picoThemes as $selector) {
    $picoMost = max($picoMost, specificity($selector));
}
echo "       Pico's most specific theme selector counts as " . $fmt($picoMost) . "\n";

// Every growcart.css rule that sets Pico's colours for one theme has to
// match or beat that, or Pico's value wins
preg_match_all('/([^{}]+)\{([^{}]*)\}/', preg_replace('#/\*.*?\*/#s', '', $grace), $rules, PREG_SET_ORDER);
foreach ($rules as $rule) {
    if (strpos($rule[1], 'data-theme') === false || !preg_match('/(?:^|;)\s*--pico-[a-z-]+\s*:/', $rule[2])) {
        continue;
    }
    foreach (splitSelectors(trim($rule[1])) as $selector) {
        $spec = specificity($selector);
        check("$selector " . $fmt($spec) . " is at least as specific as Pico's themes " . $fmt($picoMost),
            $spec >= $picoMost, true);
    }
}

// A tie goes to the stylesheet that loads last
$picoAt = strpos($header, 'css/vendor/pico.min.css');
$graceAt = strpos($header, 'css/growcart.css');
check('header.php loads Pico and growcart.css', $picoAt !== false && $graceAt !== false, true);
check('growcart.css loads after Pico, so it wins a tie', $picoAt !== false && $graceAt > $picoAt, true);

// Contrast of the colour pairs both themes rely on, worked out from the theme
// blocks: 4.5:1 for text, 3:1 for the edges of fields and tick boxes. White on
// GRACe's brighter green (#16a34a) is only 3.3:1, so the main buttons use
// #15803d, and the field edges are dark enough to see on a white card.
function themeTokens($css, $theme)
{
    $css = preg_replace('#/\*.*?\*/#s', '', $css);
    if (!preg_match('/:root\[data-theme="' . $theme . '"\]\s*\{([^}]*)\}/', $css, $m)) {
        return [];
    }
    $tokens = [];
    foreach (explode(';', $m[1]) as $declaration) {
        if (preg_match('/^\s*(--[a-z0-9-]+)\s*:\s*(.+?)\s*$/s', $declaration, $d)) {
            $tokens[$d[1]] = $d[2];
        }
    }
    return $tokens;
}

/** A token's colour as [r, g, b, alpha], following var() to other tokens. */
function tokenColour(array $tokens, $name, $depth = 0)
{
    $value = $tokens[$name] ?? null;
    if ($value === null || $depth > 10) {
        return null;
    }
    if (preg_match('/^var\((--[a-z0-9-]+)\)$/', $value, $m)) {
        return tokenColour($tokens, $m[1], $depth + 1);
    }
    if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/i', $value, $m)) {
        return [hexdec($m[1]), hexdec($m[2]), hexdec($m[3]), 1.0];
    }
    if (preg_match('/^rgba?\(\s*([\d.]+)\s*,\s*([\d.]+)\s*,\s*([\d.]+)\s*(?:,\s*([\d.]+)\s*)?\)$/', $value, $m)) {
        return [(float) $m[1], (float) $m[2], (float) $m[3], isset($m[4]) ? (float) $m[4] : 1.0];
    }
    return null;
}

function luminance(array $c)
{
    $channel = function ($v) {
        $v /= 255;
        return $v <= 0.04045 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * $channel($c[0]) + 0.7152 * $channel($c[1]) + 0.0722 * $channel($c[2]);
}

function contrastRatio(array $front, array $back)
{
    // A see-through colour is mixed with what's behind it first
    $a = $front[3];
    $front = [$front[0] * $a + $back[0] * (1 - $a), $front[1] * $a + $back[1] * (1 - $a), $front[2] * $a + $back[2] * (1 - $a)];
    $l1 = luminance($front);
    $l2 = luminance($back);
    return (max($l1, $l2) + 0.05) / (min($l1, $l2) + 0.05);
}

$pairs = [
    // what, colour, background, minimum
    ['Text on the page', '--pico-color', '--g-bg', 4.5],
    ['Muted text on the page', '--pico-muted-color', '--g-bg', 4.5],
    ['Muted text on a card', '--pico-muted-color', '--g-surface', 4.5],
    ['Links on the page', '--pico-primary', '--g-bg', 4.5],
    ['Links on a card', '--pico-primary', '--g-surface', 4.5],
    ['Text on a main button', '--pico-primary-inverse', '--pico-primary-background', 4.5],
    ['Text on a main button under the pointer', '--pico-primary-inverse', '--pico-primary-hover-background', 4.5],
    ['Outline button text on a card', '--pico-secondary', '--g-surface', 4.5],
    ['Outline button text under the pointer', '--pico-secondary-hover', '--g-surface', 4.5],
    ['Field and tick box edges on a card', '--pico-form-element-border-color', '--g-surface', 3.0],
    ['Field and tick box edges on the page', '--pico-form-element-border-color', '--g-bg', 3.0],
    ['Field and tick box edges on a raised panel', '--pico-form-element-border-color', '--g-surface-raised', 3.0],
    ['The edge of the field you are in', '--pico-form-element-active-border-color', '--g-surface', 3.0],
    ['The version number in the menu bar', '--g-accent-text', '--g-surface', 4.5],
];
foreach (['light', 'dark'] as $theme) {
    $tokens = themeTokens($grace, $theme);
    foreach ($pairs as [$what, $front, $back, $minimum]) {
        $f = tokenColour($tokens, $front);
        $b = tokenColour($tokens, $back);
        if ($f === null || $b === null) {
            check("$theme: $what ($front on $back) are colours GRACe sets", false, true);
            continue;
        }
        $ratio = contrastRatio($f, $b);
        check(sprintf('%s: %s is %.2f:1, at least %.1f:1', ucfirst($theme), $what, $ratio, $minimum), $ratio >= $minimum, true);
    }
}

// Small coloured text on its own tint (1.4.0): badges, the menu item you're
// on, status messages and the licence banner. The tint is see-through, so
// it's mixed with the card or page under it first. Before 1.4.0 the light
// theme used the tints' own colours for the text: 2.8 to 4.4:1.
$tinted = [
    ['Green badges, the menu item you are on and success messages', '--g-accent-text', '--g-accent-soft'],
    ['Amber badges', '--g-warning-text', '--g-warning-soft'],
    ['Red badges, error messages and the licence banner', '--g-danger-text', '--g-danger-soft'],
    ['Blue badges', '--g-info-text', '--g-info-soft'],
];
foreach (['light', 'dark'] as $theme) {
    $tokens = themeTokens($grace, $theme);
    foreach ($tinted as [$what, $front, $tint]) {
        foreach (['--g-surface' => 'a card', '--g-bg' => 'the page'] as $base => $where) {
            $f = tokenColour($tokens, $front);
            $t = tokenColour($tokens, $tint);
            $b = tokenColour($tokens, $base);
            if ($f === null || $t === null || $b === null) {
                check("$theme: $what ($front on $tint) are colours GRACe sets", false, true);
                continue;
            }
            $a = $t[3];
            $under = [$t[0] * $a + $b[0] * (1 - $a), $t[1] * $a + $b[1] * (1 - $a), $t[2] * $a + $b[2] * (1 - $a), 1.0];
            $ratio = contrastRatio($f, $under);
            check(sprintf('%s: %s, on their tint over %s, are %.2f:1, at least 4.5:1', ucfirst($theme), $what, $where, $ratio), $ratio >= 4.5, true);
        }
    }
}
$cssNoComments = preg_replace('#/\*.*?\*/#s', '', $grace);
foreach (['.badge--growing' => '--g-accent-text', '.badge--drying' => '--g-warning-text', '.badge--destroyed' => '--g-danger-text',
          '.badge--sent' => '--g-info-text', '.nav-menu li a.active' => '--g-accent-text', '.status-message.success' => '--g-accent-text',
          '.status-message.error' => '--g-danger-text', '.nav-brand-sub small' => '--g-accent-text'] as $selector => $token) {
    check("$selector uses $token for its text",
        (bool) preg_match('/(^|\n)' . preg_quote($selector, '/') . '\s*\{[^}]*color:\s*var\(' . preg_quote($token, '/') . '\)/', $cssNoComments), true);
}

// The specificity counting itself
check('Counting: [data-theme="light"] is (0,1,0)', specificity('[data-theme="light"]'), [0, 1, 0]);
check('Counting: :root:not([data-theme=dark]) is (0,2,0)', specificity(':root:not([data-theme=dark])'), [0, 2, 0]);
check('Counting: :where(:root) a.btn#go::after is (1,1,2)', specificity(':where(:root) a.btn#go::after'), [1, 1, 2]);
check('Counting: the old light selector lost to Pico', specificity('[data-theme="light"]') >= specificity(':root:not([data-theme=dark])'), false);

if ($failures > 0) {
    echo "[FAIL] Theme override tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Theme Override Test Completed Successfully\n";
exit(0);

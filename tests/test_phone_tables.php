<?php
/**
 * Tables on a phone. A table with more columns than fit the screen scrolls
 * sideways (the .table-wrap around it), but up to 1.3.0 the browser also
 * squeezed every column down to its longest word. A date broke over three
 * lines, a license number broke at its hyphens, and Last month's report ran
 * a company's address down the screen a word at a time: eight lines, 216px,
 * for one entry.
 *
 * Under 768px wide, a table marked wide-table keeps each value on one line
 * and lets its headers wrap instead. A cell-long cell (an address, a route,
 * a reason) wraps at a readable width, and a row with one cell across the
 * table ("Nothing to report") wraps as usual. This checks that:
 *
 * - growcart.css has those rules, and only under 768px, so tablets and
 *   desktops lay tables out as before
 * - the tables too wide for a phone carry wide-table, and their long cells
 *   carry cell-long
 *
 * Tables that fit a phone, or nearly do, stay unmarked (the file lists,
 * Receive plants' recent entries). Keeping their values on one line would
 * make them start scrolling sideways, or scroll further.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
$css = preg_replace('#/\*.*?\*/#s', '', file_get_contents($publicDir . '/css/growcart.css'));
$phoneQuery = '@media (max-width: 767.98px)';

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
 * Every @media block in $css with the query $query, as
 * [whole block, the rules inside it].
 */
function mediaBlocks($css, $query)
{
    $blocks = [];
    $from = 0;
    while (($start = strpos($css, $query, $from)) !== false) {
        $open = strpos($css, '{', $start);
        $depth = 0;
        for ($i = $open; $i < strlen($css); $i++) {
            if ($css[$i] === '{') {
                $depth++;
            } elseif ($css[$i] === '}' && --$depth === 0) {
                break;
            }
        }
        $blocks[] = [substr($css, $start, $i - $start + 1), substr($css, $open + 1, $i - $open - 1)];
        $from = $i;
    }
    return $blocks;
}

/** The rules in $css as selector => declarations, one entry per selector in a list. */
function cssRules($css)
{
    preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $m, PREG_SET_ORDER);
    $rules = [];
    foreach ($m as [, $selectors, $body]) {
        foreach (explode(',', $selectors) as $selector) {
            $selector = preg_replace('/\s+/', ' ', trim($selector));
            $rules[$selector] = ($rules[$selector] ?? '') . $body;
        }
    }
    return $rules;
}

/** The value a block of declarations gives $property, or null. */
function declared($body, $property)
{
    return preg_match('/(?:^|[;{\s])' . preg_quote($property, '/') . '\s*:\s*([^;]+)/', $body, $m) ? trim($m[1]) : null;
}

// The detectors themselves
$sample = 'a{b:c} @media (max-width: 767.98px) { .x, .y td { white-space: nowrap; } .z{min-width:17rem} } d{e:f}';
$found = mediaBlocks($sample, '@media (max-width: 767.98px)');
check('Finding a @media block and the rules inside it', count($found) === 1 ? trim($found[0][1]) : null, '.x, .y td { white-space: nowrap; } .z{min-width:17rem}');
check('Splitting a selector list into its rules', array_keys(cssRules($found[0][1])), ['.x', '.y td', '.z']);
check('Reading a declaration', declared('min-width:17rem', 'min-width'), '17rem');
check('...and not mistaking max-width for width', declared('max-width: 5rem', 'width'), null);

// The rules, under 768px only
$blocks = mediaBlocks($css, $phoneQuery);
$inside = '';
$outside = $css;
foreach ($blocks as [$whole, $body]) {
    if (strpos($body, 'wide-table') !== false) {
        $inside .= $body;
        $outside = str_replace($whole, '', $outside);
    }
}
check("growcart.css has the wide-table rules under $phoneQuery", $inside !== '', true);
$rules = cssRules($inside);
check('Headers of a wide table can wrap', declared($rules['.table-wrap .wide-table th'] ?? '', 'white-space'), 'normal');
check('Each value in a wide table stays on one line', declared($rules['.table-wrap .wide-table td'] ?? '', 'white-space'), 'nowrap');
check('A row with one cell across the table wraps as usual', declared($rules['.table-wrap .wide-table td[colspan]'] ?? '', 'white-space'), 'normal');
check('A long cell wraps', declared($rules['.table-wrap .wide-table td.cell-long'] ?? '', 'white-space'), 'normal');
$minWidth = declared($rules['.table-wrap .wide-table td.cell-long'] ?? '', 'min-width');
check("...at a readable width, 12rem to 24rem (it's $minWidth)",
    preg_match('/^(\d+(?:\.\d+)?)rem$/', (string) $minWidth, $m) === 1 && $m[1] >= 12 && $m[1] <= 24, true);
check('Tablets and desktops are left alone: no wide-table or cell-long rule outside the phone block',
    [strpos($outside, 'wide-table'), strpos($outside, 'cell-long')], [false, false]);

// The tables too wide for a phone, and their long cells
$wideTables = [
    'last_months_flower_transactions.php' => 2, // flower and plant transactions
    'this_months_flower_transactions.php' => 2,
    'annual_stocktake.php' => 2,                // plant and flower stocktake
    'harvest_plants.php' => 2,                  // the plants to pick from, and recent entries
    'list_all_genetics.php' => 1,
    'verified_companies.php' => 1,
    'complete_manifest.php' => 1,               // manifests awaiting completion
    'chain_of_custody_documents.php' => 1,      // shipment exchanges
    'record_dry_weight.php' => 1,               // recent entries
];
foreach ($wideTables as $file => $count) {
    preg_match_all('/<table\b[^>]*\bclass="[^"]*\bwide-table\b[^"]*"/', file_get_contents("$publicDir/$file"), $m);
    check("$file: $count table(s) marked wide-table", count($m[0]), $count);
}

$reportJs = file_get_contents($publicDir . '/js/transactions.js');
check("The monthly reports' company and address cells are long cells (flower and plant)",
    substr_count($reportJs, "companyCell.className = 'cell-long';"), 2);
check("Chain of Custody's route is a long cell",
    strpos(file_get_contents($publicDir . '/chain_of_custody_documents.php'), '<td class="cell-long"><?php echo htmlspecialchars(manifestRouteLabel($manifest)); ?></td>') !== false, true);
check("Record dry weight's reason is a long cell",
    strpos(file_get_contents($publicDir . '/record_dry_weight.php'), '<td class="cell-long"><?php echo htmlspecialchars($reason); ?></td>') !== false, true);

if ($failures > 0) {
    echo "[FAIL] Phone table tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Phone Tables Test Completed Successfully\n";
exit(0);

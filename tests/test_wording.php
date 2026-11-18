<?php
/**
 * Regression tests for GRACe's wording and look staying consistent (1.4.0):
 *
 * - Each card on Tracking, Reporting and Administration opens a page with
 *   the same name, as its heading and in the browser tab.
 * - Headings, buttons, labels, table headers and cards are in sentence case,
 *   the Chill Division brand guide's rule ("Plants and flower", not "Plants
 *   And Flower"). Names keep their capitals: GRACe, Form D, SOPs and so on.
 * - Form labels don't end in a colon.
 * - New Zealand spelling: a licence (the noun) on screen, never a "license".
 * - No semicolons or long dashes in what growers read.
 * - Pages are marked as NZ English, so browsers spell-check the NZ way.
 * - One date style on screen, 26/11/2026, and totals without a stray ".00".
 * - Small things growers saw: "1 plant", the logo's minimum size, number
 *   columns that line up, and Tracking / Reporting holding the right pages.
 * - Home Assistant's own word: growers read "app" (Settings → Apps), since
 *   Home Assistant renamed add-ons to apps in 2026.2.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';

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

function page($file)
{
    global $publicDir;
    return is_readable("$publicDir/$file") ? file_get_contents("$publicDir/$file") : '';
}

/** Text with tags and runs of white space taken out. */
function plain($html)
{
    return trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8')));
}

// Words that keep a capital mid-sentence: names, acronyms and the statuses
// GRACe stores ("Harvested - Drying" is a value in the ledger)
const KEEPS_CAPITAL = ['GRACe', 'Chill', 'Division', 'Form', 'D', 'CoC', 'SOPs', 'SOP', 'MCA', 'Medicinal', 'Cannabis',
    'Agency', 'Home', 'Assistant', 'Police', 'NZ', 'PDF', 'Harvested', 'Destroyed', 'Drying', 'Growing', 'Sent',
    'In', 'Progress', 'Inter', 'Māori'];

/** Words after the first that are capitalised but shouldn't be. */
function titleCaseWords($text)
{
    $text = preg_replace("/'[^']*'|\"[^\"]*\"/", '', $text); // quoted values keep their case
    $bad = [];
    // "Harvest / Destroy / Send": each part after a slash starts afresh
    foreach (preg_split('#\s/\s#', $text) as $part) {
        $words = preg_split('/[^\p{L}\']+/u', trim($part), -1, PREG_SPLIT_NO_EMPTY);
        foreach (array_slice($words, 1) as $word) {
            $first = mb_substr_safe($word);
            if ($first !== mb_strtolower_safe($first) && !in_array($word, KEEPS_CAPITAL, true) && $word !== strtoupper($word)) {
                $bad[] = $word;
            }
        }
    }
    return $bad;
}

// The add-on has no mbstring: plain string functions only (AGENTS.md section 3)
function mb_substr_safe($word)
{
    return preg_match('/^./u', $word, $m) ? $m[0] : '';
}
function mb_strtolower_safe($char)
{
    return strtolower($char) === $char ? $char : strtolower($char);
}

$pages = array_map('basename', glob("$publicDir/*.php"));

// --- Cards and the pages they open --------------------------------------------------
$checkedCards = 0;
foreach (['tracking.php', 'reporting.php', 'administration.php'] as $hub) {
    preg_match_all('#<a class="nav-card" href="([a-z_]+\.php)[^"]*">.*?<strong>(.*?)</strong>#s', page($hub), $cards, PREG_SET_ORDER);
    foreach ($cards as [, $target, $card]) {
        $card = plain($card);
        if ($target === 'show_database.php') {
            continue; // Download backup: a file, not a page
        }
        $source = page($target);
        preg_match('#<h1[^>]*>(.*?)</h1>#s', $source, $h1);
        // A heading filled in by PHP (the materials-out reports name the company) is checked by its tab title only
        $heading = isset($h1[1]) && strpos($h1[1], '<?') === false ? plain($h1[1]) : null;
        preg_match_all("#\\\$pageTitle\s*=\s*(['\"])(.*?)\\1#", $source, $titles);
        $tabTitles = array_map(function ($t) {
            return preg_replace('/^GRACe - /', '', stripcslashes($t));
        }, $titles[2]);
        if ($heading !== null) {
            check("\"$card\" opens a page headed \"$card\" ($target)", strtolower($heading), strtolower($card));
        }
        check("...and the browser tab says \"$card\" too", in_array(strtolower($card), array_map('strtolower', $tabTitles), true)
            || count(array_filter($tabTitles, function ($t) use ($card) {
                return stripos($t, $card) === 0;
            })) > 0, true);
        $checkedCards++;
    }
}
check('All three hubs have their cards checked', $checkedCards >= 20, true);

// --- Sentence case, labels without colons, NZ spelling --------------------------------
$titleCase = [];
$colons = [];
$licenses = [];
$punctuation = [];
foreach ($pages as $file) {
    if ($file === 'process_shipping_manifest.php') {
        continue;
    }
    $source = page($file);
    preg_match_all('#<(h[1-4]|label|button|th|strong|summary|legend)\b[^>]*>([^<]{2,})<#', $source, $tags, PREG_SET_ORDER);
    foreach ($tags as [, $tag, $text]) {
        $text = trim($text);
        if ($text === '' || strpos($text, '<?') !== false || $text[0] === '$') {
            continue;
        }
        // Only short things: a sentence's later capitals are names or new sentences
        if (str_word_count($text) <= 7 && substr($text, -1) !== '.' && titleCaseWords($text)) {
            $titleCase[] = "$file <$tag> $text";
        }
        if ($tag === 'label' && substr($text, -1) === ':') {
            $colons[] = "$file $text";
        }
    }
    // Text on the page, outside PHP and tag attributes
    $visible = preg_replace(['#<\?php.*?(\?>|\z)#s', '#<\?=.*?\?>#s', '#<script\b.*?</script>#s', '#<style\b.*?</style>#s', '#<[^>]*>#'], ' ', $source);
    if (preg_match_all('/\blicenses?\b/i', $visible, $m)) {
        $licenses[] = "$file: " . implode(', ', $m[0]);
    }
    $decoded = html_entity_decode($visible, ENT_QUOTES, 'UTF-8');
    if (strpos($decoded, ';') !== false || strpos($decoded, "\u{2014}") !== false || strpos($decoded, "\u{2013}") !== false) {
        $punctuation[] = $file;
    }
}
foreach (glob("$publicDir/js/*.js") as $js) {
    $source = file_get_contents($js);
    // Labels and buttons written by scripts
    if (preg_match_all("/label: '([^']*)'/", $source, $m)) {
        foreach ($m[1] as $label) {
            if (preg_match('/\blicenses?\b/i', $label)) {
                $licenses[] = basename($js) . ": $label";
            }
            if (titleCaseWords($label)) {
                $titleCase[] = basename($js) . " label $label";
            }
        }
    }
    if (preg_match_all('#>([A-Z][a-z]+(?: [A-Z][a-z]+)+)</button>#', $source, $m)) {
        foreach ($m[1] as $button) {
            if (titleCaseWords($button)) {
                $titleCase[] = basename($js) . " button $button";
            }
        }
    }
}
check('Headings, buttons, labels, table headers and cards are in sentence case', $titleCase, []);
check('Form labels have no colon at the end', $colons, []);
check('A licence (the noun) is spelt the NZ way on every page', $licenses, []);
check('On-screen text has no semicolons or long dashes (the brand guide, and AGENTS.md section 5)', $punctuation, []);
check('Pages say they are in NZ English', strpos(page('header.php'), '<html lang="en-NZ"') !== false, true);

// --- Dates and numbers ---------------------------------------------------------------
$dashFormats = [];
foreach ($pages as $file) {
    if (preg_match("/format\('d-m-Y|date\('d-m-Y|'j M Y'/", page($file))) {
        $dashFormats[] = $file;
    }
}
check('No page writes dates as 26-11-2026 or 26 Nov 2026 in a table', $dashFormats, []);
$rawDates = [];
foreach ($pages as $file) {
    // A stored date shown as it is reads 2026-11-26 20:53:31: it has to go through formatLedgerDateTime()
    if (preg_match_all("/htmlspecialchars\(\\\$[a-z]+\['([a-z_]*(?:date|_at)[a-z_]*)'\]\)/", page($file), $m)) {
        foreach ($m[1] as $field) {
            $rawDates[] = "$file: $field";
        }
    }
}
check('No page shows a stored date as it is (2026-11-26 20:53:31)', $rawDates, []);
check('The Dashboard shows licence expiry dates as 26/11/2026', strpos(page('dashboard.php'), "formatLedgerDateTime(\$doc['expiry_date'])") !== false, true);
check('...and so does the licence banner on every page', strpos(page('nav.php'), "formatLedgerDateTime(\$doc['expiry_date'])") !== false, true);
check('...and the document lists', strpos(page('fetch_files.php'), "format('d/m/Y H:i')") !== false
    && strpos(page('js/documents.js'), 'formatLedgerDate(file.expiry_date)') !== false, true);
require_once "$publicDir/format_lib.php";
check('formatLedgerDateTime() lives in format_lib.php', formatLedgerDateTime('2026-11-26'), '26/11/2026');

$transactions = page('js/transactions.js');
check('Materials out totals have no ".00": 5628, not 5628.00 (1.3.x)', strpos($transactions, 'toFixed(2)') === false
    && strpos($transactions, 'formatReportGrams(flowerReportTotal)') !== false, true);
check('...and the stat card above them reads like the Dashboard: 5,628 g', strpos($transactions, 'formatGrams(totalWeight)') !== false, true);
check('The annual stocktake totals have no ".00" either', strpos(page('annual_stocktake.php'), 'toFixed(2)') === false, true);
check('The Dashboard says "1 plant", not "1 plants"', strpos(page('dashboard.php'), "== 1 ? ' plant' : ' plants'") !== false, true);

$css = preg_replace('#/\*.*?\*/#s', '', page('css/growcart.css'));
check('Number columns are right-aligned with tabular figures, so they line up',
    (bool) preg_match('/th\.num,\s*td\.num\s*\{[^}]*font-variant-numeric:\s*tabular-nums;[^}]*text-align:\s*right;/', $css), true);
$numCols = 0;
foreach (['current_plants.php', 'current_dried_flower.php', 'annual_stocktake.php', 'this_months_flower_transactions.php',
          'last_months_flower_transactions.php', 'list_all_genetics.php', 'harvest_plants.php', 'receive_genetics.php', 'record_dry_weight.php'] as $file) {
    $numCols += substr_count(page($file), 'class="num"');
}
check('Their number columns are marked', $numCols >= 20, true);
check('The Chill Division logo is at least 34px tall, the brand guide\'s smallest on screen',
    (bool) preg_match('/\.nav-maker-logo\s*\{[^}]*height:\s*max\(1\.9rem,\s*34px\)/', $css), true);

// --- Where things live --------------------------------------------------------------
$nav = page('nav.php');
check('The menu says Tracking (it was Plant Tracking)', strpos($nav, "'label' => 'Tracking'") !== false && strpos($nav, 'Plant Tracking') === false, true);
check('All plants is under Reporting, beside Current plants and Current dried flower',
    strpos(page('reporting.php'), 'href="list_all_genetics.php"') !== false && strpos(page('tracking.php'), 'list_all_genetics.php') === false, true);
check('...and the menu highlights Reporting on it',
    (bool) preg_match("/'reporting\\.php' => \\[.*?'pages' => \\[[^\\]]*'list_all_genetics'/s", $nav), true);

// --- The entry pages ------------------------------------------------------------------
$receive = page('receive_genetics.php');
check('Receive plants or take clones asks for the genetics first, then how many',
    strpos($receive, 'for="geneticsName"') !== false && strpos($receive, 'for="geneticsName"') < strpos($receive, 'for="plantCount"'), true);
check('...in a card as wide as the recent entries under it', strpos($receive, 'form-card form-card--wide') !== false
    && strpos($receive, 'entry-fields') !== false, true);
check('Record dry weight change is laid out the same way', strpos(page('record_dry_weight.php'), 'form-card form-card--wide') !== false
    && strpos(page('record_dry_weight.php'), 'entry-fields--grid') !== false, true);

// --- Home Assistant's own word ------------------------------------------------------
// Home Assistant renamed add-ons to apps in 2026.2. Growers read "app" and
// Settings → Apps. "add-on" stays for the technical side they never see (the
// add-on image, config.yaml, the grace_addon slug), so code and comments are left out.
$addOn = [];
foreach ($pages as $file) {
    $visible = preg_replace(['#<\?php.*?(\?>|\z)#s', '#<\?=.*?\?>#s', '#<script\b.*?</script>#s', '#<style\b.*?</style>#s', '#<[^>]*>#'], ' ', page($file));
    if (preg_match('/\badd-?ons?\b/i', $visible)) {
        $addOn[] = $file;
    }
}
$readme = file_get_contents(__DIR__ . '/../README.md');
$forGrowers = [
    'grace_addon/DOCS.md' => file_get_contents(__DIR__ . '/../grace_addon/DOCS.md'),
    'README.md' => strstr($readme, '## For developers', true) ?: $readme,
    'repository.yaml' => file_get_contents(__DIR__ . '/../repository.yaml'),
];
foreach ($forGrowers as $name => $text) {
    // Links can say addon (my.home-assistant.io kept its old link names), and
    // one sentence for older versions says what apps used to be called
    $text = preg_replace(['#https?://[^\s)]+#', '/[^.\n]*2026\.2[^.\n]*\./'], '', $text);
    if (preg_match_all('/\badd-?ons?\b/i', $text, $m)) {
        $addOn[] = "$name: " . implode(', ', array_unique($m[0]));
    }
}
check('Growers read "app", Home Assistant\'s word since 2026.2, not "add-on"', $addOn, []);
check('The install steps go to Settings -> Apps, with the one-click link',
    strpos($readme, 'Settings -> Apps') !== false && strpos($readme, 'supervisor_add_addon_repository/?repository_url=') !== false, true);
check('...and the user guide tells older versions what apps used to be called',
    strpos($forGrowers['grace_addon/DOCS.md'], 'called add-ons apps since 2026.2') !== false, true);

if ($failures > 0) {
    echo "[FAIL] Wording tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Wording Test Completed Successfully\n";
exit(0);

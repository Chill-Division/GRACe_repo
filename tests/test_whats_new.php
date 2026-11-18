<?php
/**
 * Regression tests for "What's new" (1.1.0), whats_new_lib.php.
 *
 * After an update, the first person to open GRACe sees the update notes from
 * grace_addon/CHANGELOG.md, once for the whole system (not once per browser).
 * - New installs never see it.
 * - Installs upgraded from a version that didn't have this feature still see
 *   the latest notes, and only those.
 * - An install that missed a version's notes (it skipped the version, or
 *   nobody opened GRACe before the next update) sees them too, newest first
 *   (1.3.1).
 */

define('GRACE_TEST_MODE', true);

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
$repoRoot = __DIR__ . '/..';
require_once $publicDir . '/init_db.php';
if (file_exists($publicDir . '/whats_new_lib.php')) {
    require_once $publicDir . '/whats_new_lib.php';
}

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

function source($dir, $file)
{
    return file_exists($dir . '/' . $file) ? file_get_contents($dir . '/' . $file) : '';
}

function freshDb()
{
    $path = tempnam(sys_get_temp_dir(), 'grace_whatsnew_') . '.db';
    return [$path, initializeDatabase($path)];
}

foreach (['changelogSections', 'latestChangelogSection', 'renderChangelogMarkdown', 'renderChangelogSections', 'whatsNewReleaseDate', 'recordWhatsNewBaseline', 'whatsNewPending', 'markWhatsNewSeen', 'unseenChangelogSections', 'whatsNewToShow'] as $function) {
    if (!function_exists($function)) {
        check("whats_new_lib.php provides $function()", false, true);
    }
}
if ($failures > 0) {
    echo "[FAIL] What's new tests: $failures failure(s)\n";
    exit(1);
}

// --- Reading the changelog --------------------------------------------------------
$sample = "## [1.2.0] - 2026-12-01\n"
    . "A big one.\n\n"
    . "### What's new\n"
    . "- **Bold thing** does `code` & <b>tags</b>\n"
    . "- Second thing\n\n"
    . "### Fixes\n"
    . "- A fix\n\n"
    . "## [1.1.0] - 2026-09-24\n"
    . "- Something from the version before\n";

$section = latestChangelogSection($sample);
check('The newest version is the one at the top', [$section['version'] ?? null, $section['date'] ?? null], ['1.2.0', '2026-12-01']);
check('It stops where the previous version starts', in_array('- Something from the version before', $section['lines'] ?? [], true), false);

$html = renderChangelogMarkdown($section['lines']);
check('Headings, lists and bold become HTML, and anything else is escaped', $html,
    '<p>A big one.</p>'
    . '<h4>What&#039;s new</h4>'
    . '<ul><li><strong>Bold thing</strong> does <code>code</code> &amp; &lt;b&gt;tags&lt;/b&gt;</li><li>Second thing</li></ul>'
    . '<h4>Fixes</h4>'
    . '<ul><li>A fix</li></ul>');
check('A changelog with no versions in it gives nothing', latestChangelogSection("# Changelog\n\nNothing yet.\n"), null);
check('An empty changelog gives nothing', latestChangelogSection(''), null);

$all = changelogSections($sample);
check('Every version is read, in the file\'s order', array_column($all, 'version'), ['1.2.0', '1.1.0']);
check('...each with only its own lines', $all[1]['lines'] ?? null, ['- Something from the version before']);
check('...and the first is the same as the latest section', $all[0] ?? null, $section);
check('No versions means an empty list', changelogSections("# Changelog\n"), []);

check('Release dates read the way growers write them', [whatsNewReleaseDate('2026-11-16'), whatsNewReleaseDate(null), whatsNewReleaseDate('soon')], ['16 November 2026', null, 'soon']);
check('Several versions\' notes are labelled, newest first, with their headings one level down',
    renderChangelogSections($all),
    '<div class="whats-new-version"><h4 class="whats-new-version-label">GRACe 1.2.0, released 1 December 2026</h4>'
    . '<p>A big one.</p>'
    . '<h5>What&#039;s new</h5>'
    . '<ul><li><strong>Bold thing</strong> does <code>code</code> &amp; &lt;b&gt;tags&lt;/b&gt;</li><li>Second thing</li></ul>'
    . '<h5>Fixes</h5>'
    . '<ul><li>A fix</li></ul></div>'
    . '<div class="whats-new-version"><h4 class="whats-new-version-label">GRACe 1.1.0, released 24 September 2026</h4>'
    . '<ul><li>Something from the version before</li></ul></div>');
check('One version\'s notes look the same as before, with no label', renderChangelogSections([$section]), $html);

// --- The real changelog -------------------------------------------------------------
$real = latestChangelogSection();
preg_match('/version: "([^"]+)"/', source($repoRoot, 'grace_addon/config.yaml'), $config);
check('GRACe finds its own changelog', $real !== null, true);
check('Its newest section is the version in config.yaml', $real['version'] ?? null, $config[1] ?? 'unknown');
$realHtml = renderChangelogMarkdown($real['lines'] ?? []);
check('The real notes render with no markdown markers left over',
    $realHtml !== '' && strpos($realHtml, '**') === false && strpos($realHtml, '###') === false, true);
$realVersions = array_column(changelogSections(), 'version');
$newestFirst = $realVersions;
usort($newestFirst, function ($a, $b) {
    return version_compare($b, $a);
});
check('The real changelog lists its versions newest first, each once (missed notes are picked by version)',
    [$realVersions, count(array_unique($realVersions))], [$newestFirst, count($realVersions)]);

// --- New installs never see it ---------------------------------------------------------
[$pathA, $pdo] = freshDb();
performMigrations($pdo);
check('A new install starts up to date: nothing to show', whatsNewPending($pdo), false);
check('...and the page gets no pop-up', whatsNewToShow($pdo), null);
$pdo->exec("INSERT INTO OwnCompany (company_name, company_license_number) VALUES ('Grower Ltd', 'LIC-1')");
performMigrations($pdo);
check('Setting up the company afterwards doesn\'t make it appear', whatsNewPending($pdo), false);
check('The next update shows its notes once', whatsNewPending($pdo, '99.0.0'), true);
@unlink($pathA);

// --- Upgrading an install that was in use --------------------------------------------------
[$pathB, $pdo] = freshDb();
$pdo->exec("INSERT INTO OwnCompany (company_name, company_license_number) VALUES ('Grower Ltd', 'LIC-1')");
performMigrations($pdo);
$current = currentGraceVersion();
check('An install upgraded from a version without What\'s new sees the latest notes', whatsNewPending($pdo), true);
$shown = whatsNewToShow($pdo);
check('...for the version it is now running', $shown['version'] ?? null, $current);
check('...rendered as HTML', strpos($shown['html'] ?? '', '<') === 0, true);

check('Dismissing notes for another version changes nothing', [markWhatsNewSeen($pdo, '0.9.0'), whatsNewPending($pdo)], [false, true]);
check('Once someone has seen them, they\'re marked seen', markWhatsNewSeen($pdo, $current), true);
check('...so nobody sees them again', whatsNewPending($pdo), false);
$otherBrowser = new PDO('sqlite:' . $pathB);
check('...on any device (it\'s stored in the database, not the browser)', whatsNewPending($otherBrowser), false);
performMigrations($pdo);
check('...and later page loads don\'t reset it', whatsNewPending($pdo), false);
@unlink($pathB);

[$pathC, $pdo] = freshDb();
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");
$pdo->exec("INSERT INTO Plants (genetics_id, status, date_created) VALUES (1, 'Growing', '2026-01-01 10:00:00')");
performMigrations($pdo);
check('Plants in the ledger count as in use too, even without company details', whatsNewPending($pdo), true);
@unlink($pathC);

// --- Catching up on notes nobody saw ----------------------------------------------------------
$three = "## [1.3.0] - 2026-12-10\n"
    . "### Minor bug fixes and reliability improvements\n"
    . "- Newest fix\n\n"
    . $sample;
[$pathD, $pdo] = freshDb();
performMigrations($pdo);
$setSeen = function ($value) use ($pdo) {
    $pdo->prepare("UPDATE Settings SET value = ? WHERE name = 'whatsNewSeenVersion'")->execute([$value]);
};

$setSeen('1.2.0');
$shown = whatsNewToShow($pdo, $three);
check('Seen the version before: just the newest notes, with no label (as before 1.3.1)',
    [$shown['version'] ?? null, $shown['html'] ?? null], ['1.3.0', renderChangelogMarkdown(latestChangelogSection($three)['lines'])]);

$setSeen('1.1.0');
$shown = whatsNewToShow($pdo, $three);
$catchUp = $shown['html'] ?? '';
check('Missed a version: its notes come too, after the newest',
    [$shown['version'] ?? null, array_column(unseenChangelogSections($pdo, $three), 'version')], ['1.3.0', ['1.3.0', '1.2.0']]);
check('...each under its own label, newest first',
    strpos($catchUp, 'GRACe 1.3.0, released 10 December 2026') !== false
    && strpos($catchUp, 'GRACe 1.3.0') < strpos($catchUp, 'GRACe 1.2.0, released 1 December 2026'), true);
check('...and nothing from the version it had already seen', strpos($catchUp, 'version before'), false);

$setSeen('1.0.0');
check('Missed several: all of them, newest first',
    array_column(unseenChangelogSections($pdo, $three), 'version'), ['1.3.0', '1.2.0', '1.1.0']);

$setSeen('');
check('An install from before What\'s new existed gets only the newest, not the whole history',
    array_column(unseenChangelogSections($pdo, $three), 'version'), ['1.3.0']);

$setSeen('1.3.0');
check('Up to date: nothing to show', [unseenChangelogSections($pdo, $three), whatsNewToShow($pdo, $three)], [[], null]);
$setSeen('9.9.9');
check('Going back to an older version shows nothing', whatsNewToShow($pdo, $three), null);
check('No readable changelog means no pop-up (and no error)', whatsNewToShow($pdo, ''), null);
@unlink($pathD);

// --- Wiring ----------------------------------------------------------------------------------
check('performMigrations() records where What\'s new starts from',
    strpos(source($publicDir, 'init_db.php'), 'recordWhatsNewBaseline($pdo)') !== false, true);
$footer = source($publicDir, 'footer.php');
check('Every page checks for What\'s new in the footer',
    strpos($footer, 'whatsNewToShow(') !== false && strpos($footer, 'id="whatsNewDialog"') !== false, true);
check('The pop-up script is only loaded when there is something to show',
    preg_match('/if \(\$whatsNew\).*js\/whats_new\.js/s', $footer), 1);
$js = source($publicDir, 'js/whats_new.js');
check('The pop-up marks the notes seen when closed, via a relative URL',
    strpos($js, "fetch('handle_whats_new.php'") !== false, true);
check('Long notes start folded with "Show more…"', strpos($js, 'scrollHeight') !== false && strpos($footer, 'Show more') !== false, true);
$handler = source($publicDir, 'handle_whats_new.php');
check('handle_whats_new.php only accepts POST and saves through markWhatsNewSeen()',
    strpos($handler, "REQUEST_METHOD'] !== 'POST'") !== false && strpos($handler, 'markWhatsNewSeen(') !== false, true);
check('The shared menu doesn\'t leak its loop variables into pages (it used to overwrite the What\'s new page\'s notes)',
    strpos(source($publicDir, 'nav.php'), 'unset($href, $section, $isActive)') !== false
    && strpos(source($publicDir, 'whats_new.php'), '$section') === false, true);
check('Administration → What\'s new shows the notes on demand, including any nobody has seen yet',
    strpos(source($publicDir, 'administration.php'), 'href="whats_new.php"') !== false
    && strpos(source($publicDir, 'whats_new.php'), 'renderChangelogSections(') !== false
    && strpos(source($publicDir, 'whats_new.php'), 'unseenChangelogSections(') !== false, true);
check('The add-on image includes the changelog where GRACe looks for it',
    strpos(source($repoRoot, 'grace_addon/Dockerfile'), 'COPY CHANGELOG.md /www/CHANGELOG.md') !== false
    && strpos(source($publicDir, 'whats_new_lib.php'), "__DIR__ . '/../CHANGELOG.md'") !== false, true);
check('A --legacy demo seed behaves like an upgrade, so the pop-up can be tried',
    strpos(source($repoRoot, 'tests/seed_demo_data.php'), "whatsNewSeenVersion") !== false, true);

if ($failures > 0) {
    echo "[FAIL] What's new tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] What's New Test Completed Successfully\n";
exit(0);

<?php
/**
 * Regression tests for "What's new" (1.1.0), whats_new_lib.php.
 *
 * After an update, the first person to open GRACe sees the newest section of
 * grace_addon/CHANGELOG.md, once for the whole system (not once per browser).
 * - New installs never see it.
 * - Installs upgraded from a version that didn't have this feature still see
 *   the latest notes.
 * - Only the latest version is shown, never a backlog of skipped versions.
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

foreach (['latestChangelogSection', 'renderChangelogMarkdown', 'recordWhatsNewBaseline', 'whatsNewPending', 'markWhatsNewSeen', 'whatsNewToShow'] as $function) {
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

// --- The real changelog -------------------------------------------------------------
$real = latestChangelogSection();
preg_match('/version: "([^"]+)"/', source($repoRoot, 'grace_addon/config.yaml'), $config);
check('GRACe finds its own changelog', $real !== null, true);
check('Its newest section is the version in config.yaml', $real['version'] ?? null, $config[1] ?? 'unknown');
$realHtml = renderChangelogMarkdown($real['lines'] ?? []);
check('The real notes render with no markdown markers left over',
    $realHtml !== '' && strpos($realHtml, '**') === false && strpos($realHtml, '###') === false, true);

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

// --- Only the latest, never a backlog ------------------------------------------------------
[$pathD, $pdo] = freshDb();
performMigrations($pdo);
$pdo->exec("UPDATE Settings SET value = '1.0.0' WHERE name = 'whatsNewSeenVersion'");
$shown = whatsNewToShow($pdo, $sample);
check('After skipping versions, only the newest notes are shown',
    [$shown['version'] ?? null, strpos($shown['html'] ?? '', 'version before')], ['1.2.0', false]);
$pdo->exec("UPDATE Settings SET value = '9.9.9' WHERE name = 'whatsNewSeenVersion'");
check('Going back to an older version shows nothing', whatsNewToShow($pdo, $sample), null);
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
check('Administration → What\'s new shows the notes on demand',
    strpos(source($publicDir, 'administration.php'), 'href="whats_new.php"') !== false
    && strpos(source($publicDir, 'whats_new.php'), 'renderChangelogMarkdown(') !== false, true);
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

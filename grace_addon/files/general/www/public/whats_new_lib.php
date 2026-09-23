<?php
/**
 * "What's new" (1.1.0): after an update, the first person to open GRACe sees
 * the newest section of the changelog, once for the whole system. Tested by
 * tests/test_whats_new.php.
 *
 * - New installs never see it: the first time this version runs on a
 *   database with nothing in it, it's marked as already seen.
 * - Installs that were already in use (including ones upgraded from versions
 *   without this feature) see the latest notes once.
 * - Only the newest version's notes are shown, never a backlog.
 *
 * The notes come straight from grace_addon/CHANGELOG.md, which the
 * Dockerfile copies to /www/CHANGELOG.md, so whatever the changelog says is
 * what growers see. What's been seen is stored in the Settings table as
 * whatsNewSeenVersion ('' means "older than this feature").
 */

require_once __DIR__ . '/settings_lib.php';

/** The changelog: inside the add-on image, or in a checkout of the repo (dev and tests). */
function whatsNewChangelogPath()
{
    foreach ([__DIR__ . '/../CHANGELOG.md', __DIR__ . '/../../../../CHANGELOG.md'] as $path) {
        if (is_readable($path)) {
            return $path;
        }
    }
    return null;
}

/**
 * The newest version's section of the changelog.
 *
 * @param string|null $markdown the changelog text; null reads GRACe's own
 * @return array{version: string, date: string|null, lines: string[]}|null
 */
function latestChangelogSection($markdown = null)
{
    static $ownSection = false;
    if ($markdown === null) {
        if ($ownSection !== false) {
            return $ownSection; // read once per page
        }
        $path = whatsNewChangelogPath();
        $text = $path === null ? false : file_get_contents($path);
        return $ownSection = ($text === false ? null : latestChangelogSection($text));
    }

    $section = null;
    foreach (preg_split('/\r\n|\r|\n/', (string) $markdown) as $line) {
        if (preg_match('/^## \[([^\]]+)\](?:\s*-\s*(.+?))?\s*$/', $line, $m)) {
            if ($section !== null) {
                break; // the version before: stop
            }
            $section = ['version' => trim($m[1]), 'date' => isset($m[2]) ? trim($m[2]) : null, 'lines' => []];
            continue;
        }
        if ($section !== null) {
            $section['lines'][] = rtrim($line);
        }
    }
    if ($section === null) {
        return null;
    }
    while ($section['lines'] && end($section['lines']) === '') {
        array_pop($section['lines']);
    }
    while ($section['lines'] && $section['lines'][0] === '') {
        array_shift($section['lines']);
    }
    return $section;
}

/** The version GRACe is running: the newest heading in its changelog. */
function currentGraceVersion()
{
    $section = latestChangelogSection();
    return $section ? $section['version'] : null;
}

/**
 * Turn changelog lines into HTML. Only what the changelog uses: "### "
 * headings, "- " bullets, **bold**, `code` and plain paragraphs. Everything
 * is escaped first.
 */
function renderChangelogMarkdown(array $lines)
{
    $inline = function ($text) {
        $html = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        return preg_replace('/`([^`]+)`/', '<code>$1</code>', $html);
    };

    $html = '';
    $inList = false;
    foreach ($lines as $line) {
        $isItem = preg_match('/^\s*[-*]\s+(.+)$/', $line, $item);
        if ($inList && !$isItem) {
            $html .= '</ul>';
            $inList = false;
        }
        if (trim($line) === '') {
            continue;
        }
        if ($isItem) {
            if (!$inList) {
                $html .= '<ul>';
                $inList = true;
            }
            $html .= '<li>' . $inline(trim($item[1])) . '</li>';
        } elseif (preg_match('/^#{1,6}\s+(.+)$/', trim($line), $heading)) {
            $html .= '<h4>' . $inline($heading[1]) . '</h4>';
        } else {
            $html .= '<p>' . $inline(trim($line)) . '</p>';
        }
    }
    return $html . ($inList ? '</ul>' : '');
}

/**
 * Decide, once, where What's new starts from. Runs from performMigrations()
 * on every request; after the first it's a single lookup.
 */
function recordWhatsNewBaseline(PDO $pdo)
{
    try {
        $hasSettings = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'Settings'")->fetchColumn();
        if (!$hasSettings || getSetting($pdo, 'whatsNewSeenVersion') !== null) {
            return;
        }

        // Anything in the ledger or company details means GRACe was already
        // in use before this version: show the latest notes once. A new
        // install starts as having seen them.
        $inUse = false;
        foreach (['OwnCompany', 'Plants', 'Flower'] as $table) {
            $exists = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = '$table'")->fetchColumn();
            if ($exists && $pdo->query("SELECT EXISTS (SELECT 1 FROM $table)")->fetchColumn()) {
                $inUse = true;
                break;
            }
        }
        $baseline = $inUse ? '' : (string) currentGraceVersion();
        $pdo->prepare("INSERT OR IGNORE INTO Settings (name, value) VALUES ('whatsNewSeenVersion', ?)")->execute([$baseline]);
    } catch (Exception $e) {
        error_log("GRACe: could not record the What's new starting point: " . $e->getMessage());
    }
}

/** Are there notes nobody on this system has seen yet? */
function whatsNewPending(PDO $pdo, $currentVersion = null)
{
    $current = $currentVersion ?? currentGraceVersion();
    $seen = getSetting($pdo, 'whatsNewSeenVersion');
    if ($current === null || $current === '' || $seen === null) {
        return false;
    }
    // '' is an install from before What's new existed; going back to an
    // older version never shows anything
    return $seen === '' || version_compare($current, $seen, '>');
}

/** Record that the notes for $version have been seen. Only the current version counts. */
function markWhatsNewSeen(PDO $pdo, $version)
{
    $current = currentGraceVersion();
    if ($current === null || (string) $version !== $current) {
        return false;
    }
    setSetting($pdo, 'whatsNewSeenVersion', $current);
    return true;
}

/**
 * What the pop-up should show on this page, or null.
 *
 * @param string|null $markdown the changelog text; null reads GRACe's own
 * @return array{version: string, date: string|null, html: string}|null
 */
function whatsNewToShow(PDO $pdo, $markdown = null)
{
    $section = latestChangelogSection($markdown);
    if ($section === null || !whatsNewPending($pdo, $section['version'])) {
        return null;
    }
    return [
        'version' => $section['version'],
        'date' => $section['date'],
        'html' => renderChangelogMarkdown($section['lines']),
    ];
}

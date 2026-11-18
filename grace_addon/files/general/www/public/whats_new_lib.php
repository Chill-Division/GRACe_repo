<?php
/**
 * "What's new" (1.1.0): after an update, the first person to open GRACe sees
 * the update notes from the changelog, once for the whole system. Tested by
 * tests/test_whats_new.php.
 *
 * - New installs never see it: the first time this version runs on a
 *   database with nothing in it, it's marked as already seen.
 * - Installs that were already in use see the notes for every version
 *   nobody on them has seen yet, newest first (1.3.1). So a version that
 *   came and went while nobody opened GRACe, or one an update jumped past,
 *   still gets its notes shown.
 * - Installs upgraded from versions without this feature have no starting
 *   point, so they see only the newest notes, not the whole history.
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
 * Every version's section of the changelog, in the file's order (newest
 * first).
 *
 * @param string|null $markdown the changelog text; null reads GRACe's own
 * @return array<int, array{version: string, date: string|null, lines: string[]}>
 */
function changelogSections($markdown = null)
{
    static $ownSections = null;
    if ($markdown === null) {
        if ($ownSections !== null) {
            return $ownSections; // read once per page
        }
        $path = whatsNewChangelogPath();
        $text = $path === null ? false : file_get_contents($path);
        return $ownSections = ($text === false ? [] : changelogSections($text));
    }

    $sections = [];
    foreach (preg_split('/\r\n|\r|\n/', (string) $markdown) as $line) {
        if (preg_match('/^## \[([^\]]+)\](?:\s*-\s*(.+?))?\s*$/', $line, $m)) {
            $sections[] = ['version' => trim($m[1]), 'date' => isset($m[2]) ? trim($m[2]) : null, 'lines' => []];
            continue;
        }
        if ($sections) {
            $sections[count($sections) - 1]['lines'][] = rtrim($line);
        }
    }
    foreach (array_keys($sections) as $i) {
        $lines = $sections[$i]['lines'];
        while ($lines && end($lines) === '') {
            array_pop($lines);
        }
        while ($lines && $lines[0] === '') {
            array_shift($lines);
        }
        $sections[$i]['lines'] = $lines;
    }
    return $sections;
}

/**
 * The newest version's section of the changelog.
 *
 * @param string|null $markdown the changelog text; null reads GRACe's own
 * @return array{version: string, date: string|null, lines: string[]}|null
 */
function latestChangelogSection($markdown = null)
{
    $sections = changelogSections($markdown);
    return $sections ? $sections[0] : null;
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
 * is escaped first. Headings are h4, or $headingTag when the notes sit under
 * a version label (renderChangelogSections()).
 */
function renderChangelogMarkdown(array $lines, $headingTag = 'h4')
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
            $html .= '<' . $headingTag . '>' . $inline($heading[1]) . '</' . $headingTag . '>';
        } else {
            $html .= '<p>' . $inline(trim($line)) . '</p>';
        }
    }
    return $html . ($inList ? '</ul>' : '');
}

/** A changelog date (YYYY-MM-DD) the way growers read it: "16 November 2026". */
function whatsNewReleaseDate($date)
{
    if ($date === null || $date === '') {
        return null;
    }
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed ? $parsed->format('j F Y') : $date;
}

/**
 * Notes for one or more versions as HTML, newest first. One version renders
 * just as renderChangelogMarkdown() does. With several, each starts with a
 * "GRACe x.y.z, released ..." label, so it's clear which update brought what.
 *
 * @param array<int, array{version: string, date: string|null, lines: string[]}> $sections
 */
function renderChangelogSections(array $sections)
{
    if (count($sections) === 1) {
        return renderChangelogMarkdown($sections[0]['lines']);
    }
    $html = '';
    foreach ($sections as $notes) {
        $label = 'GRACe ' . $notes['version'];
        $released = whatsNewReleaseDate($notes['date']);
        if ($released !== null) {
            $label .= ', released ' . $released;
        }
        $html .= '<div class="whats-new-version">'
            . '<h4 class="whats-new-version-label">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</h4>'
            . renderChangelogMarkdown($notes['lines'], 'h5')
            . '</div>';
    }
    return $html;
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
 * The notes nobody on this system has seen yet: every version newer than the
 * one it last saw, newest first. An install from before What's new existed
 * has no starting point, so it gets only the newest.
 *
 * @param string|null $markdown the changelog text; null reads GRACe's own
 * @return array<int, array{version: string, date: string|null, lines: string[]}>
 */
function unseenChangelogSections(PDO $pdo, $markdown = null)
{
    $sections = changelogSections($markdown);
    if (!$sections || !whatsNewPending($pdo, $sections[0]['version'])) {
        return [];
    }
    $seen = (string) getSetting($pdo, 'whatsNewSeenVersion');
    if ($seen === '') {
        return [$sections[0]];
    }
    return array_values(array_filter($sections, function ($notes) use ($seen) {
        return version_compare($notes['version'], $seen, '>');
    }));
}

/**
 * What the pop-up should show on this page, or null. 'version' is the newest
 * one shown, which is what closing the pop-up marks seen.
 *
 * @param string|null $markdown the changelog text; null reads GRACe's own
 * @return array{version: string, date: string|null, html: string}|null
 */
function whatsNewToShow(PDO $pdo, $markdown = null)
{
    $unseen = unseenChangelogSections($pdo, $markdown);
    if (!$unseen) {
        return null;
    }
    return [
        'version' => $unseen[0]['version'],
        'date' => $unseen[0]['date'],
        'html' => renderChangelogSections($unseen),
    ];
}

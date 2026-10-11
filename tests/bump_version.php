<?php
/**
 * Release helper: puts the new version in config.yaml, nav.php and
 * header.php's GRACE_ASSET_VERSION, once the changelog entry for it is
 * written, then checks all four agree. Not part of CI.
 *
 *   php tests/bump_version.php 1.2.0
 *
 * Write the grace_addon/CHANGELOG.md entry first (AGENTS.md section 6): this
 * refuses to change anything until the changelog starts with
 * "## [1.2.0] - YYYY-MM-DD". tests/test_bump_version.php runs it against a
 * copy of the files with --root.
 */

$args = array_slice($argv, 1);
$root = __DIR__ . '/..';
if (($i = array_search('--root', $args, true)) !== false) {
    $root = $args[$i + 1] ?? '';
    array_splice($args, $i, 2);
}
$new = $args[0] ?? '';

function stop($message)
{
    fwrite(STDERR, $message . "\n");
    exit(1);
}

if (!preg_match('/^\d+\.\d+\.\d+$/D', $new)) {
    stop('Usage: php tests/bump_version.php x.y.z (like 1.2.0)');
}

$files = [
    'config' => "$root/grace_addon/config.yaml",
    'nav' => "$root/grace_addon/files/general/www/public/nav.php",
    'header' => "$root/grace_addon/files/general/www/public/header.php",
    'changelog' => "$root/grace_addon/CHANGELOG.md",
];
foreach ($files as $path) {
    if (!is_file($path)) {
        stop("Can't find $path");
    }
}
$patterns = [
    'config' => '/^version: "([^"]+)"$/m',
    'nav' => '/<small>v([^<]+)<\/small>/',
    'header' => "/define\('GRACE_ASSET_VERSION', '([^']+)'\);/",
];

preg_match($patterns['config'], file_get_contents($files['config']), $m);
$current = $m[1] ?? '';
if ($current === $new) {
    echo "Nothing to do: GRACe is already $new.\n";
    exit(0);
}
if ($current === '' || version_compare($new, $current, '<=')) {
    stop("$new isn't newer than the current version, $current.");
}

// The changelog entry comes first: it's what growers see in What's new
preg_match('/^## \[([^\]]+)\](?:\s*-\s*(\S+))?/m', file_get_contents($files['changelog']), $m);
$heading = $m[0] ?? '(none)';
if (($m[1] ?? '') !== $new || !preg_match('/^\d{4}-\d{2}-\d{2}$/D', $m[2] ?? '')) {
    stop("Write the changelog entry first. grace_addon/CHANGELOG.md has to start with \"## [$new] - YYYY-MM-DD\"; its newest heading is \"$heading\". Nothing was changed.");
}
$today = (new DateTimeImmutable('now', new DateTimeZone('Pacific/Auckland')))->format('Y-m-d');
if ($m[2] !== $today) {
    echo "Note: the changelog entry is dated {$m[2]}, and today is $today in NZ.\n";
}

foreach ($patterns as $name => $pattern) {
    $text = file_get_contents($files[$name]);
    if (preg_match_all($pattern, $text, $found) !== 1 || $found[1][0] !== $current) {
        stop("{$files[$name]} doesn't have the version $current exactly once. Nothing more was changed.");
    }
    $updated = preg_replace_callback($pattern, function ($match) use ($current, $new) {
        return str_replace($current, $new, $match[0]);
    }, $text);
    file_put_contents($files[$name], $updated);
    echo "Updated " . basename($files[$name]) . "\n";
}

// All four agree now?
foreach ($patterns as $name => $pattern) {
    preg_match($pattern, file_get_contents($files[$name]), $m);
    if (($m[1] ?? '') !== $new) {
        stop("[FAIL] " . basename($files[$name]) . " still says " . ($m[1] ?? 'nothing'));
    }
}
echo "[PASS] config.yaml, nav.php, header.php and the changelog all say $new.\n";
echo "Next: retake the screenshots if pages look different (tests/capture_screenshots.ps1), run bash tests/run_ci.sh, then commit and open the release PR.\n";

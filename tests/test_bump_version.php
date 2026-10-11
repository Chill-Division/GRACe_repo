<?php
/**
 * Regression tests for the release helper (tests/bump_version.php), run
 * against a copy of the four files that hold the version.
 */

$repo = realpath(__DIR__ . '/..');

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

$root = sys_get_temp_dir() . '/grace_bump_' . getmypid();
$copies = ['grace_addon/config.yaml', 'grace_addon/CHANGELOG.md',
           'grace_addon/files/general/www/public/nav.php', 'grace_addon/files/general/www/public/header.php'];
foreach ($copies as $file) {
    @mkdir(dirname("$root/$file"), 0777, true);
    copy("$repo/$file", "$root/$file");
}
preg_match('/^version: "([^"]+)"$/m', file_get_contents("$root/grace_addon/config.yaml"), $m);
$current = $m[1];
[$major, $minor] = explode('.', $current);
$next = $major . '.' . ($minor + 1) . '.0';

/** Run the helper on the copy; returns [exit code, output]. */
function bump($version)
{
    global $repo, $root;
    exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$repo/tests/bump_version.php") . ' ' . escapeshellarg($version)
        . ' --root ' . escapeshellarg($root) . ' 2>&1', $output, $code);
    return [$code, implode("\n", $output)];
}
function versions()
{
    global $root;
    preg_match('/^version: "([^"]+)"$/m', file_get_contents("$root/grace_addon/config.yaml"), $c);
    preg_match('/<small>v([^<]+)<\/small>/', file_get_contents("$root/grace_addon/files/general/www/public/nav.php"), $n);
    preg_match("/define\('GRACE_ASSET_VERSION', '([^']+)'\);/", file_get_contents("$root/grace_addon/files/general/www/public/header.php"), $h);
    return [$c[1], $n[1], $h[1]];
}

[$code] = bump('1.2');
check('A version needs three numbers', $code, 1);
[$code] = bump('0.0.1');
check('An older version is refused', $code, 1);
[$code, $output] = bump($next);
check('Without the changelog entry nothing changes', [$code, versions()], [1, [$current, $current, $current]]);
check('...and it says why', strpos($output, "has to start with \"## [$next] - YYYY-MM-DD\"") !== false, true);

$changelog = "$root/grace_addon/CHANGELOG.md";
file_put_contents($changelog, "## [$next] - 2026-01-15\n### Changes\n- Something.\n\n" . file_get_contents($changelog));
[$code, $output] = bump($next);
check('With the entry written, every place gets the new version', [$code, versions()], [0, [$next, $next, $next]]);
check('...and it confirms they agree', strpos($output, "[PASS] config.yaml, nav.php, header.php and the changelog all say $next.") !== false, true);
check('...noting an entry not dated today', strpos($output, 'Note: the changelog entry is dated 2026-01-15') !== false, true);
[$code, $output] = bump($next);
check('Running it again changes nothing', [$code, trim($output)], [0, "Nothing to do: GRACe is already $next."]);

array_map('unlink', array_map(function ($file) use ($root) { return "$root/$file"; }, $copies));
exec('rm -rf ' . escapeshellarg($root));

if ($failures > 0) {
    echo "[FAIL] Release helper tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Release Helper Test Completed Successfully\n";
exit(0);

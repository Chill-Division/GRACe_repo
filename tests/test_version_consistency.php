<?php

echo "Running Version Consistency Checks...\n";

$projectRoot = __DIR__ . '/../';
$configPath = $projectRoot . 'grace_addon/config.yaml';
$navPath = $projectRoot . 'grace_addon/files/general/www/public/nav.php';
$changelogPath = $projectRoot . 'grace_addon/CHANGELOG.md';

$versions = [];

// 1. Get version from config.yaml
if (!file_exists($configPath)) die("[FAIL] config.yaml not found\n");
$configContent = file_get_contents($configPath);
if (preg_match('/version: "([^"]+)"/', $configContent, $matches)) {
    $versions['config'] = $matches[1];
} else {
    die("[FAIL] Could not parse version from config.yaml\n");
}

// 2. Get version from nav.php
if (!file_exists($navPath)) die("[FAIL] nav.php not found\n");
$navContent = file_get_contents($navPath);
if (preg_match('/<small>v([^<]+)<\/small>/', $navContent, $matches)) {
    $versions['nav'] = $matches[1];
} else {
    die("[FAIL] Could not parse version from nav.php\n");
}

// 3. Get latest version from CHANGELOG.md
if (!file_exists($changelogPath)) die("[FAIL] CHANGELOG.md not found\n");
$changelogContent = file_get_contents($changelogPath);
if (preg_match('/## \[([^\]]+)\]/', $changelogContent, $matches)) {
    $versions['changelog'] = $matches[1];
} else {
    die("[FAIL] Could not parse latest version from CHANGELOG.md\n");
}

// Compare
$baseVersion = $versions['config'];
$mismatch = false;

foreach ($versions as $source => $ver) {
    if ($ver !== $baseVersion) {
        echo "[FAIL] Version Mismatch: $source has '$ver', expected '$baseVersion'\n";
        $mismatch = true;
    }
}

if ($mismatch) {
    echo "Versions found:\n";
    print_r($versions);
    exit(1);
} else {
    echo "[PASS] All versions match: $baseVersion\n";
    exit(0);
}
?>

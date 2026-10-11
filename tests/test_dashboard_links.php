<?php
/**
 * Regression tests for the Dashboard's plant cards (1.2.0): "Plants growing"
 * and "Plants drying" open List all plants already filtered to the plants
 * they count, so the number on the card matches the list it opens.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
$dashboard = file_get_contents($publicDir . '/dashboard.php');
$listPage = file_get_contents($publicDir . '/list_all_genetics.php');
$listScript = file_get_contents($publicDir . '/js/genetics.js');
$listData = file_get_contents($publicDir . '/get_all_genetics.php');

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

// card label => the status the card counts
$cards = ['Plants growing' => 'Growing', 'Plants drying' => 'Harvested - Drying'];
foreach ($cards as $label => $status) {
    $found = preg_match('#<a class="stat-card stat-card--link" href="([^"]*)">\s*<span class="stat-value">[^\n]*\n\s*<span class="stat-label">' . preg_quote($label, '#') . '</span>#', $dashboard, $m);
    check("The $label card is a link", $found, 1);
    $href = $m[1] ?? '';
    check("The $label card opens List all plants filtered to $status", $href, 'list_all_genetics.php?status=' . rawurlencode($status));
    check("The $label card counts $status plants", strpos($dashboard, "status = '$status'") !== false, true);
    check("List all plants can filter to $status", strpos($listPage, '<option value="' . $status . '">') !== false, true);
}

check('The list starts filtered when the address asks for a status', strpos($listScript, "new URLSearchParams(window.location.search).get('status')") !== false, true);
check('Only a status from the filter list is used', preg_match('/\[\.\.\.statusFilter\.options\]\.some\(option => option\.value === requested\)/', $listScript), 1);
check('The list shows exactly that status', strpos($listData, 'WHERE P.status = :status') !== false, true);
check('The list page loads its script with the version tag, so updates reach the browser',
    strpos($listPage, '<script src="js/genetics.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>') !== false, true);

if ($failures > 0) {
    echo "[FAIL] Dashboard link tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Dashboard Link Test Completed Successfully\n";
exit(0);

<?php
/**
 * The shell checks have to fail when they find something. syntax_check.sh
 * and the duplicate-script check in static_checks.sh used to set FAIL=1
 * inside a piped loop, which runs in a subshell, so a PHP syntax error was
 * printed and then reported as "[PASS] No PHP syntax errors found".
 */

$root = realpath(__DIR__ . '/..');

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

/** Run a command from the repo root; returns [exit code, output]. */
function run($command)
{
    global $root;
    $output = [];
    exec('cd ' . escapeshellarg($root) . ' && ' . $command . ' 2>&1', $output, $code);
    return [$code, implode("\n", $output)];
}

$dir = sys_get_temp_dir() . '/grace_check_scripts_' . getmypid();
mkdir($dir);
file_put_contents($dir . '/good.php', "<?php\necho 'fine';\n");

[$code] = run('bash tests/syntax_check.sh ' . escapeshellarg($dir));
check('The syntax check passes good PHP', $code, 0);

file_put_contents($dir . '/broken.php', "<?php\nthis is not php(\n");
[$code, $output] = run('bash tests/syntax_check.sh ' . escapeshellarg($dir));
check('The syntax check fails on a broken file', $code, 1);
check('...and names it', strpos($output, '[FAIL] Syntax error in ' . $dir . '/broken.php') !== false, true);
check('...without claiming a pass', strpos($output, '[PASS]'), false);

array_map('unlink', glob($dir . '/*'));
rmdir($dir);

$static = file_get_contents($root . '/tests/static_checks.sh');
check('The duplicate-script check keeps its failures (no piped loop)',
    preg_match('/done < <\(find grace_addon\/files\/general\/www\/public\/ -name "\*\.php" -print0\)/', $static), 1);
check('No shell check sets FAIL inside a piped loop',
    preg_match('/\|\s*while\b/', $static . file_get_contents($root . '/tests/syntax_check.sh')), 0);

if ($failures > 0) {
    echo "[FAIL] Check script tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Check Script Test Completed Successfully\n";
exit(0);

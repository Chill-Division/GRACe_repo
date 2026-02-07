<?php
define('GRACE_TEST_MODE', true);
require_once __DIR__ . '/../grace_addon/files/general/www/public/init_db.php';

echo "Running Permission Logic Tests...\n";

// Create headers for temp dir
$tempDir = sys_get_temp_dir() . '/grace_test_uploads_' . uniqid();

try {
    echo "Testing Directory Creation at $tempDir...\n";
    
    // Run the function with our temp dir
    ensureUploadDirectories($tempDir . '/');

    // Verify Base Directory
    if (!is_dir($tempDir)) {
        throw new Exception("Base directory was not created");
    }

    // Verify Subdirectories
    $categories = ['offtakes', 'sops', 'licenses', 'other_records', 'coc'];
    foreach ($categories as $cat) {
        $catDir = $tempDir . '/' . $cat;
        if (!is_dir($catDir)) {
            throw new Exception("Subdirectory '$cat' was not created");
        }
        if (!is_writable($catDir)) {
             throw new Exception("Subdirectory '$cat' is not writable");
        }
    }
    
    echo "[PASS] All directories created and writable\n";

} catch (Exception $e) {
    echo "[FAIL] " . $e->getMessage() . "\n";
    // Cleanup attempt
    exec("rm -rf " . escapeshellarg($tempDir));
    exit(1);
}

// Cleanup
exec("rm -rf " . escapeshellarg($tempDir));
echo "[PASS] Permission Logic Test Completed Successfully\n";
exit(0);
?>

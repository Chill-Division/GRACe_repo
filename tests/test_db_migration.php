<?php
define('GRACE_TEST_MODE', true);
require_once __DIR__ . '/../grace_addon/files/general/www/public/init_db.php';

echo "Running Database Migration Tests...\n";

// Create a temp file for the database
$tempDb = tempnam(sys_get_temp_dir(), 'grace_test_db');
if (!$tempDb) {
    die("[FAIL] Could not create temp file\n");
}

try {
    // 1. Initialize Database (fresh install)
    echo "Testing Fresh Install...\n";
    $pdo = initializeDatabase($tempDb);
    
    // Verify tables exist
    $tables = ['Companies', 'Genetics', 'Plants', 'Flower', 'ShippingManifests', 'Documents', 'SOPs'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='$table'");
        if (!$stmt->fetch()) {
            throw new Exception("Table $table not created");
        }
    }
    echo "[PASS] Tables created successfully\n";

    // 2. Perform Migrations (ensure columns exist)
    echo "Testing Migrations...\n";
    performMigrations($pdo);

    // Check specific columns in Documents table
    $columns = ['upload_date', 'expiry_date', 'acknowledged'];
    foreach ($columns as $col) {
        try {
            $pdo->query("SELECT $col FROM Documents LIMIT 1");
        } catch (PDOException $e) {
            throw new Exception("Column '$col' missing in Documents table");
        }
    }
    echo "[PASS] All columns verified (including upload_date, expiry_date)\n";

    // 3. Test Upgrade Scenario (Mocking an old DB)
    echo "Testing Upgrade from v0.10 (Missing columns)...\n";
    $upgradeDb = tempnam(sys_get_temp_dir(), 'grace_test_upgrade_db');
    $pdoUpgrade = new PDO('sqlite:' . $upgradeDb);
    $pdoUpgrade->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create old schema (missing upload_date, expiry_date, acknowledged)
    $pdoUpgrade->exec("CREATE TABLE IF NOT EXISTS Documents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        category TEXT NOT NULL, 
        original_filename TEXT NOT NULL,
        unique_filename TEXT NOT NULL
    );");
    
    // Run migrations
    performMigrations($pdoUpgrade);
    
    // Verify columns were added
    $columns = ['upload_date', 'expiry_date', 'acknowledged'];
    foreach ($columns as $col) {
        try {
            $pdoUpgrade->query("SELECT $col FROM Documents LIMIT 1");
        } catch (PDOException $e) {
            throw new Exception("Upgrade Failed: Column '$col' was not added to Documents table");
        }
    }
    echo "[PASS] Database Upgrade from v0.10 verified (All columns added)\n";
    
    // Cleanup upgrade DB
    unset($pdoUpgrade);
    unlink($upgradeDb);
    
} catch (Exception $e) {
    if (isset($upgradeDb) && file_exists($upgradeDb)) unlink($upgradeDb);
    echo "[FAIL] " . $e->getMessage() . "\n";
    unlink($tempDb);
    exit(1);
}

// Cleanup
unlink($tempDb);
echo "[PASS] Database Migration Test Completed Successfully\n";
exit(0);
?>

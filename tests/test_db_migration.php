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

    // Check manifest workflow columns in ShippingManifests (added 0.16.0)
    $manifestColumns = ['status', 'sending_company_name', 'receiving_company_name', 'genetics_id',
                        'genetics_name', 'quantity', 'destination_address', 'flower_transaction_id',
                        'coc_document_id', 'date_completed'];
    foreach ($manifestColumns as $col) {
        try {
            $pdo->query("SELECT $col FROM ShippingManifests LIMIT 1");
        } catch (PDOException $e) {
            throw new Exception("Column '$col' missing in ShippingManifests table");
        }
    }
    echo "[PASS] ShippingManifests workflow columns verified\n";

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

    // Old ShippingManifests schema (pre-0.16.0, no workflow columns), with a
    // pre-existing row to prove the in-place upgrade preserves data
    $pdoUpgrade->exec("CREATE TABLE IF NOT EXISTS ShippingManifests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id INTEGER,
        sending_company_id INTEGER,
        recipient_id INTEGER,
        shipment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        product_type TEXT,
        item_count INTEGER,
        net_weight DECIMAL(10, 2),
        gross_weight DECIMAL(10, 2),
        manifest_file TEXT
    );");
    $pdoUpgrade->exec("INSERT INTO ShippingManifests (product_type, item_count) VALUES ('flower', 3)");

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

    // Verify ShippingManifests workflow columns were added in place
    foreach ($manifestColumns as $col) {
        try {
            $pdoUpgrade->query("SELECT $col FROM ShippingManifests LIMIT 1");
        } catch (PDOException $e) {
            throw new Exception("Upgrade Failed: Column '$col' was not added to ShippingManifests table");
        }
    }

    // Pre-existing row must survive with the In Progress default backfilled
    $row = $pdoUpgrade->query("SELECT product_type, item_count, status FROM ShippingManifests")->fetch(PDO::FETCH_ASSOC);
    if (!$row || $row['product_type'] !== 'flower' || (int) $row['item_count'] !== 3) {
        throw new Exception("Upgrade Failed: pre-existing ShippingManifests row was lost or altered");
    }
    if ($row['status'] !== 'In Progress') {
        throw new Exception("Upgrade Failed: status default not backfilled (got '{$row['status']}')");
    }
    echo "[PASS] ShippingManifests upgraded in place (columns added, data preserved)\n";

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

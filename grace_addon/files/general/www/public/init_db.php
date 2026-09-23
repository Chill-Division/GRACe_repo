<?php

date_default_timezone_set('Pacific/Auckland');

/**
 * The current time for a ledger entry: NZ time, as 'Y-m-d H:i:s'.
 *
 * Every timestamp GRACe stores is NZ time, because the Agency reports split
 * months and years in NZ time. Never use SQLite's DATETIME('now') or
 * CURRENT_TIMESTAMP: those are UTC, 12 or 13 hours behind NZ.
 */
function ledgerTimestamp() {
    return (new DateTimeImmutable('now', new DateTimeZone('Pacific/Auckland')))->format('Y-m-d H:i:s');
}

function initializeDatabase($dbPath = '/data/grace.db') {
    try {
        // Check if the directory exists
        $dir = dirname($dbPath);
        if (!is_dir($dir)) {
            throw new Exception("Directory does not exist: $dir");
        }

        // Check if the directory is writable
        if (!is_writable($dir)) {
            throw new Exception("Directory is not writable: $dir");
        }

        // Create (or open) the SQLite database
        $pdo = new PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Enable foreign key constraints
        $pdo->exec('PRAGMA foreign_keys = ON;');

        // SQL statements to create tables
        $createTablesSQL = [
            // Companies
            "CREATE TABLE IF NOT EXISTS Companies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                license_number TEXT NOT NULL,
                address TEXT,
                primary_contact_name TEXT,
                primary_contact_email TEXT,
                primary_contact_phone TEXT
            );",

            // Genetics (Breeder and Genetic Lineage were removed in 1.1.0,
            // see removeLegacyGeneticsColumns() for how old installs upgrade)
            "CREATE TABLE IF NOT EXISTS Genetics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL
            );",

            // Plants
            "CREATE TABLE IF NOT EXISTS Plants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                genetics_id INTEGER,
                status TEXT CHECK(status IN ('Growing', 'Harvested', 'Destroyed', 'Sent', 'Harvested - Drying', 'Harvested - Destroyed')),
                date_created DATETIME,
                date_harvested DATETIME DEFAULT CURRENT_TIMESTAMP,
                company_id INTEGER,
                FOREIGN KEY (genetics_id) REFERENCES Genetics(id) ON DELETE SET NULL ON UPDATE CASCADE,
                FOREIGN KEY (company_id) REFERENCES Companies(id) ON DELETE SET NULL ON UPDATE CASCADE
            );",

            // Flower
            "CREATE TABLE IF NOT EXISTS Flower (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                genetics_id INTEGER,
                weight DECIMAL(10, 2) NOT NULL,
                transaction_type TEXT CHECK(transaction_type IN ('Add', 'Subtract')) NOT NULL,
                transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                reason TEXT,
                company_id INTEGER,
                FOREIGN KEY (company_id) REFERENCES Companies(id) ON DELETE SET NULL ON UPDATE CASCADE,
                FOREIGN KEY (genetics_id) REFERENCES Genetics(id) ON DELETE SET NULL ON UPDATE CASCADE
            );",

            // ShippingManifests
            "CREATE TABLE IF NOT EXISTS ShippingManifests (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                sender_id INTEGER,
                sending_company_id INTEGER,
                recipient_id INTEGER,
                shipment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
                product_type TEXT,
                item_count INTEGER,
                net_weight DECIMAL(10, 2),
                gross_weight DECIMAL(10, 2),
                manifest_file TEXT,
                status TEXT NOT NULL DEFAULT 'In Progress',
                sending_company_name TEXT,
                receiving_company_name TEXT,
                genetics_id INTEGER,
                genetics_name TEXT,
                quantity DECIMAL(10, 2),
                destination_address TEXT,
                flower_transaction_id INTEGER,
                coc_document_id INTEGER,
                date_completed DATETIME,
                FOREIGN KEY (sending_company_id) REFERENCES Companies(id) ON DELETE SET NULL ON UPDATE CASCADE,
                FOREIGN KEY (recipient_id) REFERENCES Companies(id) ON DELETE SET NULL ON UPDATE CASCADE
            );",

            // PoliceVettingRecords
            "CREATE TABLE IF NOT EXISTS PoliceVettingRecords (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                record_date DATE DEFAULT CURRENT_TIMESTAMP,
                file_path TEXT
            );",

            // SOPs
            "CREATE TABLE IF NOT EXISTS SOPs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                upload_date DATE DEFAULT CURRENT_TIMESTAMP,
                file_path TEXT
            );",

            // OwnCompany
            "CREATE TABLE IF NOT EXISTS OwnCompany (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_name TEXT NOT NULL,
                company_license_number TEXT NOT NULL,
                company_address TEXT,
                primary_contact_email TEXT
            );",
            
            // Documents
            "CREATE TABLE IF NOT EXISTS Documents (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                category TEXT NOT NULL,
                original_filename TEXT NOT NULL,
                unique_filename TEXT NOT NULL,
                upload_date DATETIME DEFAULT CURRENT_TIMESTAMP
            );",

            // ReportReminders, tracks Agency report reminders the user has
            // dismissed or drafted (added in 0.17.0). period is '2026-05'
            // for monthly reports or '2025' for the annual stocktake.
            "CREATE TABLE IF NOT EXISTS ReportReminders (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                report_type TEXT NOT NULL CHECK(report_type IN ('monthly', 'annual')),
                period TEXT NOT NULL,
                status TEXT NOT NULL CHECK(status IN ('dismissed', 'drafted')),
                actioned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE(report_type, period)
            );"

        ];

        // Execute each CREATE TABLE statement
        foreach ($createTablesSQL as $sql) {
            $pdo->exec($sql);
        }

        return $pdo;

    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    } catch (Exception $e) {
        die("Initialization error: " . $e->getMessage());
    }
}

// Add a column to a table if it doesn't exist yet (in-place upgrade helper)
function addColumnIfMissing($pdo, $table, $column, $ddl) {
    try {
        $pdo->query("SELECT $column FROM $table LIMIT 1");
    } catch (PDOException $e) {
        // Column doesn't exist, add it
        $pdo->exec("ALTER TABLE $table ADD COLUMN $ddl");
    }
}

// Function to perform migrations
function performMigrations($pdo) {
    // Check for expiry_date column in Documents
    try {
        $pdo->query("SELECT expiry_date FROM Documents LIMIT 1");
    } catch (PDOException $e) {
        // Column doesn't exist, add it
        $pdo->exec("ALTER TABLE Documents ADD COLUMN expiry_date DATE DEFAULT NULL");
    }

    // Check for acknowledged column in Documents
    try {
        $pdo->query("SELECT acknowledged FROM Documents LIMIT 1");
    } catch (PDOException $e) {
        // Column doesn't exist, add it
        $pdo->exec("ALTER TABLE Documents ADD COLUMN acknowledged INTEGER DEFAULT 0");
    }

    // Check for upload_date column in Documents (Added for image compression/upload update)
    try {
        $pdo->query("SELECT upload_date FROM Documents LIMIT 1");
    } catch (PDOException $e) {
        // Column doesn't exist, add it. SQLite can't add a column with a
        // CURRENT_TIMESTAMP default, and upload.php always sets the date.
        $pdo->exec("ALTER TABLE Documents ADD COLUMN upload_date DATETIME");
    }

    // ShippingManifests workflow columns (added in 0.16.0 for the
    // In Progress -> Completed manifest lifecycle with Chain of Custody)
    $manifestColumns = [
        'status' => "status TEXT NOT NULL DEFAULT 'In Progress'",
        'sending_company_name' => "sending_company_name TEXT",
        'receiving_company_name' => "receiving_company_name TEXT",
        'genetics_id' => "genetics_id INTEGER",
        'genetics_name' => "genetics_name TEXT",
        'quantity' => "quantity DECIMAL(10, 2)",
        'destination_address' => "destination_address TEXT",
        'flower_transaction_id' => "flower_transaction_id INTEGER",
        'coc_document_id' => "coc_document_id INTEGER",
        'date_completed' => "date_completed DATETIME",
    ];
    foreach ($manifestColumns as $column => $ddl) {
        addColumnIfMissing($pdo, 'ShippingManifests', $column, $ddl);
    }

    // Check Plants table constraint for new Harvest statuses
    // We try to insert a dummy record with new status inside a transaction. 
    // If it fails, we need migration. If it succeeds, we rollback.
    $needsMigration = false;
    $pdo->beginTransaction();
    try {
        // Prepare statement to avoid syntax errors if table structure is different (though likely same columns)
        // usage of simple INSERT with NULLs for other columns should suffice for CHECK constraint test
        $stmt = $pdo->prepare("INSERT INTO Plants (status) VALUES ('Harvested - Drying')");
        $stmt->execute();
        // If successful, constraint supports it. Rollback.
        $pdo->rollBack();
    } catch (PDOException $e) {
        // Insert failed, likely due to constraint.
        $pdo->rollBack();
        // Check if error is constraint violation
        if (strpos($e->getMessage(), 'constraint') !== false) {
             $needsMigration = true;
        }
    }

    if ($needsMigration) {
        try {
            $pdo->beginTransaction();
            
            // 1. Rename existing table
            $pdo->exec("ALTER TABLE Plants RENAME TO Plants_old");

            // 2. Create new table with updated CHECK constraint
            $pdo->exec("CREATE TABLE Plants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                genetics_id INTEGER,
                status TEXT CHECK(status IN ('Growing', 'Harvested', 'Destroyed', 'Sent', 'Harvested - Drying', 'Harvested - Destroyed')),
                date_created DATETIME,
                date_harvested DATETIME DEFAULT CURRENT_TIMESTAMP,
                company_id INTEGER,
                FOREIGN KEY (genetics_id) REFERENCES Genetics(id) ON DELETE SET NULL ON UPDATE CASCADE,
                FOREIGN KEY (company_id) REFERENCES Companies(id) ON DELETE SET NULL ON UPDATE CASCADE
            )");

            // 3. Copy data
            $pdo->exec("INSERT INTO Plants (id, genetics_id, status, date_created, date_harvested, company_id)
                        SELECT id, genetics_id, status, date_created, date_harvested, company_id FROM Plants_old");

            // 4. Drop old table
            $pdo->exec("DROP TABLE Plants_old");

            $pdo->commit();
            error_log("Plants table migration completed successfully.");
        } catch (Exception $e) {
            $pdo->rollBack();
            die("Migration failed: " . $e->getMessage());
        }
    }

    removeLegacyGeneticsColumns($pdo);
    convertLedgerTimesToNzTime($pdo);
}

const GRACE_NZ_TIME_MIGRATION = 'ledger-times-to-nz-time';

/**
 * Before 1.1.0, plants and manual flower entries were stamped with SQLite's
 * DATETIME('now'), which is UTC, 12 or 13 hours behind NZ. The reports split
 * months and years in NZ time, so anything recorded before about 1pm on the
 * 1st of a month was counted in the previous month. This converts those old
 * UTC times to NZ time, once per install, on the first page load after the
 * update. New entries use ledgerTimestamp(), which is already NZ time.
 *
 * Converted: Plants.date_created, Plants.date_harvested, and
 * Flower.transaction_date, except flower deducted by a shipping manifest
 * (always NZ time). Manifests and documents were already NZ time.
 *
 * Exactly once: the first run records which rows are old (the highest plant
 * and flower ids) in DataMigrations before converting anything, then
 * converts only those rows and marks the job done in the same transaction.
 * If the conversion ever has to be retried, entries written in the meantime
 * are already NZ time and are never touched. tests/test_nz_time.php guards
 * all of this.
 */
function convertLedgerTimesToNzTime($pdo)
{
    try {
        $ledgerTables = (int) $pdo->query("SELECT COUNT(*) FROM sqlite_master
                                           WHERE type = 'table' AND name IN ('Plants', 'Flower', 'ShippingManifests')")->fetchColumn();
        if ($ledgerTables < 3) {
            return; // not a GRACe ledger (only happens in tests)
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS DataMigrations (
            name TEXT PRIMARY KEY,
            status TEXT NOT NULL,
            details TEXT,
            updated_at DATETIME
        )");

        $findJob = $pdo->prepare("SELECT status, details FROM DataMigrations WHERE name = ?");
        $findJob->execute([GRACE_NZ_TIME_MIGRATION]);
        $job = $findJob->fetch(PDO::FETCH_ASSOC);
        if ($job && $job['status'] === 'done') {
            return; // every page load after the first
        }

        if (!$job) {
            // Nothing written by this version exists yet: this runs before any
            // page gets to add entries. If two page loads race, the first wins.
            $oldRows = json_encode([
                'plantsUpToId' => (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM Plants")->fetchColumn(),
                'flowerUpToId' => (int) $pdo->query("SELECT COALESCE(MAX(id), 0) FROM Flower")->fetchColumn(),
            ]);
            $pdo->prepare("INSERT OR IGNORE INTO DataMigrations (name, status, details, updated_at) VALUES (?, 'pending', ?, ?)")
                ->execute([GRACE_NZ_TIME_MIGRATION, $oldRows, ledgerTimestamp()]);
        }
    } catch (Exception $e) {
        error_log("GRACe: could not start converting ledger times to NZ time: " . $e->getMessage());
        return;
    }

    if ($pdo->inTransaction()) {
        return; // never nest inside someone else's transaction; try next time
    }

    try {
        // IMMEDIATE takes the write lock up front, so a second page load
        // waits here and then finds the job done instead of converting twice
        $pdo->exec('BEGIN IMMEDIATE');

        $findJob->execute([GRACE_NZ_TIME_MIGRATION]);
        $job = $findJob->fetch(PDO::FETCH_ASSOC);
        if (!$job || $job['status'] === 'done') {
            $pdo->exec('COMMIT');
            return;
        }
        $details = json_decode($job['details'] ?? '', true) ?: [];
        $plantsUpToId = (int) ($details['plantsUpToId'] ?? 0);
        $flowerUpToId = (int) ($details['flowerUpToId'] ?? 0);

        $utc = new DateTimeZone('UTC');
        $nz = new DateTimeZone('Pacific/Auckland');
        $converted = 0;
        $movedMonth = 0;
        // Returns the NZ time, or the value unchanged if it isn't a full
        // 'Y-m-d H:i:s' time (blank, a date on its own, or anything odd)
        $toNz = function ($value) use ($utc, $nz, &$converted, &$movedMonth) {
            if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $value)) {
                return $value;
            }
            $time = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $utc);
            if (!$time || $time->format('Y-m-d H:i:s') !== $value) {
                return $value;
            }
            $local = $time->setTimezone($nz)->format('Y-m-d H:i:s');
            $converted++;
            if (substr($local, 0, 7) !== substr($value, 0, 7)) {
                $movedMonth++;
            }
            return $local;
        };

        $plants = $pdo->prepare("SELECT id, date_created, date_harvested FROM Plants WHERE id <= ?");
        $plants->execute([$plantsUpToId]);
        $updatePlant = $pdo->prepare("UPDATE Plants SET date_created = ?, date_harvested = ? WHERE id = ?");
        foreach ($plants->fetchAll(PDO::FETCH_ASSOC) as $plant) {
            $created = $toNz($plant['date_created']);
            $harvested = $toNz($plant['date_harvested']);
            if ($created !== $plant['date_created'] || $harvested !== $plant['date_harvested']) {
                $updatePlant->execute([$created, $harvested, $plant['id']]);
            }
        }

        $flower = $pdo->prepare("SELECT id, transaction_date FROM Flower
                                 WHERE id <= ?
                                   AND id NOT IN (SELECT flower_transaction_id FROM ShippingManifests
                                                  WHERE flower_transaction_id IS NOT NULL)");
        $flower->execute([$flowerUpToId]);
        $updateFlower = $pdo->prepare("UPDATE Flower SET transaction_date = ? WHERE id = ?");
        foreach ($flower->fetchAll(PDO::FETCH_ASSOC) as $entry) {
            $date = $toNz($entry['transaction_date']);
            if ($date !== $entry['transaction_date']) {
                $updateFlower->execute([$date, $entry['id']]);
            }
        }

        $details['convertedTimes'] = $converted;
        $details['movedMonth'] = $movedMonth;
        $pdo->prepare("UPDATE DataMigrations SET status = 'done', details = ?, updated_at = ? WHERE name = ?")
            ->execute([json_encode($details), ledgerTimestamp(), GRACE_NZ_TIME_MIGRATION]);

        $pdo->exec('COMMIT');
        if ($converted > 0) {
            error_log("GRACe: converted $converted ledger times from UTC to NZ time ($movedMonth moved into a different month)");
        }
    } catch (Exception $e) {
        try {
            $pdo->exec('ROLLBACK');
        } catch (Exception $ignored) {
            // no transaction left to roll back
        }
        // Never block the app over this; the next page load tries again
        error_log("GRACe: could not convert ledger times to NZ time yet: " . $e->getMessage());
    }
}

/**
 * Breeder and Genetic Lineage were removed in 1.1.0 because nobody used them.
 * Existing installs drop the two columns from Genetics here, on the first
 * page load after the update. Anything people had typed into them is copied
 * into LegacyGeneticsDetails first, so entered data is never thrown away (it
 * also still shows up in "Download backup").
 *
 * ALTER TABLE ... DROP COLUMN rewrites the table in place, so genetics ids
 * never change and every Plants/Flower link survives. Never rebuild Genetics
 * with DROP TABLE plus a rename instead: foreign keys are on, and the
 * ON DELETE SET NULL links would wipe every plant's and flower entry's
 * genetics. tests/test_genetics_upgrade.php guards this.
 *
 * @param PDO $pdo
 * @param string|null $sqliteVersion override for tests; defaults to the real version
 */
function removeLegacyGeneticsColumns($pdo, $sqliteVersion = null)
{
    $columns = array_column($pdo->query("PRAGMA table_info(Genetics)")->fetchAll(PDO::FETCH_ASSOC), 'name');
    $legacyColumns = array_values(array_intersect(['breeder', 'genetic_lineage'], $columns));
    if (!$legacyColumns) {
        return; // fresh install, or already upgraded
    }

    // DROP COLUMN needs SQLite 3.35+. On anything older, leave the columns
    // alone: nothing reads them any more, so they're harmless.
    $sqliteVersion = $sqliteVersion ?? $pdo->query('SELECT sqlite_version()')->fetchColumn();
    if (version_compare($sqliteVersion, '3.35.0', '<')) {
        error_log("GRACe: SQLite $sqliteVersion cannot drop columns, leaving the unused Genetics columns in place");
        return;
    }

    // Only values with real content count; blanks, empty strings and
    // whitespace were never "filled in"
    $breeder = in_array('breeder', $legacyColumns, true) ? "NULLIF(TRIM(breeder), '')" : 'NULL';
    $lineage = in_array('genetic_lineage', $legacyColumns, true) ? "NULLIF(TRIM(genetic_lineage), '')" : 'NULL';
    $filledIn = "($breeder IS NOT NULL OR $lineage IS NOT NULL)";

    try {
        $pdo->beginTransaction();

        $filledCount = (int) $pdo->query("SELECT COUNT(*) FROM Genetics WHERE $filledIn")->fetchColumn();
        if ($filledCount > 0) {
            $pdo->exec("CREATE TABLE IF NOT EXISTS LegacyGeneticsDetails (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                genetics_id INTEGER,
                genetics_name TEXT,
                breeder TEXT,
                genetic_lineage TEXT,
                archived_at DATETIME
            )");
            $stmt = $pdo->prepare("INSERT INTO LegacyGeneticsDetails (genetics_id, genetics_name, breeder, genetic_lineage, archived_at)
                                   SELECT id, name, $breeder, $lineage, ? FROM Genetics WHERE $filledIn ORDER BY id");
            $stmt->execute([date('Y-m-d H:i:s')]);
        }

        foreach ($legacyColumns as $column) {
            $pdo->exec("ALTER TABLE Genetics DROP COLUMN $column");
        }

        $pdo->commit();
        error_log("GRACe: removed unused Genetics columns (" . implode(', ', $legacyColumns) . "), kept $filledCount filled-in entries in LegacyGeneticsDetails");
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Never block the app over this: the columns are unused, so leaving
        // them in place is safe, and the next page load will try again
        error_log("GRACe: could not remove unused Genetics columns: " . $e->getMessage());
    }
}

// Function to ensure directories exist
function ensureUploadDirectories($baseDir = '/data/uploads/') {
    $categories = ['offtakes', 'sops', 'licenses', 'other_records', 'coc', 'manifests'];

    if (!is_dir($baseDir)) {
        if (!mkdir($baseDir, 0755, true)) {
             error_log("Failed to create base upload directory: $baseDir");
        }
    }

    foreach ($categories as $category) {
        $catDir = $baseDir . $category;
        if (!is_dir($catDir)) {
            if (!mkdir($catDir, 0777, true)) { // 0777 or 0755 depending on user/group config, 0777 safe for now
                error_log("Failed to create directory: $catDir");
            }
        }
    }
}

// Run additional setup only if not in test mode
if (!defined('GRACE_TEST_MODE')) {
    $pdo = initializeDatabase();
    performMigrations($pdo);
    ensureUploadDirectories();
}

?>

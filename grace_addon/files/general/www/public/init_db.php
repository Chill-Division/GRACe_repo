<?php

date_default_timezone_set('Pacific/Auckland'); 


function initializeDatabase($dbPath = '/grace.db') {
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

            // Genetics
            "CREATE TABLE IF NOT EXISTS Genetics (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                breeder TEXT,
                genetic_lineage TEXT
            );",

            // Plants
            "CREATE TABLE IF NOT EXISTS Plants (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                genetics_id INTEGER,
                status TEXT CHECK(status IN ('Growing', 'Harvested', 'Destroyed', 'Sent')),
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

?>

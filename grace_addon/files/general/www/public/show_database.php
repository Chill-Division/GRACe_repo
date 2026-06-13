<?php
require_once 'init_db.php';

// Downloads the full ledger as a timestamped JSON file, useful for backups
// and audits. Read-only; the SQLite file itself lives at /data/grace.db.
try {
    $pdo = initializeDatabase();

    // Get the list of tables in the database
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $databaseData = [
        '_meta' => [
            'exported_at' => date('c'),
            'source' => 'GRACe Portal ledger export',
        ],
    ];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT * FROM $table");
        $databaseData[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $filename = 'grace-backup-' . date('Y-m-d-His') . '.json';
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo json_encode($databaseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

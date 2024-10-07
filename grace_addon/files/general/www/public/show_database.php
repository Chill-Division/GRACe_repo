<?php require_once 'auth.php'; ?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'init_db.php'; // Include your database initialization script
// This page is used to debug the transition to sqlite
try {
    // Get the PDO instance from the initializeDatabase function
    $pdo = initializeDatabase();

    // Get the list of tables in the database
    $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $databaseData = [];
    // Loop through each table
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT * FROM $table");
        $databaseData[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Send data as JSON
    header('Content-Type: application/json');
    echo json_encode($databaseData); 

} catch (PDOException $e) {
    // Handle errors gracefully (log the error or send an error response)
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

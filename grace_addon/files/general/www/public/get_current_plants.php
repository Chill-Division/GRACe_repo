<?php
require_once 'init_db.php';  // Utilize your SQLite database configuration
require_once 'current_plants_lib.php';

try {
    // Initialize the PDO connection using SQLite
    $pdo = initializeDatabase();

    // Growing and drying plants for each genetic, sorted alphabetically by name
    $plantData = currentPlantCounts($pdo);

    // Send data as JSON
    header('Content-Type: application/json');
    echo json_encode($plantData);
} catch (PDOException $e) {
    // Handle errors gracefully
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

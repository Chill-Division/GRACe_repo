<?php
require_once 'init_db.php'; // Include your database initialization script
require_once 'genetics_lib.php';

try {
    // Get the PDO instance from the initializeDatabase function
    $pdo = initializeDatabase();

    // Genetics for the entry forms (receiving plants, recording flower),
    // sorted alphabetically by name. Retired genetics are left out.
    $genetics = activeGenetics($pdo);

    // Send data as JSON
    header('Content-Type: application/json');
    echo json_encode($genetics);
} catch (PDOException $e) {
    // Handle errors gracefully (log the error or send an error response)
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database error']);
}
?>

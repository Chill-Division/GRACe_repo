<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php'; // Include your database initialization script

try {
    // Get the PDO instance from the initializeDatabase function
    $pdo = initializeDatabase(); 

    // Fetch genetics data
    $stmt = $pdo->query("SELECT id, name FROM Genetics");
    $genetics = $stmt->fetchAll();

    // Send data as JSON
    header('Content-Type: application/json');
    echo json_encode($genetics);
} catch (PDOException $e) {
    // Handle errors gracefully (log the error or send an error response)
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database error']);
}
?>

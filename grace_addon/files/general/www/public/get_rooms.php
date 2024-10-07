<?php require_once 'auth.php'; ?>
<?php
require_once 'config.php';

try {
    // Create PDO connection
    $dsn = "mysql:host=$servername;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, $username, $password,
 $options);

    // Fetch rooms data
    $stmt = $pdo->query("SELECT id, name FROM Rooms");
    $rooms = $stmt->fetchAll();

    // Send data as JSON
    header('Content-Type: application/json');
    echo json_encode($rooms);
} catch (PDOException $e) {
    // Handle errors gracefully (log the error or send an error response)
    http_response_code(500); // Internal Server Error
    echo json_encode(['error' => 'Database error']);
}
?>

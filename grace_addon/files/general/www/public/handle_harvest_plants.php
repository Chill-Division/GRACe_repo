<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Set the content type to JSON
header('Content-Type: application/json');

try {
    // Initialize PDO connection
    $pdo = initializeDatabase();

    // Check if it's a POST request
    if ($_SERVER["REQUEST_METHOD"] != "POST") {
        throw new Exception('Invalid request method');
    }

    // Get the JSON data from the request body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    // Log the received data (for debugging)
    error_log('Received data: ' . print_r($data, true));

    // Check if selectedPlants and action exist in the data
    if (!isset($data['selectedPlants']) || !is_array($data['selectedPlants']) || !isset($data['action'])) {
        throw new Exception('Invalid or missing data');
    }

    $selectedPlantIds = $data['selectedPlants'];
    $action = $data['action'];

    // Basic input validation
    if (empty($selectedPlantIds)) {
        throw new Exception('No plants selected.');
    }

    // Set the status based on the action
    $newStatus = ($action === 'harvest') ? 'Harvested' : 'Destroyed';

    // Prepare SQL to update the status and date_harvested for selected plants
    $placeholders = implode(',', array_fill(0, count($selectedPlantIds), '?'));
    $sql = "UPDATE Plants SET status = ?, date_harvested = DATETIME('now') WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);

    // Execute the update
    $stmt->execute(array_merge([$newStatus], $selectedPlantIds));

    $affectedRows = $stmt->rowCount();

    echo json_encode(['success' => true, 'message' => "Success: $affectedRows plants $action" . 'ed successfully']);

} catch (Exception $e) {
    // Log the error
    error_log('Error in handle_harvest_plants.php: ' . $e->getMessage());

    // Send a JSON error response
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

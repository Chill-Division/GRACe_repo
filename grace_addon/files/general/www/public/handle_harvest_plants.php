<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php';

header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    $pdo = initializeDatabase();

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        throw new Exception('Invalid request method');
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);

    if (!isset($data['selectedPlants']) || !is_array($data['selectedPlants']) || !isset($data['action'])) {
        throw new Exception('Invalid or missing data');
    }

    $selectedPlantIds = $data['selectedPlants'];
    $action = $data['action'];
    $companyId = $data['companyId'] ?? null;

    if (empty($selectedPlantIds)) {
        throw new Exception('No plants selected.');
    }

    $newStatus = ($action === 'harvest') ? 'Harvested' : (($action === 'destroy') ? 'Destroyed' : 'Sent');
    $placeholders = implode(',', array_fill(0, count($selectedPlantIds), '?'));
    $sql = "UPDATE Plants SET status = ?, date_harvested = DATETIME('now')";

    if ($action === 'send' && $companyId !== null) {
        $sql .= ", company_id = ?";
    }

    $sql .= " WHERE id IN ($placeholders)";
    $stmt = $pdo->prepare($sql);

    $params = ($action === 'send' && $companyId !== null) ? array_merge([$newStatus, $companyId], $selectedPlantIds) : array_merge([$newStatus], $selectedPlantIds);
    $stmt->execute($params);

    $affectedRows = $stmt->rowCount();
    echo json_encode(['success' => true, 'message' => "Success: $affectedRows plants $action" . 'ed successfully']);

} catch (Exception $e) {
    error_log('Error in handle_harvest_plants.php: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

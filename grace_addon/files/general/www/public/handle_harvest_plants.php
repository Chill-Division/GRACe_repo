<?php
require_once 'init_db.php';
require_once 'harvest_lib.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

// Expects JSON: { selectedPlants: [ids], action: 'harvest'|'destroy'|'send', companyId }
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    echo json_encode(['success' => false, 'message' => 'Invalid or missing data']);
    exit();
}

try {
    $pdo = initializeDatabase();
    $result = processPlants($pdo, $data['selectedPlants'] ?? [], $data['action'] ?? '', $data['companyId'] ?? null);
} catch (Exception $e) {
    error_log('Error in handle_harvest_plants.php: ' . $e->getMessage());
    $result = ['success' => false, 'message' => 'Something went wrong and nothing was changed: ' . $e->getMessage()];
}

echo json_encode($result);

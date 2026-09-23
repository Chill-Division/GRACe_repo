<?php
require_once 'init_db.php';
require_once 'genetics_lib.php';

// "+ Add new genetics…" pop-up (js/quick_add.js). Answers with JSON:
// { success, message, id, name, duplicate }. A duplicate carries the existing
// genetics, so the page can select it.
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $pdo = initializeDatabase();
    echo json_encode(addGenetics($pdo, $_POST['geneticsName'] ?? ''));
} catch (Exception $e) {
    error_log('Quick add genetics: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not add the genetics: ' . $e->getMessage()]);
}

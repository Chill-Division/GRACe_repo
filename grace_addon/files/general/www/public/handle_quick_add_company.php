<?php
require_once 'init_db.php';
require_once 'company_lib.php';

// "+ Add new company…" pop-up (js/quick_add.js). Answers with JSON:
// { success, message, id, name, duplicate, company }. A duplicate (same
// license number) carries the existing company, so the page can select it.
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $pdo = initializeDatabase();
    echo json_encode(addVerifiedCompany($pdo, $_POST));
} catch (Exception $e) {
    error_log('Quick add company: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not add the company: ' . $e->getMessage()]);
}

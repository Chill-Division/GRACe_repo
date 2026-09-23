<?php
require_once 'init_db.php';
require_once 'materials_out_lib.php';

header('Content-Type: application/json');

try {
    $pdo = initializeDatabase();

    echo json_encode(getMaterialsOut($pdo, currentReportMonth()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("This month's materials out: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

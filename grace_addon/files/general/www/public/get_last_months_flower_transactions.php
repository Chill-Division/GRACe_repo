<?php
require_once 'init_db.php';
require_once 'materials_out_lib.php';

header('Content-Type: application/json');

try {
    $pdo = initializeDatabase();

    // The report page passes the month it is showing (?period=YYYY-MM), so
    // the figures always match the heading. Without one, use last month.
    $month = parseReportMonth($_GET['period'] ?? null) ?? previousReportMonth();

    echo json_encode(getMaterialsOut($pdo, $month), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    error_log("Last month's materials out: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}

<?php
require_once 'init_db.php';
require_once 'annual_stocktake_lib.php';

try {
    $pdo = initializeDatabase();

    $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : (date('Y') - 1);

    header('Content-Type: application/json');
    echo json_encode(computeAnnualFlowerStocktake($pdo, $selectedYear));
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . htmlentities($e->getMessage())]);
}
?>

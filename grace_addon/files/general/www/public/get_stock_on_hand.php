<?php
require_once 'init_db.php';
require_once 'flower_lib.php';

// What's on hand for every genetics, keyed by genetics id, for the hints on
// Receive plants and Record dry weight:
// { "3": { "growing": 12, "drying": 4, "flower": 395.5 }, ... }
header('Content-Type: application/json');

try {
    $pdo = initializeDatabase();
    echo json_encode((object) stockByGenetics($pdo));
} catch (Exception $e) {
    error_log('Stock on hand: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}

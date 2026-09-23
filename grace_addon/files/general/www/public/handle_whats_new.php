<?php
require_once 'init_db.php';
require_once 'whats_new_lib.php';

// The "What's new" pop-up was closed (js/whats_new.js): the notes for this
// version now count as seen by everyone on this system
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $pdo = initializeDatabase();
    echo json_encode(['success' => markWhatsNewSeen($pdo, $_POST['version'] ?? '')]);
} catch (Exception $e) {
    error_log("GRACe: could not record What's new as seen: " . $e->getMessage());
    echo json_encode(['success' => false]);
}

<?php
require_once 'init_db.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;

    if ($id) {
        $pdo = initializeDatabase(); // Creates and returns existing PDO connection (cached? No, new instance but lightweight sqlite)
        // Ensure migrations ran? validation runs at end of init_db so yes.

        $stmt = $pdo->prepare("UPDATE Documents SET acknowledged = 1 WHERE id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    }
}
?>

<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php';

try {
    // Initialize the PDO connection using SQLite
    $pdo = initializeDatabase();

    // Fetch all genetics
    $geneticsStmt = $pdo->query("SELECT id, name FROM Genetics");
    $genetics = $geneticsStmt->fetchAll(PDO::FETCH_ASSOC);

    $flowerData = [];

    foreach ($genetics as $genetic) {
        // Calculate the running total weight for each genetic
        $sql = "SELECT SUM(weight) AS totalWeight
                FROM Flower
                WHERE genetics_id = :geneticsId";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':geneticsId', $genetic['id'], PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $flowerData[] = [
            'geneticsName' => $genetic['name'],
            'totalWeight' => $result['totalWeight'] ? intval($result['totalWeight']) : 0 // Convert to integer to hide decimals
        ];
    }

    // Send data as JSON
    header('Content-Type: application/json');
    echo json_encode($flowerData);
} catch (PDOException $e) {
    // Handle errors gracefully
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

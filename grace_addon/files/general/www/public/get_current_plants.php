<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php';  // Utilize your SQLite database configuration

try {
    // Initialize the PDO connection using SQLite
    $pdo = initializeDatabase();

    // Fetch all genetics
    $geneticsStmt = $pdo->query("SELECT id, name FROM Genetics");
    $genetics = $geneticsStmt->fetchAll(PDO::FETCH_ASSOC);

    $plantData = [];

    foreach ($genetics as $genetic) {
        // Count the number of plants with 'Growing' status for each genetic
        $sql = "SELECT COUNT(*) AS plantCount
                FROM Plants
                WHERE genetics_id = :geneticsId
                AND status = 'Growing'";

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':geneticsId', $genetic['id'], PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        $plantData[] = [
            'geneticsName' => $genetic['name'],
            'plantCount' => $result['plantCount']
        ];
    }

    // Send data as JSON
    header('Content-Type: application/json');
    echo json_encode($plantData);
} catch (PDOException $e) {
    // Handle errors gracefully
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

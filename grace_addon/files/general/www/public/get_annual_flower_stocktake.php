<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php';

try {
    $pdo = initializeDatabase();

    $selectedYear = isset($_GET['year']) ? intval($_GET['year']) : (date('Y') - 1);

    $startDate = "{$selectedYear}-01-01";
    $endDate = "{$selectedYear}-12-31";

    $query = "SELECT id, name FROM Genetics";
    $geneticsStmt = $pdo->query($query);
    $genetics = $geneticsStmt->fetchAll(PDO::FETCH_ASSOC);

    $flowerStocktakeData = [];

    foreach ($genetics as $genetic) {
        $startWeightQuery = "SELECT COALESCE(SUM(weight), 0) AS startWeight
                             FROM Flower
                             WHERE genetics_id = :geneticsId
                             AND transaction_date < :startDate";
        
        $stmt = $pdo->prepare($startWeightQuery);
        $stmt->bindParam(':geneticsId', $genetic['id'], PDO::PARAM_INT);
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->execute();
        $startWeight = floatval($stmt->fetchColumn());

        $inWeightQuery = "SELECT COALESCE(SUM(weight), 0) AS inWeight
                          FROM Flower
                          WHERE genetics_id = :geneticsId
                          AND transaction_type = 'Add'
                          AND transaction_date BETWEEN :startDate AND :endDate";
        
        $stmt = $pdo->prepare($inWeightQuery);
        $stmt->bindParam(':geneticsId', $genetic['id'], PDO::PARAM_INT);
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();
        $inWeight = floatval($stmt->fetchColumn());

        $outWeightQuery = "SELECT COALESCE(SUM(weight), 0) AS outWeight
                           FROM Flower
                           WHERE genetics_id = :geneticsId
                           AND transaction_type = 'Subtract'
                           AND reason IN ('Send external', 'Testing')
                           AND transaction_date BETWEEN :startDate AND :endDate";
        
        $stmt = $pdo->prepare($outWeightQuery);
        $stmt->bindParam(':geneticsId', $genetic['id'], PDO::PARAM_INT);
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();
        $outWeight = floatval($stmt->fetchColumn());

        $destroyedWeightQuery = "SELECT COALESCE(SUM(weight), 0) AS destroyedWeight
                                 FROM Flower
                                 WHERE genetics_id = :geneticsId
                                 AND transaction_type = 'Subtract'
                                 AND reason NOT IN ('Send external', 'Testing')
                                 AND transaction_date BETWEEN :startDate AND :endDate";
        
        $stmt = $pdo->prepare($destroyedWeightQuery);
        $stmt->bindParam(':geneticsId', $genetic['id'], PDO::PARAM_INT);
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();
        $destroyedWeight = floatval($stmt->fetchColumn());

        $endWeight = $startWeight + $inWeight - abs($outWeight) - abs($destroyedWeight);

        $flowerStocktakeData[] = [
            'geneticsName' => $genetic['name'],
            'startWeight' => $startWeight,
            'in' => $inWeight,
            'out' => abs($outWeight),
            'destroyed' => abs($destroyedWeight),
            'end' => $endWeight
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($flowerStocktakeData);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . htmlentities($e->getMessage())]);
}
?>

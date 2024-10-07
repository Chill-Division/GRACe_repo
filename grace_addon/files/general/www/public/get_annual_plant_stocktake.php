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

    $plantStocktakeData = [];

    foreach ($genetics as $genetic) {
        $startAmountQuery = "SELECT COUNT(*) AS startAmount
                             FROM Plants
                             WHERE genetics_id = :geneticsId
                             AND date_created < :startDate";
        
        $stmt = $pdo->prepare($startAmountQuery);
        $stmt->bindParam(':geneticsId', $genetic['id'], PDO::PARAM_INT);
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->execute();
        $startAmount = (int) $stmt->fetchColumn();

        $inCountQuery = "SELECT COUNT(*) AS inCount
                         FROM Plants
                         WHERE genetics_id = :geneticsId
                         AND date_created BETWEEN :startDate AND :endDate";
        
        $stmt = $pdo->prepare($inCountQuery);
        $stmt->bindParam(':geneticsId', $genetic['id'], PDO::PARAM_INT);
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();
        $inCount = (int) $stmt->fetchColumn();

        $outCountQuery = "SELECT COUNT(*) AS outCount
                          FROM Plants
                          WHERE genetics_id = :geneticsId
                          AND status IN ('Sent', 'Harvested')
                          AND date_harvested BETWEEN :startDate AND :endDate";
        
        $stmt = $pdo->prepare($outCountQuery);
        $stmt->bindParam(':geneticsId', $genetic['id'], PDO::PARAM_INT);
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();
        $outCount = (int) $stmt->fetchColumn();

        $destroyedCountQuery = "SELECT COUNT(*) AS destroyedCount
                                FROM Plants
                                WHERE genetics_id = :geneticsId
                                AND status = 'Destroyed'
                                AND date_harvested BETWEEN :startDate AND :endDate";
        
        $stmt = $pdo->prepare($destroyedCountQuery);
        $stmt->bindParam(':geneticsId', $genetic['id'], PDO::PARAM_INT);
        $stmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
        $stmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
        $stmt->execute();
        $destroyedCount = (int) $stmt->fetchColumn();

        $endAmount = $startAmount + $inCount - abs($outCount) - abs($destroyedCount);

        $plantStocktakeData[] = [
            'geneticsName' => $genetic['name'],
            'startAmount' => $startAmount,
            'in' => $inCount,
            'out' => $outCount,
            'destroyed' => $destroyedCount,
            'end' => $endAmount
        ];
    }

    header('Content-Type: application/json');
    echo json_encode($plantStocktakeData);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . htmlentities($e->getMessage())]);
}
?>

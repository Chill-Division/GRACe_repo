<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php';

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

try {
    // Initialize the PDO connection using SQLite
    $pdo = initializeDatabase();

    // Calculate the start and end dates for the last month
    $startDate = date('Y-m-01', strtotime('-1 month')); // First day of the previous month
    $endDate = date('Y-m-t 23:59:59', strtotime('-1 month')); // Last day of the previous month

    // Log the date range being queried
    error_log("Querying for dates between $startDate and $endDate");

    // Fetch flower transaction data for the last month
    $flowerSql = "SELECT
                    G.name AS geneticsName,
                    F.weight,
                    F.transaction_date,
		    C.name || ', ' || COALESCE(C.address, '') AS companyNameAddress  -- Concatenate name and address
                  FROM
                    Flower F
                  JOIN
                    Genetics G ON F.genetics_id = G.id
                  LEFT JOIN
                    Companies C ON F.company_id = C.id
                  WHERE
                    F.transaction_type = 'Subtract'
                    AND F.reason IN ('Send external', 'Testing')
                    AND F.transaction_date BETWEEN :startDate AND :endDate
                  ORDER BY
                    F.transaction_date DESC";

    $flowerStmt = $pdo->prepare($flowerSql);
    $flowerStmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
    $flowerStmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
    $flowerStmt->execute();
    $flowerData = $flowerStmt->fetchAll(PDO::FETCH_ASSOC);

    // Make sure weights are presented positively
    foreach ($flowerData as &$flower) {
        $flower['weight'] = abs($flower['weight']);
    }

    // Fetch plant transaction data for the last month
    $plantSql = "SELECT
                    G.name AS geneticsName,
                    COUNT(P.id) AS plantCount,
                    P.date_harvested AS transaction_date,
		    C.name || ', ' || COALESCE(C.address, '') AS companyNameAddress  -- Concatenate name and address
                 FROM
                    Plants P
                 JOIN
                    Genetics G ON P.genetics_id = G.id
                 LEFT JOIN
                    Companies C ON P.company_id = C.id
                 WHERE
                    P.status = 'Sent'
                    AND P.date_harvested BETWEEN :startDate AND :endDate
                 GROUP BY
                    G.name, C.name, p.date_harvested
                 ORDER BY
                    transaction_date DESC";

    $plantStmt = $pdo->prepare($plantSql);
    $plantStmt->bindParam(':startDate', $startDate, PDO::PARAM_STR);
    $plantStmt->bindParam(':endDate', $endDate, PDO::PARAM_STR);
    $plantStmt->execute();
    $plantData = $plantStmt->fetchAll(PDO::FETCH_ASSOC);

    // Combine the data for output
    $result = [
        'flowers' => $flowerData,
        'plants' => $plantData
    ];

    // Send data as JSON
    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Handle SQL/database errors
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Handle other unforeseen errors
    error_log("Unexpected error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'An unexpected error occurred']);
}

// Ensure no additional output
exit();
?>

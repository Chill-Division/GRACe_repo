<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php'; // Ensure you're using your SQLite initialization

try {
    // Initialize the SQLite DB connection
    $pdo = initializeDatabase();

    // Check if status filter is provided
    $statusFilter = isset($_GET['status']) && !empty($_GET['status']) ? $_GET['status'] : null;

    // Build the SQL query with optional status filter
    $sql = "SELECT
                G.name AS geneticsName,
                CAST((julianday('now') - julianday(P.date_created)) AS INTEGER) AS age,
                P.status
            FROM
                Plants P
            JOIN
                Genetics G ON P.genetics_id = G.id ";

    if ($statusFilter) {
        $sql .= "WHERE P.status = :status ";
    }

    $sql .= "ORDER BY age ASC";

    $stmt = $pdo->prepare($sql);

    if ($statusFilter) {
        $stmt->bindParam(':status', $statusFilter, PDO::PARAM_STR);
    }

    $stmt->execute();
    $geneticsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Send data as JSON
    header('Content-Type: application/json');
    echo json_encode($geneticsData);
} catch (PDOException $e) {
    // Handle errors gracefully
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>

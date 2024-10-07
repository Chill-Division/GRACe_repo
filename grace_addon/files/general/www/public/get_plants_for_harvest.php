<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php';

try {
    $pdo = initializeDatabase();

    $sql = "SELECT
                P.id,
                G.name AS geneticsName,
                CAST((julianday('now') - julianday(P.date_created)) AS INTEGER) AS age,
                P.status
            FROM
                Plants P
            JOIN
                Genetics G ON P.genetics_id = G.id
            WHERE
                P.status = 'Growing'
            ORDER BY
                age ASC";

    $stmt = $pdo->query($sql);
    $plantsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Properly send JSON header
    header('Content-Type: application/json');
    echo json_encode($plantsData);
    exit(); // Ensure no further output occurs
} catch (PDOException $e) {
    http_response_code(500);
    exit(json_encode(['error' => 'Database error: ' . $e->getMessage()]));
}

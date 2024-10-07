<?php
require_once 'init_db.php';

// Initialize the PDO connection using SQLite
$pdo = initializeDatabase();

try {
    // Check if company details exist
    $sql = "SELECT COUNT(*) FROM OwnCompany";
    $stmt = $pdo->query($sql);
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        // Redirect to own_company.php if no company details are found
        header("Location: own_company.php");
        exit();
    } else {
        // Redirect to tracking.php if company details are present
        header("Location: tracking.php");
        exit();
    }
} catch (PDOException $e) {
    // Handle errors (e.g., log the issue, display a message)
    echo "Database error: " . htmlentities($e->getMessage());
    // Redirect to a generic error page or display an error message
    header("Location: error.php"); // Optional custom error page
    exit();
}
?>

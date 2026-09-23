<?php
require_once 'init_db.php'; // Include your database initialization script

// Get the PDO instance from the initializeDatabase function
$pdo = initializeDatabase();

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data from the form
    $geneticsName = $_POST['geneticsName'] ?? '';

    // Basic input validation (you can add more checks as needed)
    if (empty($geneticsName)) {
        // Redirect with error message and submitted data
        $data = urlencode(json_encode($_POST));
        header("Location: add_new_genetics.php?error=" . urlencode("Genetics name is required") . "&data=$data");
        exit();
    }

    try {
        // Check for duplicate genetics name
        $checkStmt = $pdo->prepare("SELECT id FROM Genetics WHERE name = ?");
        $checkStmt->execute([$geneticsName]);

        if ($checkStmt->fetch()) {
            // Redirect with error about duplicate
            $data = urlencode(json_encode($_POST));
            $error = urlencode("Don't try to add it a second time");
            header("Location: add_new_genetics.php?error=$error&data=$data");
            exit();
        }
        // Prepare and execute SQL query to insert data using PDO
        // Genetics are just a name (Breeder and Genetic Lineage were removed in 1.1.0)
        $stmt = $pdo->prepare("INSERT INTO Genetics (name) VALUES (:geneticsName)");
        $stmt->bindParam(':geneticsName', $geneticsName);

        $stmt->execute();

        // Redirect with success message
        header("Location: add_new_genetics.php?success=" . urlencode("New genetics added successfully"));
        exit();
    } catch (PDOException $e) {
        // Redirect with error message and submitted data safely encoded
        $data = urlencode(json_encode($_POST)); // Encode submitted data as JSON
        $error = urlencode("Error adding genetics: " . $e->getMessage());
        header("Location: add_new_genetics.php?error=$error&data=$data");
        exit();
    }
}
?>

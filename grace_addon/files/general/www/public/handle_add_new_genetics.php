<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php'; // Include your database initialization script

// Get the PDO instance from the initializeDatabase function
$pdo = initializeDatabase();

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data from the form
    $geneticsName = $_POST['geneticsName'];
    $breeder = $_POST['breeder']; // Optional, might be empty
    $geneticLineage = $_POST['geneticLineage']; // Optional, might be empty

    // Basic input validation (you can add more checks as needed)
    if (empty($geneticsName)) {
        // Redirect with error message and submitted data
        $data = urlencode(json_encode($_POST));
        header("Location: add_new_genetics.php?error=" . urlencode("Genetics name is required") . "&data=$data");
        exit();
    }

    try {
        // Prepare and execute SQL query to insert data using PDO
        $stmt = $pdo->prepare("INSERT INTO Genetics (name, breeder, genetic_lineage)
                                VALUES (:geneticsName, :breeder, :geneticLineage)");

        // Bind parameters to prevent SQL injection
        $stmt->bindParam(':geneticsName', $geneticsName);
        $stmt->bindParam(':breeder', $breeder);
        $stmt->bindParam(':geneticLineage', $geneticLineage);

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

<?php
require_once 'init_db.php'; // Include your database initialization script

// Get the PDO instance from the initializeDatabase function
$pdo = initializeDatabase();

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data from the form
    $plantCount = $_POST['plantCount'];
    $geneticsId = $_POST['geneticsName'];

    // Basic input validation
    if (empty($plantCount) || empty($geneticsId)) {
        // Redirect with error message and submitted data
        $data = urlencode(json_encode($_POST));
        header("Location: receive_genetics.php?error=" . urlencode("Plant count and genetics name are required") . "&data=$data");
        exit();
    }

    try {
        // Prepare the SQL statement outside the loop for efficiency. Times are
        // NZ time; the harvest date stays blank until the plant leaves
        // (the column's old default filled it with the UTC time)
        $stmt = $pdo->prepare("INSERT INTO Plants (genetics_id, status, date_created, date_harvested)
                                VALUES (:geneticsId, 'Growing', :createdAt, NULL)");

        // Bind the parameters only once
        $createdAt = ledgerTimestamp();
        $stmt->bindParam(':geneticsId', $geneticsId);
        $stmt->bindParam(':createdAt', $createdAt);

        // Insert into Plants table directly
        for ($i = 0; $i < $plantCount; $i++) {
            $stmt->execute();
        }

        // Redirect with success message
        header("Location: receive_genetics.php?success=" . urlencode("Genetics received successfully"));
        exit();
    } catch (PDOException $e) {
        // Handle Plants insertion error
        $data = urlencode(json_encode($_POST));
        $error = urlencode("Error inserting plants: " . $e->getMessage());
        header("Location: receive_genetics.php?error=$error&data=$data");
        exit();
    }
}
?>

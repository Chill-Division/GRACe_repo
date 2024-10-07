<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php';

// Use PDO for SQLite connection
$pdo = initializeDatabase();

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data from the form
    $companyName = $_POST['companyName'];
    $licenseNumber = $_POST['licenseNumber'];
    $address = $_POST['address'];
    $contactName = $_POST['contactName'];
    $contactEmail = $_POST['contactEmail'];
    $contactPhone = $_POST['contactPhone'];

    // Basic input validation
    if (empty($companyName) || empty($licenseNumber) || empty($address) || empty($contactName) || empty($contactEmail) || empty($contactPhone)) {
        echo "Error: All fields are required.";
        exit();
    }

    try {
        // Prepare SQL and bind parameters
        $sql = "INSERT INTO Companies (name, license_number, address, primary_contact_name, primary_contact_email, primary_contact_phone)
                VALUES (:companyName, :licenseNumber, :address, :contactName, :contactEmail, :contactPhone)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':companyName', $companyName);
        $stmt->bindParam(':licenseNumber', $licenseNumber);
        $stmt->bindParam(':address', $address);
        $stmt->bindParam(':contactName', $contactName);
        $stmt->bindParam(':contactEmail', $contactEmail);
        $stmt->bindParam(':contactPhone', $contactPhone);

        if ($stmt->execute()) {
            echo "Success: New company added successfully";
        } else {
            echo "Error: Could not execute the statement";
        }
    } catch (PDOException $e) {
        echo "Error: " . htmlspecialchars($e->getMessage());
    }
}
?>

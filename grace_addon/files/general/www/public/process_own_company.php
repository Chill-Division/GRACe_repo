<?php
require_once 'auth.php';
require_once 'init_db.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize input data
    $companyName = filter_input(INPUT_POST, 'companyName', FILTER_SANITIZE_STRING);
    $companyLicense = filter_input(INPUT_POST, 'companyLicense', FILTER_SANITIZE_STRING);
    $companyAddress = filter_input(INPUT_POST, 'companyAddress', FILTER_SANITIZE_STRING);
    $primaryContactEmail = filter_input(INPUT_POST, 'primaryContactEmail', FILTER_SANITIZE_EMAIL);

    // Basic validation
    if (empty($companyName) || empty($companyLicense) || empty($companyAddress) || empty($primaryContactEmail) || !filter_var($primaryContactEmail, FILTER_VALIDATE_EMAIL)) {
        echo "Invalid input. Please check your data and try again.";
        exit;
    }

    // Initialize PDO connection
    $pdo = initializeDatabase();

    try {
        // Check if a record exists
        $sql = "SELECT id FROM OwnCompany LIMIT 1";
        $stmt = $pdo->query($sql);
        $companyExists = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($companyExists) {
            // Update existing record
            $updateSql = "UPDATE OwnCompany SET
                            company_name = :companyName,
                            company_license_number = :companyLicense,
                            company_address = :companyAddress,
                            primary_contact_email = :primaryContactEmail";

            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([
                ':companyName' => $companyName,
                ':companyLicense' => $companyLicense,
                ':companyAddress' => $companyAddress,
                ':primaryContactEmail' => $primaryContactEmail
            ]);

        } else {
            // Insert new record
            $insertSql = "INSERT INTO OwnCompany (company_name, company_license_number, company_address, primary_contact_email)
                            VALUES (:companyName, :companyLicense, :companyAddress, :primaryContactEmail)";

            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([
                ':companyName' => $companyName,
                ':companyLicense' => $companyLicense,
                ':companyAddress' => $companyAddress,
                ':primaryContactEmail' => $primaryContactEmail
            ]);
        }

        // Redirect to administration.php after successful save
        header("Location: administration.php");
        exit;

    } catch (PDOException $e) {
        echo "Database error: " . htmlspecialchars($e->getMessage());
    }
}
?>

<?php
require_once 'auth.php';
require_once 'init_db.php';

// Check if the form was submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Sanitize and validate input (add more validation as needed)
    $companyName = filter_input(INPUT_POST, 'companyName', FILTER_SANITIZE_STRING);
    $companyLicense = filter_input(INPUT_POST, 'companyLicense', FILTER_SANITIZE_STRING);
    $companyAddress = filter_input(INPUT_POST, 'companyAddress', FILTER_SANITIZE_STRING);
    $primaryContactEmail = filter_input(INPUT_POST, 'primaryContactEmail', FILTER_SANITIZE_EMAIL);

    // Basic validation (you can add more robust validation here)
    if (empty($companyName) || empty($companyLicense) || empty($companyAddress) || empty($primaryContactEmail) || !filter_var($primaryContactEmail, FILTER_VALIDATE_EMAIL)) {
        // Handle validation errors (e.g., display error messages, redirect back to the form)
        echo "Invalid input. Please check your data and try again.";
        exit;
    }

    // Get the currently logged-in user's ID from the session
    $userId = $_SESSION['user_id'];

    // Establish PDO connection
    $pdo = initializeDatabase();

    try {
        // Check if a record already exists for this user
        $sql = "SELECT id FROM OwnCompany WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$userId]);

        if ($stmt->rowCount() > 0) {
            // Update existing record
            $updateSql = "UPDATE OwnCompany SET
                            company_name = ?,
                            company_license_number = ?,
                            company_address = ?,
                            primary_contact_email = ?
                            WHERE id = ?";
            $stmt = $pdo->prepare($updateSql);
            $stmt->execute([$companyName, $companyLicense, $companyAddress, $primaryContactEmail, $userId]);

            echo "Company information updated successfully!";
        } else {
            // Insert new record
            $insertSql = "INSERT INTO OwnCompany (id, company_name, company_license_number, company_address, primary_contact_email)
                            VALUES (?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($insertSql);
            $stmt->execute([$userId, $companyName, $companyLicense, $companyAddress, $primaryContactEmail]);

            echo "Company information saved successfully!";
        }

    } catch (PDOException $e) {
        echo "Error saving company information: " . htmlspecialchars($e->getMessage());
    }

    // Redirect back to the own_company.php page
    header("Location: own_company.php");
    exit;
}
?>

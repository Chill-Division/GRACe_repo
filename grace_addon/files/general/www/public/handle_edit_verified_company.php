<?php
require_once 'init_db.php';

// Updates an existing verified company. There is intentionally no delete
// counterpart: the ledger and Chain of Custody history reference companies,
// so they can only ever be edited.

$pdo = initializeDatabase();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "Error: Invalid request.";
    exit();
}

$companyId = isset($_POST['companyId']) ? (int) $_POST['companyId'] : 0;
$companyName = trim($_POST['companyName'] ?? '');
$licenseNumber = trim($_POST['licenseNumber'] ?? '');
$address = trim($_POST['address'] ?? '');
$contactName = trim($_POST['contactName'] ?? '');
$contactEmail = trim($_POST['contactEmail'] ?? '');
$contactPhone = trim($_POST['contactPhone'] ?? '');

// Basic input validation, mirrors handle_add_verified_company.php
if ($companyId <= 0) {
    echo "Error: Unknown company.";
    exit();
}
if (empty($companyName) || empty($licenseNumber) || empty($address) || empty($contactName) || empty($contactEmail) || empty($contactPhone)) {
    echo "Error: All fields are required.";
    exit();
}

try {
    // The company must exist
    $stmt = $pdo->prepare("SELECT id FROM Companies WHERE id = ?");
    $stmt->execute([$companyId]);
    if (!$stmt->fetch()) {
        echo "Error: Unknown company.";
        exit();
    }

    // The new license number / contact email must not belong to a DIFFERENT
    // company (re-saving this company's own values is fine)
    $stmt = $pdo->prepare("SELECT id FROM Companies WHERE license_number = ? AND id != ?");
    $stmt->execute([$licenseNumber, $companyId]);
    if ($stmt->fetch()) {
        echo "Error: Another company already has that license number.";
        exit();
    }

    $stmt = $pdo->prepare("SELECT id FROM Companies WHERE primary_contact_email = ? AND id != ?");
    $stmt->execute([$contactEmail, $companyId]);
    if ($stmt->fetch()) {
        echo "Error: Another company already has that contact email.";
        exit();
    }

    $stmt = $pdo->prepare("UPDATE Companies
                           SET name = :companyName,
                               license_number = :licenseNumber,
                               address = :address,
                               primary_contact_name = :contactName,
                               primary_contact_email = :contactEmail,
                               primary_contact_phone = :contactPhone
                           WHERE id = :companyId");
    $stmt->execute([
        ':companyName' => $companyName,
        ':licenseNumber' => $licenseNumber,
        ':address' => $address,
        ':contactName' => $contactName,
        ':contactEmail' => $contactEmail,
        ':contactPhone' => $contactPhone,
        ':companyId' => $companyId,
    ]);

    echo "Success: Company details updated";
} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
?>

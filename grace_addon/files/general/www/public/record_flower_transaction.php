<?php require_once 'auth.php'; ?>
<?php
require_once 'init_db.php';

// Ensure a connection using PDO
$pdo = initializeDatabase();

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get data from the form
    $geneticsId = $_POST['geneticsName'];
    $weight = $_POST['weight'];
    $transactionType = $_POST['transactionType'];
    $reason = $_POST['reason'];
    $otherReason = $_POST['otherReason'] ?? null;
    $companyId = $_POST['companyId'] ?? null;

    // Basic input validation
    if (empty($geneticsId) || empty($weight) || empty($transactionType) || empty($reason)) {
        header("Location: record_dry_weight.php?error=" . urlencode("All fields are required"));
        exit();
    }

    // If the reason is 'Other', ensure 'otherReason' is provided
    if ($reason === 'Other' && empty($otherReason)) {
        header("Location: record_dry_weight.php?error=" . urlencode("Please provide the 'Other' reason"));
        exit();
    }

    // Validate company selection for 'Testing' and 'Send external' reasons
    if ($transactionType === 'Subtract' && ($reason === 'Testing' || $reason === 'Send external') && empty($companyId)) {
        header("Location: record_dry_weight.php?error=" . urlencode("Please select a company for Testing or Send external transactions"));
        exit();
    }

    // Adjust weight based on transaction type
    if ($transactionType === 'Subtract') {
        $weight *= -1;
    }

    // Use the appropriate reason based on the selection
    $finalReason = ($reason === 'Other') ? $otherReason : $reason;

    // Insert into Flower table
    try {
        $sql = "INSERT INTO Flower (genetics_id, weight, transaction_type, transaction_date, reason, company_id)
                VALUES (:geneticsId, :weight, :transactionType, DATETIME('now'), :finalReason, :companyId)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':geneticsId' => $geneticsId,
            ':weight' => $weight,
            ':transactionType' => $transactionType,
            ':finalReason' => $finalReason,
            ':companyId' => $companyId,
        ]);
        header("Location: record_dry_weight.php?success=" . urlencode("Flower transaction recorded successfully"));
    } catch (PDOException $e) {
        header("Location: record_dry_weight.php?error=" . urlencode("Error recording transaction: " . $e->getMessage()));
    }
}

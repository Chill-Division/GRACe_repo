<?php
require_once 'init_db.php';
require_once 'flower_lib.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: record_dry_weight.php");
    exit();
}

try {
    $pdo = initializeDatabase();
    // Checks every field, and refuses a Subtract of more than is on hand
    $result = recordFlowerTransaction($pdo, $_POST);
} catch (PDOException $e) {
    $result = ['success' => false, 'message' => 'Error recording transaction: ' . $e->getMessage()];
}

if ($result['success']) {
    header("Location: record_dry_weight.php?success=" . urlencode($result['message']));
} else {
    // Back to the form with the message and everything that was typed
    $typed = array_intersect_key($_POST, array_flip(['geneticsName', 'weight', 'transactionType', 'reason', 'otherReason', 'companyId']));
    header("Location: record_dry_weight.php?error=" . urlencode($result['message']) . "&data=" . urlencode(json_encode($typed)));
}
exit();

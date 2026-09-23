<?php
require_once 'init_db.php';
require_once 'company_lib.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: own_company.php");
    exit;
}

try {
    $pdo = initializeDatabase();
    $result = saveOwnCompany($pdo, $_POST);
} catch (PDOException $e) {
    error_log('Saving own company details: ' . $e->getMessage());
    $result = ['success' => false, 'message' => 'Could not save your company details: ' . $e->getMessage()];
}

if ($result['success']) {
    header("Location: administration.php?saved=company");
} else {
    // Back to the form with the message and everything that was typed
    $typed = array_intersect_key($_POST, array_flip(['companyName', 'companyLicense', 'companyAddress', 'primaryContactEmail']));
    header("Location: own_company.php?error=" . urlencode($result['message']) . "&data=" . urlencode(json_encode($typed)));
}
exit;

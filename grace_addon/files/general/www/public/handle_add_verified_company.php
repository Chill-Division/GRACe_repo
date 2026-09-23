<?php
require_once 'init_db.php';
require_once 'company_lib.php';

// Administration → Add Verified Company. The page reads a plain-text answer
// starting with "Success:" or "Error:".
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "Error: Invalid request method";
    exit();
}

try {
    $pdo = initializeDatabase();
    $result = addVerifiedCompany($pdo, $_POST);
    echo ($result['success'] ? 'Success: ' : 'Error: ') . $result['message'];
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

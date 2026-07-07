<?php
require_once 'init_db.php';
require_once 'company_lib.php';

// Updates an existing verified company. There is intentionally no delete
// counterpart: the ledger and Chain of Custody history reference companies,
// so they can only ever be edited. Logic lives in company_lib.php so the
// CI suite can test it (tests/test_company_editing.php).

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo "Error: Invalid request.";
    exit();
}

$pdo = initializeDatabase();
echo updateVerifiedCompany($pdo, $_POST);
?>

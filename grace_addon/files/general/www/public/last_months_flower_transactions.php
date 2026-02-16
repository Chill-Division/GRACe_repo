<?php
require_once 'init_db.php';

try {
    $pdo = initializeDatabase();

    // Fetch company name and license number from OwnCompany table
    $stmt = $pdo->query("SELECT company_name, company_license_number FROM OwnCompany LIMIT 1");
    $companyData = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if company data was found
    if ($companyData) {
        $companyName = $companyData['company_name'];
        $companyLicense = $companyData['company_license_number'];
        $pageTitle = "Last month's materials out for $companyName ($companyLicense)";
    } else {
        $pageTitle = "Last month's materials out"; // Fallback if no company data
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo "Error: An unexpected error occurred. Please try again later.";
}
?>

<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css">
    <title>GRACe - <?php echo $pageTitle; ?></title>
</head>
<body>
    <header class="container-fluid">
        <?php require_once 'nav.php'; ?>
    </header>

    <main class="container" data-endpoint="get_last_months_flower_transactions.php">
        <h1><?php echo $pageTitle; ?></h1>

        <p>Total Weight Sent Out: <span id="totalWeightSent">0</span> grams</p>

        <h2>Flower Transactions</h2>
        <table id="flowerTransactionsTable" class="table">
            <thead>
                <tr>
                    <th>Genetics Name</th>
                    <th>Weight (grams)</th>
                    <th>Transaction Date</th>
                    <th>Company</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

        <h2>Plant Transactions</h2>
        <table id="plantTransactionsTable" class="table">
            <thead>
                <tr>
                    <th>Genetics Name</th>
                    <th># of Plants</th>
                    <th>Transaction Date</th>
                    <th>Company</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </main>
    <script src="js/transactions.js"></script>

<script src="js/growcart.js"></script>
</body>
</html>

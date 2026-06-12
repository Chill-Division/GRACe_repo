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
        $pageTitle = "Current month's materials out for $companyName ($companyLicense)";
    } else {
        $pageTitle = "Current month's materials out"; // Fallback if no company data
    }

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo "Error: An unexpected error occurred. Please try again later.";
}

$reportHeading = $pageTitle;
$pageTitle = "GRACe - $pageTitle";
require 'header.php';
?>

    <main class="container" data-endpoint="get_this_months_flower_transactions.php">
        <hgroup class="page-header">
            <h1><?php echo $reportHeading; ?></h1>
            <p>Pre-formatted and ready to send to the Agency.</p>
        </hgroup>

        <div class="stat-card">
            <span class="stat-value"><span id="totalWeightSent">0</span> g</span>
            <span class="stat-label">Total weight sent out</span>
        </div>

        <h2>Flower Transactions</h2>
        <figure class="table-wrap">
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
        </figure>

        <h2>Plant Transactions</h2>
        <figure class="table-wrap">
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
        </figure>
    </main>

    <script src="js/transactions.js"></script>
<?php require 'footer.php'; ?>

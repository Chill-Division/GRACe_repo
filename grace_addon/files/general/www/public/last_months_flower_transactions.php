<?php
require_once 'init_db.php';

$companyName = '';
$companyLicense = '';
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

$reportMonthLabel = date('F Y', strtotime('first day of last month'));
$reportPeriod = date('Y-m', strtotime('first day of last month'));

$reportHeading = $pageTitle;
$pageTitle = "GRACe - $pageTitle";
require 'header.php';
?>

    <main class="container" data-endpoint="get_last_months_flower_transactions.php"
          data-report-month="<?php echo htmlspecialchars($reportMonthLabel); ?>"
          data-report-period="<?php echo htmlspecialchars($reportPeriod); ?>"
          data-company-name="<?php echo htmlspecialchars($companyName); ?>"
          data-company-license="<?php echo htmlspecialchars($companyLicense); ?>">
        <hgroup class="page-header">
            <h1><?php echo $reportHeading; ?></h1>
            <p>Pre-formatted and ready to send to the Agency.</p>
        </hgroup>

        <div class="toolbar">
            <div class="stat-card">
                <span class="stat-value"><span id="totalWeightSent">0</span> g</span>
                <span class="stat-label">Total weight sent out</span>
            </div>
            <?php if ($companyName !== ''): ?>
            <button type="button" class="button" id="draftEmailButton">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em;height:1em;vertical-align:-0.125em;margin-right:0.35em;"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4Z"/></svg>Draft this in an email
            </button>
            <?php endif; ?>
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
    <script src="js/report_email.js"></script>
    <script>
        const draftButton = document.getElementById('draftEmailButton');
        if (draftButton) {
            draftButton.addEventListener('click', () => {
                const main = document.querySelector('main');
                const monthLabel = main.dataset.reportMonth;
                const company = main.dataset.companyName;
                const license = main.dataset.companyLicense;

                const flowerLines = reportTableLines(
                    document.getElementById('flowerTransactionsTable'),
                    // cells: genetics, weight, date, company — skip the bold totals row
                    cells => cells[0] === 'Total' ? null : `- ${cells[0]}: ${cells[1]} g on ${cells[2]} to ${cells[3]}`
                ).filter(Boolean);

                const plantLines = reportTableLines(
                    document.getElementById('plantTransactionsTable'),
                    cells => cells[0] === 'Total' ? null : `- ${cells[0]}: ${cells[1]} plant(s) on ${cells[2]} to ${cells[3]}`
                ).filter(Boolean);

                const body = [
                    `Monthly materials out report for ${monthLabel}`,
                    `${company} (${license})`,
                    '',
                    'FLOWER TRANSACTIONS',
                    ...(flowerLines.length ? flowerLines : ['- Nothing to report']),
                    '',
                    'PLANT TRANSACTIONS',
                    ...(plantLines.length ? plantLines : ['- Nothing to report']),
                    '',
                    `Total weight sent out: ${document.getElementById('totalWeightSent').textContent} grams`,
                    '',
                    'Generated by GRACe Portal'
                ].join('\n');

                draftReportEmail({
                    subject: `Monthly materials out report for ${monthLabel} for ${company} (${license})`,
                    body: body,
                    reminderType: 'monthly',
                    reminderPeriod: main.dataset.reportPeriod
                });
            });
        }
    </script>
<?php require 'footer.php'; ?>

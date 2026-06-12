<?php
require_once 'init_db.php';

$companyName = '';
$companyLicense = '';
try {
    $pdo = initializeDatabase();
    $stmt = $pdo->query("SELECT company_name, company_license_number FROM OwnCompany LIMIT 1");
    $companyData = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($companyData) {
        $companyName = $companyData['company_name'];
        $companyLicense = $companyData['company_license_number'];
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
}

$pageTitle = 'GRACe - Annual Stocktake';
require 'header.php';
?>

    <main class="container"
          data-company-name="<?php echo htmlspecialchars($companyName); ?>"
          data-company-license="<?php echo htmlspecialchars($companyLicense); ?>">
        <hgroup class="page-header">
            <h1>Annual Stocktake</h1>
            <p>This will generate a full in / out of plants / flower for you to stock-take, and reconcile against prior to sending details through to the Medicinal Cannabis Agency in January.</p>
        </hgroup>

        <div class="toolbar">
            <div>
                <label for="year">Select Year:</label>
                <input type="number" id="year" name="year" class="input" value="<?php echo date('Y') - 1; ?>" min="2000" max="<?php echo date('Y'); ?>" required>
            </div>
            <button type="button" class="button" id="generateReportButton">Generate Report</button>
            <label>
                <input type="checkbox" id="hideZeroRowsCheckbox"> Hide rows with all zero values
            </label>
            <?php if ($companyName !== ''): ?>
            <button type="button" class="button" id="draftEmailButton" style="display: none;">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:1em;height:1em;vertical-align:-0.125em;margin-right:0.35em;"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4Z"/></svg>Draft this in an email
            </button>
            <?php endif; ?>
        </div>

        <section id="plantStocktakeSection" style="display: none;">
            <h2>Plant Stocktake</h2>
            <figure class="table-wrap">
                <table id="plantStocktakeTable" class="table">
                    <thead>
                        <tr>
                            <th>Genetics Name</th>
                            <th>Start Amount</th>
                            <th>In</th>
                            <th>Out</th>
                            <th>Harvested</th>
                            <th>Destroyed</th>
                            <th>End</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </figure>
        </section>

        <section id="flowerStocktakeSection" style="display: none;">
            <h2>Flower Stocktake</h2>
            <figure class="table-wrap">
                <table id="flowerStocktakeTable" class="table">
                    <thead>
                        <tr>
                            <th>Genetics Name</th>
                            <th>Start Weight (g)</th>
                            <th>In (g)</th>
                            <th>Out (g)</th>
                            <th>Destroyed (g)</th>
                            <th>End (g)</th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </figure>
        </section>
    </main>

    <script src="js/report_email.js"></script>
    <script>
        const yearInput = document.getElementById('year');
        const generateReportButton = document.getElementById('generateReportButton');
        const plantStocktakeSection = document.getElementById('plantStocktakeSection');
        const flowerStocktakeSection = document.getElementById('flowerStocktakeSection');
        const hideZeroRowsCheckbox = document.getElementById('hideZeroRowsCheckbox');
        const draftEmailButton = document.getElementById('draftEmailButton');

        // Set the default value of the year input to the previous year
        yearInput.value = new Date().getFullYear() - 1;

        generateReportButton.addEventListener('click', () => {
            const selectedYear = yearInput.value;

            // Fetch and display plant stocktake data
            fetch(`get_annual_plant_stocktake.php?year=${selectedYear}`)
                .then(response => response.json())
                .then(plantData => {
                    if (Array.isArray(plantData)) {
                        populateStocktakeTable('plantStocktakeTable', plantData);
                        plantStocktakeSection.style.display = 'block';
                        filterStocktakeTables(); // Apply filtering after populating the table
                        if (draftEmailButton) draftEmailButton.style.display = '';
                    } else {
                        console.error('Unexpected data format for plant stocktake:', plantData);
                    }
                })
                .catch(error => console.error('Error fetching plant stocktake data:', error));

            // Fetch and display flower stocktake data
            fetch(`get_annual_flower_stocktake.php?year=${selectedYear}`)
                .then(response => response.json())
                .then(flowerData => {
                    if (Array.isArray(flowerData)) {
                        populateStocktakeTable('flowerStocktakeTable', flowerData);
                        flowerStocktakeSection.style.display = 'block';
                        filterStocktakeTables(); // Apply filtering after populating the table
                    } else {
                        console.error('Unexpected data format for flower stocktake:', flowerData);
                    }
                })
                .catch(error => console.error('Error fetching flower stocktake data:', error));
        });

        function populateStocktakeTable(tableId, data) {
            const tableBody = document.getElementById(tableId).getElementsByTagName('tbody')[0];
            tableBody.innerHTML = ''; // Clear existing rows

            // Initialize totals array (size based on first row, assuming all rows have same columns)
            let totals = [];
            if (data.length > 0) {
                totals = new Array(Object.values(data[0]).length).fill(0);
            }

            data.forEach(item => {
                const row = tableBody.insertRow();
                Object.values(item).forEach((value, index) => {
                    const cell = row.insertCell();
                    cell.textContent = value;

                    // Add to total if index > 0 (assuming first column 'Genetics Name' is not numeric to sum)
                    if (index > 0) {
                        totals[index] += parseFloat(value) || 0;
                    }
                });
            });

             // Add Footer Row
            if (data.length > 0) {
                const footerRow = tableBody.insertRow();
                footerRow.style.fontWeight = 'bold';
                footerRow.classList.add('stocktake-total'); // Class for identification if needed

                totals.forEach((total, index) => {
                    const cell = footerRow.insertCell();
                    if (index === 0) {
                        cell.textContent = 'Totals';
                    } else {
                        // Check if integer (plant counts) or float (weights).
                        // Simplistic check: if rounded total equals total, show integer, else fixed(2)
                        // Or safer: just fixed(2) for weight tables, but plant tables are ints.
                        // Let's use simple logic: if sum % 1 === 0 show int, else fixed(2)
                        cell.textContent = (total % 1 === 0) ? total : total.toFixed(2);
                    }
                });
            }
        }

        hideZeroRowsCheckbox.addEventListener('change', filterStocktakeTables);

        function filterStocktakeTables() {
            const tables = [document.getElementById('plantStocktakeTable'), document.getElementById('flowerStocktakeTable')];

            tables.forEach(table => {
                const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
                for (const row of rows) {
                    const cells = row.getElementsByTagName('td');
                    let allZero = true;
                    for (let i = 1; i < cells.length; i++) {
                        const value = cells[i].textContent.trim();
                        if (value !== '' && !isNaN(value) && parseFloat(value) !== 0) {
                            allZero = false;
                            break;
                        }
                    }
                    row.style.display = (hideZeroRowsCheckbox.checked && allZero) ? 'none' : 'table-row';
                }
            });
        }

        // Call filterStocktakeTables initially to apply filtering based on the checkbox's default state
        filterStocktakeTables();

        // Draft the visible report as an email to the Agency
        if (draftEmailButton) {
            draftEmailButton.addEventListener('click', () => {
                const main = document.querySelector('main');
                const company = main.dataset.companyName;
                const license = main.dataset.companyLicense;
                const year = yearInput.value;

                const plantLines = reportTableLines(
                    document.getElementById('plantStocktakeTable'),
                    cells => `- ${cells[0]}: start ${cells[1]}, in ${cells[2]}, out ${cells[3]}, harvested ${cells[4]}, destroyed ${cells[5]}, end ${cells[6]}`
                );

                const flowerLines = reportTableLines(
                    document.getElementById('flowerStocktakeTable'),
                    cells => `- ${cells[0]}: start ${cells[1]} g, in ${cells[2]} g, out ${cells[3]} g, destroyed ${cells[4]} g, end ${cells[5]} g`
                );

                const body = [
                    `Annual stocktake report for ${year}`,
                    `${company} (${license})`,
                    '',
                    'PLANT STOCKTAKE',
                    ...(plantLines.length ? plantLines : ['- Nothing to report']),
                    '',
                    'FLOWER STOCKTAKE',
                    ...(flowerLines.length ? flowerLines : ['- Nothing to report']),
                    '',
                    'Generated by GRACe Portal'
                ].join('\n');

                draftReportEmail({
                    subject: `Annual stocktake report for ${year} for ${company} (${license})`,
                    body: body,
                    reminderType: 'annual',
                    reminderPeriod: year
                });
            });
        }

        // Deep-link support: annual_stocktake.php?year=2025&autorun=1
        // (used by the dashboard reminder) pre-selects the year, hides
        // zero rows, and generates the report immediately
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('year')) {
            yearInput.value = parseInt(urlParams.get('year'), 10);
        }
        if (urlParams.get('autorun') === '1') {
            hideZeroRowsCheckbox.checked = true;
            generateReportButton.click();
        }
    </script>
<?php require 'footer.php'; ?>

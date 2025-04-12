<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css">
    <title>GRACe - Annual Stocktake</title>
</head>
<body>
    <header class="container-fluid">
        <?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Annual Stocktake</h1>

        <p><small>This will generate a full in / out of plants / flower for you to stock-take, and reconcile against prior to sending details through to the Medicinal Cannabis Agency in January.</small></p>

        <label for="year">Select Year:</label>
        <input type="number" id="year" name="year" class="input" value="<?php echo date('Y') - 1; ?>" min="2000" max="<?php echo date('Y'); ?>" required>
        <button type="button" class="button" id="generateReportButton">Generate Report</button>

        <div class="grid">
            <label>
                <input type="checkbox" id="hideZeroRowsCheckbox"> Hide rows with all zero values
            </label>
        </div>

        <section id="plantStocktakeSection" style="display: none;">
            <h2>Plant Stocktake</h2>
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
        </section>

        <section id="flowerStocktakeSection" style="display: none;">
            <h2>Flower Stocktake</h2>
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
        </section>
    </main>

    <script src="js/growcart.js"></script>
    <script>
        const yearInput = document.getElementById('year');
        const generateReportButton = document.getElementById('generateReportButton');
        const plantStocktakeSection = document.getElementById('plantStocktakeSection');
        const flowerStocktakeSection = document.getElementById('flowerStocktakeSection');
        const hideZeroRowsCheckbox = document.getElementById('hideZeroRowsCheckbox');

        // Set the default value of the year input to the previous year
        yearInput.value = new Date().getFullYear() - 1;

        generateReportButton.addEventListener('click', () => {
            const selectedYear = yearInput.value;

            // Fetch and display plant stocktake data
            fetch(`get_annual_plant_stocktake.php?year=${selectedYear}`)
                .then(response => response.json())
                .then(plantData => {
                    console.log('Plant stocktake data:', plantData);
                    if (Array.isArray(plantData)) {
                        populateStocktakeTable('plantStocktakeTable', plantData);
                        plantStocktakeSection.style.display = 'block';
                        filterStocktakeTables(); // Apply filtering after populating the table
                    } else {
                        console.error('Unexpected data format for plant stocktake:', plantData);
                    }
                })
                .catch(error => console.error('Error fetching plant stocktake data:', error));

            // Fetch and display flower stocktake data
            fetch(`get_annual_flower_stocktake.php?year=${selectedYear}`)
                .then(response => response.json())
                .then(flowerData => {
                    console.log('Flower stocktake data:', flowerData);
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

            data.forEach(item => {
                const row = tableBody.insertRow();
                Object.values(item).forEach(value => {
                    const cell = row.insertCell();
                    cell.textContent = value;
                });
            });
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
    </script>
</body>
</html>

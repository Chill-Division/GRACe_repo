<?php
require_once 'auth.php';
$pageTitle = 'GRACe - Annual Stocktake';
require 'header.php';
?>

    <main class="container">
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
    </script>
<?php require 'footer.php'; ?>

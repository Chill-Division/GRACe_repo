<?php
require_once 'auth.php';
$pageTitle = 'GRACe - Current Plants';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Current Plants</h1>
            <p>Live plant counts on hand, by genetics.</p>
        </hgroup>

        <div class="toolbar">
            <label for="hideZeroRowsCheckbox">
                <input type="checkbox" id="hideZeroRowsCheckbox" name="hideZeroRows">
                Hide rows with all zero values
            </label>
        </div>

        <figure class="table-wrap">
            <table id="plantsTable" class="table">
                <thead>
                    <tr>
                        <th>Genetics Name</th>
                        <th>Number of Plants</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </figure>
        <p id="noDataMessage" style="display: none; text-align: center; font-style: italic;"></p>
    </main>

    <script>
        const plantsTable = document.getElementById('plantsTable').getElementsByTagName('tbody')[0];

        // Fetch current plants data from the server
        fetch('get_current_plants.php')
            .then(response => response.json())
            .then(plantData => {
                plantData.forEach(item => {
                    const row = plantsTable.insertRow();
                    const nameCell = row.insertCell();
                    const countCell = row.insertCell();

                    nameCell.textContent = item.geneticsName;
                    countCell.textContent = item.plantCount;
                });
            })
            .catch(error => console.error('Error fetching current plants data:', error));
    </script>
    <script>
        document.getElementById('hideZeroRowsCheckbox').addEventListener('change', function() {
            const rows = plantsTable.rows;
            let visibleRowCount = 0;

            for (let i = 0; i < rows.length; i++) {
                const weight = parseFloat(rows[i].cells[1].textContent);
                const shouldHide = this.checked && weight === 0;
                rows[i].style.display = shouldHide ? 'none' : '';

                if (!shouldHide) {
                    visibleRowCount++;
                }
            }

            const noDataMessage = document.getElementById('noDataMessage');
            if (visibleRowCount === 0) {
                noDataMessage.textContent = "No plants available.";
                noDataMessage.style.display = 'block';
            } else {
                noDataMessage.style.display = 'none';
            }
        });
    </script>
<?php require 'footer.php'; ?>

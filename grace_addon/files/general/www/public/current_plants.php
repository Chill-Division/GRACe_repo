<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - Current Plants</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Current Plants</h1>

        <div class="grid">
            <label for="hideZeroRowsCheckbox">
                <input type="checkbox" id="hideZeroRowsCheckbox" name="hideZeroRows">
                Hide rows with all zero values
            </label>
        </div>

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
        <p id="noDataMessage" style="display: none; text-align: center; font-style: italic;"></p>
    </main>

    <script src="js/growcart.js"></script> 
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
</body>
</html>

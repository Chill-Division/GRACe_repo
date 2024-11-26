<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css">
    <title>GRACe - Last Months Flower Transactions (Out)</title>
</head>
<body>
    <header class="container-fluid">
        <?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Last months materials out</h1>

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

<script>
    const flowerTransactionsTable = document.getElementById('flowerTransactionsTable').getElementsByTagName('tbody')[0];
    const plantTransactionsTable = document.getElementById('plantTransactionsTable').getElementsByTagName('tbody')[0];
    const totalWeightSentSpan = document.getElementById('totalWeightSent');

    fetch('get_last_months_flower_transactions.php')
        .then(response => response.json())
        .then(data => {
            const flowerData = data.flowers || [];
            const plantData = data.plants || [];

            let totalWeight = 0;
            
            // Process flower data
            if (flowerData.length === 0) {
                flowerTransactionsTable.innerHTML = '<tr><td colspan="4">Nothing to report</td></tr>';
            } else {
                flowerData.forEach(transaction => {
                    totalWeight += parseFloat(transaction.weight) || 0;

                    const row = flowerTransactionsTable.insertRow();
                    const nameCell = row.insertCell();
                    const weightCell = row.insertCell();
                    const dateCell = row.insertCell();
                    const companyCell = row.insertCell();

                    nameCell.textContent = transaction.geneticsName;
                    weightCell.textContent = transaction.weight;
                    dateCell.textContent = new Date(transaction.transaction_date).toLocaleDateString();
                    companyCell.textContent = transaction.companyNameAddress || '-';
                });
            }

            // Process plant data
            if (plantData.length === 0) {
                plantTransactionsTable.innerHTML = '<tr><td colspan="4">Nothing to report</td></tr>';
            } else {
                plantData.forEach(transaction => {
                    const row = plantTransactionsTable.insertRow();
                    const nameCell = row.insertCell();
                    const countCell = row.insertCell();
                    const dateCell = row.insertCell();
                    const companyCell = row.insertCell();

                    nameCell.textContent = transaction.geneticsName;
                    countCell.textContent = transaction.plantCount;
                    dateCell.textContent = new Date(transaction.transaction_date).toLocaleDateString();
                    companyCell.textContent = transaction.companyNameAddress || '-';
                });
            }

            totalWeightSentSpan.textContent = totalWeight.toFixed(2);
        })
        .catch(error => {
            console.error('Error fetching or processing transaction data:', error);
            totalWeightSentSpan.textContent = 'Error';
            flowerTransactionsTable.innerHTML = '<tr><td colspan="4">Error loading flower data. Please check the console for details.</td></tr>';
            plantTransactionsTable.innerHTML = '<tr><td colspan="4">Error loading plant data. Please check the console for details.</td></tr>';
        });
</script>
</body>
</html>

<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css">
    <title>GRACe - This Month's Flower Transactions (Out)</title>
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>This Month's Flower Transactions (Out)</h1>

        <p><small>Preview of transactions prior to the end of the month for sending to the Medicinal Cannabis Agency.</small></p>

        <p>Total Weight Sent Out: <span id="totalWeightSent">0</span> grams</p>

        <table id="flowerTransactionsTable" class="table">
            <thead>
                <tr>
                    <th>Genetics Name</th>
                    <th>Weight (grams)</th>
                    <th>Transaction Date</th>
                    <th>Reason</th>
                    <th>Company</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </main>

    <script src="js/growcart.js"></script>
    <script>
        const totalWeightSentSpan = document.getElementById('totalWeightSent');
        const flowerTransactionsTable = document.getElementById('flowerTransactionsTable').getElementsByTagName('tbody')[0];

        // Fetch flower transaction data from the server
        fetch('get_this_months_flower_transactions.php')
            .then(response => response.json())
            .then(data => {
                console.log('Received data:', data); // Log the received data for debugging

                if (data.warning) {
                    console.warn('Warning from server:', data.warning);
                    totalWeightSentSpan.textContent = '0';
                    flowerTransactionsTable.innerHTML = '<tr><td colspan="5">' + data.warning + '</td></tr>';
                    return;
                }

                let totalWeight = 0;

                data.forEach(transaction => {
                    totalWeight += parseFloat(transaction.weight) || 0;

                    const row = flowerTransactionsTable.insertRow();
                    const nameCell = row.insertCell();
                    const weightCell = row.insertCell();
                    const dateCell = row.insertCell();
                    const reasonCell = row.insertCell();
                    const companyCell = row.insertCell();

                    nameCell.textContent = transaction.geneticsName;
                    weightCell.textContent = transaction.weight;
		    const transactionDate = new Date(transaction.transaction_date);
		    const formattedDate = transactionDate.toLocaleDateString(); // Adjust formatting options as needed
		    dateCell.textContent = formattedDate; 
                    reasonCell.textContent = transaction.reason;
	            if (transaction.companyName && transaction.companyAddress) {
	                companyCell.textContent = `${transaction.companyName} - ${transaction.companyAddress}`;
	            } else {
	                companyCell.textContent = '-';
	            }
                });

                totalWeightSentSpan.textContent = totalWeight.toFixed(2);
            })
            .catch(error => {
                console.error('Error fetching or processing flower transaction data:', error);
                totalWeightSentSpan.textContent = 'Error';
                flowerTransactionsTable.innerHTML = '<tr><td colspan="5">Error loading data. Please check the console for details.</td></tr>';
            });
    </script>
</body>
</html>

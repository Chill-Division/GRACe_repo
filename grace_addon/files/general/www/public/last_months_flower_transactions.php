<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - Last Month's Flower Transactions (Out)</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Last Month's Flower Transactions (Out)</h1>

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
        fetch('get_last_months_flower_transactions.php')
            .then(response => response.json())
            .then(transactionData => {
                let totalWeight = 0;

                transactionData.forEach(transaction => {
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

                    // Display company name and address or '-' if null
                    if (transaction.companyName && transaction.companyAddress) {
                        companyCell.textContent = `${transaction.companyName} - ${transaction.companyAddress}`;
                    } else {
                        companyCell.textContent = '-';
                    }
                });

                totalWeightSentSpan.textContent = totalWeight.toFixed(2);
            })
            .catch(error => console.error('Error fetching flower transaction data:', error));
    </script>
</body>
</html>

<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - Record Flower Transaction</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div> 

        <h1>Record Flower Transaction</h1>

	<p><small>If you are harvesting flower, receiving a sample, destroying, or sending off for testing, you can do it all from here.</small></p>

        <form id="recordFlowerTransactionForm" class="form" action="record_flower_transaction.php" method="post">
            <label for="geneticsName">Genetics:</label>
            <select id="geneticsName" name="geneticsName" class="input" required>
                <option value="" disabled selected>Select Genetics</option>
            </select>

            <label for="weight">Weight (grams):</label>
            <input type="number" id="weight" name="weight" class="input" min="0.01" step="0.01" required>

            <label for="transactionType">Transaction Type:</label>
            <select id="transactionType" name="transactionType" class="input" required>
                <option value="Add">Add</option>
                <option value="Subtract">Subtract</option>
            </select>

            <label for="reason">Reason:</label>
            <select id="reason" name="reason" class="input" required>
                <option value="" disabled selected>Select Reason</option>
            </select>

            <div id="companySelection" style="display: none;">
                <label for="companyId">Company:</label>
                <select id="companyId" name="companyId" class="input">
                    <option value="" disabled selected>Select Company</option>
                </select>
            </div>

            <div id="otherReasonSection" style="display: none;">
                <label for="otherReason">Other Reason:</label>
                <textarea id="otherReason" name="otherReason" class="input" rows="3"></textarea>
            </div>

            <button type="submit" class="button">Record Transaction</button>
        </form>
    </main>

    <script src="js/growcart.js"></script> 
    <script src="js/transaction_form.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initTransactionForm);
    </script>
</body>
</html>

<?php
$pageTitle = 'GRACe - Record Flower Transaction';
require 'header.php';
?>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div>

        <hgroup class="page-header">
            <h1>Record Flower Transaction</h1>
            <p>If you are harvesting flower, receiving a sample, destroying, or sending off for testing, you can do it all from here.</p>
        </hgroup>

        <article class="form-card">
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
        </article>
    </main>

    <script src="js/transaction_form.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initTransactionForm);
    </script>
<?php require 'footer.php'; ?>

/**
 * transactions.js
 * 
 * Called from:
 * - this_months_flower_transactions.php: To display the current month's flower and plant transactions.
 * - last_months_flower_transactions.php: To display the previous month's flower and plant transactions.
 * 
 * Why:
 * Consolidates the logic for checking usage endpoints (data-attribute driven) and rendering 
 * the flower/plant transaction tables with totals.
 */

document.addEventListener('DOMContentLoaded', () => {
    const flowerTable = document.getElementById('flowerTransactionsTable');
    const plantTable = document.getElementById('plantTransactionsTable');

    // Check if we are on a page with these tables
    if (flowerTable && plantTable) {
        // Determine which endpoint to use based on the current page or a data attribute
        // We can check the URL or look for a specific data attribute on the main container
        const mainContainer = document.querySelector('main');
        const endpoint = mainContainer.getAttribute('data-endpoint');

        if (endpoint) {
            initTransactionReport(endpoint, flowerTable, plantTable);
        } else {
            console.error('No data-endpoint attribute found on <main> tag.');
        }
    }
});

function initTransactionReport(endpoint, flowerTableElement, plantTableElement) {
    const flowerTbody = flowerTableElement.getElementsByTagName('tbody')[0];
    const plantTbody = plantTableElement.getElementsByTagName('tbody')[0];
    const totalWeightSentSpan = document.getElementById('totalWeightSent');

    fetch(endpoint)
        .then(response => response.json())
        .then(data => {
            const flowerData = data.flowers || [];
            const plantData = data.plants || [];
            let totalWeight = 0;

            // Process flower data
            let flowerReportTotal = 0;
            if (flowerData.length === 0) {
                flowerTbody.innerHTML = '<tr><td colspan="4">Nothing to report</td></tr>';
            } else {
                flowerData.forEach(transaction => {
                    let weight = parseFloat(transaction.weight) || 0;
                    flowerReportTotal += weight;
                    totalWeight += weight;

                    const row = flowerTbody.insertRow();
                    const nameCell = row.insertCell();
                    const weightCell = row.insertCell();
                    const dateCell = row.insertCell();
                    const companyCell = row.insertCell();

                    nameCell.textContent = transaction.geneticsName;
                    weightCell.textContent = transaction.weight;
                    dateCell.textContent = new Date(transaction.transaction_date)
                        .toLocaleDateString('en-NZ', { timeZone: 'Pacific/Auckland' });
                    companyCell.textContent = transaction.companyNameAddress || '-';
                });
                // Add Footer
                const footerRow = flowerTbody.insertRow();
                footerRow.style.fontWeight = 'bold';
                footerRow.insertCell().textContent = 'Total';
                footerRow.insertCell().textContent = flowerReportTotal.toFixed(2);
                footerRow.insertCell(); // Date placeholder
                footerRow.insertCell(); // Company placeholder
            }

            // Process plant data
            let plantReportTotal = 0;
            if (plantData.length === 0) {
                plantTbody.innerHTML = '<tr><td colspan="4">Nothing to report</td></tr>';
            } else {
                plantData.forEach(transaction => {
                    let count = parseInt(transaction.plantCount) || 0;
                    plantReportTotal += count;

                    const row = plantTbody.insertRow();
                    const nameCell = row.insertCell();
                    const countCell = row.insertCell();
                    const dateCell = row.insertCell();
                    const companyCell = row.insertCell();

                    nameCell.textContent = transaction.geneticsName;
                    countCell.textContent = transaction.plantCount;
                    dateCell.textContent = new Date(transaction.transaction_date)
                        .toLocaleDateString('en-NZ', { timeZone: 'Pacific/Auckland' });
                    companyCell.textContent = transaction.companyNameAddress || '-';
                });
                // Add Footer
                const footerRow = plantTbody.insertRow();
                footerRow.style.fontWeight = 'bold';
                footerRow.insertCell().textContent = 'Total';
                footerRow.insertCell().textContent = plantReportTotal;
                footerRow.insertCell(); // Date placeholder
                footerRow.insertCell(); // Company placeholder
            }

            if (totalWeightSentSpan) {
                totalWeightSentSpan.textContent = totalWeight.toFixed(2);
            }
        })
        .catch(error => {
            console.error('Error fetching or processing transaction data:', error);
            if (totalWeightSentSpan) totalWeightSentSpan.textContent = 'Error';
            flowerTbody.innerHTML = '<tr><td colspan="4">Error loading flower data. Please check the console for details.</td></tr>';
            plantTbody.innerHTML = '<tr><td colspan="4">Error loading plant data. Please check the console for details.</td></tr>';
        });
}

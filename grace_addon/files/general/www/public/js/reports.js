/**
 * reports.js
 * 
 * Called from:
 * - current_dried_flower.php: To display the current dried flower stock table.
 * 
 * Why:
 * Centralizes logic for fetching and displaying reporting data, starting with the dried flower report.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Current Dried Flower Report
    const driedFlowerTable = document.getElementById('driedFlowerTable');
    if (driedFlowerTable) {
        initDriedFlowerReport(driedFlowerTable);
    }
});

function initDriedFlowerReport(tableElement) {
    const tbody = tableElement.getElementsByTagName('tbody')[0];
    const hideZeroCheckbox = document.getElementById('hideZeroRowsCheckbox');
    const noDataMessage = document.getElementById('noDataMessage');

    // Fetch dried flower data from the server
    fetch('get_current_dried_flower.php')
        .then(response => response.json())
        .then(flowerData => {
            flowerData.forEach(item => {
                const row = tbody.insertRow();
                const nameCell = row.insertCell();
                const weightCell = row.insertCell();

                nameCell.textContent = item.geneticsName;
                weightCell.textContent = item.totalWeight;
            });
        })
        .catch(error => console.error('Error fetching dried flower data:', error));

    if (hideZeroCheckbox) {
        hideZeroCheckbox.addEventListener('change', function () {
            const rows = tbody.rows;
            let visibleRowCount = 0;

            for (let i = 0; i < rows.length; i++) {
                const weight = parseFloat(rows[i].cells[1].textContent);
                const shouldHide = this.checked && weight === 0;
                rows[i].style.display = shouldHide ? 'none' : '';

                if (!shouldHide) {
                    visibleRowCount++;
                }
            }

            if (visibleRowCount === 0) {
                noDataMessage.textContent = "No dried flowers available.";
                noDataMessage.style.display = 'block';
            } else {
                noDataMessage.style.display = 'none';
            }
        });
    }
}

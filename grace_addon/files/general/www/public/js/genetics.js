/**
 * genetics.js
 *
 * Called from:
 * - list_all_genetics.php: To display the list of all genetics with status filtering.
 * - receive_genetics.php: To handle the "Receive Genetics" form, including fetching genetics list and showing status messages.
 */

// Function to list all genetics with status filter (from list_all_genetics.php)
function initGeneticsList() {
    const table = document.getElementById('geneticsListTable');
    const statusFilter = document.getElementById('statusFilter');

    if (!table || !statusFilter) {
        console.warn('initGeneticsList called but elements not found');
        return;
    }

    const tbody = table.getElementsByTagName('tbody')[0];

    function fetchAndDisplayGenetics(statusFilterValue = '') {
        tbody.innerHTML = '';

        fetch('get_all_genetics.php' + (statusFilterValue ? `?status=${encodeURIComponent(statusFilterValue)}` : ''))
            .then(response => response.json())
            .then(geneticsData => {
                if (!Array.isArray(geneticsData) || geneticsData.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3">No plants found.</td></tr>';
                    return;
                }

                // Sort by age (oldest to newest)
                geneticsData.sort((a, b) => a.age - b.age);

                geneticsData.forEach(genetics => {
                    const row = tbody.insertRow();
                    row.insertCell().textContent = genetics.geneticsName;
                    row.insertCell().textContent = genetics.age;

                    const statusCell = row.insertCell();
                    if (typeof statusBadge === 'function') {
                        statusCell.appendChild(statusBadge(genetics.status));
                    } else {
                        statusCell.textContent = genetics.status;
                    }
                });
            })
            .catch(error => console.error('Error fetching genetics data:', error));
    }

    fetchAndDisplayGenetics();

    statusFilter.addEventListener('change', () => {
        fetchAndDisplayGenetics(statusFilter.value);
    });
}

// Function to handle receiving genetics (from receive_genetics.php)
function initReceiveGenetics() {
    const form = document.getElementById('receiveGeneticsForm');
    const statusMessage = document.getElementById('statusMessage');
    const geneticsDropdown = document.getElementById('geneticsName');

    if (!form || !statusMessage || !geneticsDropdown) {
        console.warn('initReceiveGenetics called but elements not found');
        return;
    }

    // Check if there's a success or error message in the URL parameters
    const urlParams = new URLSearchParams(window.location.search);
    const successMessage = urlParams.get('success');
    const errorMessage = urlParams.get('error');

    if (successMessage) {
        showStatusMessage(successMessage, 'success');
        form.reset(); // Clear the form
    } else if (errorMessage) {
        showStatusMessage(errorMessage, 'error');

        // Pre-populate the form with the submitted data (if available)
        const submittedData = JSON.parse(urlParams.get('data') || '{}');
        if (form.plantCount) form.plantCount.value = submittedData.plantCount || '';
        if (form.geneticsName) form.geneticsName.value = submittedData.geneticsName || '';
    }

    function showStatusMessage(message, type) {
        statusMessage.textContent = message;
        statusMessage.classList.add(type);
        statusMessage.style.display = 'block';

        setTimeout(() => {
            statusMessage.style.display = 'none';
            statusMessage.classList.remove(type);
        }, 5000);
    }

    // Fetch genetics data and populate dropdown on load
    fetch('get_genetics.php')
        .then(response => response.json())
        .then(genetics => {
            genetics.forEach(geneticsItem => {
                const option = document.createElement('option');
                option.value = geneticsItem.id;
                option.textContent = geneticsItem.name;
                geneticsDropdown.appendChild(option);
            });
        })
        .catch(error => console.error('Error fetching genetics:', error));
}

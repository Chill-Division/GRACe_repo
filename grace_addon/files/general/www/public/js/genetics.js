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

    // Pre-populate the form with the submitted data after an error (the
    // genetics choice is restored once the list has loaded, below)
    const submittedData = errorMessage ? JSON.parse(urlParams.get('data') || '{}') : {};

    if (successMessage) {
        showStatusMessage(successMessage, 'success');
        form.reset(); // Clear the form
    } else if (errorMessage) {
        showStatusMessage(errorMessage, 'error');
        if (form.plantCount) form.plantCount.value = submittedData.plantCount || '';
    }

    function showStatusMessage(message, type) {
        statusMessage.textContent = message;
        statusMessage.classList.add(type);
        statusMessage.style.display = 'block';
        statusMessage.setAttribute('role', type === 'error' ? 'alert' : 'status');

        // Errors stay until the next entry, so there's time to read why
        if (type !== 'error') {
            setTimeout(() => {
                statusMessage.style.display = 'none';
                statusMessage.classList.remove(type);
            }, 8000);
        }
    }

    // How many of the chosen genetics are already growing / drying
    const stockHint = document.getElementById('stockHint');
    let stock = {};
    function showStock() {
        if (!stockHint) return;
        const id = geneticsDropdown.value;
        if (!/^\d+$/.test(id)) {
            stockHint.textContent = ''; // nothing chosen, or "+ Add new genetics…"
            return;
        }
        const name = geneticsDropdown.options[geneticsDropdown.selectedIndex].textContent;
        const growing = stock[id] ? stock[id].growing : 0;
        const drying = stock[id] ? stock[id].drying : 0;
        stockHint.textContent = `Growing now: ${growing} ${name} plant${growing === 1 ? '' : 's'}`
            + (drying ? `, plus ${drying} drying.` : '.');
    }
    geneticsDropdown.addEventListener('change', showStock);

    // Show exactly what's about to be recorded before saving it: the ledger
    // can't be edited afterwards. (The browser has already checked the
    // required fields by the time this runs.)
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const count = parseInt(form.plantCount.value, 10);
        const plants = `plant${count === 1 ? '' : 's'}`;
        const geneticsName = geneticsDropdown.options[geneticsDropdown.selectedIndex].textContent;
        const growing = stock[geneticsDropdown.value] ? stock[geneticsDropdown.value].growing : 0;

        confirmAction({
            title: `Add ${count} ${geneticsName} ${plants}?`,
            message: "They're recorded in the ledger with today's date and can't be edited afterwards.",
            items: [`${count} × ${geneticsName}`, `Growing after this: ${growing + count}`],
            confirmLabel: `Add ${count} ${plants}`
        }).then(confirmed => {
            if (!confirmed) return;
            form.submit();
        });
    });

    fetch('get_stock_on_hand.php')
        .then(response => response.json())
        .then(data => { stock = data || {}; showStock(); })
        .catch(error => console.error('Error fetching stock on hand:', error));

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
            if (submittedData.geneticsName) geneticsDropdown.value = submittedData.geneticsName;
            showStock();

            // "+ Add new genetics…" at the bottom of the list (quick_add.js)
            if (typeof enableQuickAddGenetics === 'function') enableQuickAddGenetics(geneticsDropdown);
        })
        .catch(error => console.error('Error fetching genetics:', error));
}

<?php
$pageTitle = 'GRACe - Harvest/Destroy/Send Plants';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Harvest / Destroy / Send Plants</h1>
            <p>Select the plants below, choose an action, and process them in one go.</p>
        </hgroup>

        <div class="toolbar">
            <div>
                <label for="action">Action:</label>
                <select id="action" name="action" class="input" required>
                    <option value="harvest">Harvested - Drying</option>
                    <option value="destroy">Harvested - Destroyed</option>
                    <option value="send">Send External</option>
                </select>
            </div>

            <div id="companySelection" style="display: none;">
                <label for="companyId">Company:</label>
                <select id="companyId" name="companyId" class="input">
                    <option value="" disabled selected>Select Company</option>
                </select>
            </div>

            <div>
                <label for="quickSelectGenetics">Quick select:</label>
                <select id="quickSelectGenetics" class="input">
                    <option value="" disabled selected>Genetics</option>
                </select>
            </div>

            <div>
                <label for="quickSelectCount">How many:</label>
                <input type="number" id="quickSelectCount" class="input" min="1" step="1" style="width: 7rem;">
            </div>

            <div>
                <label for="quickSelectOrder">Starting with:</label>
                <select id="quickSelectOrder" class="input">
                    <option value="oldest">Oldest first</option>
                    <option value="youngest">Youngest first</option>
                </select>
            </div>

            <button type="button" class="button" id="quickSelectButton">Select</button>
        </div>

        <figure class="table-wrap">
            <table id="plantsTable" class="table">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="selectAllCheckbox" aria-label="Select all plants"></th>
                        <th>Genetics Name</th>
                        <th>Age (Days)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </figure>

        <button type="button" class="button" id="processSelectedButton">Process Selected</button>
    </main>

    <script>
        const plantsTable = document.getElementById('plantsTable').getElementsByTagName('tbody')[0];
        const selectAllCheckbox = document.getElementById('selectAllCheckbox');
        const processSelectedButton = document.getElementById('processSelectedButton');
        const actionDropdown = document.getElementById('action');
        const companySelection = document.getElementById('companySelection');
        const companyDropdown = document.getElementById('companyId');
        const quickSelectGenetics = document.getElementById('quickSelectGenetics');
        const quickSelectCount = document.getElementById('quickSelectCount');
        const quickSelectOrder = document.getElementById('quickSelectOrder');
        const quickSelectButton = document.getElementById('quickSelectButton');

        // Fetch plant data from the server
        fetch('get_plants_for_harvest.php')
            .then(response => response.json())
            .then(plantsData => {
                plantsData.forEach(plant => {
                    const row = plantsTable.insertRow();

                    const checkboxCell = row.insertCell();
                    const checkbox = document.createElement('input');
                    checkbox.type = 'checkbox';
                    checkbox.name = 'selectedPlants[]';
                    checkbox.value = plant.id;
                    checkboxCell.appendChild(checkbox);

                    const nameCell = row.insertCell();
                    const ageCell = row.insertCell();
                    const statusCell = row.insertCell();

                    nameCell.textContent = plant.geneticsName;
                    ageCell.textContent = plant.age;
                    if (typeof statusBadge === 'function') {
                        statusCell.appendChild(statusBadge(plant.status));
                    } else {
                        statusCell.textContent = plant.status;
                    }
                });

                // Offer each genetics in the list for quick selection
                [...new Set(plantsData.map(plant => plant.geneticsName))].sort().forEach(name => {
                    const option = document.createElement('option');
                    option.value = name;
                    option.textContent = name;
                    quickSelectGenetics.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching plant data:', error));


        // Create selection counter element (styled via #selectionCounter in growcart.css)
        const selectionCounter = document.createElement('div');
        selectionCounter.id = 'selectionCounter';
        document.body.appendChild(selectionCounter);

        function updateSelectionCount() {
            const selectedCheckboxes = plantsTable.querySelectorAll('input[type="checkbox"]:checked');
            const count = selectedCheckboxes.length;

            if (count > 0) {
                selectionCounter.textContent = `${count} plant${count !== 1 ? 's' : ''} selected`;
                selectionCounter.style.display = 'block';
            } else {
                selectionCounter.style.display = 'none';
            }
        }

        // Handle "Select All" checkbox
        selectAllCheckbox.addEventListener('change', () => {
            const checkboxes = plantsTable.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
            updateSelectionCount();
        });

        // Add event listener to individual checkboxes dynamically via delegation or after fetch
        // Since we create checkboxes in code, better to attach listener in the fetch loop or delegate.
        // Let's delegate to the table body.
        plantsTable.addEventListener('change', (event) => {
             if (event.target.type === 'checkbox' && event.target.name === 'selectedPlants[]') {
                 updateSelectionCount();
             }
        });

        // Quick select: tick N plants of one genetics in age order. It only
        // pre-fills the checkboxes, so individual plants can still be ticked
        // or unticked by hand before processing (e.g. to hold back a mother
        // plant that would otherwise be picked as one of the oldest).
        quickSelectButton.addEventListener('click', () => {
            const genetics = quickSelectGenetics.value;
            const wanted = parseInt(quickSelectCount.value, 10);

            if (!genetics) {
                showToast('Choose a genetics to quick select.', 'error');
                return;
            }
            if (!wanted || wanted < 1) {
                showToast('Enter how many plants to select.', 'error');
                return;
            }

            const rows = [...plantsTable.rows].filter(row => row.cells[1].textContent === genetics);
            // Oldest first means highest age first; equal ages keep their table order
            rows.sort((a, b) => {
                const ageA = parseInt(a.cells[2].textContent, 10) || 0;
                const ageB = parseInt(b.cells[2].textContent, 10) || 0;
                return quickSelectOrder.value === 'oldest' ? ageB - ageA : ageA - ageB;
            });

            // Re-running replaces this genetics' previous selection, so
            // "select 88" always means exactly 88. Other genetics keep
            // whatever is already ticked.
            rows.forEach((row, index) => {
                row.querySelector('input[type="checkbox"]').checked = index < wanted;
            });
            updateSelectionCount();

            if (rows.length === 0) {
                showToast(`No ${genetics} plants in the list.`, 'error');
            } else if (wanted > rows.length) {
                showToast(`Only ${rows.length} ${genetics} available. Selected all ${rows.length}.`, 'info');
            } else {
                showToast(`Selected ${wanted} of ${rows.length} ${genetics}, ${quickSelectOrder.value} first.`, 'success');
            }
        });

        // Handle "Process Selected" button click
        processSelectedButton.addEventListener('click', () => {
            const selectedCheckboxes = plantsTable.querySelectorAll('input[type="checkbox"]:checked');
            const selectedPlantIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
            const selectedAction = actionDropdown.value;

            if (selectedPlantIds.length === 0) {
                showToast('Please select at least one plant to process.', 'error');
                return;
            }

            if (selectedAction === 'send' && !companyDropdown.value) {
                showToast('Please select a company for external sending.', 'error');
                return;
            }

            // Build a per-genetics summary of what's about to happen, the ledger
            // can't be edited afterwards, so make the user review it first
            const countsByGenetics = {};
            selectedCheckboxes.forEach(checkbox => {
                const name = checkbox.closest('tr').cells[1].textContent;
                countsByGenetics[name] = (countsByGenetics[name] || 0) + 1;
            });
            const summaryItems = Object.keys(countsByGenetics).sort().map(
                name => `${countsByGenetics[name]} × ${name}`
            );

            const actionLabels = {
                harvest: 'Harvested - Drying',
                destroy: 'Harvested - Destroyed',
                send: 'Send External'
            };
            let actionText = actionLabels[selectedAction] || selectedAction;
            if (selectedAction === 'send') {
                const companyName = companyDropdown.options[companyDropdown.selectedIndex].textContent;
                actionText += ' to ' + companyName;
            }
            const total = selectedPlantIds.length;

            confirmAction({
                title: `Process ${total} plant${total !== 1 ? 's' : ''}?`,
                message: `Action: ${actionText}. This is recorded in the ledger and cannot be edited afterwards.`,
                items: summaryItems,
                confirmLabel: actionLabels[selectedAction] ? `Confirm: ${actionLabels[selectedAction]}` : 'Confirm',
                danger: selectedAction === 'destroy'
            }).then(confirmed => {
                if (!confirmed) return;

                // Send selected plant IDs, action, and company (if applicable) to the server
                fetch('handle_harvest_plants.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ selectedPlants: selectedPlantIds, action: selectedAction, companyId: companyDropdown.value })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        flashToast(data.message, 'success');
                        location.reload();
                    } else {
                        console.error('Error from server:', data.message);
                        showToast('An error occurred: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error during fetch or processing response:', error);
                    showToast('An error occurred. Please check the console for details.', 'error');
                });
            });
        });

        // Show/Hide company selection based on action
        actionDropdown.addEventListener('change', () => {
            companySelection.style.display = actionDropdown.value === 'send' ? 'block' : 'none';
        });

        // Fetch and populate company dropdown
        fetch('get_companies.php')
            .then(response => response.json())
            .then(companies => {
                companies.forEach(company => {
                    const option = document.createElement('option');
                    option.value = company.id;
                    option.textContent = company.name;
                    companyDropdown.appendChild(option);
                });
            })
            .catch(error => console.error('Error fetching companies:', error));
    </script>
<?php require 'footer.php'; ?>

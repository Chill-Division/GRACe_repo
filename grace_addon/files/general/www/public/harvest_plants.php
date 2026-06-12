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

            // Build a per-genetics summary of what's about to happen — the ledger
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

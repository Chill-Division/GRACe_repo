<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css">
    <title>GRACe - Harvest/Destroy/Send Plants</title>
</head>
<body>
    <header class="container-fluid">
        <?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Harvest/Destroy/Send Plants</h1>

        <p><small>Manage plant actions here.</small></p>

        <label for="action">Action:</label>
        <select id="action" name="action" class="input" required>
            <option value="harvest">Harvest</option>
            <option value="destroy">Destroy</option>
            <option value="send">Send External</option>
        </select>

        <div id="companySelection" style="display: none;">
            <label for="companyId">Company:</label>
            <select id="companyId" name="companyId" class="input">
                <option value="" disabled selected>Select Company</option>
            </select>
        </div>

        <table id="plantsTable" class="table">
            <thead>
                <tr>
                    <th><input type="checkbox" id="selectAllCheckbox"></th>
                    <th>Genetics Name</th>
                    <th>Age (Days)</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>

        <button type="button" class="button" id="processSelectedButton">Process Selected</button>
    </main>

    <script src="js/growcart.js"></script>
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
                    statusCell.textContent = plant.status;
                });
            })
            .catch(error => console.error('Error fetching plant data:', error));

        // Handle "Select All" checkbox
        selectAllCheckbox.addEventListener('change', () => {
            const checkboxes = plantsTable.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(checkbox => checkbox.checked = selectAllCheckbox.checked);
        });

        // Handle "Process Selected" button click
        processSelectedButton.addEventListener('click', () => {
            const selectedCheckboxes = plantsTable.querySelectorAll('input[type="checkbox"]:checked');
            const selectedPlantIds = Array.from(selectedCheckboxes).map(checkbox => checkbox.value);
            const selectedAction = actionDropdown.value;

            if (selectedPlantIds.length === 0) {
                alert('Please select at least one plant to process.');
                return;
            }

            if (selectedAction === 'send' && !companyDropdown.value) {
                alert('Please select a company for external sending.');
                return;
            }

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
                    alert(data.message);
                    location.reload();
                } else {
                    console.error('Error from server:', data.message);
                    alert('An error occurred: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error during fetch or processing response:', error);
                alert('An error occurred. Please check the console for details.');
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
</body>
</html>

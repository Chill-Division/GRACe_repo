<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - List All Genetics</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>List All Genetics</h1>

        <label for="statusFilter">Filter by Status:</label>
        <select id="statusFilter" name="statusFilter" class="input">
            <option value="">All</option>
            <option value="Growing">Growing</option>
            <option value="Harvested">Harvested</option>
            <option value="Destroyed">Destroyed</option>
            <option value="Sent">Sent</option>
        </select>

        <table id="geneticsListTable" class="table">
            <thead>
                <tr>
                    <th>Genetics Name</th>
                    <th>Age (Days)</th> 
                    <th>Status</th> 
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
    </main>

    <script src="js/growcart.js"></script> 
    <script>
        const geneticsListTable = document.getElementById('geneticsListTable').getElementsByTagName('tbody')[0];
        const statusFilter = document.getElementById('statusFilter');

        // Fetch genetics data from the server
        function fetchAndDisplayGenetics(statusFilterValue = '') {
            // Clear the table body before populating it with new data
            geneticsListTable.innerHTML = ''; 

            fetch('get_all_genetics.php' + (statusFilterValue ? `?status=${statusFilterValue}` : ''))
                .then(response => response.json())
                .then(geneticsData => {
                    // Sort by age (oldest to newest)
                    geneticsData.sort((a, b) => a.age - b.age);

                    geneticsData.forEach(genetics => {
                        const row = geneticsListTable.insertRow();
                        const nameCell = row.insertCell();
                        const ageCell = row.insertCell();
                        const statusCell = row.insertCell();

                        nameCell.textContent = genetics.geneticsName;
                        ageCell.textContent = genetics.age;
                        statusCell.textContent = genetics.status;
                    });
                })
                .catch(error => console.error('Error fetching genetics data:', error));
        }

        // Initial fetch on page load
        fetchAndDisplayGenetics();

        // Handle status filter change
        statusFilter.addEventListener('change', () => {
            const selectedStatus = statusFilter.value;
            fetchAndDisplayGenetics(selectedStatus);
        });
    </script>
</body>
</html>

<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - Receive Genetics</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div> 

        <h1>Receive Genetics</h1>

	<p><small>Any time you're receiving or adding genetics, either through a Form D declaration, taking clones, or from another licensed cultivator, this is where you want to add them.</small></p>

        <form id="receiveGeneticsForm" class="form" action="handle_receive_genetics.php" method="post"> 

            <label for="plantCount">How many plants received / clones taken:</label>
            <input type="number" id="plantCount" name="plantCount" class="input" min="1" required>

            <label for="geneticsName">Genetics Name:</label>
            <select id="geneticsName" name="geneticsName" class="input" required>
                <option value="" disabled selected>Select Genetics</option>
            </select>

            <button type="submit" class="button">Add plants</button>
        </form>
    </main>

    <script src="js/growcart.js"></script> 
    <script>
        const form = document.getElementById('receiveGeneticsForm');
        const statusMessage = document.getElementById('statusMessage');
        const geneticsDropdown = document.getElementById('geneticsName');

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
            form.plantCount.value = submittedData.plantCount || '';
            form.geneticsName.value = submittedData.geneticsName || '';
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

        // Fetch genetics data and populate dropdown on page load
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
    </script>
</body>
</html>

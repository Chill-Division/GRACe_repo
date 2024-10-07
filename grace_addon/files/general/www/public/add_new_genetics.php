<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css">
    <title>GRACe - Add New Genetics</title>
    <style>
        /* Add some basic styling for the status message */
        .status-message {
            padding: 0.5rem 1rem;
            margin-bottom: 1rem;
            border-radius: 0.25rem;
        }

        .success {
            background-color: var(--success);
            color: var(--success-contrast);
        }

        .error {
            background-color: var(--error);
            color: var(--error-contrast);
        }
    </style>
</head>
<body>
    <header class="container-fluid">
        <?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div>

        <h1>Add New Genetics</h1>

        <p>Prior to genetics being available to clone or receive, they need to be added into your system here.</p>

        <form id="addGeneticsForm" class="form" action="handle_add_new_genetics.php" method="post">
            <label for="geneticsName">Genetics Name:</label>
            <input type="text" id="geneticsName" name="geneticsName" class="input" required>

            <label for="breeder">Breeder (Optional):</label>
            <input type="text" id="breeder" name="breeder" class="input">

            <label for="geneticLineage">Genetic Lineage (Optional):</label>
            <textarea id="geneticLineage" name="geneticLineage" class="input" rows="3"></textarea>

            <button type="submit" class="button">Add Genetics</button>
        </form>
    </main>

    <script src="js/growcart.js"></script>
    <script>
        const form = document.getElementById('addGeneticsForm');
        const statusMessage = document.getElementById('statusMessage');

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
            form.geneticsName.value = submittedData.geneticsName || '';
            form.breeder.value = submittedData.breeder || '';
            form.geneticLineage.value = submittedData.geneticLineage || '';
        }

        function showStatusMessage(message, type) {
            statusMessage.textContent = message;
            statusMessage.classList.add(type);
            statusMessage.style.display = 'block';

            // Hide the message after a few seconds
            setTimeout(() => {
                statusMessage.style.display = 'none';
                statusMessage.classList.remove(type);
            }, 5000); // Adjust the timeout as needed
        }
    </script>
</body>
</html>

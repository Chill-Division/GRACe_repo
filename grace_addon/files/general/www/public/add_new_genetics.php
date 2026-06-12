<?php
require_once 'auth.php';
$pageTitle = 'GRACe - Add New Genetics';
require 'header.php';
?>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div>

        <hgroup class="page-header">
            <h1>Add New Genetics</h1>
            <p>Prior to genetics being available to clone or receive, they need to be added into your system here.</p>
        </hgroup>

        <article class="form-card">
            <form id="addGeneticsForm" class="form" action="handle_add_new_genetics.php" method="post">
                <label for="geneticsName">Genetics Name:</label>
                <input type="text" id="geneticsName" name="geneticsName" class="input" required>

                <label for="breeder">Breeder (Optional):</label>
                <input type="text" id="breeder" name="breeder" class="input">

                <label for="geneticLineage">Genetic Lineage (Optional):</label>
                <textarea id="geneticLineage" name="geneticLineage" class="input" rows="3"></textarea>

                <button type="submit" class="button">Add Genetics</button>
            </form>
        </article>
    </main>

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
            alert(errorMessage, 'error');

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
<?php require 'footer.php'; ?>

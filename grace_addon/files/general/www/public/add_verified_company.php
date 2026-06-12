<?php
require_once 'auth.php';
$pageTitle = 'GRACe - Add Verified Company';
require 'header.php';
?>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div>

        <hgroup class="page-header">
            <h1>Add Verified Company</h1>
            <p>Companies added here will show up as a destination in the forms for CoC / Testing.</p>
        </hgroup>

        <article class="form-card">
            <form id="addCompanyForm" class="form">
                <label for="companyName">Company Name:</label>
                <input type="text" id="companyName" name="companyName" class="input" required>

                <label for="licenseNumber">License #:</label>
                <input type="text" id="licenseNumber" name="licenseNumber" class="input" required>

                <label for="address">Address:</label>
                <textarea id="address" name="address" class="input" rows="3" required></textarea>

                <label for="contactName">Primary Contact Name:</label>
                <input type="text" id="contactName" name="contactName" class="input" required>

                <label for="contactEmail">Primary Contact Email:</label>
                <input type="email" id="contactEmail" name="contactEmail" class="input" required>

                <label for="contactPhone">Primary Contact Phone:</label>
                <input type="tel" id="contactPhone" name="contactPhone" class="input" required>

                <button type="submit" class="button">Add Company</button>
            </form>
        </article>
    </main>

    <script>
        const form = document.getElementById('addCompanyForm');
        const statusMessage = document.getElementById('statusMessage');

        form.addEventListener('submit', (event) => {
            event.preventDefault();

            const formData = new FormData(form);

            fetch('handle_add_verified_company.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(message => {
                    if (message.startsWith('Success')) {
                        showStatusMessage(message, 'success');
                        form.reset();
                    } else if (message.startsWith('Error')) {
                        alert(message);
                        showStatusMessage(message, 'error');
                    } else {
                    showStatusMessage('Unexpected response: ' + message, 'error');
                    }
            })
        .catch(error => {
                console.error('Error adding company:', error);
                showStatusMessage('An error occurred while adding the company.', 'error');
            });
        });

        function showStatusMessage(message, type) {
            statusMessage.textContent = message;
            statusMessage.classList.add(type);
            statusMessage.style.display = 'block';

            setTimeout(() => {
                statusMessage.style.display = 'none';
                statusMessage.classList.remove(type);
            }, 5000);
        }
    </script>
<?php require 'footer.php'; ?>

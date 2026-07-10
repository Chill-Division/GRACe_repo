<?php
require_once 'init_db.php';

$pdo = initializeDatabase();

// Load the company being edited; bounce back to the list if the id is bogus
$companyId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$company = null;
if ($companyId > 0) {
    $stmt = $pdo->prepare("SELECT id, name, license_number, address, primary_contact_name, primary_contact_email, primary_contact_phone
                           FROM Companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$company) {
    header('Location: verified_companies.php');
    exit();
}

$pageTitle = 'GRACe - Edit ' . $company['name'];
require 'header.php';
?>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div>

        <hgroup class="page-header">
            <h1>Edit <?php echo htmlspecialchars($company['name']); ?></h1>
            <p>Update this company's details, for example when their license is renewed each year or their staff contact changes. New Chain of Custody documents and manifests will use the updated details; records already in your ledger keep the details from when they were created.</p>
        </hgroup>

        <article class="form-card">
            <form id="editCompanyForm" class="form">
                <input type="hidden" name="companyId" value="<?php echo (int) $company['id']; ?>">

                <label for="companyName">Company Name:</label>
                <input type="text" id="companyName" name="companyName" class="input" required value="<?php echo htmlspecialchars($company['name']); ?>">

                <label for="licenseNumber">License #:</label>
                <input type="text" id="licenseNumber" name="licenseNumber" class="input" required value="<?php echo htmlspecialchars($company['license_number']); ?>">

                <label for="address">Address:</label>
                <textarea id="address" name="address" class="input" rows="3" required><?php echo htmlspecialchars($company['address'] ?? ''); ?></textarea>

                <label for="contactName">Primary Contact Name:</label>
                <input type="text" id="contactName" name="contactName" class="input" required value="<?php echo htmlspecialchars($company['primary_contact_name'] ?? ''); ?>">

                <label for="contactEmail">Primary Contact Email:</label>
                <input type="email" id="contactEmail" name="contactEmail" class="input" required value="<?php echo htmlspecialchars($company['primary_contact_email'] ?? ''); ?>">

                <label for="contactPhone">Primary Contact Phone:</label>
                <input type="tel" id="contactPhone" name="contactPhone" class="input" required value="<?php echo htmlspecialchars($company['primary_contact_phone'] ?? ''); ?>">

                <button type="submit" class="button">Save Changes</button>
                <a href="verified_companies.php" role="button" class="secondary outline">Cancel</a>
            </form>
        </article>
    </main>

    <script>
        const form = document.getElementById('editCompanyForm');
        const statusMessage = document.getElementById('statusMessage');

        form.addEventListener('submit', (event) => {
            event.preventDefault();

            fetch('handle_edit_verified_company.php', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(response => response.text())
            .then(message => {
                if (message.startsWith('Success')) {
                    flashToast('Company details updated.', 'success');
                    window.location.href = 'verified_companies.php';
                } else {
                    showStatusMessage(message, 'error');
                }
            })
            .catch(() => {
                showStatusMessage('An error occurred while saving the company.', 'error');
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

<?php
require_once 'init_db.php';

// Initialize PDO connection
$pdo = initializeDatabase();

// Fetch OwnCompany details
$ownCompanyStmt = $pdo->query("SELECT company_name, company_license_number, company_address, primary_contact_email FROM OwnCompany LIMIT 1");
$ownCompany = $ownCompanyStmt->fetch(PDO::FETCH_ASSOC);

// Fetch list of external companies, sorted alphabetically by name
$companiesStmt = $pdo->query("SELECT id, name, license_number, address, primary_contact_email FROM Companies ORDER BY name ASC");
$companies = $companiesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch genetics for dropdown, sorted alphabetically by name
$geneticsStmt = $pdo->query("SELECT id, name FROM Genetics ORDER BY name ASC");
$geneticsList = $geneticsStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'GRACe - Generate Shipping Manifest';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Generate Shipping Manifest</h1>
            <p>Create a manifest for plants or flower moving between license holders.</p>
        </hgroup>

        <article class="form-card">
            <form id="shippingManifestForm" class="form" method="post" action="process_shipping_manifest.php">
                <h2>Sending Party</h2>
                <label for="sendingChoice">Choose:</label>
                <select id="sendingChoice" name="sendingChoice" class="input" required>
                    <option value="us">Us</option>
                    <option value="external">External</option>
                </select>

                <div id="sendingDetails"></div>

                <h2>Receiving Party</h2>
                <label for="receivingChoice">Choose:</label>
                <select id="receivingChoice" name="receivingChoice" class="input" required>
                    <option value="us">Us</option>
                    <option value="external">External</option>
                </select>

                <div id="receivingDetails"></div>

                <h2>Product Details</h2>
                <label for="productType">Product Type:</label>
                <select id="productType" name="productType" class="input" required>
                    <option value="flower">Flower</option>
                    <option value="plant">Plant</option>
                </select>

                <label for="quantity">Quantity or Weight:</label>
                <input type="number" id="quantity" name="quantity" class="input" min="1" step="0.01" required>

                <label for="geneticsName">Genetics:</label>
                <select id="geneticsName" name="geneticsName" class="input" required>
                    <?php foreach ($geneticsList as $genetic): ?>
                        <option value="<?php echo htmlspecialchars($genetic['name']); ?>">
                            <?php echo htmlspecialchars($genetic['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="button">Generate Manifest</button>
            </form>
        </article>
    </main>

    <script>
        const sendingChoice = document.getElementById('sendingChoice');
        const receivingChoice = document.getElementById('receivingChoice');

        const ownCompany = <?php echo json_encode($ownCompany); ?>;
        const companies = <?php echo json_encode($companies); ?>;

        const populateDetails = (choice, detailElementId) => {
            const detailElement = document.getElementById(detailElementId);
            detailElement.innerHTML = ''; // clear previous details

            if (choice === 'us') {
                detailElement.innerHTML = `
                    <label for="companyName">Company Name:</label>
                    <input type="text" name="companyName" class="input" value="${ownCompany.company_name}" readonly>

                    <label for="licenseNumber">License #:</label>
                    <input type="text" name="licenseNumber" class="input" value="${ownCompany.company_license_number}" readonly>

                    <label for="address">Address:</label>
                    <textarea name="address" class="input" rows="2" readonly>${ownCompany.company_address}</textarea>

                    <label for="contactEmail">Contact Email:</label>
                    <input type="email" name="contactEmail" class="input" value="${ownCompany.primary_contact_email}" readonly>
                `;
            } else {
                let options = '<label for="companySelect">Select Company:</label>';
                options += '<select id="companySelect" name="companySelect" class="input" required>';
                companies.forEach(company => {
                    options += `<option value="${company.id}">${company.name}</option>`;
                });
                options += '</select>';
                detailElement.innerHTML = options;

                const companySelect = detailElement.querySelector('#companySelect');
                companySelect.addEventListener('change', function() {
                    updateExternalDetails(this, detailElement);
                });
                updateExternalDetails(companySelect, detailElement);
            }
        };

        const updateExternalDetails = (selectElement, detailElement) => {
            let infoContainer = document.createElement('div');
            const selectedCompany = companies.find(company => company.id == selectElement.value);
            infoContainer.innerHTML = `
                <label for="companyName">Company Name:</label>
                <input type="text" name="companyName" class="input" value="${selectedCompany.name}" readonly>

                <label for="licenseNumber">License #:</label>
                <input type="text" name="licenseNumber" class="input" value="${selectedCompany.license_number}" readonly>

                <label for="address">Address:</label>
                <textarea name="address" class="input" rows="2" readonly>${selectedCompany.address}</textarea>

                <label for="contactEmail">Contact Email:</label>
                <input type="email" name="contactEmail" class="input" value="${selectedCompany.primary_contact_email}" readonly>
            `;
            // Remove any previous appended company details
                const existingDetails = detailElement.querySelector('div');
                if (existingDetails) {
                    existingDetails.remove();
                }

                // Append new details
                detailElement.appendChild(infoContainer);
            };

        sendingChoice.addEventListener('change', () => {
            populateDetails(sendingChoice.value, 'sendingDetails');
        });

        receivingChoice.addEventListener('change', () => {
            populateDetails(receivingChoice.value, 'receivingDetails');
        });

        // Initialize with default options
        populateDetails(sendingChoice.value, 'sendingDetails');
        populateDetails(receivingChoice.value, 'receivingDetails');
    </script>
<?php require 'footer.php'; ?>

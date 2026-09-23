<?php
require_once 'init_db.php';
require_once 'settings_lib.php';

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

// Above these the form asks for an extra tick before generating
$limits = getEntryWarningLimits($pdo);

// Current dried-flower stock per genetics, so the form can show what an
// automatic deduction will run against before the manifest is generated
$stockStmt = $pdo->query("SELECT genetics_id, COALESCE(SUM(weight), 0) AS total FROM Flower GROUP BY genetics_id");
$flowerStock = [];
foreach ($stockStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $flowerStock[(int) $row['genetics_id']] = (float) $row['total'];
}

$pageTitle = 'GRACe - Generate Shipping Manifest';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Generate Shipping Manifest</h1>
            <p>Create a manifest for plants or flower moving between license holders.</p>
        </hgroup>

        <article class="form-card">
            <p><strong>How this works:</strong> generating a manifest records it as <span class="badge badge--drying">In Progress</span>.
            If you are sending <strong>flower</strong> to an external company, the shipped weight is
            <strong>automatically deducted from your dried-flower inventory</strong> the moment the manifest is generated,
            so no separate ledger entry is needed. The manifest stays In Progress until the signed Chain of Custody
            (photo or PDF) is attached on the <a href="complete_manifest.php">Complete Manifest</a> page.</p>
        </article>

        <article class="form-card">
            <form id="shippingManifestForm" class="form" method="post" action="process_shipping_manifest.php"
                  data-large-plants="<?php echo (int) $limits['plants']; ?>"
                  data-large-grams="<?php echo (int) $limits['grams']; ?>">
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

                <label for="quantity">Quantity or Weight (grams for flower):</label>
                <input type="number" id="quantity" name="quantity" class="input" min="0.01" step="0.01" required>

                <label for="geneticsId">Genetics:</label>
                <select id="geneticsId" name="geneticsId" class="input" required>
                    <?php foreach ($geneticsList as $genetic): ?>
                        <option value="<?php echo (int) $genetic['id']; ?>">
                            <?php echo htmlspecialchars($genetic['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small id="stockHint"></small>

                <button type="submit" class="button">Generate Manifest</button>
            </form>
        </article>
    </main>

    <script src="js/quick_add.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
    <script>
        const sendingChoice = document.getElementById('sendingChoice');
        const receivingChoice = document.getElementById('receivingChoice');

        const ownCompany = <?php echo json_encode($ownCompany); ?>;
        const companies = <?php echo json_encode($companies); ?>;
        const flowerStock = <?php echo json_encode($flowerStock); ?>;

        // Surface processing errors (e.g. insufficient stock) sent back via query string
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('error')) {
            document.addEventListener('DOMContentLoaded', () => showToast(urlParams.get('error'), 'error', 8000));
        }

        // prefix distinguishes the sending fields from the receiving fields so
        // both sides can be external without the POSTed values clobbering each other
        const populateDetails = (choice, detailElementId, prefix) => {
            const detailElement = document.getElementById(detailElementId);
            detailElement.innerHTML = ''; // clear previous details

            if (choice === 'us') {
                detailElement.innerHTML = `
                    <label>Company Name:</label>
                    <input type="text" name="${prefix}CompanyName" class="input" value="${escapeHtml(ownCompany.company_name)}" readonly>

                    <label>License #:</label>
                    <input type="text" name="${prefix}LicenseNumber" class="input" value="${escapeHtml(ownCompany.company_license_number)}" readonly>

                    <label>Address:</label>
                    <textarea name="${prefix}Address" class="input" rows="2" readonly>${escapeHtml(ownCompany.company_address)}</textarea>

                    <label>Contact Email:</label>
                    <input type="email" name="${prefix}ContactEmail" class="input" value="${escapeHtml(ownCompany.primary_contact_email)}" readonly>
                `;
            } else {
                // No company is picked for you: choosing the first one in the
                // list by default made it easy to address a shipment wrongly
                let options = '<label>Select Company:</label>';
                options += `<select name="${prefix}CompanySelect" class="input" required>`;
                options += '<option value="" disabled selected>Select company</option>';
                companies.forEach(company => {
                    options += `<option value="${escapeHtml(company.id)}">${escapeHtml(company.name)}</option>`;
                });
                options += '</select>';
                detailElement.innerHTML = options;

                const companySelect = detailElement.querySelector('select');
                companySelect.addEventListener('change', function() {
                    updateExternalDetails(this, detailElement, prefix);
                });

                // "+ Add new company…" at the bottom of the list (quick_add.js).
                // A new company joins this page's list, so its details show,
                // and the other party's list too.
                enableQuickAddCompany(companySelect, saved => {
                    if (!companies.some(company => company.id == saved.company.id)) {
                        companies.push(saved.company);
                    }
                    document.querySelectorAll('select[name$="CompanySelect"]').forEach(other => {
                        if (other !== companySelect) addQuickAddOption(other, saved.company.id, saved.company.name);
                    });
                });
            }
        };

        const updateExternalDetails = (selectElement, detailElement, prefix) => {
            const existing = detailElement.querySelector('div');
            const selectedCompany = companies.find(company => company.id == selectElement.value);
            if (!selectedCompany) {
                // Nothing picked yet (or "+ Add new company…" while its pop-up is open)
                if (existing) existing.remove();
                return;
            }
            let infoContainer = document.createElement('div');
            infoContainer.innerHTML = `
                <label>Company Name:</label>
                <input type="text" name="${prefix}CompanyName" class="input" value="${escapeHtml(selectedCompany.name)}" readonly>

                <label>License #:</label>
                <input type="text" name="${prefix}LicenseNumber" class="input" value="${escapeHtml(selectedCompany.license_number)}" readonly>

                <label>Address:</label>
                <textarea name="${prefix}Address" class="input" rows="2" readonly>${escapeHtml(selectedCompany.address)}</textarea>

                <label>Contact Email:</label>
                <input type="email" name="${prefix}ContactEmail" class="input" value="${escapeHtml(selectedCompany.primary_contact_email)}" readonly>
            `;
            // Remove any previous appended company details
                const existingDetails = detailElement.querySelector('div');
                if (existingDetails) {
                    existingDetails.remove();
                }

                // Append new details
                detailElement.appendChild(infoContainer);
            };

        // Show the recorded dried-flower stock the automatic deduction will run against
        const productType = document.getElementById('productType');
        const geneticsSelect = document.getElementById('geneticsId');
        const stockHint = document.getElementById('stockHint');

        const updateStockHint = () => {
            const deductionApplies = productType.value === 'flower' && sendingChoice.value === 'us' && receivingChoice.value === 'external';
            if (productType.value !== 'flower' || !geneticsSelect.value) {
                stockHint.textContent = '';
                return;
            }
            const grams = flowerStock[geneticsSelect.value] || 0;
            const name = geneticsSelect.options[geneticsSelect.selectedIndex].text.trim();
            stockHint.textContent = `Recorded dried-flower stock for ${name}: ${grams} g` +
                (deductionApplies ? '. The manifest weight will be deducted from this automatically.' : '');
        };

        [productType, geneticsSelect].forEach(el => el.addEventListener('change', updateStockHint));

        // Plants are counted in whole numbers; flower is weighed to 0.01 g
        const quantityInput = document.getElementById('quantity');
        const updateQuantityStep = () => {
            const wholePlants = productType.value === 'plant';
            quantityInput.step = wholePlants ? '1' : '0.01';
            quantityInput.min = wholePlants ? '1' : '0.01';
        };
        productType.addEventListener('change', updateQuantityStep);
        updateQuantityStep();

        // An unusually large shipment (Administration → Entry warning limits)
        // has to be ticked as right first, to catch an extra zero
        const manifestForm = document.getElementById('shippingManifestForm');
        manifestForm.addEventListener('submit', (event) => {
            const quantity = parseFloat(quantityInput.value);
            const plants = productType.value === 'plant';
            const limit = parseInt(plants ? manifestForm.dataset.largePlants : manifestForm.dataset.largeGrams, 10) || 0;
            if (!limit || !(quantity > limit)) return; // normal size: generate straight away

            event.preventDefault();
            const amount = plants ? `${quantity} plants` : `${formatGrams(quantity)} g`;
            const geneticsName = geneticsSelect.options[geneticsSelect.selectedIndex].text.trim();
            confirmAction({
                title: `Generate a manifest for ${amount} of ${geneticsName}?`,
                message: productType.value === 'flower' && sendingChoice.value === 'us' && receivingChoice.value === 'external'
                    ? 'The weight comes off your dried-flower stock straight away.' : '',
                confirmLabel: 'Generate manifest',
                warning: `That's more than ${plants ? limit + ' plants' : formatGrams(limit) + ' g'}, your large-entry limit. Check the quantity before you confirm.`,
                warningTick: `Yes, ${amount} is right`
            }).then(confirmed => {
                if (confirmed) manifestForm.submit();
            });
        });

        sendingChoice.addEventListener('change', () => {
            populateDetails(sendingChoice.value, 'sendingDetails', 'sending');
            updateStockHint();
        });

        receivingChoice.addEventListener('change', () => {
            populateDetails(receivingChoice.value, 'receivingDetails', 'receiving');
            updateStockHint();
        });

        // Initialize with default options, once the shared helpers in
        // growcart.js (escapeHtml) have loaded
        document.addEventListener('DOMContentLoaded', () => {
            populateDetails(sendingChoice.value, 'sendingDetails', 'sending');
            populateDetails(receivingChoice.value, 'receivingDetails', 'receiving');
            updateStockHint();
        });
    </script>
<?php require 'footer.php'; ?>

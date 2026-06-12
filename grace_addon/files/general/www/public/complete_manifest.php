<?php
require_once 'init_db.php';
require_once 'manifest_lib.php';

$pdo = initializeDatabase();

$manifestId = (int) ($_GET['id'] ?? 0);
$manifest = $manifestId ? fetchManifest($pdo, $manifestId) : null;

if (!$manifest) {
    // List mode: every manifest still awaiting its Chain of Custody
    $pendingManifests = fetchManifests($pdo, 'In Progress');
} else {
    // Detail mode: CoC documents already uploaded manually that aren't tied
    // to another manifest can be attached instead of uploading a new file
    $stmt = $pdo->query("SELECT id, original_filename, upload_date FROM Documents
                         WHERE category = 'coc'
                           AND id NOT IN (SELECT coc_document_id FROM ShippingManifests WHERE coc_document_id IS NOT NULL)
                         ORDER BY upload_date DESC");
    $availableCocDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'GRACe - Complete Manifest';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Complete Manifest</h1>
            <p>Attach the signed Chain of Custody and close out manifests in transit.</p>
        </hgroup>

<?php if (!$manifest): ?>
        <section>
            <h2>Manifests Awaiting Completion</h2>
            <?php if (!$pendingManifests): ?>
                <article class="form-card"><p>No manifests are awaiting completion. <a href="generate_shipping_manifest.php">Generate a shipping manifest</a> to start one.</p></article>
            <?php else: ?>
            <figure class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Destination</th>
                            <th>Shipment</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingManifests as $row): ?>
                        <tr>
                            <td><?php echo (int) $row['id']; ?></td>
                            <td><?php echo htmlspecialchars($row['shipment_date']); ?></td>
                            <td><?php echo htmlspecialchars($row['receiving_company_name']); ?></td>
                            <td><?php echo htmlspecialchars(manifestShipmentLabel($row)); ?></td>
                            <td>
                                <a href="manifest_summary.php?id=<?php echo (int) $row['id']; ?>">Summary</a> ·
                                <a href="complete_manifest.php?id=<?php echo (int) $row['id']; ?>">Complete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </figure>
            <?php endif; ?>
        </section>
<?php elseif ($manifest['status'] === 'Completed'): ?>
        <article class="form-card">
            <p>Manifest #<?php echo (int) $manifest['id']; ?> is already completed.</p>
            <p><a href="manifest_summary.php?id=<?php echo (int) $manifest['id']; ?>">View the exchange summary</a></p>
        </article>
<?php else: ?>
        <article class="form-card">
            <h2>Manifest #<?php echo (int) $manifest['id']; ?> <?php echo manifestStatusBadge($manifest); ?></h2>
            <figure class="table-wrap">
                <table>
                    <tbody>
                        <tr><td><strong>Prepared</strong></td><td><?php echo htmlspecialchars($manifest['shipment_date']); ?></td></tr>
                        <tr><td><strong>Route</strong></td><td><?php echo htmlspecialchars(manifestRouteLabel($manifest)); ?></td></tr>
                        <tr><td><strong>Shipment</strong></td><td><?php echo htmlspecialchars(manifestShipmentLabel($manifest)); ?></td></tr>
                    </tbody>
                </table>
            </figure>
            <p><a href="manifest_summary.php?id=<?php echo (int) $manifest['id']; ?>">Full exchange summary</a></p>
        </article>

        <article class="form-card">
            <h2>Attach Chain of Custody &amp; Complete</h2>
            <p>A manifest cannot be completed without its signed Chain of Custody (photo or PDF).
               Images over 1MB are compressed automatically.</p>
            <form id="completeManifestForm">
                <input type="hidden" name="manifest_id" value="<?php echo (int) $manifest['id']; ?>">

                <label for="cocFile">Upload the signed Chain of Custody:</label>
                <input type="file" id="cocFile" name="file" accept="image/*,.pdf">

                <?php if (!empty($availableCocDocs)): ?>
                <label for="existingDocId">…or attach one already uploaded on the Chain of Custody page:</label>
                <select id="existingDocId" name="existing_document_id">
                    <option value="">— Select an uploaded document —</option>
                    <?php foreach ($availableCocDocs as $doc): ?>
                    <option value="<?php echo (int) $doc['id']; ?>">
                        <?php echo htmlspecialchars($doc['original_filename'] . ' (uploaded ' . $doc['upload_date'] . ')'); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>

                <button type="submit">Complete Manifest</button>
            </form>
        </article>

        <script src="js/image-compress.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
        <script>
            document.getElementById('completeManifestForm').addEventListener('submit', async function (e) {
                e.preventDefault();
                const form = this;
                const fileInput = document.getElementById('cocFile');
                const existingSelect = document.getElementById('existingDocId');
                const submitButton = form.querySelector('button[type="submit"]');

                let file = fileInput.files[0] || null;
                const existingDocId = existingSelect ? existingSelect.value : '';

                if (!file && !existingDocId) {
                    showToast('Attach a Chain of Custody photo or PDF before completing the manifest.', 'error');
                    return;
                }
                if (file && existingDocId) {
                    showToast('Choose either a new upload or an existing document, not both.', 'error');
                    return;
                }

                const confirmed = await confirmAction({
                    title: 'Complete manifest #<?php echo (int) $manifest['id']; ?>?',
                    message: 'The Chain of Custody will be attached and the manifest closed. This cannot be undone.',
                    confirmLabel: 'Complete Manifest'
                });
                if (!confirmed) return;

                submitButton.disabled = true;
                submitButton.textContent = 'Processing...';

                try {
                    if (file && file.type.match(/^image\//) && file.size > 1024 * 1024 && typeof compressImage === 'function') {
                        submitButton.textContent = 'Compressing image...';
                        file = await compressImage(file, 1024 * 1024);
                    }

                    const formData = new FormData();
                    formData.append('manifest_id', form.querySelector('[name="manifest_id"]').value);
                    if (file) formData.append('file', file, file.name);
                    if (existingDocId) formData.append('existing_document_id', existingDocId);

                    submitButton.textContent = 'Completing...';
                    const response = await fetch('handle_complete_manifest.php', { method: 'POST', body: formData });
                    const result = await response.json();

                    if (result.success) {
                        window.location = 'manifest_summary.php?id=' + result.manifest_id + '&completed=1';
                    } else {
                        showToast(result.message || 'Failed to complete the manifest.', 'error');
                        submitButton.disabled = false;
                        submitButton.textContent = 'Complete Manifest';
                    }
                } catch (error) {
                    showToast('Error: ' + error.message, 'error');
                    submitButton.disabled = false;
                    submitButton.textContent = 'Complete Manifest';
                }
            });
        </script>
<?php endif; ?>
    </main>

<?php require 'footer.php'; ?>

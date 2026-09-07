<?php
require_once 'init_db.php';
require_once 'manifest_lib.php';

$pdo = initializeDatabase();
$manifest = fetchManifest($pdo, $_GET['id'] ?? 0);

$pageTitle = 'GRACe - Manifest Summary';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Manifest Summary</h1>
            <p>Source, destination and details of the exchange.</p>
        </hgroup>

<?php if (!$manifest): ?>
        <article class="form-card">
            <p>Manifest not found.</p>
            <p><a href="complete_manifest.php">Back to Complete Manifest</a></p>
        </article>
<?php else: ?>
        <article class="form-card">
            <h2>Manifest #<?php echo (int) $manifest['id']; ?> <?php echo manifestStatusBadge($manifest); ?></h2>
            <figure class="table-wrap">
                <table>
                    <tbody>
                        <tr><td><strong>Prepared</strong></td><td><?php echo htmlspecialchars($manifest['shipment_date']); ?></td></tr>
                        <tr><td><strong>Sending party</strong></td><td><?php echo htmlspecialchars($manifest['sending_company_name']); ?></td></tr>
                        <tr><td><strong>Receiving party</strong></td><td><?php echo htmlspecialchars($manifest['receiving_company_name']); ?></td></tr>
                        <tr><td><strong>Destination address</strong></td><td><?php echo nl2br(htmlspecialchars($manifest['destination_address'] ?? '')); ?></td></tr>
                        <tr><td><strong>Shipment</strong></td><td><?php echo htmlspecialchars(manifestShipmentLabel($manifest)); ?></td></tr>
                        <tr>
                            <td><strong>Inventory deduction</strong></td>
                            <td>
                                <?php if ($manifest['flower_transaction_id']): ?>
                                    <?php echo htmlspecialchars(abs((float) $manifest['deducted_weight']) . ' g deducted from the dried-flower ledger on ' . $manifest['deduction_date']); ?>
                                <?php elseif ($manifest['product_type'] === 'flower'): ?>
                                    None. This shipment was not sent from our inventory.
                                <?php else: ?>
                                    n/a (plants are processed out via Harvest / Destroy / Send)
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Chain of Custody</strong></td>
                            <td>
                                <?php if ($manifest['coc_document_id']): ?>
                                    <a href="download.php?category=coc&file=<?php echo urlencode($manifest['coc_unique_filename']); ?>" download="<?php echo htmlspecialchars($manifest['coc_filename']); ?>">
                                        <?php echo htmlspecialchars($manifest['coc_filename']); ?>
                                    </a>
                                <?php else: ?>
                                    <span class="badge badge--drying">Not yet attached</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr><td><strong>Completed</strong></td><td><?php echo $manifest['date_completed'] ? htmlspecialchars($manifest['date_completed']) : 'Not yet'; ?></td></tr>
                        <tr>
                            <td><strong>Manifest PDF</strong></td>
                            <td>
                                <?php if ($manifest['manifest_file']): ?>
                                    <a href="download.php?category=manifests&file=<?php echo urlencode($manifest['manifest_file']); ?>" download="shipping-manifest-<?php echo (int) $manifest['id']; ?>.pdf">Download manifest PDF</a>
                                <?php else: ?>
                                    None
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </figure>

            <?php if ($manifest['status'] !== 'Completed'): ?>
                <a href="complete_manifest.php?id=<?php echo (int) $manifest['id']; ?>" role="button">Complete this manifest</a>
            <?php endif; ?>
        </article>

        <p>
            <a href="complete_manifest.php">All manifests awaiting completion</a> ·
            <a href="chain_of_custody_documents.php">Chain of Custody documents</a>
        </p>

        <script>
            const urlParams = new URLSearchParams(window.location.search);
            document.addEventListener('DOMContentLoaded', () => {
                if (urlParams.get('created')) {
                    <?php if ($manifest['flower_transaction_id']): ?>
                    showToast('Manifest #<?php echo (int) $manifest['id']; ?> created. <?php echo abs((float) $manifest['deducted_weight']); ?> g deducted from dried-flower inventory. Attach the Chain of Custody to complete it.', 'success', 8000);
                    <?php else: ?>
                    showToast('Manifest #<?php echo (int) $manifest['id']; ?> created as In Progress. Attach the Chain of Custody to complete it.', 'success', 8000);
                    <?php endif; ?>
                }
                if (urlParams.get('completed')) {
                    showToast('Manifest #<?php echo (int) $manifest['id']; ?> completed. Chain of Custody attached.', 'success');
                }
            });
        </script>
<?php endif; ?>
    </main>

<?php require 'footer.php'; ?>

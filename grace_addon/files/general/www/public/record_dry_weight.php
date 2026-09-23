<?php
require_once 'init_db.php';
require_once 'settings_lib.php';
require_once 'recent_lib.php';

$pdo = initializeDatabase();
// Above this many grams the confirm step asks for an extra tick
$limits = getEntryWarningLimits($pdo);
$recent = recentFlowerEntries($pdo);

$pageTitle = 'GRACe - Record Flower Transaction';
require 'header.php';
?>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div>

        <hgroup class="page-header">
            <h1>Record Flower Transaction</h1>
            <p>If you are harvesting flower, receiving a sample, destroying, or sending off for testing, you can do it all from here.</p>
        </hgroup>

        <article class="form-card">
            <form id="recordFlowerTransactionForm" class="form" action="record_flower_transaction.php" method="post"
                  data-large-grams="<?php echo (int) $limits['grams']; ?>">
                <label for="geneticsName">Genetics:</label>
                <select id="geneticsName" name="geneticsName" class="input" required>
                    <option value="" disabled selected>Select Genetics</option>
                </select>
                <small id="stockHint" aria-live="polite"></small>

                <label for="weight">Weight (grams):</label>
                <input type="number" id="weight" name="weight" class="input" min="0.1" step="0.1" inputmode="decimal" required>

                <label for="transactionType">Transaction Type:</label>
                <select id="transactionType" name="transactionType" class="input" required>
                    <option value="Add">Add</option>
                    <option value="Subtract">Subtract</option>
                </select>

                <label for="reason">Reason:</label>
                <select id="reason" name="reason" class="input" required>
                    <option value="" disabled selected>Select Reason</option>
                </select>

                <div id="companySelection" style="display: none;">
                    <label for="companyId">Company:</label>
                    <select id="companyId" name="companyId" class="input">
                        <option value="" disabled selected>Select Company</option>
                    </select>
                </div>

                <div id="otherReasonSection" style="display: none;">
                    <label for="otherReason">Other Reason:</label>
                    <textarea id="otherReason" name="otherReason" class="input" rows="3"></textarea>
                </div>

                <button type="submit" class="button">Record Transaction</button>
            </form>
        </article>

        <?php if ($recent): ?>
        <section class="recent-entries">
            <h2>Recent entries</h2>
            <p class="recent-entries-note">Your last <?php echo GRACE_RECENT_ENTRIES; ?>, newest first, so you can check what you just did and spot anything entered twice.</p>
            <figure class="table-wrap">
                <table>
                    <thead><tr><th>When</th><th>Genetics</th><th>In / out</th><th>Reason</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $entry):
                            $reason = $entry['reason'] . ($entry['company'] !== null ? ', ' . $entry['company'] : '')
                                . ($entry['manifestId'] !== null ? ' (manifest #' . $entry['manifestId'] . ')' : '');
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars(formatLedgerDateTime($entry['when'])); ?></td>
                            <td><?php echo htmlspecialchars($entry['genetics']); ?></td>
                            <td><?php echo ($entry['grams'] < 0 ? '−' : '+') . htmlspecialchars(formatFlowerGrams(abs($entry['grams']))); ?> g</td>
                            <td><?php echo htmlspecialchars($reason); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </figure>
        </section>
        <?php endif; ?>
    </main>

    <script src="js/quick_add.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
    <script src="js/transaction_form.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initTransactionForm);
    </script>
<?php require 'footer.php'; ?>

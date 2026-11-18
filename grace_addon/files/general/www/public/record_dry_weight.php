<?php
require_once 'init_db.php';
require_once 'settings_lib.php';
require_once 'recent_lib.php';

$pdo = initializeDatabase();
// Above this many grams the confirm step asks for an extra tick
$limits = getEntryWarningLimits($pdo);
$recent = recentFlowerEntries($pdo);

$pageTitle = 'GRACe - Record dry weight change';
require 'header.php';
?>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div>

        <hgroup class="page-header">
            <h1>Record dry weight change</h1>
            <p>If you are harvesting flower, receiving a sample, destroying, or sending off for testing, you can do it all from here.</p>
        </hgroup>

        <article class="form-card form-card--wide">
            <form id="recordFlowerTransactionForm" class="form" action="record_flower_transaction.php" method="post"
                  data-large-grams="<?php echo (int) $limits['grams']; ?>">
                <div class="entry-fields entry-fields--grid">
                    <div class="field">
                        <label for="geneticsName">Genetics</label>
                        <select id="geneticsName" name="geneticsName" class="input" required>
                            <option value="" disabled selected>Choose genetics</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="weight">Weight (g)</label>
                        <input type="number" id="weight" name="weight" class="input" min="0.1" step="0.1" inputmode="decimal" required>
                    </div>
                    <div class="field">
                        <label for="transactionType">Add or subtract</label>
                        <select id="transactionType" name="transactionType" class="input" required>
                            <option value="Add">Add</option>
                            <option value="Subtract">Subtract</option>
                        </select>
                    </div>
                    <div class="field">
                        <label for="reason">Reason</label>
                        <select id="reason" name="reason" class="input" required>
                            <option value="" disabled selected>Choose a reason</option>
                        </select>
                    </div>
                    <div id="companySelection" class="field field--wide" style="display: none;">
                        <label for="companyId">Company</label>
                        <select id="companyId" name="companyId" class="input">
                            <option value="" disabled selected>Choose a company</option>
                        </select>
                    </div>
                    <div id="otherReasonSection" class="field field--wide" style="display: none;">
                        <label for="otherReason">Other reason</label>
                        <textarea id="otherReason" name="otherReason" class="input" rows="2"></textarea>
                    </div>
                </div>
                <small id="stockHint" class="entry-hint" aria-live="polite"></small>
                <div class="entry-actions">
                    <button type="submit" class="button">Record change</button>
                </div>
            </form>
        </article>

        <?php if ($recent): ?>
        <section class="recent-entries">
            <h2>Recent entries</h2>
            <p class="recent-entries-note">Your last <?php echo GRACE_RECENT_ENTRIES; ?>, newest first, so you can check what you just did and spot anything entered twice.</p>
            <figure class="table-wrap">
                <table class="wide-table">
                    <thead><tr><th>When</th><th>Genetics</th><th class="num">In / out</th><th>Reason</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $entry):
                            $reason = $entry['reason'] . ($entry['company'] !== null ? ', ' . $entry['company'] : '')
                                . ($entry['manifestId'] !== null ? ' (manifest #' . $entry['manifestId'] . ')' : '');
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars(formatLedgerDateTime($entry['when'])); ?></td>
                            <td><?php echo htmlspecialchars($entry['genetics']); ?></td>
                            <td class="num"><?php echo ($entry['grams'] < 0 ? '−' : '+') . htmlspecialchars(formatFlowerGrams(abs($entry['grams']))); ?> g</td>
                            <td class="cell-long"><?php echo htmlspecialchars($reason); ?></td>
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

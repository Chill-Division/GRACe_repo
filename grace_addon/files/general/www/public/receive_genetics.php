<?php
require_once 'init_db.php';
require_once 'receive_lib.php';
require_once 'settings_lib.php';
require_once 'recent_lib.php';

$pdo = initializeDatabase();
// Above this many plants the confirm step asks for an extra tick
$limits = getEntryWarningLimits($pdo);
$recent = recentReceipts($pdo);

$pageTitle = 'GRACe - Receive Genetics';
require 'header.php';
?>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div>

        <hgroup class="page-header">
            <h1>Receive Genetics</h1>
            <p>Any time you're receiving or adding genetics, either through a Form D declaration, taking clones, or from another licensed cultivator, this is where you want to add them.</p>
        </hgroup>

        <article class="form-card">
            <form id="receiveGeneticsForm" class="form" action="handle_receive_genetics.php" method="post"
                  data-large-plants="<?php echo (int) $limits['plants']; ?>">

                <label for="plantCount">How many plants received / clones taken:</label>
                <input type="number" id="plantCount" name="plantCount" class="input" min="1" max="<?php echo GRACE_MAX_PLANTS_PER_ENTRY; ?>" step="1" inputmode="numeric" required>

                <label for="geneticsName">Genetics Name:</label>
                <select id="geneticsName" name="geneticsName" class="input" required>
                    <option value="" disabled selected>Select Genetics</option>
                </select>
                <small id="stockHint" aria-live="polite"></small>

                <button type="submit" class="button">Add plants</button>
            </form>
        </article>

        <?php if ($recent): ?>
        <section class="recent-entries">
            <h2>Recent entries</h2>
            <p class="recent-entries-note">Your last <?php echo GRACE_RECENT_ENTRIES; ?>, newest first, so you can check what you just did and spot anything entered twice.</p>
            <figure class="table-wrap">
                <table>
                    <thead><tr><th>When</th><th>Genetics</th><th>Plants</th></tr></thead>
                    <tbody>
                        <?php foreach ($recent as $entry): ?>
                        <tr>
                            <td><?php echo htmlspecialchars(formatLedgerDateTime($entry['when'])); ?></td>
                            <td><?php echo htmlspecialchars($entry['genetics']); ?></td>
                            <td><?php echo (int) $entry['count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </figure>
        </section>
        <?php endif; ?>
    </main>

    <script src="js/quick_add.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
    <script src="js/genetics.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initReceiveGenetics);
    </script>
<?php require 'footer.php'; ?>

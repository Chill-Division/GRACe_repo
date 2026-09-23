<?php
/**
 * Shared page footer. Include after any page-specific scripts.
 */

// "What's new": after an update, the newest changelog section, shown once
// for the whole system (whats_new_lib.php)
require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/whats_new_lib.php';
$whatsNew = null;
try {
    $whatsNew = whatsNewToShow(initializeDatabase());
} catch (Exception $e) {
    error_log("GRACe: What's new check failed: " . $e->getMessage());
}
$assetVersion = defined('GRACE_ASSET_VERSION') ? GRACE_ASSET_VERSION : '0';
?>
<?php if ($whatsNew): ?>
    <dialog id="whatsNewDialog" class="grace-modal whats-new" aria-labelledby="whatsNewTitle" data-version="<?php echo htmlspecialchars($whatsNew['version']); ?>">
        <article>
            <h3 id="whatsNewTitle">What's new in GRACe <?php echo htmlspecialchars($whatsNew['version']); ?></h3>
            <p>GRACe has been updated. Here's what changed.</p>
            <div class="whats-new-notes">
                <?php echo $whatsNew['html']; ?>
            </div>
            <footer>
                <button type="button" class="secondary" data-whats-new-more hidden>Show more…</button>
                <button type="button" data-whats-new-close>Got it</button>
            </footer>
        </article>
    </dialog>
    <script src="js/whats_new.js?v=<?php echo $assetVersion; ?>"></script>
<?php endif; ?>
    <script src="js/growcart.js?v=<?php echo $assetVersion; ?>"></script>
</body>
</html>

<?php
require_once 'init_db.php';
require_once 'whats_new_lib.php';

// The update notes, on demand (Administration → What's new): any that nobody
// on this system has seen yet (what the pop-up would show), otherwise the
// latest
$latestNotes = latestChangelogSection();
$notesShown = $latestNotes !== null ? [$latestNotes] : [];
$released = null;
if ($latestNotes !== null) {
    // Reading them here counts as seeing them, so the pop-up won't appear too
    try {
        $whatsNewDb = initializeDatabase();
        $unseenNotes = unseenChangelogSections($whatsNewDb);
        if ($unseenNotes) {
            $notesShown = $unseenNotes;
        }
        markWhatsNewSeen($whatsNewDb, $latestNotes['version']);
    } catch (Exception $e) {
        error_log("GRACe: could not record What's new as seen: " . $e->getMessage());
    }
    $released = whatsNewReleaseDate($latestNotes['date']);
}

$pageTitle = "GRACe - What's new";
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>What's new</h1>
            <p><?php if ($latestNotes): ?>GRACe <?php echo htmlspecialchars($latestNotes['version']); ?><?php if ($released): ?>, released <?php echo htmlspecialchars($released); ?><?php endif; ?>.<?php else: ?>The update notes aren't available on this system.<?php endif; ?></p>
        </hgroup>

        <?php if ($latestNotes): ?>
        <article class="form-card whats-new-page">
            <?php echo renderChangelogSections($notesShown); ?>
        </article>
        <p><small>Notes for earlier versions are in GRACe's changelog in Home Assistant, under Settings → Apps.</small></p>
        <?php endif; ?>
    </main>

<?php require 'footer.php'; ?>

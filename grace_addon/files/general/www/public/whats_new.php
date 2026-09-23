<?php
require_once 'init_db.php';
require_once 'whats_new_lib.php';

// The latest update notes, on demand (Administration → What's new)
$latestNotes = latestChangelogSection();
$released = null;
if ($latestNotes !== null) {
    // Reading them here counts as seeing them, so the pop-up won't appear too
    try {
        markWhatsNewSeen(initializeDatabase(), $latestNotes['version']);
    } catch (Exception $e) {
        error_log("GRACe: could not record What's new as seen: " . $e->getMessage());
    }
    $date = $latestNotes['date'] ? DateTimeImmutable::createFromFormat('!Y-m-d', $latestNotes['date']) : false;
    $released = $date ? $date->format('j F Y') : $latestNotes['date'];
}

$pageTitle = "GRACe - What's New";
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>What's new</h1>
            <p><?php if ($latestNotes): ?>GRACe <?php echo htmlspecialchars($latestNotes['version']); ?><?php if ($released): ?>, released <?php echo htmlspecialchars($released); ?><?php endif; ?>.<?php else: ?>The update notes aren't available on this system.<?php endif; ?></p>
        </hgroup>

        <?php if ($latestNotes): ?>
        <article class="form-card whats-new-page">
            <?php echo renderChangelogMarkdown($latestNotes['lines']); ?>
        </article>
        <p><small>Notes for earlier versions are in the add-on's changelog in Home Assistant.</small></p>
        <?php endif; ?>
    </main>

<?php require 'footer.php'; ?>

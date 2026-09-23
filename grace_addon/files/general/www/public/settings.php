<?php
require_once 'init_db.php';
require_once 'settings_lib.php';

$pdo = initializeDatabase();
$message = '';
$messageType = '';
$typed = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = saveEntryWarningLimits($pdo, $_POST);
    if ($result['success']) {
        header('Location: settings.php?saved=1');
        exit();
    }
    // Show the form again with the reason and what was typed
    $message = $result['message'];
    $messageType = 'error';
    $typed = $_POST;
} elseif (isset($_GET['saved'])) {
    $message = 'Entry warning limits saved.';
    $messageType = 'success';
}

$limits = getEntryWarningLimits($pdo);
$plantsValue = (string) ($typed['largePlantEntry'] ?? $limits['plants']);
$gramsValue = (string) ($typed['largeFlowerEntryGrams'] ?? $limits['grams']);

$pageTitle = 'GRACe - Entry Warning Limits';
require 'header.php';
?>

    <main class="container">
        <?php if ($message !== ''): ?>
        <div class="status-message <?php echo $messageType; ?>" role="<?php echo $messageType === 'error' ? 'alert' : 'status'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <hgroup class="page-header">
            <h1>Entry Warning Limits</h1>
            <p>Anything bigger than these gets an extra "are you sure?" before it's saved, to catch typos like an extra zero. It's a warning, not a block.</p>
        </hgroup>

        <article class="form-card">
            <form method="post" class="form">
                <label for="largePlantEntry">Warn when adding or shipping more than this many plants at once:</label>
                <input type="number" id="largePlantEntry" name="largePlantEntry" class="input" min="1" max="<?php echo GRACE_MAX_ENTRY_WARNING_LIMIT; ?>" step="1" inputmode="numeric" required value="<?php echo htmlspecialchars($plantsValue); ?>">

                <label for="largeFlowerEntryGrams">Warn when recording or shipping more than this many grams of flower at once:</label>
                <input type="number" id="largeFlowerEntryGrams" name="largeFlowerEntryGrams" class="input" min="1" max="<?php echo GRACE_MAX_ENTRY_WARNING_LIMIT; ?>" step="1" inputmode="numeric" required value="<?php echo htmlspecialchars($gramsValue); ?>">

                <p><small>We recommend about half of what one of your flower rooms usually holds. They start at <?php echo GRACE_DEFAULT_LARGE_PLANT_ENTRY; ?> plants and <?php echo number_format(GRACE_DEFAULT_LARGE_FLOWER_ENTRY_GRAMS); ?> g.</small></p>

                <button type="submit" class="button">Save</button>
            </form>
        </article>
    </main>

<?php require 'footer.php'; ?>

<?php
require_once 'init_db.php';

$pdo = initializeDatabase();
$message = '';
$countToMigrate = 0;

// Get current count of 'Harvested' plants
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM Plants WHERE status = 'Harvested'");
    $countToMigrate = $stmt->fetchColumn();
} catch (PDOException $e) {
    $message = "Error fetching count: " . $e->getMessage();
}

// Handle Migration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['migrate'])) {
    try {
        $pdo->beginTransaction();
        
        $updateStmt = $pdo->prepare("UPDATE Plants SET status = 'Harvested - Destroyed' WHERE status = 'Harvested'");
        $updateStmt->execute();
        $updatedCount = $updateStmt->rowCount(); // rowCount works for UPDATE in SQLite usually, but let's be safe
        
        $pdo->commit();
        $message = "Success! Migrated $updatedCount plants from 'Harvested' to 'Harvested - Destroyed'.";
        
        // Refresh count
        $stmt = $pdo->query("SELECT COUNT(*) FROM Plants WHERE status = 'Harvested'");
        $countToMigrate = $stmt->fetchColumn();

    } catch (PDOException $e) {
        $pdo->rollBack();
        $message = "Error during migration: " . $e->getMessage();
    }
}
$pageTitle = 'GRACe - Migrate Legacy Harvest Status';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Migrate Legacy Harvest Status</h1>
            <p>This tool migrates all plants with the legacy status 'Harvested' to the new status 'Harvested - Destroyed'. Plants currently in 'Harvested - Drying' are not affected.</p>
        </hgroup>

        <?php if ($message): ?>
            <div class="status-message <?php echo strpos($message, 'Success') === 0 ? 'success' : 'error'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <article class="form-card">
            <header><strong>Migration Status</strong></header>
            <p>Plants pending migration (Status: 'Harvested'): <strong><?php echo $countToMigrate; ?></strong></p>
            
            <?php if ($countToMigrate > 0): ?>
                <form method="post">
                    <button type="submit" name="migrate" class="contrast">Migrate All to 'Harvested - Destroyed'</button>
                </form>
            <?php else: ?>
                <button disabled>No plants to migrate</button>
            <?php endif; ?>
        </article>
        
        <p><a href="administration.php" role="button" class="secondary">Back to Administration</a></p>
    </main>
<?php require 'footer.php'; ?>

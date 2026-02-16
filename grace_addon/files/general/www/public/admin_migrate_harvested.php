<?php
require_once 'auth.php';
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
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css">
    <title>Migrate Legacy Harvest Status</title>
</head>
<body>
    <header class="container-fluid">
        <?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Migrate Legacy Harvest Status</h1>
        <p>This tool migrates all plants with the legacy status <strong>'Harvested'</strong> to the new status <strong>'Harvested - Destroyed'</strong>.</p>
        <p>Plants currently in 'Harvested - Drying' are not affected.</p>

        <?php if ($message): ?>
            <article>
                <?php echo htmlspecialchars($message); ?>
            </article>
        <?php endif; ?>

        <article>
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
</body>
</html>

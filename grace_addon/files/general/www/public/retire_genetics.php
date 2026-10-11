<?php
require_once 'init_db.php';
require_once 'genetics_lib.php';

// Retire genetics (1.3.0): hide genetics from new entries, or bring them
// back. Tested by tests/test_retire_genetics.php.
$pdo = initializeDatabase();

// Retire or bring back, then come back here to show how it went (so a
// refresh can't send it again)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['geneticsId'] ?? 0);
    $do = (string) ($_POST['do'] ?? '');
    if ($do === 'retire') {
        $result = retireGenetics($pdo, $id);
    } elseif ($do === 'bring-back') {
        $result = unretireGenetics($pdo, $id);
    } else {
        $result = ['success' => false, 'message' => 'Something went wrong. Reload the page and try again.'];
    }
    header('Location: retire_genetics.php?' . http_build_query([$result['success'] ? 'done' : 'error' => $result['message']]));
    exit();
}

$message = '';
$messageType = '';
foreach (['done' => 'success', 'error' => 'error'] as $param => $type) {
    if (isset($_GET[$param]) && is_string($_GET[$param])) {
        $message = $_GET[$param];
        $messageType = $type;
    }
}

// What's left on site decides whether each one can be retired
$inUse = [];
$retired = [];
foreach ($pdo->query("SELECT id, name, retired_at FROM Genetics ORDER BY name ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC) as $genetics) {
    if ($genetics['retired_at'] === null) {
        $genetics['onSite'] = describeOnHand(geneticsOnHand($pdo, $genetics['id']));
        $inUse[] = $genetics;
    } else {
        $retiredTime = strtotime($genetics['retired_at']);
        $genetics['retiredOn'] = $retiredTime ? date('j M Y', $retiredTime) : $genetics['retired_at'];
        $retired[] = $genetics;
    }
}

$pageTitle = 'GRACe - Retire Genetics';
require 'header.php';
?>

    <main class="container">
        <?php if ($message !== ''): ?>
        <div class="status-message <?php echo $messageType; ?>" role="<?php echo $messageType === 'error' ? 'alert' : 'status'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <hgroup class="page-header">
            <h1>Retire Genetics</h1>
            <p>Retired genetics don't show when adding plants, recording flower or making a manifest, so a typo or a genetics you've stopped growing stays out of the way. Reports and history never change, and you can bring a genetics back at any time.</p>
        </hgroup>

        <section>
            <h2>In use</h2>
            <p>A genetics can be retired once none of it is left on site: no plants growing or drying, and no flower.</p>
            <figure class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Genetics</th>
                            <th>On site now</th>
                            <th class="row-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$inUse): ?>
                        <tr><td colspan="3">No genetics in use. <a href="add_new_genetics.php">Add New Genetics</a></td></tr>
                        <?php endif; ?>
                        <?php foreach ($inUse as $genetics): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($genetics['name']); ?></td>
                            <td id="onSite<?php echo (int) $genetics['id']; ?>"><?php echo $genetics['onSite'] === null ? 'Nothing' : htmlspecialchars($genetics['onSite']); ?></td>
                            <td class="row-actions">
                                <form method="post" class="row-action retire-form" data-name="<?php echo htmlspecialchars($genetics['name']); ?>">
                                    <input type="hidden" name="do" value="retire">
                                    <input type="hidden" name="geneticsId" value="<?php echo (int) $genetics['id']; ?>">
                                    <button type="submit" class="secondary outline" aria-describedby="onSite<?php echo (int) $genetics['id']; ?>"<?php echo $genetics['onSite'] === null ? '' : ' disabled'; ?>>Retire</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </figure>
        </section>

        <section>
            <h2>Retired (hidden from new entries)</h2>
            <figure class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Genetics</th>
                            <th>Retired</th>
                            <th class="row-actions"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$retired): ?>
                        <tr><td colspan="3">No retired genetics.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($retired as $genetics): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($genetics['name']); ?></td>
                            <td><?php echo htmlspecialchars($genetics['retiredOn']); ?></td>
                            <td class="row-actions">
                                <form method="post" class="row-action">
                                    <input type="hidden" name="do" value="bring-back">
                                    <input type="hidden" name="geneticsId" value="<?php echo (int) $genetics['id']; ?>">
                                    <button type="submit" class="secondary outline">Bring back</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </figure>
        </section>
    </main>

    <script>
        // Retiring asks first, saying exactly what it does
        document.querySelectorAll('form.retire-form').forEach(form => {
            form.addEventListener('submit', (event) => {
                event.preventDefault();
                confirmAction({
                    title: `Retire ${form.dataset.name}?`,
                    message: "It won't show when adding plants, recording flower or making a manifest. Reports and history stay exactly as they are, and you can bring it back at any time.",
                    confirmLabel: 'Retire'
                }).then(confirmed => {
                    if (confirmed) submitOnce(form);
                });
            });
        });
    </script>
<?php require 'footer.php'; ?>

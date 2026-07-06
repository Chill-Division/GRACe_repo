<?php
require_once 'init_db.php';
require_once 'report_reminders_lib.php';

// All dashboard queries are read-only summaries of the ledger
$dueReminders = [];
$stats = [
    'growing' => 0,
    'drying' => 0,
    'flowerOnHand' => 0,
    'sentThisMonthGrams' => 0,
    'sentThisMonthPlants' => 0,
    'manifestsInProgress' => 0,
];
$expiringLicenses = [];
$companyName = '';

try {
    $pdo = initializeDatabase();

    $stmt = $pdo->query("SELECT company_name FROM OwnCompany LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $companyName = $row['company_name'];
    }

    $stats['growing'] = (int) $pdo->query("SELECT COUNT(*) FROM Plants WHERE status = 'Growing'")->fetchColumn();
    $stats['drying'] = (int) $pdo->query("SELECT COUNT(*) FROM Plants WHERE status = 'Harvested - Drying'")->fetchColumn();
    $stats['flowerOnHand'] = (float) $pdo->query("SELECT COALESCE(SUM(weight), 0) FROM Flower")->fetchColumn();

    // Materials out this month, same definitions as the monthly Agency report
    $startDate = date('Y-m-01');
    $endDate = date('Y-m-t 23:59:59');

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(ABS(weight)), 0) FROM Flower
                           WHERE transaction_type = 'Subtract'
                             AND reason IN ('Send external', 'Testing')
                             AND transaction_date BETWEEN :startDate AND :endDate");
    $stmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
    $stats['sentThisMonthGrams'] = (float) $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Plants
                           WHERE status = 'Sent'
                             AND date_harvested BETWEEN :startDate AND :endDate");
    $stmt->execute([':startDate' => $startDate, ':endDate' => $endDate]);
    $stats['sentThisMonthPlants'] = (int) $stmt->fetchColumn();

    $stats['manifestsInProgress'] = (int) $pdo->query("SELECT COUNT(*) FROM ShippingManifests WHERE status = 'In Progress'")->fetchColumn();

    // Licenses expiring within 30 days (or already expired), skipping any
    // the user has already acknowledged on the Company Licenses page
    $horizon = date('Y-m-d', strtotime('+30 days'));
    $stmt = $pdo->prepare("SELECT original_filename, expiry_date FROM Documents
                           WHERE category = 'licenses' AND expiry_date IS NOT NULL AND expiry_date <= ?
                             AND (acknowledged IS NULL OR acknowledged = 0)
                           ORDER BY expiry_date ASC");
    $stmt->execute([$horizon]);
    $expiringLicenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Agency report reminders (windowed, see report_reminders_lib.php).
    // ?demo_date=YYYY-MM-DD pretends it's another day, read-only, used for
    // demos/videos to show the banners outside their real windows.
    $demoDate = (isset($_GET['demo_date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['demo_date']))
        ? $_GET['demo_date'] : null;
    $dueReminders = getDueReportReminders($pdo, $demoDate);
} catch (Exception $e) {
    error_log('Dashboard error: ' . $e->getMessage());
}

function formatGrams($grams) {
    return rtrim(rtrim(number_format($grams, 2, '.', ','), '0'), '.');
}

$pageTitle = 'GRACe - Dashboard';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Dashboard</h1>
            <p><?php echo $companyName !== '' ? htmlspecialchars($companyName) . ' at a glance.' : 'Your grow at a glance.'; ?></p>
        </hgroup>

        <?php foreach ($dueReminders as $reminder):
            $isMonthly = $reminder['type'] === 'monthly';
            $reportHref = $isMonthly
                ? 'last_months_flower_transactions.php'
                : 'annual_stocktake.php?year=' . urlencode($reminder['period']) . '&autorun=1';
            $title = $isMonthly
                ? 'Monthly materials out report due'
                : 'Annual stocktake due';
            $text = $isMonthly
                ? "Your {$reminder['label']} materials-out report is ready to send to the Medicinal Cannabis Agency."
                : "Your {$reminder['label']} annual stocktake is due to the Medicinal Cannabis Agency this month.";
        ?>
        <article class="reminder-banner" data-report-type="<?php echo $reminder['type']; ?>" data-report-period="<?php echo htmlspecialchars($reminder['period']); ?>">
            <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4Z"/></svg></span>
            <div class="reminder-banner-body">
                <strong><?php echo $title; ?></strong>
                <small><?php echo htmlspecialchars($text); ?></small>
            </div>
            <div class="reminder-banner-actions">
                <a href="<?php echo $reportHref; ?>" role="button" class="button">Open report</a>
                <button type="button" class="secondary outline reminder-dismiss" aria-label="Dismiss this reminder">Dismiss</button>
            </div>
        </article>
        <?php endforeach; ?>

        <div class="stat-grid">
            <a class="stat-card stat-card--link" href="current_plants.php">
                <span class="stat-value"><?php echo $stats['growing']; ?></span>
                <span class="stat-label">Plants growing</span>
            </a>
            <a class="stat-card stat-card--link" href="list_all_genetics.php">
                <span class="stat-value"><?php echo $stats['drying']; ?></span>
                <span class="stat-label">Plants drying</span>
            </a>
            <a class="stat-card stat-card--link" href="current_dried_flower.php">
                <span class="stat-value"><?php echo formatGrams($stats['flowerOnHand']); ?> g</span>
                <span class="stat-label">Dried flower on hand</span>
            </a>
            <a class="stat-card stat-card--link" href="this_months_flower_transactions.php">
                <span class="stat-value"><?php echo formatGrams($stats['sentThisMonthGrams']); ?> g<?php if ($stats['sentThisMonthPlants'] > 0): ?> + <?php echo $stats['sentThisMonthPlants']; ?> plants<?php endif; ?></span>
                <span class="stat-label">Materials out this month</span>
            </a>
            <a class="stat-card stat-card--link" href="complete_manifest.php">
                <span class="stat-value"><?php echo $stats['manifestsInProgress']; ?></span>
                <span class="stat-label">Manifests awaiting CoC</span>
            </a>
        </div>

        <?php if ($expiringLicenses): ?>
        <section class="hub-section">
            <h2>License Renewals Due</h2>
            <figure class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>License</th>
                            <th>Expiry Date</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expiringLicenses as $doc): ?>
                        <tr class="<?php echo $doc['expiry_date'] < date('Y-m-d') ? 'expiring' : ''; ?>">
                            <td><?php echo htmlspecialchars($doc['original_filename']); ?></td>
                            <td><?php echo htmlspecialchars($doc['expiry_date']); ?></td>
                            <td><a href="company_licenses.php">Manage</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </figure>
        </section>
        <?php endif; ?>

        <section class="hub-section">
            <h2>Quick Actions</h2>
            <div class="card-grid">
                <a class="nav-card" href="receive_genetics.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8Z"/><path d="M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2Z"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Receive plants / take clones</strong>
                        <small>Add new plants to the ledger</small>
                    </span>
                </a>
                <a class="nav-card" href="harvest_plants.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4 8.12 15.88"/><path d="M14.47 14.48 20 20"/><path d="M8.12 8.12 12 12"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Harvest / Destroy / Send</strong>
                        <small>Process plants out of the grow</small>
                    </span>
                </a>
                <a class="nav-card" href="record_dry_weight.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Record dry weight change</strong>
                        <small>Log flower in or out of inventory</small>
                    </span>
                </a>
                <a class="nav-card" href="generate_shipping_manifest.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Generate Shipping Manifest</strong>
                        <small>Paperwork for outgoing product</small>
                    </span>
                </a>
            </div>
        </section>
    </main>

    <script>
        // Dismissing a report reminder records it server-side so it stays
        // dismissed on every device
        document.querySelectorAll('.reminder-banner .reminder-dismiss').forEach(button => {
            button.addEventListener('click', () => {
                const banner = button.closest('.reminder-banner');
                const body = new URLSearchParams({
                    report_type: banner.dataset.reportType,
                    period: banner.dataset.reportPeriod,
                    status: 'dismissed'
                });
                fetch('handle_report_reminder.php', { method: 'POST', body: body })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            banner.remove();
                            showToast('Reminder dismissed. It won\'t show again for this report.', 'info');
                        } else {
                            showToast('Could not dismiss the reminder: ' + (data.message || 'unknown error'), 'error');
                        }
                    })
                    .catch(() => showToast('Could not dismiss the reminder. Please try again.', 'error'));
            });
        });
    </script>
<?php require 'footer.php'; ?>

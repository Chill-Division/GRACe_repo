<?php
require_once 'init_db.php';
require_once 'report_notify_lib.php';

$pdo = initializeDatabase();
$services = notifyServices(); // null when Home Assistant can't be reached
$message = '';
$messageType = '';
$typed = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $typed = $_POST; // shown again as typed unless it was saved
    if (($_POST['do'] ?? '') === 'test') {
        // A test sends to the device chosen on screen and saves nothing
        $service = (string) ($_POST['service'] ?? '');
        if ($services === null) {
            $message = "GRACe can't reach Home Assistant, so it can't send a test.";
            $messageType = 'error';
        } elseif (!in_array($service, array_column($services, 'service'), true)) {
            $message = 'Choose a device to send the test to.';
            $messageType = 'error';
        } else {
            $sent = sendNotification($service, 'GRACe test', 'Report reminders from GRACe will arrive like this.');
            $message = $sent['ok'] ? 'Test sent. Check the device.' : "The test didn't send: " . $sent['error'] . '.';
            $messageType = $sent['ok'] ? 'success' : 'error';
        }
    } else {
        $result = saveReportNotifySettings($pdo, $_POST, $services);
        if ($result['success']) {
            header('Location: report_reminders.php?saved=1');
            exit();
        }
        $message = $result['message'];
        $messageType = 'error';
    }
} elseif (isset($_GET['saved'])) {
    $message = getReportNotifySettings($pdo)['enabled'] ? 'Saved. Report reminders are on.' : 'Saved. Report reminders are off.';
    $messageType = 'success';
}

$settings = getReportNotifySettings($pdo);
$enabled = $typed !== null ? (($typed['enabled'] ?? '') === '1') : $settings['enabled'];
$chosen = $typed !== null ? (string) ($typed['service'] ?? '') : $settings['service'];
$known = $services !== null && in_array($chosen, array_column($services, 'service'), true);
$now = new DateTimeImmutable('now');
$next = nextReportNotificationTime($now, getSetting($pdo, 'reportNotifyLastSent'));

$pageTitle = 'GRACe - Report Reminders';
require 'header.php';
?>

    <main class="container">
        <?php if ($message !== ''): ?>
        <div class="status-message <?php echo $messageType; ?>" role="<?php echo $messageType === 'error' ? 'alert' : 'status'; ?>"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <hgroup class="page-header">
            <h1>Report Reminders</h1>
            <p>Get a notification on your phone when a report is due to the Agency, as well as the banner on the Dashboard.</p>
        </hgroup>

        <?php if ($services === null): ?>
        <div class="status-message error" role="alert">GRACe can't reach Home Assistant right now, so it can't list your devices or send reminders. Try again in a minute, or restart the add-on.</div>
        <?php endif; ?>

        <article class="form-card">
            <form method="post" class="form">
                <div class="form-toggle">
                    <label>
                        <input type="checkbox" role="switch" name="enabled" value="1"<?php echo $enabled ? ' checked' : ''; ?><?php echo $services === null ? ' disabled' : ''; ?>>
                        Send report reminders to my phone
                    </label>
                    <small>From 9am on the first weekday of each month, if last month's materials-out report is due, and in January the annual stocktake too. Never more than one a month, and none when there's nothing to report.</small>
                </div>

                <label for="service">Send to:</label>
                <select id="service" name="service" class="input"<?php echo empty($services) ? ' disabled' : ''; ?>>
                    <option value="" disabled<?php echo $known ? '' : ' selected'; ?>>Choose a device</option>
                    <?php foreach ($services ?? [] as $device): ?>
                    <option value="<?php echo htmlspecialchars($device['service']); ?>"<?php echo $device['service'] === $chosen ? ' selected' : ''; ?>><?php echo htmlspecialchars($device['label']); ?></option>
                    <?php endforeach; ?>
                </select>
                <small>
                    <?php if ($services === []): ?>
                    No devices found. Install the Home Assistant app on your phone and sign in to your Home Assistant, then come back to this page.
                    <?php elseif ($services !== null && $chosen !== '' && !$known): ?>
                    The device chosen before isn't in Home Assistant any more. Choose another.
                    <?php else: ?>
                    Phones show here once the Home Assistant app is installed on them and signed in.
                    <?php endif; ?>
                </small>

                <?php if ($settings['enabled']): ?>
                <p><small>Next reminder: <?php echo $next <= $now ? 'within a few minutes' : 'from ' . $next->format('ga \o\n l j F'); ?>, if a report is due then.</small></p>
                <?php endif; ?>

                <button type="submit" name="do" value="save" class="button">Save</button>
                <button type="submit" name="do" value="test" class="secondary outline"<?php echo empty($services) ? ' disabled' : ''; ?>>Send a test notification</button>
            </form>
        </article>
    </main>

<?php require 'footer.php'; ?>

<?php
/**
 * Run by the add-on every few minutes (etc/services.d/report-reminders) to
 * send the report reminder to the chosen device when it's due
 * (report_notify_lib.php). Command line only. It writes a line to the
 * add-on log only when there's something to say.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit();
}

require_once __DIR__ . '/init_db.php';
require_once __DIR__ . '/report_notify_lib.php';

$status = sendDueReportNotification(initializeDatabase(), new DateTimeImmutable('now'), 'sendNotification');
if ($status !== 'off' && $status !== 'not-time') {
    echo date('Y-m-d H:i') . " Report reminder: $status\n";
}

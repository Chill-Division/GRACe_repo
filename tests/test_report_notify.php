<?php
/**
 * Regression tests for report reminders on a phone
 * (grace_addon/files/general/www/public/report_notify_lib.php, 1.2.0).
 *
 * Opt-in; from 9am on the first weekday of the month (catching up within
 * the Dashboard's first week if GRACe was off); only when a report is due;
 * never more than once a month; failed sends are tried again. The Home
 * Assistant calls are checked against a stand-in for the Supervisor that
 * this test starts with PHP's built-in server.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
$addonDir = __DIR__ . '/../grace_addon';
require_once $publicDir . '/init_db.php';
require_once $publicDir . '/report_notify_lib.php';

$failures = 0;
function check($label, $actual, $expected)
{
    global $failures;
    if ($actual === $expected) {
        echo "[PASS] $label\n";
    } else {
        echo "[FAIL] $label, expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        $failures++;
    }
}
function at($when)
{
    return new DateTimeImmutable($when, new DateTimeZone('Pacific/Auckland'));
}

// --- When ----------------------------------------------------------------------
check('First weekday when the 1st is a Sunday', firstWeekdayOfMonth(at('2026-11-15'))->format('Y-m-d D'), '2026-11-02 Mon');
check('First weekday when the 1st is a Saturday', firstWeekdayOfMonth(at('2026-08-20'))->format('Y-m-d D'), '2026-08-03 Mon');
check('First weekday when the 1st is a weekday', firstWeekdayOfMonth(at('2026-10-09'))->format('Y-m-d D'), '2026-10-01 Thu');

check('Not before 9am on the first weekday', reportNotificationDue(at('2026-11-02 08:59'), null), false);
check('From 9am on the first weekday', reportNotificationDue(at('2026-11-02 09:00'), null), true);
check('Not over the weekend before it', reportNotificationDue(at('2026-11-01 12:00'), null), false);
check('Catches up later in the first week', reportNotificationDue(at('2026-11-07 23:00'), null), true);
check('Not after the first week', reportNotificationDue(at('2026-11-08 09:00'), null), false);
check('Not twice in a month', reportNotificationDue(at('2026-11-02 09:00'), '2026-11'), false);
check('Last month having been sent does not count', reportNotificationDue(at('2026-11-02 09:00'), '2026-10'), true);

check('Next reminder after this month\'s window: next month\'s first weekday',
    nextReportNotificationTime(at('2026-10-11 16:00'), null)->format('Y-m-d H:i'), '2026-11-02 09:00');
check('Next reminder over the weekend before it', nextReportNotificationTime(at('2026-11-01 12:00'), null)->format('Y-m-d H:i'), '2026-11-02 09:00');
check('Next reminder when one is due now', nextReportNotificationTime(at('2026-11-03 12:00'), null)->format('Y-m-d H:i'), '2026-11-03 12:00');
check('Next reminder once this month is done', nextReportNotificationTime(at('2026-11-03 12:00'), '2026-11')->format('Y-m-d H:i'), '2026-12-01 09:00');

// --- What gets sent ------------------------------------------------------------
$tmpDb = tempnam(sys_get_temp_dir(), 'grace_notify_') . '.db';
$pdo = initializeDatabase($tmpDb);
$pdo->exec("INSERT INTO OwnCompany (company_name, company_license_number, company_address, primary_contact_email)
            VALUES ('Joe''s Farm', 'LIC-1', '1 Road', 'joe@example.com')");
$pdo->exec("INSERT INTO Genetics (name) VALUES ('Alpha')");
$sentPlant = $pdo->prepare("INSERT INTO Plants (genetics_id, status, date_created, date_harvested) VALUES (1, 'Sent', '2026-06-01 10:00:00', ?)");
$sentPlant->execute(['2026-10-15 10:00:00']); // October: the November reminder has something to report

$calls = [];
$works = function ($service, $title, $message) use (&$calls) {
    $calls[] = compact('service', 'title', 'message');
    return ['ok' => true, 'error' => ''];
};
$fails = function () {
    return ['ok' => false, 'error' => 'boom'];
};

check('Off until it is switched on', sendDueReportNotification($pdo, at('2026-11-02 09:00'), $works), 'off');
setSetting($pdo, 'reportNotifyEnabled', '1');
check('Off without a device chosen', sendDueReportNotification($pdo, at('2026-11-02 09:00'), $works), 'off');
setSetting($pdo, 'reportNotifyService', 'mobile_app_joes_phone');
check('Waits for 9am', sendDueReportNotification($pdo, at('2026-11-02 08:30'), $works), 'not-time');
check('A failed send is reported', sendDueReportNotification($pdo, at('2026-11-02 09:00'), $fails), 'failed: boom');
check('...and the month is not marked done', getSetting($pdo, 'reportNotifyLastSent'), null);
check('Sends once it works', sendDueReportNotification($pdo, at('2026-11-02 09:05'), $works), 'sent');
check('To the chosen device', $calls[0]['service'] ?? null, 'mobile_app_joes_phone');
check('Titled as a report reminder', $calls[0]['title'] ?? null, 'GRACe: report due');
check('Naming last month\'s report', $calls[0]['message'] ?? null,
    'Your October 2026 materials-out report is ready to send to the Medicinal Cannabis Agency. Open GRACe to send it.');
check('Only once a month', sendDueReportNotification($pdo, at('2026-11-02 09:10'), $works), 'not-time');
check('...with no second notification', count($calls), 1);

check('A month with nothing to report sends nothing', sendDueReportNotification($pdo, at('2026-12-01 09:00'), $works), 'nothing-due');
check('...and is not looked at again', sendDueReportNotification($pdo, at('2026-12-01 09:05'), $works), 'not-time');

$sentPlant->execute(['2026-12-10 10:00:00']); // December shipment, and the ledger has a year behind it
check('January sends both reports at once', sendDueReportNotification($pdo, at('2027-01-01 09:00'), $works), 'sent');
check('...titled for both', $calls[1]['title'] ?? null, 'GRACe: reports due');
check('...naming both', $calls[1]['message'] ?? null,
    'Your December 2026 materials-out report and your 2026 annual stocktake are due to the Medicinal Cannabis Agency. Open GRACe to send them.');

$sentPlant->execute(['2027-01-20 10:00:00']);
actionReportReminder($pdo, 'monthly', '2027-01', 'drafted'); // already sent from the Dashboard
check('Nothing once the report is sent or dismissed on the Dashboard', sendDueReportNotification($pdo, at('2027-02-01 09:00'), $works), 'nothing-due');

$sentPlant->execute(['2027-02-20 10:00:00']);
check('Catches up if GRACe was off on the first weekday', sendDueReportNotification($pdo, at('2027-03-05 14:00'), $works), 'sent');
check('...with the right month', strpos($calls[2]['message'] ?? '', 'February 2027') !== false, true);

// --- Settings ------------------------------------------------------------------
$devices = [['service' => 'mobile_app_joes_phone', 'label' => 'Joes Phone']];
$saved = saveReportNotifySettings($pdo, ['enabled' => '1', 'service' => 'mobile_app_joes_phone'], null);
check("Can't switch on while Home Assistant can't be reached", $saved['success'], false);
$saved = saveReportNotifySettings($pdo, ['enabled' => '1', 'service' => 'mobile_app_someone_else'], $devices);
check('Only a device Home Assistant offers can be chosen', $saved['message'], 'Choose the device to send reminders to.');
$saved = saveReportNotifySettings($pdo, ['service' => 'mobile_app_joes_phone'], null);
check('Switching off always works', [$saved['success'], getReportNotifySettings($pdo)['enabled']], [true, false]);
$saved = saveReportNotifySettings($pdo, ['enabled' => '1', 'service' => 'mobile_app_joes_phone'], $devices);
check('Switching on saves the device', getReportNotifySettings($pdo), ['enabled' => true, 'service' => 'mobile_app_joes_phone']);

unlink($tmpDb);

// --- Home Assistant ------------------------------------------------------------
check('Devices from Home Assistant: phones first, then others, never the catch-alls', parseNotifyServices([
    ['domain' => 'light', 'services' => ['turn_on' => []]],
    ['domain' => 'notify', 'services' => [
        'telegram_bot' => [], 'notify' => [], 'persistent_notification' => [], 'send_message' => [],
        'mobile_app_joes_phone' => [], 'mobile_app_a_tablet' => [], 'Bad-Name' => [],
    ]],
]), [
    ['service' => 'mobile_app_a_tablet', 'label' => 'A Tablet'],
    ['service' => 'mobile_app_joes_phone', 'label' => 'Joes Phone'],
    ['service' => 'telegram_bot', 'label' => 'Telegram Bot'],
]);

// A stand-in for the Supervisor's Home Assistant API, logging each request
$mockDir = sys_get_temp_dir() . '/grace_ha_mock_' . getmypid();
@mkdir($mockDir);
$log = $mockDir . '/requests.log';
file_put_contents($mockDir . '/router.php', '<?php
$path = parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH);
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$auth = $headers["authorization"] ?? "";
file_put_contents(__DIR__ . "/requests.log", json_encode(["method" => $_SERVER["REQUEST_METHOD"], "path" => $path, "auth" => $auth, "body" => file_get_contents("php://input")]) . "\n", FILE_APPEND);
header("Content-Type: application/json");
if ($auth !== "Bearer test-token") { http_response_code(401); echo "{\"message\": \"401: Unauthorized\"}"; return; }
if ($path === "/core/api/services") { echo json_encode([["domain" => "notify", "services" => ["mobile_app_joes_phone" => [], "notify" => []]]]); return; }
if (preg_match("#^/core/api/services/notify/([a-z0-9_]+)$#", $path, $m)) {
    if ($m[1] === "mobile_app_gone") { http_response_code(400); echo "{\"message\": \"Service not found.\"}"; return; }
    echo "[]"; return;
}
http_response_code(404); echo "{}";
');
$probe = stream_socket_server('tcp://127.0.0.1:0');
$port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
fclose($probe);
$server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", $mockDir . '/router.php'],
    [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
for ($i = 0; $i < 50; $i++) {
    $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
    if ($connection) {
        fclose($connection);
        break;
    }
    usleep(100000);
}

putenv("GRACE_SUPERVISOR_URL=http://127.0.0.1:$port");
putenv('SUPERVISOR_TOKEN=');
check('Without the token GRACe knows it cannot reach Home Assistant', [homeAssistantAvailable(), notifyServices()], [false, null]);

putenv('SUPERVISOR_TOKEN=test-token');
check('Lists the devices Home Assistant offers', notifyServices(), [['service' => 'mobile_app_joes_phone', 'label' => 'Joes Phone']]);
check('Sends a notification', sendNotification('mobile_app_joes_phone', 'GRACe test', 'Hello'), ['ok' => true, 'error' => '']);
$requests = array_map(function ($line) { return json_decode($line, true); }, file($log, FILE_IGNORE_NEW_LINES));
$last = end($requests);
check('...to the notify service through the Supervisor', [$last['method'], $last['path']], ['POST', '/core/api/services/notify/mobile_app_joes_phone']);
check('...with the add-on\'s token', $last['auth'], 'Bearer test-token');
check('...with the title and message', json_decode($last['body'], true), ['title' => 'GRACe test', 'message' => 'Hello']);
check('A device Home Assistant no longer has is reported', sendNotification('mobile_app_gone', 'T', 'M'), ['ok' => false, 'error' => "Home Assistant couldn't do it (error 400)"]);
$before = count(file($log));
check('A device name that is not a plain name is refused', sendNotification('mobile_app_x/../../config', 'T', 'M')['ok'], false);
check('...without asking Home Assistant', count(file($log)), $before);

proc_terminate($server);
proc_close($server);
putenv("GRACE_SUPERVISOR_URL=http://127.0.0.1:$port"); // nothing listens there now
check('Home Assistant being down is handled', notifyServices(), null);
array_map('unlink', glob($mockDir . '/*'));
rmdir($mockDir);

// --- Wiring --------------------------------------------------------------------
check('The add-on may use the Home Assistant API', preg_match('/^homeassistant_api: true$/m', file_get_contents($addonDir . '/config.yaml')), 1);
check('The image has the PHP command line', preg_match('/^\s+php83 \\\\$/m', file_get_contents($addonDir . '/Dockerfile')), 1);
$run = $addonDir . '/files/php83/etc/services.d/report-reminders/run';
check('A background service runs the check', is_file($run) && is_executable($run), true);
check('...every few minutes', preg_match('/while true; do\s+php83 \/www\/public\/report_reminder_notify\.php\s+sleep \d+\s+done/', (string) @file_get_contents($run)), 1);
check('The check only runs from the command line', strpos(file_get_contents($publicDir . '/report_reminder_notify.php'), "if (PHP_SAPI !== 'cli')") !== false, true);
check('Administration links to Report reminders', strpos(file_get_contents($publicDir . '/administration.php'), 'href="report_reminders.php"') !== false, true);
check('The menu highlights Administration on it', strpos(file_get_contents($publicDir . '/nav.php'), "'report_reminders'") !== false, true);

if ($failures > 0) {
    echo "[FAIL] Report reminder notification tests: $failures failure(s)\n";
    exit(1);
}
echo "[PASS] Report Notify Test Completed Successfully\n";
exit(0);

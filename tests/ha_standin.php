<?php
/**
 * A stand-in for Home Assistant's API (through the Supervisor), to try
 * Administration → Report reminders on a dev machine. Not part of CI.
 *
 *   php -S 127.0.0.1:8899 tests/ha_standin.php
 *
 * then start the dev server pointed at it (from grace_addon/files/general/www/public):
 *
 *   GRACE_SUPERVISOR_URL=http://127.0.0.1:8899 SUPERVISOR_TOKEN=dev-token php -S 127.0.0.1:8420
 *
 * It offers two phones and logs every request, notifications included, to
 * grace-ha-standin.log in the system temp folder.
 */
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$headers = array_change_key_case(getallheaders(), CASE_LOWER);
$auth = $headers['authorization'] ?? '';
file_put_contents(sys_get_temp_dir() . '/grace-ha-standin.log',
    date('H:i:s') . ' ' . json_encode(['method' => $_SERVER['REQUEST_METHOD'], 'path' => $path, 'body' => file_get_contents('php://input')]) . "\n",
    FILE_APPEND);

header('Content-Type: application/json');
if ($auth !== 'Bearer dev-token') {
    http_response_code(401);
    echo '{"message": "401: Unauthorized"}';
    return;
}
if ($path === '/core/api/services') {
    echo json_encode([
        ['domain' => 'light', 'services' => ['turn_on' => new stdClass()]],
        ['domain' => 'notify', 'services' => [
            'mobile_app_joes_iphone' => new stdClass(),
            'mobile_app_packing_room_tablet' => new stdClass(),
            'notify' => new stdClass(),
            'persistent_notification' => new stdClass(),
        ]],
    ]);
    return;
}
if (preg_match('#^/core/api/services/notify/[a-z0-9_]+$#', $path)) {
    echo '[]';
    return;
}
http_response_code(404);
echo '{}';

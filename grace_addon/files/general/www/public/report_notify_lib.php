<?php
/**
 * Report reminders on a phone (1.2.0, Administration → Report reminders),
 * tested by tests/test_report_notify.php.
 *
 * Opt-in. From 9am on the first weekday of the month, if a report is due
 * (the same reminders the Dashboard shows: last month's materials out, and
 * the annual stocktake in January), GRACe sends one notification through
 * Home Assistant to the device chosen in Administration. If GRACe wasn't
 * running then, it goes out the next time it is, within the Dashboard's
 * first-week window. Never more than one a month.
 *
 * etc/services.d/report-reminders runs report_reminder_notify.php every few
 * minutes to check. Home Assistant is reached through the Supervisor's API
 * proxy (allowed by homeassistant_api in config.yaml) with the
 * SUPERVISOR_TOKEN the Supervisor gives the add-on. Development and tests
 * can point GRACE_SUPERVISOR_URL at a stand-in.
 */

require_once __DIR__ . '/settings_lib.php';
require_once __DIR__ . '/report_reminders_lib.php';

// Reminders go out from this hour, NZ time, on the first weekday of the month
const GRACE_REPORT_NOTIFY_HOUR = 9;

/** The first Monday-to-Friday day of $day's month, at midnight. */
function firstWeekdayOfMonth(DateTimeImmutable $day)
{
    $first = $day->modify('first day of this month')->setTime(0, 0);
    while ((int) $first->format('N') >= 6) {
        $first = $first->modify('+1 day');
    }
    return $first;
}

/**
 * Is it time for this month's reminder? From 9am on the first weekday to
 * the end of the Dashboard's first-week window, if this month hasn't been
 * handled yet.
 *
 * @param string|null $lastSentMonth 'Y-m' of the last month handled
 */
function reportNotificationDue(DateTimeImmutable $now, $lastSentMonth)
{
    if ($lastSentMonth === $now->format('Y-m')) {
        return false;
    }
    $from = firstWeekdayOfMonth($now)->setTime(GRACE_REPORT_NOTIFY_HOUR, 0);
    return $now >= $from && (int) $now->format('j') <= GRACE_MONTHLY_REMINDER_WINDOW_DAYS;
}

/** When the next reminder could go out (it only does if a report is due then). */
function nextReportNotificationTime(DateTimeImmutable $now, $lastSentMonth)
{
    if ($lastSentMonth !== $now->format('Y-m') && (int) $now->format('j') <= GRACE_MONTHLY_REMINDER_WINDOW_DAYS) {
        $thisMonth = firstWeekdayOfMonth($now)->setTime(GRACE_REPORT_NOTIFY_HOUR, 0);
        return $thisMonth > $now ? $thisMonth : $now;
    }
    return firstWeekdayOfMonth($now->modify('first day of next month'))->setTime(GRACE_REPORT_NOTIFY_HOUR, 0);
}

/**
 * The notification for the reminders getDueReportReminders() returns, or
 * null when nothing is due.
 *
 * @return array{title: string, message: string}|null
 */
function reportNotificationMessage(array $due)
{
    $monthly = $annual = null;
    foreach ($due as $reminder) {
        if ($reminder['type'] === 'monthly') {
            $monthly = $reminder['label'];
        } elseif ($reminder['type'] === 'annual') {
            $annual = $reminder['label'];
        }
    }
    if ($monthly !== null && $annual !== null) {
        return [
            'title' => 'GRACe: reports due',
            'message' => "Your $monthly materials-out report and your $annual annual stocktake are due to the Medicinal Cannabis Agency. Open GRACe to send them.",
        ];
    }
    if ($monthly !== null) {
        return [
            'title' => 'GRACe: report due',
            'message' => "Your $monthly materials-out report is ready to send to the Medicinal Cannabis Agency. Open GRACe to send it.",
        ];
    }
    if ($annual !== null) {
        return [
            'title' => 'GRACe: stocktake due',
            'message' => "Your $annual annual stocktake is due to the Medicinal Cannabis Agency this month. Open GRACe to send it.",
        ];
    }
    return null;
}

/** @return array{enabled: bool, service: string} */
function getReportNotifySettings(PDO $pdo)
{
    return [
        'enabled' => getSetting($pdo, 'reportNotifyEnabled', '0') === '1',
        'service' => (string) getSetting($pdo, 'reportNotifyService', ''),
    ];
}

/**
 * Send this month's report reminder if it's switched on, it's time, and a
 * report is due. A failed send is tried again on the next check.
 *
 * @param callable $notify function (string $service, string $title, string $message): array{ok: bool, error: string}
 * @return string 'off' | 'not-time' | 'nothing-due' | 'sent' | 'failed: <why>'
 */
function sendDueReportNotification(PDO $pdo, DateTimeImmutable $now, callable $notify)
{
    $settings = getReportNotifySettings($pdo);
    if (!$settings['enabled'] || $settings['service'] === '') {
        return 'off';
    }
    if (!reportNotificationDue($now, getSetting($pdo, 'reportNotifyLastSent'))) {
        return 'not-time';
    }

    $notification = reportNotificationMessage(getDueReportReminders($pdo, $now->format('Y-m-d')));
    if ($notification === null) {
        // Nothing to report, or it's already been sent or dismissed on the
        // Dashboard. Last month can't change now, so look again next month.
        setSetting($pdo, 'reportNotifyLastSent', $now->format('Y-m'));
        return 'nothing-due';
    }

    $result = $notify($settings['service'], $notification['title'], $notification['message']);
    if (empty($result['ok'])) {
        return 'failed: ' . ($result['error'] ?? 'unknown error');
    }
    setSetting($pdo, 'reportNotifyLastSent', $now->format('Y-m'));
    return 'sent';
}

/**
 * Save Administration → Report reminders.
 *
 * @param array $input enabled ('1' when switched on), service
 * @param array[]|null $services notifyServices(), or null if Home Assistant can't be reached
 * @return array{success: bool, message: string}
 */
function saveReportNotifySettings(PDO $pdo, array $input, $services)
{
    $enabled = ($input['enabled'] ?? '') === '1';
    $service = trim((string) ($input['service'] ?? ''));
    if ($enabled) {
        if ($services === null) {
            return ['success' => false, 'message' => "GRACe can't reach Home Assistant right now, so reminders can't be switched on. Try again in a minute."];
        }
        if (!in_array($service, array_column($services, 'service'), true)) {
            return ['success' => false, 'message' => 'Choose the device to send reminders to.'];
        }
    }
    setSetting($pdo, 'reportNotifyEnabled', $enabled ? '1' : '0');
    if (preg_match('/^[a-z0-9_]+$/D', $service)) {
        setSetting($pdo, 'reportNotifyService', $service);
    }
    return ['success' => true, 'message' => $enabled ? 'Report reminders are on.' : 'Report reminders are off.'];
}

// --- Home Assistant -----------------------------------------------------------

/** Can GRACe talk to Home Assistant? Only with the token homeassistant_api gives. */
function homeAssistantAvailable()
{
    return (string) getenv('SUPERVISOR_TOKEN') !== '';
}

/**
 * Call Home Assistant's REST API through the Supervisor.
 *
 * @return array{ok: bool, status: int, data: mixed, error: string}
 */
function homeAssistantRequest($method, $path, $body = null)
{
    $unreachable = ['ok' => false, 'status' => 0, 'data' => null, 'error' => "GRACe can't reach Home Assistant"];
    if (!homeAssistantAvailable()) {
        return $unreachable;
    }
    $base = rtrim(getenv('GRACE_SUPERVISOR_URL') ?: 'http://supervisor', '/');
    $context = stream_context_create(['http' => [
        'method' => $method,
        'header' => "Authorization: Bearer " . getenv('SUPERVISOR_TOKEN') . "\r\nContent-Type: application/json",
        'content' => $body === null ? '' : json_encode($body),
        'timeout' => 10,
        'ignore_errors' => true, // read Home Assistant's answer even when it says no
    ]]);
    $response = @file_get_contents($base . '/core/api' . $path, false, $context);

    $status = 0;
    foreach ($http_response_header ?? [] as $line) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
            $status = (int) $m[1];
        }
    }
    if ($response === false || $status === 0) {
        return $unreachable;
    }
    $data = json_decode($response, true);
    if ($status < 200 || $status >= 300) {
        return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => "Home Assistant couldn't do it (error $status)"];
    }
    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
}

/**
 * The devices Home Assistant can notify, phones with the Home Assistant app
 * first. Null if Home Assistant can't be reached.
 *
 * @return array[]|null each ['service' => 'mobile_app_joes_phone', 'label' => 'Joes Phone']
 */
function notifyServices()
{
    $result = homeAssistantRequest('GET', '/services');
    if (!$result['ok'] || !is_array($result['data'])) {
        return null;
    }
    return parseNotifyServices($result['data']);
}

/** The notify services in Home Assistant's /api/services answer. */
function parseNotifyServices(array $domains)
{
    // Not devices: the send-to-everything group, Home Assistant's own
    // notification list, and the newer entity-based action
    $skip = ['notify', 'persistent_notification', 'send_message'];
    $phones = $others = [];
    foreach ($domains as $domain) {
        if (($domain['domain'] ?? '') !== 'notify' || !is_array($domain['services'] ?? null)) {
            continue;
        }
        foreach (array_keys($domain['services']) as $service) {
            $service = (string) $service;
            if (!preg_match('/^[a-z0-9_]+$/D', $service) || in_array($service, $skip, true)) {
                continue;
            }
            if (strpos($service, 'mobile_app_') === 0) {
                $phones[] = ['service' => $service, 'label' => ucwords(str_replace('_', ' ', substr($service, strlen('mobile_app_'))))];
            } else {
                $others[] = ['service' => $service, 'label' => ucwords(str_replace('_', ' ', $service))];
            }
        }
    }
    $byLabel = function ($a, $b) {
        return strcasecmp($a['label'], $b['label']);
    };
    usort($phones, $byLabel);
    usort($others, $byLabel);
    return array_merge($phones, $others);
}

/** Send one notification. @return array{ok: bool, error: string} */
function sendNotification($service, $title, $message)
{
    if (!preg_match('/^[a-z0-9_]+$/D', (string) $service)) {
        return ['ok' => false, 'error' => 'That device name is not valid'];
    }
    $result = homeAssistantRequest('POST', '/services/notify/' . $service, ['title' => $title, 'message' => $message]);
    return ['ok' => $result['ok'], 'error' => $result['error']];
}

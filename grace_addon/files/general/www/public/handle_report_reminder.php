<?php
require_once 'init_db.php';
require_once 'report_reminders_lib.php';

// Records a dashboard report reminder as dismissed or drafted.
// POST: report_type ('monthly'|'annual'), period ('2026-05'|'2025'), status ('dismissed'|'drafted')

header('Content-Type: application/json');

$type = $_POST['report_type'] ?? '';
$period = $_POST['period'] ?? '';
$status = $_POST['status'] ?? '';

if (!in_array($type, ['monthly', 'annual'], true)
    || !in_array($status, ['dismissed', 'drafted'], true)
    || !preg_match($type === 'monthly' ? '/^\d{4}-\d{2}$/' : '/^\d{4}$/', $period)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid reminder parameters']);
    exit();
}

try {
    $pdo = initializeDatabase();
    actionReportReminder($pdo, $type, $period, $status);
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    error_log('Report reminder error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>

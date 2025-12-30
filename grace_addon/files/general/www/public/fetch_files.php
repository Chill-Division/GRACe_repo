<?php
require_once 'init_db.php';

date_default_timezone_set('Pacific/Auckland');

$pdo = initializeDatabase();
$category = $_GET['category'] ?? 'offtakes';
$order = $_GET['order'] ?? 'date_desc';

$orderBy = $order === 'name_asc' ? 'original_filename ASC' : 'upload_date DESC';

$stmt = $pdo->prepare("SELECT * FROM Documents WHERE category = ? ORDER BY $orderBy");
$stmt->execute([$category]);

$files = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($files as &$file) {
    if (isset($file['upload_date']) && $file['upload_date']) {
        $dateTime = new DateTime($file['upload_date'], new DateTimeZone('Pacific/Auckland'));
        $file['upload_date'] = $dateTime->format('d-m-Y H:i');
    }
}

echo json_encode($files);
?>
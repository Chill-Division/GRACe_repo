<?php
require_once 'init_db.php';

$pdo = initializeDatabase();
$category = $_GET['category'] ?? 'offtakes';
$order = $_GET['order'] ?? 'date_desc';

$orderBy = $order === 'name_asc' ? 'original_filename ASC' : 'upload_date DESC';

$stmt = $pdo->prepare("SELECT * FROM Documents WHERE category = ? ORDER BY $orderBy");
$stmt->execute([$category]);

$files = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($files);
?>
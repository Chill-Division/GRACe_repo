<?php
require_once 'init_db.php';
require_once 'download_lib.php';

$uploadDir = '/data/uploads/';
$category = $_GET['category'] ?? '';
$filename = $_GET['file'] ?? '';

// Basic validation
if (empty($category) || empty($filename)) {
    die("Invalid request.");
}

// Allowed categories
$allowedCategories = ['offtakes', 'sops', 'licenses', 'other_records', 'coc', 'manifests'];
if (!in_array($category, $allowedCategories)) {
    die("Invalid category.");
}

// Security: Prevent directory traversal
$filename = basename($filename);
$path = $uploadDir . $category . '/' . $filename;

if (!file_exists($path)) {
    http_response_code(404);
    die("File not found.");
}

$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

// Serve the file under the name the user uploaded it as (or a clean
// manifest name), never the uniqid-prefixed storage name
try {
    $pdo = initializeDatabase();
} catch (Exception $e) {
    $pdo = null;
}
$downloadName = sanitizeDownloadName(resolveDownloadName($pdo, $category, $filename), $ext);

header("Cache-Control: private, must-revalidate");
header("Pragma: public");
header("Expires: 0");
header("Content-Type: " . contentTypeForExtension($ext));
header("Content-Disposition: " . downloadDispositionHeader($downloadName));
header("Content-Length: " . filesize($path));
readfile($path);
exit;
?>

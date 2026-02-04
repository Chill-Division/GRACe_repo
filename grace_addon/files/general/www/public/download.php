<?php
require_once 'auth.php'; // Ensure user is logged in

$uploadDir = '/data/uploads/';
$category = $_GET['category'] ?? '';
$filename = $_GET['file'] ?? '';

// Basic validation
if (empty($category) || empty($filename)) {
    die("Invalid request.");
}

// Allowed categories
$allowedCategories = ['offtakes', 'sops', 'licenses', 'other_records', 'coc'];
if (!in_array($category, $allowedCategories)) {
    die("Invalid category.");
}

// Security: Prevent directory traversal
$filename = basename($filename);
$path = $uploadDir . $category . '/' . $filename;

if (file_exists($path)) {
    // Determine content type (optional, can depend on extension)
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    switch ($ext) {
        case "pdf": $ctype = "application/pdf"; break;
        case "exe": $ctype = "application/octet-stream"; break;
        case "zip": $ctype = "application/zip"; break;
        case "doc": $ctype = "application/msword"; break;
        case "xls": $ctype = "application/vnd.ms-excel"; break;
        case "ppt": $ctype = "application/vnd.ms-powerpoint"; break;
        case "gif": $ctype = "image/gif"; break;
        case "png": $ctype = "image/png"; break;
        case "jpeg":
        case "jpg": $ctype = "image/jpg"; break;
        default: $ctype = "application/octet-stream";
    }

    header("Pragma: public"); 
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Cache-Control: private",false); 
    header("Content-Type: $ctype");
    header("Content-Disposition: attachment; filename=\"" . $filename . "\";");
    header("Content-Transfer-Encoding: binary");
    header("Content-Length: " . filesize($path));
    readfile($path);
    exit;
} else {
    http_response_code(404);
    die("File not found.");
}
?>

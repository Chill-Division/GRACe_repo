<?php
require_once 'init_db.php';

$pdo = initializeDatabase();
$uploadDir = __DIR__ . '/uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $category = $_POST['category'] ?? 'other_records'; 

    if (!in_array($category, ['offtakes', 'sops', 'licenses', 'other_records' , 'coc'])) {
        die("Invalid category");
    }

    $originalName = $file['name'];
    $uniqueName = uniqid() . '-' . basename($originalName);
    $targetPath = $uploadDir . $category . '/' . $uniqueName;

    if (!is_dir($uploadDir . $category)) {
        mkdir($uploadDir . $category, 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $stmt = $pdo->prepare("INSERT INTO Documents (category, original_filename, unique_filename) VALUES (?, ?, ?)");
        $stmt->execute([$category, $originalName, $uniqueName]);
        echo json_encode(["success" => true, "message" => "File uploaded"]);
    } else {
        echo json_encode(["success" => false, "message" => "Upload failed"]);
    }
}
?>
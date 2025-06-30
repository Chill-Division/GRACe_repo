<?php
require_once 'init_db.php';

$pdo = initializeDatabase();
$uploadDir = '/data/uploads/';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $category = $_POST['category'] ?? 'other_records'; 
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    if ($file['error'] === 1) {
        echo json_encode([
            "success" => false,
            "message" => "File exceeds PHP limit (upload_max_filesize or post_max_size)"
        ]);
        exit;
    }

    if (!in_array($category, ['offtakes', 'sops', 'licenses', 'other_records' , 'coc'])) {
        die("Invalid category");
    }
    
    // Validate expiry date: must be at most 12 months in the future
    if ($expiryDate) {
        $expiryTimestamp = strtotime($expiryDate);
        $maxExpiryTimestamp = strtotime('+12 months');
        
        if ($expiryTimestamp > $maxExpiryTimestamp) {
             echo json_encode(["success" => false, "message" => "Expiry date must be within 12 months."]);
             exit;
        }
    }

    // Size check (if file did upload)
    $maxSize = 50 * 1024 * 1024; // 50 MB
    if ($file['size'] > $maxSize) {
        echo json_encode(["success" => false, "message" => "File too large. Max 50MB allowed."]);
        exit;
    }

    $originalName = $file['name'];
    $uniqueName = uniqid() . '-' . basename($originalName);
    $targetPath = $uploadDir . $category . '/' . $uniqueName;

    // Directory creation is now handled in init_db.php, but keeping this as backup check is fine, 
    // or we can remove it. For robustness, we can keep simple mkdir or rely on init_db.
    if (!is_dir($uploadDir . $category)) {
        if (!mkdir($uploadDir . $category, 0777, true)) {
             echo json_encode(["success" => false, "message" => "Failed to create upload directory."]);
             exit;
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Insert with expiry_date and acknowledged default to 0
        $stmt = $pdo->prepare("INSERT INTO Documents (category, original_filename, unique_filename, expiry_date, acknowledged) VALUES (?, ?, ?, ?, 0)");
        $stmt->execute([$category, $originalName, $uniqueName, $expiryDate]);
        echo json_encode(["success" => true, "message" => "File uploaded"]);
    } else {
        echo json_encode(["success" => false, "message" => "Upload failed"]);
    }
}
?>

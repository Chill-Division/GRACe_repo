 <?php
 require_once 'init_db.php';

 date_default_timezone_set('Pacific/Auckland');
 
 $pdo = initializeDatabase();
$uploadDir = '/data/uploads/';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $category = $_POST['category'] ?? 'other_records'; 
    $expiryDate = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errorMessages = [
            UPLOAD_ERR_INI_SIZE => "File exceeds PHP upload_max_filesize limit",
            UPLOAD_ERR_FORM_SIZE => "File exceeds form MAX_FILE_SIZE limit",
            UPLOAD_ERR_PARTIAL => "File was only partially uploaded",
            UPLOAD_ERR_NO_FILE => "No file was uploaded",
            UPLOAD_ERR_NO_TMP_DIR => "Missing temporary folder",
            UPLOAD_ERR_CANT_WRITE => "Failed to write file to disk",
            UPLOAD_ERR_EXTENSION => "A PHP extension stopped the file upload"
        ];
        
        $errorMsg = isset($errorMessages[$file['error']]) 
            ? $errorMessages[$file['error']] 
            : "Upload error code: " . $file['error'];
        
        // Special handling for nginx 1MB limit (usually shows as empty file or error 0)
        if ($file['error'] === UPLOAD_ERR_OK && $file['size'] === 0 && isset($_SERVER['CONTENT_LENGTH'])) {
            $contentLength = (int)$_SERVER['CONTENT_LENGTH'];
            if ($contentLength > 1024 * 1024) {
                $errorMsg = "File exceeds nginx 1MB limit. Please compress or resize your image before uploading.";
            }
        }
        
        echo json_encode([
            "success" => false,
            "message" => $errorMsg
        ]);
        exit;
    }

    if (!is_uploaded_file($file['tmp_name'])) {
        echo json_encode([
            "success" => false,
            "message" => "File upload validation failed. The file may exceed nginx 1MB limit."
        ]);
        exit;
    }

    $maxSize = 1024 * 1024;
    if ($file['size'] > $maxSize) {
        echo json_encode([
            "success" => false, 
            "message" => "File too large. Maximum size is 1MB. Please compress or resize your image before uploading."
        ]);
        exit;
    }

    if (!in_array($category, ['offtakes', 'sops', 'licenses', 'other_records', 'coc'])) {
        echo json_encode([
            "success" => false,
            "message" => "Invalid category"
        ]);
        exit;
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

    $originalName = $file['name'];
    $uniqueName = uniqid() . '-' . basename($originalName);
    $targetPath = $uploadDir . $category . '/' . $uniqueName;

    if (!is_dir($uploadDir . $category)) {
        if (!mkdir($uploadDir . $category, 0777, true)) {
            echo json_encode([
                "success" => false,
                "message" => "Failed to create upload directory"
            ]);
            exit;
        }
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        try {

            $uploadDate = date('Y-m-d H:i:s');
            
            $stmt = $pdo->prepare("INSERT INTO Documents (category, original_filename, unique_filename, upload_date, expiry_date, acknowledged) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$category, $originalName, $uniqueName, $uploadDate, $expiryDate]);
            echo json_encode([
                "success" => true, 
                "message" => "File uploaded successfully"
            ]);
        } catch (PDOException $e) {
            @unlink($targetPath);
            echo json_encode([
                "success" => false,
                "message" => "Database error: " . $e->getMessage()
            ]);
        }
    } else {
        echo json_encode([
            "success" => false, 
            "message" => "Failed to save uploaded file. Please check directory permissions."
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Invalid request"
    ]);
}
 ?> 

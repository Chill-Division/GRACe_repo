<?php
require_once 'init_db.php';
require_once 'manifest_lib.php';

$pdo = initializeDatabase();

header('Content-Type: application/json');

function respond($success, $message, $extra = []) {
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request');
}

$manifestId = (int) ($_POST['manifest_id'] ?? 0);
$manifest = fetchManifest($pdo, $manifestId);

if (!$manifest) {
    respond(false, 'Manifest not found');
}
if ($manifest['status'] === 'Completed') {
    respond(false, 'Manifest #' . $manifestId . ' is already completed');
}

$existingDocId = (int) ($_POST['existing_document_id'] ?? 0);
$hasUpload = isset($_FILES['file']) && $_FILES['file']['error'] !== UPLOAD_ERR_NO_FILE;

// Hard rule: a manifest cannot be closed off without a Chain of Custody attached
if (!$hasUpload && !$existingDocId) {
    respond(false, 'A Chain of Custody photo or PDF must be attached before the manifest can be completed.');
}

$cocDocumentId = null;

if ($hasUpload) {
    $file = $_FILES['file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(false, 'Upload failed (error code ' . $file['error'] . ')');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        respond(false, 'File upload validation failed. The file may exceed nginx 1MB limit.');
    }
    if ($file['size'] > 1024 * 1024) {
        respond(false, 'File too large. Maximum size is 1MB. Please compress or resize your image before uploading.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'], true)) {
        respond(false, 'Chain of Custody must be a photo (jpg, png, gif, webp) or a PDF.');
    }

    $uploadDir = '/data/uploads/coc';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        respond(false, 'Failed to create upload directory');
    }

    $uniqueName = uniqid() . '-' . basename($file['name']);
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $uniqueName)) {
        respond(false, 'Failed to save uploaded file. Please check directory permissions.');
    }

    $stmt = $pdo->prepare("INSERT INTO Documents (category, original_filename, unique_filename, upload_date, acknowledged) VALUES ('coc', ?, ?, ?, 0)");
    $stmt->execute([$file['name'], $uniqueName, date('Y-m-d H:i:s')]);
    $cocDocumentId = (int) $pdo->lastInsertId();
} else {
    // Attach an existing manually-uploaded CoC document, it must exist and
    // not already be covering another manifest
    $stmt = $pdo->prepare("SELECT id FROM Documents
                           WHERE id = ? AND category = 'coc'
                             AND id NOT IN (SELECT coc_document_id FROM ShippingManifests WHERE coc_document_id IS NOT NULL)");
    $stmt->execute([$existingDocId]);
    if (!$stmt->fetch()) {
        respond(false, 'Selected Chain of Custody document is unavailable or already attached to another manifest.');
    }
    $cocDocumentId = $existingDocId;
}

// status guard in the WHERE clause protects against a double submit
$stmt = $pdo->prepare("UPDATE ShippingManifests
                       SET status = 'Completed', coc_document_id = ?, date_completed = ?
                       WHERE id = ? AND status = 'In Progress'");
$stmt->execute([$cocDocumentId, date('Y-m-d H:i:s'), $manifestId]);

if ($stmt->rowCount() === 0) {
    respond(false, 'Manifest could not be completed. It may have been completed already.');
}

respond(true, 'Manifest completed', ['manifest_id' => $manifestId]);

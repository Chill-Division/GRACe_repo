<?php
require_once 'init_db.php';
require_once 'manifest_lib.php';
require_once 'genetics_lib.php';

// Initialize PDO connection
$pdo = initializeDatabase();

// Manifests are only ever created from the generate form (POST). A stray GET
// (refresh, bookmark) just goes back to the form, nothing is written twice.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: generate_shipping_manifest.php');
    exit();
}

function backToFormWithError($message) {
    header('Location: generate_shipping_manifest.php?error=' . urlencode($message));
    exit();
}

// Fetch OwnCompany details
$ownCompanyStmt = $pdo->query("SELECT company_name, company_license_number, company_address, primary_contact_email FROM OwnCompany LIMIT 1");
$ownCompany = $ownCompanyStmt->fetch(PDO::FETCH_ASSOC);
if (!$ownCompany) {
    backToFormWithError('Set up your own company details before generating a manifest.');
}

$sendingChoice = $_POST['sendingChoice'] ?? '';
$receivingChoice = $_POST['receivingChoice'] ?? '';
$productType = $_POST['productType'] ?? '';
$quantity = (float) ($_POST['quantity'] ?? 0);
$geneticsId = (int) ($_POST['geneticsId'] ?? 0);

if (!in_array($sendingChoice, ['us', 'external'], true)
    || !in_array($receivingChoice, ['us', 'external'], true)
    || !in_array($productType, ['flower', 'plant'], true)) {
    backToFormWithError('All manifest fields are required.');
}
// Above 0, and whole plants for a plant manifest
$quantityError = validateManifestQuantity($productType, $_POST['quantity'] ?? '');
if ($quantityError !== null) {
    backToFormWithError($quantityError);
}

// Not a retired one either, in case the form was opened before it was retired
$chosen = geneticsForNewEntry($pdo, $geneticsId);
if ($chosen['error'] !== null) {
    backToFormWithError($chosen['error']);
}
$genetics = $chosen['genetics'];

// Resolve a party (sending or receiving) to a consistent shape
function resolveParty($pdo, $ownCompany, $choice, $companyId) {
    if ($choice === 'us') {
        return [
            'id' => null,
            'name' => $ownCompany['company_name'],
            'license' => $ownCompany['company_license_number'],
            'email' => $ownCompany['primary_contact_email'],
            'address' => $ownCompany['company_address'],
        ];
    }
    $stmt = $pdo->prepare("SELECT id, name, license_number, address, primary_contact_email FROM Companies WHERE id = ?");
    $stmt->execute([(int) $companyId]);
    $company = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$company) {
        return null;
    }
    return [
        'id' => (int) $company['id'],
        'name' => $company['name'],
        'license' => $company['license_number'],
        'email' => $company['primary_contact_email'],
        'address' => $company['address'],
    ];
}

$sender = resolveParty($pdo, $ownCompany, $sendingChoice, $_POST['sendingCompanySelect'] ?? 0);
$receiver = resolveParty($pdo, $ownCompany, $receivingChoice, $_POST['receivingCompanySelect'] ?? 0);
if (!$sender || !$receiver) {
    backToFormWithError('Please select a valid company for both parties.');
}

// Automatic inventory deduction: flower leaving us for an external company
// comes straight off the dried-flower ledger as part of generating the manifest
$deductFlower = ($productType === 'flower' && $sendingChoice === 'us' && $receivingChoice === 'external');

if ($deductFlower) {
    $stockStmt = $pdo->prepare("SELECT COALESCE(SUM(weight), 0) FROM Flower WHERE genetics_id = ?");
    $stockStmt->execute([$geneticsId]);
    $stock = (float) $stockStmt->fetchColumn();
    // Compared at 0.1 g, the precision weights are entered in (like
    // recordFlowerTransaction()), so an older balance with hundredths can
    // still be shipped in full
    if ($quantity > round($stock, 1) + 0.0001) {
        backToFormWithError(sprintf(
            'Insufficient dried flower for %s: %.2f g recorded, manifest needs %.2f g. Correct the ledger before generating the manifest.',
            $genetics['name'], $stock, $quantity
        ));
    }
}

$datePrepared = date('Y-m-d H:i:s');

try {
    $pdo->beginTransaction();

    $flowerTransactionId = null;
    if ($deductFlower) {
        // Reason must be exactly 'Send external' so the deduction shows up in
        // the monthly Agency report and dashboard totals like a manual entry
        $flowerStmt = $pdo->prepare("INSERT INTO Flower (genetics_id, weight, transaction_type, transaction_date, reason, company_id)
                                     VALUES (?, ?, 'Subtract', ?, 'Send external', ?)");
        $flowerStmt->execute([$geneticsId, -$quantity, $datePrepared, $receiver['id']]);
        $flowerTransactionId = (int) $pdo->lastInsertId();
    }

    $manifestStmt = $pdo->prepare("INSERT INTO ShippingManifests
        (sending_company_id, recipient_id, shipment_date, product_type, status,
         sending_company_name, receiving_company_name, genetics_id, genetics_name,
         quantity, destination_address, flower_transaction_id)
        VALUES (?, ?, ?, ?, 'In Progress', ?, ?, ?, ?, ?, ?, ?)");
    $manifestStmt->execute([
        $sender['id'], $receiver['id'], $datePrepared, $productType,
        $sender['name'], $receiver['name'], $geneticsId, $genetics['name'],
        $quantity, $receiver['address'], $flowerTransactionId,
    ]);
    $manifestId = (int) $pdo->lastInsertId();

    // --- Build the manifest PDF (manifest_lib.php) and keep it so it can be re-downloaded later ---
    $pdfOutput = renderShippingManifestPdf([
        'id' => $manifestId,
        'sender' => $sender,
        'receiver' => $receiver,
        'genetics' => $genetics['name'],
        'productType' => $productType,
        'quantity' => $quantity,
        'prepared' => $datePrepared,
    ]);

    $manifestDir = '/data/uploads/manifests';
    if (!is_dir($manifestDir)) {
        mkdir($manifestDir, 0777, true);
    }
    $manifestFile = uniqid() . '-shipping-manifest-' . $manifestId . '.pdf';
    if (file_put_contents($manifestDir . '/' . $manifestFile, $pdfOutput) === false) {
        throw new Exception('Failed to write the manifest PDF to disk.');
    }

    $pdo->prepare("UPDATE ShippingManifests SET manifest_file = ? WHERE id = ?")
        ->execute([$manifestFile, $manifestId]);

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    if (isset($manifestFile) && file_exists($manifestDir . '/' . $manifestFile)) {
        @unlink($manifestDir . '/' . $manifestFile);
    }
    backToFormWithError('Error generating manifest: ' . $e->getMessage());
}

// The summary or, for plants leaving us with "Then mark these plants as
// sent" on, Harvest / Destroy / Send with them ticked to check and confirm
header('Location: ' . manifestNextPage($productType, $sendingChoice, $receivingChoice, !empty($_POST['markPlantsSent']), $manifestId));
exit();

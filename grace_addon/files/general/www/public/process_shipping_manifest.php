<?php
require_once 'TCPDF/tcpdf.php';
require_once 'init_db.php';

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
    || !in_array($productType, ['flower', 'plant'], true)
    || $quantity <= 0) {
    backToFormWithError('All manifest fields are required.');
}

$geneticsStmt = $pdo->prepare("SELECT id, name FROM Genetics WHERE id = ?");
$geneticsStmt->execute([$geneticsId]);
$genetics = $geneticsStmt->fetch(PDO::FETCH_ASSOC);
if (!$genetics) {
    backToFormWithError('Please select a valid genetics.');
}

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
    if ($quantity > $stock) {
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

    // --- Build the manifest PDF and persist it so it can be re-downloaded later ---
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor('Chill Division');
    $pdf->SetTitle('Shipping Manifest');
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetHeaderMargin(15);
    $pdf->AddPage();

    $senderName = htmlspecialchars($sender['name'] ?? '');
    $senderLicense = htmlspecialchars($sender['license'] ?? '');
    $senderEmail = htmlspecialchars($sender['email'] ?? '');
    $receiverName = htmlspecialchars($receiver['name'] ?? '');
    $receiverLicense = htmlspecialchars($receiver['license'] ?? '');
    $geneticsName = htmlspecialchars($genetics['name']);
    $formattedAddress = nl2br(htmlspecialchars($receiver['address'] ?? ''));
    $quantityLabel = $productType === 'flower' ? $quantity . ' g' : $quantity;

    $htmlContent = <<<EOD

    <style>
         h1 { color: #85bbe9; text-align: left; font-weight: bold; }
        table { width: 100%; }
        td { padding: 5px; vertical-align: middle; }
    </style>


    <div class="flex items-center justify-between">
        <div>
            <h1>Chill Division Universal Shipping Manifest</h1>
        </div>
    </div>

    <h2>Sending Party:</h2>
    <table>
        <tr><td><strong>Manifest #:</strong></td><td><u>{$manifestId}</u></td></tr>
        <tr><td><strong>Staff Name Preparing Shipment:</strong></td><td>________________________</td></tr>
        <tr><td><strong>Sending Company name + Licence #:</strong></td><td><u>{$senderName} / {$senderLicense}</u></td></tr>
        <tr><td><strong>Arrival Notification Email:</strong></td><td><u>{$senderEmail}</u></td></tr>
        <tr><td><strong>Date / Time Prepared for shipment:</strong></td><td><u>{$datePrepared}</u></td></tr>
        <tr><td><strong>Product Type:</strong></td><td><u>{$productType}</u></td></tr>
        <tr><td><strong>Genetics:</strong></td><td><u>{$geneticsName}</u></td></tr>
        <tr><td><strong># of Items Sent:</strong></td><td><u>{$quantityLabel}</u></td></tr>
        <tr><td><strong>Shipment Weight (Net / Gross):</strong></td><td>________________________</td></tr>
        <tr><td><strong>Recipient Staff Name:</strong></td><td>________________________</td></tr>
        <tr><td><strong>Recipient Company + Licence #:</strong></td><td><u>{$receiverName} / {$receiverLicense}</u></td></tr>
        <tr><td><strong>Destination Address:</strong></td><td><u>{$formattedAddress}</u></td></tr>
    </table>

    <p class="signature"><strong>• Staff Signature:</strong> ________________________</p>

    <h2>Transit Chain of Custody (if applicable):</h2>
    <table class="ms-[30px]">
        <tr><td><strong>Collected from Facility By:</strong></td><td>________________________</td></tr>
        <tr><td><strong>Date / Time Shipment Collected:</strong></td><td>____/____/______ ____:____</td></tr>
        <tr><td><strong># of Items Collected:</strong></td><td>________________________</td></tr>
    </table>

     <p class="signature"><strong>• Collection Signature / Tracking Number:</strong> ________________________</p>


    <h2>Receiving Party:</h2>
    <table class="ms-[30px]">
        <tr><td><strong>Received By (Staff Name):</strong></td><td>________________________</td></tr>
        <tr><td><strong>Date / Time of Receipt:</strong></td><td>____/____/______ ____:____</td></tr>
        <tr><td><strong># of Items Received:</strong></td><td>________________________</td></tr>
        <tr><td><strong>Shipment Weight (Net / Gross):</strong></td><td>________________________</td></tr>
    </table>

    <p class="signature"><strong>• Signature of Receiving Party:</strong>________________________</p>

    <p><em>Please scan/photo upon receipt of goods and send a copy to <strong>{$senderEmail}</strong></em></p>
    <p><em>This manifest is recorded as In Progress (manifest #{$manifestId}) in GRACe. It must be completed with the
    signed Chain of Custody attached once the shipment is received.</em></p>
    EOD;

    $pdf->writeHTML($htmlContent, true, false, true, false, '');
    $pdfOutput = $pdf->Output('shipping_manifest.pdf', 'S');

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

header('Location: manifest_summary.php?id=' . $manifestId . '&created=1');
exit();

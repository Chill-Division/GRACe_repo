<?php
/**
 * manifest_lib.php
 *
 * Shared helpers for the shipping manifest workflow pages:
 * - manifest_summary.php (exchange summary)
 * - complete_manifest.php (list + complete In Progress manifests)
 * - handle_complete_manifest.php (attach CoC + close out)
 * - chain_of_custody_documents.php (exchange list)
 * - process_shipping_manifest.php (the manifest PDF)
 */

const MANIFEST_SELECT = "SELECT SM.*,
            D.original_filename AS coc_filename,
            D.unique_filename AS coc_unique_filename,
            F.weight AS deducted_weight,
            F.transaction_date AS deduction_date
        FROM ShippingManifests SM
        LEFT JOIN Documents D ON SM.coc_document_id = D.id
        LEFT JOIN Flower F ON SM.flower_transaction_id = F.id";

function fetchManifest($pdo, $id) {
    $stmt = $pdo->prepare(MANIFEST_SELECT . " WHERE SM.id = ?");
    $stmt->execute([(int) $id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

function fetchManifests($pdo, $status = null) {
    $sql = MANIFEST_SELECT;
    $params = [];
    if ($status !== null) {
        $sql .= " WHERE SM.status = ?";
        $params[] = $status;
    }
    $sql .= " ORDER BY SM.shipment_date DESC, SM.id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Check a manifest quantity as typed: above 0, whole plants for a plant
 * manifest, and grams to one decimal place for flower (like every weight in
 * GRACe, see AGENTS.md). Returns an error message, or null if it's fine.
 */
function validateManifestQuantity($productType, $quantity)
{
    $text = trim((string) $quantity);
    if (!preg_match('/^(\d+(\.\d*)?|\.\d+)$/D', $text) || (float) $text <= 0) {
        return 'Please enter a quantity above 0.';
    }
    if ($productType === 'plant' && !preg_match('/^\d+(\.0*)?$/D', $text)) {
        return 'Plants are counted in whole numbers.';
    }
    if ($productType === 'flower' && preg_match('/\.\d{2,}$/D', rtrim($text, '0'))) {
        return 'Weights are recorded to one decimal place, like 12.5 g.';
    }
    return null;
}

/** "200 g White Widow (flower)" / "5 x GG4 (plant)" */
function manifestShipmentLabel($manifest) {
    $quantity = rtrim(rtrim(number_format((float) $manifest['quantity'], 2, '.', ''), '0'), '.');
    $genetics = $manifest['genetics_name'] ?? '';
    if ($manifest['product_type'] === 'flower') {
        return "$quantity g $genetics (flower)";
    }
    return "$quantity x $genetics (plant)";
}

/** "Sender → Receiver" */
function manifestRouteLabel($manifest) {
    return ($manifest['sending_company_name'] ?? '?') . ' → ' . ($manifest['receiving_company_name'] ?? '?');
}

/** Status rendered as a coloured badge (HTML, pre-escaped). */
function manifestStatusBadge($manifest) {
    $status = $manifest['status'] ?? 'In Progress';
    $class = $status === 'Completed' ? 'badge--growing' : 'badge--drying';
    return '<span class="badge ' . $class . '">' . htmlspecialchars($status) . '</span>';
}

/**
 * Where to go once a manifest is generated: its summary or, for plants
 * leaving us with "Then mark these plants as sent" on, Harvest / Destroy /
 * Send with them ticked, to be checked and confirmed there. Nothing is
 * marked as sent until then. Tested by tests/test_manifest_mark_sent.php.
 */
function manifestNextPage($productType, $sendingChoice, $receivingChoice, $markPlantsSent, $manifestId)
{
    if ($markPlantsSent && $productType === 'plant' && $sendingChoice === 'us' && $receivingChoice === 'external') {
        return 'harvest_plants.php?manifest=' . (int) $manifestId;
    }
    return 'manifest_summary.php?id=' . (int) $manifestId . '&created=1';
}

/**
 * A plant manifest from us to another company, for Harvest / Destroy / Send
 * to fill in: which genetics, how many, and who to. Null for anything else
 * (flower, plants coming in, a manifest that doesn't exist).
 *
 * @return array{id: int, geneticsName: string, quantity: int, companyId: int, companyName: string}|null
 */
function plantManifestToMarkSent(PDO $pdo, $manifestId)
{
    if (!preg_match('/^[0-9]+$/D', (string) $manifestId)) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT SM.id, SM.quantity, G.name AS genetics_name, C.id AS company_id, C.name AS company_name
                           FROM ShippingManifests SM
                           JOIN Genetics G ON G.id = SM.genetics_id
                           JOIN Companies C ON C.id = SM.recipient_id
                           WHERE SM.id = ? AND SM.product_type = 'plant' AND SM.sending_company_id IS NULL");
    $stmt->execute([(int) $manifestId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }
    return [
        'id' => (int) $row['id'],
        'geneticsName' => $row['genetics_name'],
        'quantity' => (int) $row['quantity'],
        'companyId' => (int) $row['company_id'],
        'companyName' => $row['company_name'],
    ];
}


/**
 * The shipping manifest as a PDF (the file's bytes), in the Chill Division
 * brand look (1.4.0): the logo top left, Inter and sentence case labels.
 * Inter lives in TCPDF/fonts, cut down to Latin, macrons and punctuation
 * (tests/build_pdf_fonts.php), so a name like Ōtaki Whānau Growers prints
 * properly. TCPDF's own Helvetica has no macrons and printed
 * "?taki Wh?nau Growers". Tested by tests/test_manifest_pdf.php.
 *
 * @param array $m id, sender and receiver (name, license, email, address),
 *                 genetics (name), productType, quantity, prepared (Y-m-d H:i:s)
 * @return string
 */
function renderShippingManifestPdf(array $m)
{
    if (!defined('K_PATH_CACHE')) {
        // TCPDF deletes any image under its cache folder once a PDF is done,
        // and that folder defaults to the whole temp folder. Whenever GRACe
        // itself sits under /tmp (a dev copy, a test run) that took the logo
        // with it, so TCPDF gets a folder of its own.
        $cache = rtrim(sys_get_temp_dir(), '/') . '/grace-tcpdf/';
        if (!is_dir($cache)) {
            @mkdir($cache, 0700, true);
        }
        define('K_PATH_CACHE', $cache);
    }
    require_once __DIR__ . '/TCPDF/tcpdf.php';
    require_once __DIR__ . '/format_lib.php';

    $manifestId = (int) $m['id'];
    $sender = $m['sender'];
    $receiver = $m['receiver'];
    $genetics = ['name' => $m['genetics']];
    $productType = $m['productType'];
    $quantity = $m['quantity'];
    $datePrepared = $m['prepared'];

    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    $pdf->SetCreator('GRACe by Chill Division');
    $pdf->SetAuthor('Chill Division');
    $pdf->SetTitle('Shipping manifest');
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    // Inter everywhere, set before the first page, so TCPDF never loads its
    // default Helvetica (no macrons) even for the page set-up
    $pdf->setHeaderFont(['inter', '', 10]);
    $pdf->setFooterFont(['inter', '', 8]);
    $pdf->SetDefaultMonospacedFont('inter');
    $pdf->SetFont('inter', '', 10);
    $pdf->SetMargins(15, 15, 15);
    $pdf->SetAutoPageBreak(true, 15);
    $pdf->AddPage();
    $pdf->SetTextColor(44, 44, 42);
    // 12 mm tall: the brand guide's smallest logo in print is 10 mm
    $pdf->Image(__DIR__ . '/icons/chill-division-logo-print.png', 15, 14, 0, 12, 'PNG');
    $pdf->SetY(31);

    $senderName = htmlspecialchars($sender['name'] ?? '');
    $senderLicense = htmlspecialchars($sender['license'] ?? '');
    $senderEmail = htmlspecialchars($sender['email'] ?? '');
    $receiverName = htmlspecialchars($receiver['name'] ?? '');
    $receiverLicense = htmlspecialchars($receiver['license'] ?? '');
    $geneticsName = htmlspecialchars($genetics['name']);
    $formattedAddress = nl2br(htmlspecialchars($receiver['address'] ?? ''));
    $quantityLabel = $productType === 'flower' ? $quantity . ' g' : $quantity;
    $productLabel = htmlspecialchars(ucfirst($productType));
    $preparedLabel = htmlspecialchars(formatLedgerDateTime($datePrepared));
    $blank = '________________________';

    $htmlContent = <<<EOD
    <style>
        h1 { color: #0d0d0d; font-size: 18pt; font-weight: bold; }
        h2 { color: #0d0d0d; font-size: 12pt; font-weight: bold; }
        td { padding: 4px; }
        .note { color: #64645f; font-size: 9pt; }
    </style>

    <h1>Universal shipping manifest</h1>
    <br />
    <h2>Sending party</h2>
    <table cellpadding="4">
        <tr><td width="42%"><strong>Manifest number:</strong></td><td width="58%">{$manifestId}</td></tr>
        <tr><td><strong>Staff name preparing the shipment:</strong></td><td>{$blank}</td></tr>
        <tr><td><strong>Sending company and licence number:</strong></td><td>{$senderName} / {$senderLicense}</td></tr>
        <tr><td><strong>Arrival notification email:</strong></td><td>{$senderEmail}</td></tr>
        <tr><td><strong>Date and time prepared:</strong></td><td>{$preparedLabel}</td></tr>
        <tr><td><strong>Product type:</strong></td><td>{$productLabel}</td></tr>
        <tr><td><strong>Genetics:</strong></td><td>{$geneticsName}</td></tr>
        <tr><td><strong>Number of items sent:</strong></td><td>{$quantityLabel}</td></tr>
        <tr><td><strong>Shipment weight (net / gross):</strong></td><td>{$blank}</td></tr>
        <tr><td><strong>Recipient staff name:</strong></td><td>{$blank}</td></tr>
        <tr><td><strong>Receiving company and licence number:</strong></td><td>{$receiverName} / {$receiverLicense}</td></tr>
        <tr><td><strong>Destination address:</strong></td><td>{$formattedAddress}</td></tr>
        <tr><td><strong>Staff signature:</strong></td><td>{$blank}</td></tr>
    </table>

    <h2>Transit chain of custody (if applicable)</h2>
    <table cellpadding="4">
        <tr><td width="42%"><strong>Collected from the facility by:</strong></td><td width="58%">{$blank}</td></tr>
        <tr><td><strong>Date and time collected:</strong></td><td>____/____/______ ____:____</td></tr>
        <tr><td><strong>Number of items collected:</strong></td><td>{$blank}</td></tr>
        <tr><td><strong>Collection signature or tracking number:</strong></td><td>{$blank}</td></tr>
    </table>

    <h2>Receiving party</h2>
    <table cellpadding="4">
        <tr><td width="42%"><strong>Received by (staff name):</strong></td><td width="58%">{$blank}</td></tr>
        <tr><td><strong>Date and time received:</strong></td><td>____/____/______ ____:____</td></tr>
        <tr><td><strong>Number of items received:</strong></td><td>{$blank}</td></tr>
        <tr><td><strong>Shipment weight (net / gross):</strong></td><td>{$blank}</td></tr>
        <tr><td><strong>Signature of receiving party:</strong></td><td>{$blank}</td></tr>
    </table>

    <p class="note">Please scan or photograph this when the goods arrive and send a copy to <strong>{$senderEmail}</strong>.</p>
    <p class="note">This manifest is recorded in GRACe as In Progress (manifest #{$manifestId}). Complete it there with the
    signed chain of custody attached once the shipment is received.</p>
    EOD;

    $pdf->writeHTML($htmlContent, true, false, true, false, '');
    return $pdf->Output('shipping_manifest.pdf', 'S');
}

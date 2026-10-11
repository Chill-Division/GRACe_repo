<?php
/**
 * manifest_lib.php
 *
 * Shared helpers for the shipping manifest workflow pages:
 * - manifest_summary.php (exchange summary)
 * - complete_manifest.php (list + complete In Progress manifests)
 * - handle_complete_manifest.php (attach CoC + close out)
 * - chain_of_custody_documents.php (exchange list)
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

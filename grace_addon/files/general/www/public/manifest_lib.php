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
 * Check a manifest quantity as typed: above 0, and whole plants for a
 * plant manifest. Returns an error message, or null if it's fine.
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

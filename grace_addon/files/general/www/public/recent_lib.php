<?php
/**
 * "Recent entries" under the forms on Receive plants, Record dry weight and
 * Harvest / Destroy / Send (1.1.0), so you can see what you just did and
 * spot something entered twice. Tested by tests/test_recent_entries.php.
 */

require_once __DIR__ . '/flower_lib.php';

const GRACE_RECENT_ENTRIES = 10;

/**
 * Plants received, one line per entry (a batch shares one time stamp),
 * newest first, whatever has happened to the plants since.
 *
 * @return array[] each: when, genetics, count
 */
function recentReceipts(PDO $pdo, $limit = GRACE_RECENT_ENTRIES)
{
    $stmt = $pdo->prepare(
        "SELECT P.date_created AS entry_time, COALESCE(G.name, 'Unknown genetics') AS genetics, COUNT(*) AS plants
         FROM Plants P
         LEFT JOIN Genetics G ON G.id = P.genetics_id
         WHERE P.date_created IS NOT NULL
         GROUP BY P.date_created, P.genetics_id
         ORDER BY P.date_created DESC, MIN(P.id) DESC
         LIMIT ?"
    );
    $stmt->execute([(int) $limit]);
    return array_map(fn($row) => [
        'when' => $row['entry_time'],
        'genetics' => $row['genetics'],
        'count' => (int) $row['plants'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Plants harvested, destroyed or sent, one line per batch, newest first.
 *
 * @return array[] each: when, status, genetics, count, company (or null)
 */
function recentPlantMoves(PDO $pdo, $limit = GRACE_RECENT_ENTRIES)
{
    $stmt = $pdo->prepare(
        "SELECT P.date_harvested AS entry_time, P.status, COALESCE(G.name, 'Unknown genetics') AS genetics,
                COUNT(*) AS plants, C.name AS company
         FROM Plants P
         LEFT JOIN Genetics G ON G.id = P.genetics_id
         LEFT JOIN Companies C ON C.id = P.company_id
         WHERE P.status <> 'Growing' AND P.date_harvested IS NOT NULL
         GROUP BY P.date_harvested, P.status, P.genetics_id, P.company_id
         ORDER BY P.date_harvested DESC, MIN(P.id) DESC
         LIMIT ?"
    );
    $stmt->execute([(int) $limit]);
    return array_map(fn($row) => [
        'when' => $row['entry_time'],
        'status' => $row['status'],
        'genetics' => $row['genetics'],
        'count' => (int) $row['plants'],
        'company' => $row['company'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/**
 * Dried flower entries, newest first, including deductions made by
 * shipping manifests.
 *
 * @return array[] each: when, genetics, grams (negative when taken out),
 *                 reason, company (or null), manifestId (or null)
 */
function recentFlowerEntries(PDO $pdo, $limit = GRACE_RECENT_ENTRIES)
{
    $stmt = $pdo->prepare(
        "SELECT F.transaction_date AS entry_time, COALESCE(G.name, 'Unknown genetics') AS genetics, F.weight,
                F.reason, C.name AS company, SM.id AS manifest_id
         FROM Flower F
         LEFT JOIN Genetics G ON G.id = F.genetics_id
         LEFT JOIN Companies C ON C.id = F.company_id
         LEFT JOIN ShippingManifests SM ON SM.flower_transaction_id = F.id
         ORDER BY F.transaction_date DESC, F.id DESC
         LIMIT ?"
    );
    $stmt->execute([(int) $limit]);
    return array_map(fn($row) => [
        'when' => $row['entry_time'],
        'genetics' => $row['genetics'],
        'grams' => round((float) $row['weight'], 2),
        'reason' => $row['reason'],
        'company' => $row['company'],
        'manifestId' => $row['manifest_id'] === null ? null : (int) $row['manifest_id'],
    ], $stmt->fetchAll(PDO::FETCH_ASSOC));
}

/** '2026-09-23 10:32:05' (stored NZ time) -> '23/09/2026 10:32'; a date alone -> '05/05/2024' */
function formatLedgerDateTime($value)
{
    $value = (string) $value;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}))?/', $value, $m)) {
        return "$m[3]/$m[2]/$m[1]" . (isset($m[4]) ? " $m[4]:$m[5]" : '');
    }
    return $value;
}

/** A plant status as a coloured badge (HTML, escaped), matching statusBadge() in growcart.js. */
function plantStatusBadge($status)
{
    $lower = strtolower((string) $status);
    if ($lower === 'growing') {
        $class = 'badge--growing';
    } elseif (strpos($lower, 'drying') !== false) {
        $class = 'badge--drying';
    } elseif (strpos($lower, 'destroyed') !== false) {
        $class = 'badge--destroyed';
    } elseif ($lower === 'sent') {
        $class = 'badge--sent';
    } else {
        $class = 'badge--neutral';
    }
    return '<span class="badge ' . $class . '">' . htmlspecialchars((string) $status) . '</span>';
}

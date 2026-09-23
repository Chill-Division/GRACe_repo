<?php
/**
 * Harvest / Destroy / Send for whole plants (harvest_plants.php via
 * handle_harvest_plants.php), tested by tests/test_harvest_safeguards.php.
 *
 * The ledger can't be edited afterwards, so this refuses anything it can't
 * do exactly as asked: an unknown action, a send with no company, or plants
 * that have already left (for example ticked in a stale browser tab). A
 * batch is all or nothing.
 */

// Statuses the Harvest page lists, i.e. plants that can still be processed
const GRACE_PROCESSABLE_STATUSES = ['Growing', 'Harvested - Drying', 'Harvested'];

// action => the status it sets, and the statuses a plant may be in beforehand
const GRACE_PLANT_ACTIONS = [
    'harvest' => ['status' => 'Harvested - Drying', 'from' => ['Growing']],
    'destroy' => ['status' => 'Harvested - Destroyed', 'from' => GRACE_PROCESSABLE_STATUSES],
    'send' => ['status' => 'Sent', 'from' => GRACE_PROCESSABLE_STATUSES],
];

/**
 * Harvest, destroy or send a batch of plants.
 *
 * @param PDO $pdo
 * @param array $plantIds ids of the ticked plants
 * @param string $action 'harvest' | 'destroy' | 'send'
 * @param int|string|null $companyId where the plants went (send only)
 * @return array{success: bool, message: string, count: int, reload: bool}
 */
function processPlants(PDO $pdo, $plantIds, $action, $companyId)
{
    $refuse = function ($message, $reload = false) {
        return ['success' => false, 'message' => $message, 'count' => 0, 'reload' => $reload];
    };

    if (!is_string($action) || !isset(GRACE_PLANT_ACTIONS[$action])) {
        return $refuse('Unknown action. Nothing was changed.');
    }
    if (!is_array($plantIds) || !$plantIds) {
        return $refuse('Select at least one plant.');
    }
    $ids = [];
    foreach ($plantIds as $id) {
        if (!(is_int($id) || (is_string($id) && preg_match('/^[0-9]+$/D', $id))) || (int) $id <= 0) {
            return $refuse('That plant selection is not valid. Nothing was changed.');
        }
        $ids[(int) $id] = true; // a plant ticked twice counts once
    }
    $ids = array_keys($ids);

    $company = null;
    if ($action === 'send') {
        if (!(is_int($companyId) || (is_string($companyId) && preg_match('/^[0-9]+$/D', $companyId)))) {
            return $refuse('Choose which company the plants were sent to.');
        }
        $stmt = $pdo->prepare("SELECT id, name FROM Companies WHERE id = ?");
        $stmt->execute([(int) $companyId]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            return $refuse('That company could not be found. Reload the page and try again.', true);
        }
    }

    $rule = GRACE_PLANT_ACTIONS[$action];
    $idList = implode(',', array_fill(0, count($ids), '?'));

    return withWriteLock($pdo, function (PDO $pdo) use ($ids, $idList, $rule, $action, $company, $refuse) {
        // Where is each selected plant right now?
        $stmt = $pdo->prepare("SELECT status, COUNT(*) FROM Plants WHERE id IN ($idList) GROUP BY status");
        $stmt->execute($ids);
        $byStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        $stillListed = 0;
        foreach (GRACE_PROCESSABLE_STATUSES as $status) {
            $stillListed += (int) ($byStatus[$status] ?? 0);
        }
        $gone = count($ids) - $stillListed; // already left, or never existed
        if ($gone > 0) {
            return $refuse(sprintf(
                '%d of the selected plants %s already been processed, probably in another tab or window. Nothing was changed. Check the list and try again.',
                $gone, $gone === 1 ? 'has' : 'have'
            ), true);
        }

        $allowed = 0;
        foreach ($rule['from'] as $status) {
            $allowed += (int) ($byStatus[$status] ?? 0);
        }
        if ($allowed < count($ids)) {
            // Only possible for harvest: some ticked plants are already drying
            $drying = count($ids) - $allowed;
            return $refuse(sprintf(
                '%d of the selected plants %s already drying. Untick %s, or choose Destroy or Send for %s. Nothing was changed.',
                $drying, $drying === 1 ? 'is' : 'are', $drying === 1 ? 'it' : 'them', $drying === 1 ? 'it' : 'them'
            ));
        }

        $fromList = implode(',', array_fill(0, count($rule['from']), '?'));
        $sql = "UPDATE Plants SET status = ?, date_harvested = ?"
             . ($company ? ", company_id = ?" : "")
             . " WHERE id IN ($idList) AND status IN ($fromList)";
        $params = array_merge(
            [$rule['status'], ledgerTimestamp()],
            $company ? [(int) $company['id']] : [],
            $ids,
            $rule['from']
        );
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = $stmt->rowCount();
        if ($count !== count($ids)) {
            // Can't happen under the write lock, but never half-process a batch
            throw new RuntimeException("expected to update " . count($ids) . " plants, updated $count");
        }

        $plants = $count === 1 ? '1 plant' : "$count plants";
        $message = $action === 'send'
            ? "Marked $plants as sent to {$company['name']}."
            : "Marked $plants as {$rule['status']}.";
        return ['success' => true, 'message' => $message, 'count' => $count, 'reload' => true];
    });
}

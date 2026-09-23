<?php
/**
 * Receive plants or take clones (receive_genetics.php via
 * handle_receive_genetics.php), tested by tests/test_entry_messages.php.
 */

// A runaway typo (1000000 instead of 100) can't flood the ledger. Big
// entries below this still get an "are you sure?" (settings_lib.php).
const GRACE_MAX_PLANTS_PER_ENTRY = 10000;

/**
 * Add new plants to the ledger, stamped with today's NZ time.
 *
 * @param PDO $pdo
 * @param array $input plantCount, geneticsName (the genetics id), as the form posts them
 * @return array{success: bool, message: string}
 */
function receivePlants(PDO $pdo, array $input)
{
    $stmt = $pdo->prepare("SELECT id, name FROM Genetics WHERE id = ?");
    $stmt->execute([(int) ($input['geneticsName'] ?? 0)]);
    $genetics = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$genetics) {
        return ['success' => false, 'message' => 'Please choose a genetics.'];
    }

    // Whole plants only: "2.5" used to become 3 plants
    $countText = trim((string) ($input['plantCount'] ?? ''));
    if (!preg_match('/^[0-9]+$/D', $countText) || (int) $countText < 1) {
        return ['success' => false, 'message' => 'Please enter a whole number of plants (1 or more).'];
    }
    $count = (int) $countText;
    if ($count > GRACE_MAX_PLANTS_PER_ENTRY) {
        return ['success' => false, 'message' => 'One entry can add at most ' . number_format(GRACE_MAX_PLANTS_PER_ENTRY)
            . ' plants. Split it up if you really received more.'];
    }

    // Times are NZ time; the harvest date stays blank until the plant leaves
    // (the column's old default filled it with the UTC time)
    $insert = $pdo->prepare("INSERT INTO Plants (genetics_id, status, date_created, date_harvested)
                             VALUES (?, 'Growing', ?, NULL)");
    $createdAt = ledgerTimestamp();
    for ($i = 0; $i < $count; $i++) {
        $insert->execute([$genetics['id'], $createdAt]);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM Plants WHERE genetics_id = ? AND status = 'Growing'");
    $stmt->execute([$genetics['id']]);
    $growing = (int) $stmt->fetchColumn();

    return ['success' => true, 'message' => sprintf('Added %d %s %s. You now have %d growing.',
        $count, $genetics['name'], $count === 1 ? 'plant' : 'plants', $growing)];
}

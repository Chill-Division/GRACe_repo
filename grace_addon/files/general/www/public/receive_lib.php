<?php
/**
 * Receive plants or take clones (receive_genetics.php via
 * handle_receive_genetics.php), tested by tests/test_entry_messages.php.
 */

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

    $count = (int) ($input['plantCount'] ?? 0);
    if ($count < 1) {
        return ['success' => false, 'message' => 'Please enter how many plants.'];
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

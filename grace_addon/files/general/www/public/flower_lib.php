<?php
/**
 * Dried flower ledger entries (Record dry weight change), tested by
 * tests/test_flower_stock.php.
 *
 * Everything the form sends is checked here, not just in the browser, and a
 * Subtract can never take more than is on hand (shipping manifests already
 * refused that; manual entries used to take stock below zero).
 */

// Reasons allowed for each transaction type (what the form offers)
const GRACE_FLOWER_REASONS = [
    'Add' => ['Harvest', 'Other'],
    'Subtract' => ['Testing', 'Destroy', 'Send external', 'Other'],
];

// Subtract reasons that go to another company (and the monthly report)
const GRACE_FLOWER_COMPANY_REASONS = ['Testing', 'Send external'];

/** Grams of this genetics' dried flower on hand: everything added minus everything taken out. */
function flowerOnHand(PDO $pdo, $geneticsId)
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(weight), 0) FROM Flower WHERE genetics_id = ?");
    $stmt->execute([(int) $geneticsId]);
    return round((float) $stmt->fetchColumn(), 2);
}

/**
 * What's on hand for every genetics, for the entry forms' hints
 * (get_stock_on_hand.php): growing and drying plants, and grams of flower.
 *
 * @return array<int, array{growing: int, drying: int, flower: float}> keyed by genetics id
 */
function stockByGenetics(PDO $pdo)
{
    $stock = [];
    foreach ($pdo->query("SELECT id FROM Genetics ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $stock[(int) $id] = ['growing' => 0, 'drying' => 0, 'flower' => 0.0];
    }

    $plants = $pdo->query("SELECT genetics_id,
                                  SUM(status = 'Growing') AS growing,
                                  SUM(status = 'Harvested - Drying') AS drying
                           FROM Plants GROUP BY genetics_id");
    foreach ($plants->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($stock[(int) $row['genetics_id']])) {
            $stock[(int) $row['genetics_id']]['growing'] = (int) $row['growing'];
            $stock[(int) $row['genetics_id']]['drying'] = (int) $row['drying'];
        }
    }

    $flower = $pdo->query("SELECT genetics_id, SUM(weight) AS grams FROM Flower GROUP BY genetics_id");
    foreach ($flower->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if (isset($stock[(int) $row['genetics_id']])) {
            $stock[(int) $row['genetics_id']]['flower'] = round((float) $row['grams'], 2);
        }
    }

    return $stock;
}

/** 1234.5 -> "1,234.5", 70.0 -> "70" */
function formatFlowerGrams($grams)
{
    return rtrim(rtrim(number_format((float) $grams, 2, '.', ','), '0'), '.');
}

/**
 * Record flower coming in or going out.
 *
 * @param PDO $pdo
 * @param array $input geneticsName (the genetics id), weight, transactionType,
 *                     reason, otherReason, companyId, as the form posts them
 * @return array{success: bool, message: string, onHand: float|null}
 */
function recordFlowerTransaction(PDO $pdo, array $input)
{
    $refuse = function ($message) {
        return ['success' => false, 'message' => $message, 'onHand' => null];
    };

    $stmt = $pdo->prepare("SELECT id, name FROM Genetics WHERE id = ?");
    $stmt->execute([(int) ($input['geneticsName'] ?? 0)]);
    $genetics = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$genetics) {
        return $refuse('Please choose a genetics.');
    }

    $weightText = trim((string) ($input['weight'] ?? ''));
    if (!preg_match('/^(\d+(\.\d*)?|\.\d+)$/D', $weightText) || (float) $weightText <= 0) {
        return $refuse('Please enter a weight in grams, above 0.');
    }
    // Grams to one decimal place: GRACe's users aren't GMP facilities, and
    // the Agency doesn't need hundredths of a gram (see AGENTS.md)
    if (preg_match('/\.\d{2,}$/D', rtrim($weightText, '0'))) {
        return $refuse('Weights are recorded to one decimal place, like 12.5 g.');
    }
    $weight = round((float) $weightText, 1);

    $type = (string) ($input['transactionType'] ?? '');
    if (!isset(GRACE_FLOWER_REASONS[$type])) {
        return $refuse('Please choose Add or Subtract.');
    }
    $reason = (string) ($input['reason'] ?? '');
    if (!in_array($reason, GRACE_FLOWER_REASONS[$type], true)) {
        return $refuse('Please choose a reason.');
    }

    $finalReason = $reason;
    if ($reason === 'Other') {
        $finalReason = trim((string) ($input['otherReason'] ?? ''));
        if ($finalReason === '') {
            return $refuse("Please say what the 'Other' reason is.");
        }
    }

    $company = null;
    if ($type === 'Subtract' && in_array($reason, GRACE_FLOWER_COMPANY_REASONS, true)) {
        $stmt = $pdo->prepare("SELECT id, name FROM Companies WHERE id = ?");
        $stmt->execute([(int) ($input['companyId'] ?? 0)]);
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$company) {
            return $refuse('Please select a company for Testing or Send external transactions.');
        }
    }
    $companyId = $company ? (int) $company['id'] : null;

    // The stock check and the entry happen under the write lock, so two
    // entries at once can't both take the last of the stock
    return withWriteLock($pdo, function (PDO $pdo) use ($genetics, $weight, $type, $finalReason, $company, $companyId, $refuse) {
        // Compared at 0.1 g, the precision weights are entered in, so a
        // balance with hundredths from an older version can still be cleared
        $onHand = flowerOnHand($pdo, $genetics['id']);
        if ($type === 'Subtract' && $weight > round($onHand, 1) + 0.0001) {
            return $refuse(sprintf(
                "Only %s g of %s is recorded, so %s g can't be subtracted. If more is really there, record it with Add first.",
                formatFlowerGrams($onHand), $genetics['name'], formatFlowerGrams($weight)
            ));
        }

        $stmt = $pdo->prepare("INSERT INTO Flower (genetics_id, weight, transaction_type, transaction_date, reason, company_id)
                               VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $genetics['id'],
            $type === 'Subtract' ? -$weight : $weight,
            $type,
            ledgerTimestamp(), // NZ time, the same clock the reports use
            $finalReason,
            $companyId,
        ]);

        // Say exactly what happened: "Subtracted 10 g of White Widow
        // (Testing, Lab Ltd). On hand now: 602.5 g."
        $onHandNow = flowerOnHand($pdo, $genetics['id']);
        $message = sprintf('%s %s g of %s (%s). On hand now: %s g.',
            $type === 'Add' ? 'Added' : 'Subtracted',
            formatFlowerGrams($weight),
            $genetics['name'],
            $finalReason . ($company ? ", {$company['name']}" : ''),
            formatFlowerGrams($onHandNow)
        );
        return ['success' => true, 'message' => $message, 'onHand' => $onHandNow];
    });
}

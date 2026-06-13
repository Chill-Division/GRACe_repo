<?php
/**
 * Annual stocktake calculations, shared by the report endpoints
 * (get_annual_plant_stocktake.php / get_annual_flower_stocktake.php)
 * and the regression tests (tests/test_annual_stocktake.php).
 *
 * Date handling: date_created / date_harvested / transaction_date are stored
 * as 'YYYY-MM-DD HH:MM:SS' datetimes, so all comparisons wrap them in DATE()
 * and compare against exclusive year boundaries, a plain string BETWEEN
 * '...-01-01' AND '...-12-31' silently drops anything timestamped on
 * 31 December after midnight.
 */

// Statuses that mean a plant has left stock. 'Harvested - Drying' is
// deliberately absent: drying plants count as active stock (since 0.14).
const GRACE_PLANT_DEPARTED_STATUSES = ['Sent', 'Harvested', 'Harvested - Destroyed', 'Destroyed'];

/**
 * Per-genetics plant stocktake for one calendar year.
 *
 * Start amount = plants created before 1 Jan that had not left stock before
 * 1 Jan, regardless of WHICH earlier year they left in. (Pre-0.16.1 this
 * only subtracted departures dated in the immediately-previous year, so a
 * plant destroyed two or more years ago kept appearing in the opening
 * balance forever.)
 *
 * @return array[] rows of geneticsName/startAmount/in/out/harvested/destroyed/end
 */
function computeAnnualPlantStocktake(PDO $pdo, int $year): array
{
    $startDate = sprintf('%04d-01-01', $year);       // first day of the report year
    $nextStartDate = sprintf('%04d-01-01', $year + 1); // exclusive upper bound

    $departedList = "'" . implode("','", GRACE_PLANT_DEPARTED_STATUSES) . "'";

    $genetics = $pdo->query("SELECT id, name FROM Genetics ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($genetics as $genetic) {
        // In stock at 00:00 on 1 Jan: created earlier, and either still an
        // active status, or departed during/after the report year. Departed
        // rows with no date_harvested can't be placed in time, so they're
        // treated as already gone.
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Plants
             WHERE genetics_id = :geneticsId
               AND DATE(date_created) < :startDate
               AND (
                    status NOT IN ($departedList)
                    OR (date_harvested IS NOT NULL AND DATE(date_harvested) >= :startDate)
               )"
        );
        $stmt->execute([':geneticsId' => $genetic['id'], ':startDate' => $startDate]);
        $startAmount = (int) $stmt->fetchColumn();

        // Created during the year
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Plants
             WHERE genetics_id = :geneticsId
               AND DATE(date_created) >= :startDate AND DATE(date_created) < :nextStartDate"
        );
        $stmt->execute([':geneticsId' => $genetic['id'], ':startDate' => $startDate, ':nextStartDate' => $nextStartDate]);
        $inCount = (int) $stmt->fetchColumn();

        // Sent externally during the year (Out)
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Plants
             WHERE genetics_id = :geneticsId AND status = 'Sent'
               AND date_harvested IS NOT NULL
               AND DATE(date_harvested) >= :startDate AND DATE(date_harvested) < :nextStartDate"
        );
        $stmt->execute([':geneticsId' => $genetic['id'], ':startDate' => $startDate, ':nextStartDate' => $nextStartDate]);
        $sentCount = (int) $stmt->fetchColumn();

        // Legacy 'Harvested' during the year ("Harvested - Drying" stays in stock)
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Plants
             WHERE genetics_id = :geneticsId AND status = 'Harvested'
               AND date_harvested IS NOT NULL
               AND DATE(date_harvested) >= :startDate AND DATE(date_harvested) < :nextStartDate"
        );
        $stmt->execute([':geneticsId' => $genetic['id'], ':startDate' => $startDate, ':nextStartDate' => $nextStartDate]);
        $harvestedCount = (int) $stmt->fetchColumn();

        // Destroyed during the year
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM Plants
             WHERE genetics_id = :geneticsId
               AND status IN ('Destroyed', 'Harvested - Destroyed')
               AND date_harvested IS NOT NULL
               AND DATE(date_harvested) >= :startDate AND DATE(date_harvested) < :nextStartDate"
        );
        $stmt->execute([':geneticsId' => $genetic['id'], ':startDate' => $startDate, ':nextStartDate' => $nextStartDate]);
        $destroyedCount = (int) $stmt->fetchColumn();

        $rows[] = [
            'geneticsName' => $genetic['name'],
            'startAmount' => $startAmount,
            'in' => $inCount,
            'out' => $sentCount,
            'harvested' => $harvestedCount,
            'destroyed' => $destroyedCount,
            'end' => $startAmount + $inCount - $sentCount - $harvestedCount - $destroyedCount,
        ];
    }

    return $rows;
}

/**
 * Per-genetics dried flower stocktake for one calendar year.
 * The Flower table is a signed ledger, so the opening balance is simply the
 * running sum of everything dated before 1 Jan.
 *
 * @return array[] rows of geneticsName/startWeight/in/out/destroyed/end
 */
function computeAnnualFlowerStocktake(PDO $pdo, int $year): array
{
    $startDate = sprintf('%04d-01-01', $year);
    $nextStartDate = sprintf('%04d-01-01', $year + 1);

    $genetics = $pdo->query("SELECT id, name FROM Genetics ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

    $rows = [];
    foreach ($genetics as $genetic) {
        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(weight), 0) FROM Flower
             WHERE genetics_id = :geneticsId AND DATE(transaction_date) < :startDate"
        );
        $stmt->execute([':geneticsId' => $genetic['id'], ':startDate' => $startDate]);
        $startWeight = floatval($stmt->fetchColumn());

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(weight), 0) FROM Flower
             WHERE genetics_id = :geneticsId AND transaction_type = 'Add'
               AND DATE(transaction_date) >= :startDate AND DATE(transaction_date) < :nextStartDate"
        );
        $stmt->execute([':geneticsId' => $genetic['id'], ':startDate' => $startDate, ':nextStartDate' => $nextStartDate]);
        $inWeight = floatval($stmt->fetchColumn());

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(weight), 0) FROM Flower
             WHERE genetics_id = :geneticsId AND transaction_type = 'Subtract'
               AND reason IN ('Send external', 'Testing')
               AND DATE(transaction_date) >= :startDate AND DATE(transaction_date) < :nextStartDate"
        );
        $stmt->execute([':geneticsId' => $genetic['id'], ':startDate' => $startDate, ':nextStartDate' => $nextStartDate]);
        $outWeight = floatval($stmt->fetchColumn());

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(weight), 0) FROM Flower
             WHERE genetics_id = :geneticsId AND transaction_type = 'Subtract'
               AND reason NOT IN ('Send external', 'Testing')
               AND DATE(transaction_date) >= :startDate AND DATE(transaction_date) < :nextStartDate"
        );
        $stmt->execute([':geneticsId' => $genetic['id'], ':startDate' => $startDate, ':nextStartDate' => $nextStartDate]);
        $destroyedWeight = floatval($stmt->fetchColumn());

        $rows[] = [
            'geneticsName' => $genetic['name'],
            'startWeight' => $startWeight,
            'in' => $inWeight,
            'out' => abs($outWeight),
            'destroyed' => abs($destroyedWeight),
            'end' => $startWeight + $inWeight - abs($outWeight) - abs($destroyedWeight),
        ];
    }

    return $rows;
}

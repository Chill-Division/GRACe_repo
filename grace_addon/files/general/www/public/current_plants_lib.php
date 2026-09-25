<?php
/**
 * The Current Plants report (current_plants.php via get_current_plants.php),
 * tested by tests/test_current_plants.php.
 *
 * Drying plants are still plants on site until they're sent or destroyed,
 * so they count here, just as they do in the annual stocktake.
 */

// Statuses that count as a plant on hand
const GRACE_CURRENT_PLANT_STATUSES = ['Growing', 'Harvested - Drying'];

/**
 * Plants on hand for every genetics, alphabetically, including genetics
 * with none (the page can hide those rows).
 *
 * @return array[] rows of geneticsName/plantCount
 */
function currentPlantCounts(PDO $pdo)
{
    $statusList = implode(',', array_fill(0, count(GRACE_CURRENT_PLANT_STATUSES), '?'));
    $stmt = $pdo->prepare(
        "SELECT G.name AS geneticsName, COUNT(P.id) AS plantCount
         FROM Genetics G
         LEFT JOIN Plants P ON P.genetics_id = G.id AND P.status IN ($statusList)
         GROUP BY G.id
         ORDER BY G.name ASC, G.id ASC"
    );
    $stmt->execute(GRACE_CURRENT_PLANT_STATUSES);

    $rows = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $rows[] = ['geneticsName' => $row['geneticsName'], 'plantCount' => (int) $row['plantCount']];
    }
    return $rows;
}

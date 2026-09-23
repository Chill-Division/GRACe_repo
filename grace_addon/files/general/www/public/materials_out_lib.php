<?php
/**
 * Materials out reports (the monthly report to the Medicinal Cannabis Agency).
 *
 * Shared by this_months_flower_transactions.php and
 * last_months_flower_transactions.php, their data endpoints, and
 * tests/test_report_periods.php.
 */

/**
 * One calendar month, ready to query and to label.
 *
 * @param string $yearMonth 'YYYY-MM'
 * @return array{period: string, label: string, start: string, nextStart: string}
 */
function reportMonth($yearMonth)
{
    $first = DateTimeImmutable::createFromFormat('!Y-m', $yearMonth);
    return [
        'period' => $first->format('Y-m'),        // '2026-06'
        'label' => $first->format('F Y'),         // 'June 2026'
        'start' => $first->format('Y-m-d'),       // first day of the month
        'nextStart' => $first->modify('first day of next month')->format('Y-m-d'), // exclusive end
    ];
}

/**
 * The calendar month before $today. Never use strtotime('-1 month') for this:
 * on 31 July it gives "31 June", which PHP rolls over to 1 July, so the report
 * showed July's figures under a June heading.
 *
 * @param string|null $today 'Y-m-d', defaults to today
 */
function previousReportMonth($today = null)
{
    $today = new DateTimeImmutable($today ?? 'today');
    return reportMonth($today->modify('first day of last month')->format('Y-m'));
}

/** The calendar month $today is in. */
function currentReportMonth($today = null)
{
    return reportMonth((new DateTimeImmutable($today ?? 'today'))->format('Y-m'));
}

/** A 'YYYY-MM' passed in the URL, or null if it isn't one. */
function parseReportMonth($value)
{
    if (!is_string($value) || !preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
        return null;
    }
    return reportMonth($value);
}

/**
 * Everything that went out during a month: flower subtracted for
 * 'Send external' or 'Testing', and plants sent to another company.
 * report_reminders_lib.php uses the same definitions to decide whether a
 * month needs reporting at all.
 *
 * @param array $month from reportMonth()
 * @return array{flowers: array[], plants: array[]}
 */
function getMaterialsOut(PDO $pdo, array $month)
{
    $range = [':start' => $month['start'], ':nextStart' => $month['nextStart']];

    $flowerStmt = $pdo->prepare(
        "SELECT
            G.name AS geneticsName,
            F.weight,
            F.transaction_date,
            C.name || ', ' || COALESCE(C.address, '') AS companyNameAddress
         FROM Flower F
         JOIN Genetics G ON F.genetics_id = G.id
         LEFT JOIN Companies C ON F.company_id = C.id
         WHERE F.transaction_type = 'Subtract'
           AND F.reason IN ('Send external', 'Testing')
           AND DATE(F.transaction_date) >= :start AND DATE(F.transaction_date) < :nextStart
         ORDER BY F.transaction_date DESC"
    );
    $flowerStmt->execute($range);
    $flowers = $flowerStmt->fetchAll(PDO::FETCH_ASSOC);

    // Subtractions are stored as negative weights; the report shows them positive
    foreach ($flowers as &$flower) {
        $flower['weight'] = abs($flower['weight']);
    }
    unset($flower);

    // Plants sent together share a timestamp, so each send is one row
    $plantStmt = $pdo->prepare(
        "SELECT
            G.name AS geneticsName,
            COUNT(P.id) AS plantCount,
            P.date_harvested AS transaction_date,
            C.name || ', ' || COALESCE(C.address, '') AS companyNameAddress
         FROM Plants P
         JOIN Genetics G ON P.genetics_id = G.id
         LEFT JOIN Companies C ON P.company_id = C.id
         WHERE P.status = 'Sent'
           AND DATE(P.date_harvested) >= :start AND DATE(P.date_harvested) < :nextStart
         GROUP BY G.name, C.name, P.date_harvested
         ORDER BY transaction_date DESC"
    );
    $plantStmt->execute($range);

    return [
        'flowers' => $flowers,
        'plants' => $plantStmt->fetchAll(PDO::FETCH_ASSOC),
    ];
}

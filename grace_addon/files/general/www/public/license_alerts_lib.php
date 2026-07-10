<?php
/**
 * License expiry alerts, shared by the nav banner (3-day window) and the
 * Dashboard's "License Renewals Due" list (30-day window).
 *
 * Only unacknowledged licenses are returned: once the user acknowledges an
 * expiring/expired license on the Company Licenses page, it disappears from
 * both alert surfaces (0.18.0 fixed the Dashboard ignoring the flag).
 */

/**
 * Licenses expiring within the next N days (or already expired) that the
 * user has not acknowledged, soonest expiry first.
 *
 * @param PDO $pdo
 * @param int $withinDays alert window in days from today
 * @return array[] rows of original_filename / expiry_date
 */
function getUnacknowledgedExpiringLicenses(PDO $pdo, $withinDays)
{
    $horizon = date('Y-m-d', strtotime("+{$withinDays} days"));
    $stmt = $pdo->prepare(
        "SELECT original_filename, expiry_date FROM Documents
         WHERE category = 'licenses'
           AND expiry_date IS NOT NULL
           AND expiry_date <= ?
           AND (acknowledged IS NULL OR acknowledged = 0)
         ORDER BY expiry_date ASC"
    );
    $stmt->execute([$horizon]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

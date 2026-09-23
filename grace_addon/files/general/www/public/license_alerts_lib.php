<?php
/**
 * License helpers.
 *
 * Expiry alerts, shared by the nav banner (3-day window) and the Dashboard's
 * "License Renewals Due" list (30-day window). Only unacknowledged licenses
 * are returned: once the user acknowledges an expiring/expired license on the
 * Company Licenses page, it disappears from both alert surfaces (1.0.0 fixed
 * the Dashboard ignoring the flag).
 *
 * Upload limit, shared by upload.php and the Company Licenses form: a license
 * may expire at most 15 months from today (1.1.0; it used to be 12 months,
 * which blocked early renewals). Tested by tests/test_license_expiry.php.
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

/**
 * The latest expiry date a license upload may have: 15 months from today.
 * Licenses are annual, but the Agency can issue a renewal up to 3 months
 * early, so a freshly issued renewal can expire up to 15 months from the day
 * it's uploaded. Anything later is almost certainly a typo.
 *
 * Month ends are clamped rather than overflowed: 15 months from 30 November
 * is the last day of February, not 1 or 2 March.
 *
 * @param string|null $today 'Y-m-d', injectable for tests; defaults to today
 * @return string 'Y-m-d'
 */
function licenseMaxExpiryDate($today = null)
{
    $base = new DateTimeImmutable($today ?? 'today');
    $targetMonth = $base->modify('first day of this month')->modify('+15 months');
    $day = min((int) $base->format('j'), (int) $targetMonth->format('t'));
    return $targetMonth->setDate((int) $targetMonth->format('Y'), (int) $targetMonth->format('n'), $day)->format('Y-m-d');
}

/**
 * Check a license expiry date from the upload form.
 * Already-expired dates are allowed, so old licenses can be kept on file.
 *
 * @param string $expiryDate 'Y-m-d' from the date picker
 * @param string|null $today 'Y-m-d', injectable for tests
 * @return string|null null when it's fine, otherwise a message for the user
 */
function validateLicenseExpiryDate($expiryDate, $today = null)
{
    $expiryDate = (string) $expiryDate;
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $expiryDate);
    if (!$parsed || $parsed->format('Y-m-d') !== $expiryDate) {
        return 'Please enter a valid expiry date.';
    }
    if ($expiryDate > licenseMaxExpiryDate($today)) {
        return 'The expiry date can be at most 15 months from today.';
    }
    return null;
}

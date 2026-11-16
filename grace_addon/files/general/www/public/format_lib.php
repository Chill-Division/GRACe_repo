<?php
/**
 * How GRACe shows dates on screen: day/month/year, the NZ way, everywhere
 * (1.4.0 settled on this, matching the reports sent to the Agency). Tested by
 * tests/test_recent_entries.php and tests/test_wording.php.
 */

/**
 * A stored date or date and time as 23/09/2026 or 23/09/2026 10:32.
 * Anything that isn't a date is shown as it is.
 */
function formatLedgerDateTime($value)
{
    $value = (string) $value;
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?: (\d{2}):(\d{2}))?/', $value, $m)) {
        return "$m[3]/$m[2]/$m[1]" . (isset($m[4]) ? " $m[4]:$m[5]" : '');
    }
    return $value;
}

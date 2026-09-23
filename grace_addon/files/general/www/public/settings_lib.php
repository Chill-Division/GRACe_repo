<?php
/**
 * Settings you can change in Administration (1.1.0), stored in the Settings
 * table. Tested by tests/test_entry_limits.php.
 *
 * Entry warning limits: anything bigger than these gets an extra "are you
 * sure?" before it's saved, to catch typos like an extra zero. They're a
 * warning, not a block. We recommend about half of what one flower room
 * usually holds.
 */

const GRACE_DEFAULT_LARGE_PLANT_ENTRY = 100;
const GRACE_DEFAULT_LARGE_FLOWER_ENTRY_GRAMS = 5000;
const GRACE_MAX_ENTRY_WARNING_LIMIT = 1000000;

/** A saved setting, or $default if it has never been set. */
function getSetting(PDO $pdo, $name, $default = null)
{
    $stmt = $pdo->prepare("SELECT value FROM Settings WHERE name = ?");
    $stmt->execute([$name]);
    $value = $stmt->fetchColumn();
    return $value === false ? $default : $value;
}

function setSetting(PDO $pdo, $name, $value)
{
    $pdo->prepare("INSERT INTO Settings (name, value) VALUES (?, ?)
                   ON CONFLICT(name) DO UPDATE SET value = excluded.value")
        ->execute([$name, (string) $value]);
}

/** @return array{plants: int, grams: int} */
function getEntryWarningLimits(PDO $pdo)
{
    return [
        'plants' => (int) getSetting($pdo, 'largePlantEntry', GRACE_DEFAULT_LARGE_PLANT_ENTRY),
        'grams' => (int) getSetting($pdo, 'largeFlowerEntryGrams', GRACE_DEFAULT_LARGE_FLOWER_ENTRY_GRAMS),
    ];
}

/**
 * @param array $input largePlantEntry, largeFlowerEntryGrams (whole numbers)
 * @return array{success: bool, message: string}
 */
function saveEntryWarningLimits(PDO $pdo, array $input)
{
    $limits = [
        'largePlantEntry' => 'Enter the plant limit as a whole number of plants',
        'largeFlowerEntryGrams' => 'Enter the flower limit as a whole number of grams',
    ];
    $values = [];
    foreach ($limits as $name => $message) {
        $text = trim((string) ($input[$name] ?? ''));
        if (!preg_match('/^[0-9]+$/D', $text) || (int) $text < 1 || (int) $text > GRACE_MAX_ENTRY_WARNING_LIMIT) {
            return ['success' => false, 'message' => $message . ', from 1 to ' . number_format(GRACE_MAX_ENTRY_WARNING_LIMIT) . '.'];
        }
        $values[$name] = (int) $text;
    }

    foreach ($values as $name => $value) {
        setSetting($pdo, $name, $value);
    }
    return ['success' => true, 'message' => 'Entry warning limits saved.'];
}

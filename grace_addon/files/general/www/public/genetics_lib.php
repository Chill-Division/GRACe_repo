<?php
/**
 * Adding genetics (1.1.0), shared by Add New Genetics and anywhere else a
 * genetics can be added. Tested by tests/test_genetics_duplicates.php.
 *
 * Names that differ only by capitals or spaces are the same genetics:
 * "white widow " or "WhiteWidow" must not become a second entry next to
 * "White Widow", or its plants and flower get split across two names in
 * every report.
 *
 * The add-on image has no mbstring extension, so comparisons use PCRE's
 * Unicode matching (the /u and /i modifiers) instead of lowercasing.
 */

const GRACE_GENETICS_NAME_MAX = 100;

/** Trim and collapse runs of spaces: "  Blue   Dream " is stored as "Blue Dream". */
function tidyGeneticsName($name)
{
    $tidy = preg_replace(['/^\s+|\s+$/u', '/\s+/u'], ['', ' '], (string) $name);
    return $tidy === null ? '' : $tidy; // null: not valid UTF-8
}

/** Do two names mean the same genetics (ignoring capitals and spaces)? */
function sameGeneticsName($a, $b)
{
    $a = preg_replace('/\s+/u', '', (string) $a);
    $b = preg_replace('/\s+/u', '', (string) $b);
    if ($a === null || $b === null || $a === '') {
        return false;
    }
    return preg_match('/^' . preg_quote($a, '/') . '$/iuD', $b) === 1;
}

/** The existing genetics with this name (ignoring capitals and spaces), or null. */
function findGeneticsByName(PDO $pdo, $name)
{
    foreach ($pdo->query("SELECT id, name FROM Genetics ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $genetics) {
        if (sameGeneticsName($genetics['name'], $name)) {
            return $genetics;
        }
    }
    return null;
}

/**
 * Add a genetics, unless it's already in the list.
 *
 * @return array{success: bool, message: string, id: int|null, name: string|null, duplicate: bool}
 *         For a duplicate, id and name are the existing genetics, so a form
 *         can simply select it.
 */
function addGenetics(PDO $pdo, $name)
{
    $name = tidyGeneticsName($name);
    if ($name === '') {
        return ['success' => false, 'message' => 'Please enter a genetics name.', 'id' => null, 'name' => null, 'duplicate' => false];
    }
    if (preg_match_all('/./su', $name) > GRACE_GENETICS_NAME_MAX) {
        return ['success' => false, 'message' => 'Genetics names can be at most ' . GRACE_GENETICS_NAME_MAX . ' characters.',
                'id' => null, 'name' => null, 'duplicate' => false];
    }

    // Under the write lock, so two people adding the same name at once
    // can't both get past the duplicate check
    return withWriteLock($pdo, function (PDO $pdo) use ($name) {
        $existing = findGeneticsByName($pdo, $name);
        if ($existing) {
            return ['success' => false, 'message' => "{$existing['name']} is already in your genetics list.",
                    'id' => (int) $existing['id'], 'name' => $existing['name'], 'duplicate' => true];
        }
        $pdo->prepare("INSERT INTO Genetics (name) VALUES (?)")->execute([$name]);
        return ['success' => true, 'message' => "Added $name.",
                'id' => (int) $pdo->lastInsertId(), 'name' => $name, 'duplicate' => false];
    });
}

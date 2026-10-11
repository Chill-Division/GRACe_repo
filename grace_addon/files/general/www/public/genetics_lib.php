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
 *
 * Retired genetics (1.3.0, Administration → Retire genetics, tested by
 * tests/test_retire_genetics.php) are hidden from the lists used for new
 * entries: receiving plants, recording flower and making a manifest. Reports
 * and history never change. A genetics can only be retired with nothing of
 * it left on site, and adding its name again brings it back.
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

/** The existing genetics with this name (ignoring capitals and spaces), retired or not, or null. */
function findGeneticsByName(PDO $pdo, $name)
{
    foreach ($pdo->query("SELECT id, name, retired_at FROM Genetics ORDER BY id")->fetchAll(PDO::FETCH_ASSOC) as $genetics) {
        if (sameGeneticsName($genetics['name'], $name)) {
            return $genetics;
        }
    }
    return null;
}

/**
 * Add a genetics, unless it's already in the list. A retired one with the
 * same name is brought back instead (say another cut came in), keeping its
 * history together.
 *
 * @return array{success: bool, message: string, id: int|null, name: string|null, duplicate: bool}
 *         For a duplicate, id and name are the existing genetics, so a form
 *         can simply select it. A genetics brought back counts as a success.
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
        if ($existing && $existing['retired_at'] !== null) {
            $pdo->prepare("UPDATE Genetics SET retired_at = NULL WHERE id = ?")->execute([(int) $existing['id']]);
            return ['success' => true, 'message' => "{$existing['name']} was retired, so it's been brought back into your lists.",
                    'id' => (int) $existing['id'], 'name' => $existing['name'], 'duplicate' => false];
        }
        if ($existing) {
            return ['success' => false, 'message' => "{$existing['name']} is already in your genetics list.",
                    'id' => (int) $existing['id'], 'name' => $existing['name'], 'duplicate' => true];
        }
        $pdo->prepare("INSERT INTO Genetics (name) VALUES (?)")->execute([$name]);
        return ['success' => true, 'message' => "Added $name.",
                'id' => (int) $pdo->lastInsertId(), 'name' => $name, 'duplicate' => false];
    });
}

/** Genetics in use (not retired), alphabetically: the lists for new entries. */
function activeGenetics(PDO $pdo)
{
    return $pdo->query("SELECT id, name FROM Genetics WHERE retired_at IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * The genetics a new entry (plants received, flower recorded, a manifest)
 * was made for, checked server-side: it has to exist and not be retired,
 * in case a form was opened before it was retired.
 *
 * @return array{genetics: array|null, error: string|null}
 */
function geneticsForNewEntry(PDO $pdo, $id)
{
    $stmt = $pdo->prepare("SELECT id, name, retired_at FROM Genetics WHERE id = ?");
    $stmt->execute([(int) $id]);
    $genetics = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$genetics) {
        return ['genetics' => null, 'error' => 'Please choose a genetics.'];
    }
    if ($genetics['retired_at'] !== null) {
        return ['genetics' => null, 'error' => "{$genetics['name']} is retired. Bring it back in Administration → Retire genetics first."];
    }
    return ['genetics' => ['id' => (int) $genetics['id'], 'name' => $genetics['name']], 'error' => null];
}

/**
 * What of a genetics is still on site: plants growing or drying, and grams
 * of dried flower.
 *
 * @return array{growing: int, drying: int, flower: float}
 */
function geneticsOnHand(PDO $pdo, $id)
{
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(status = 'Growing'), 0), COALESCE(SUM(status = 'Harvested - Drying'), 0)
                           FROM Plants WHERE genetics_id = ?");
    $stmt->execute([(int) $id]);
    [$growing, $drying] = $stmt->fetch(PDO::FETCH_NUM);
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(weight), 0) FROM Flower WHERE genetics_id = ?");
    $stmt->execute([(int) $id]);
    return ['growing' => (int) $growing, 'drying' => (int) $drying, 'flower' => round((float) $stmt->fetchColumn(), 2)];
}

/**
 * "2 plants growing, 1 drying and 12.5 g of flower", or null when nothing
 * is left.
 *
 * Flower is entered to 0.1 g, so a balance within 0.05 g of zero counts as
 * none. Only an older version's hundredths can leave one, and 0.1 g steps
 * can always bring it that close (12.45 g less 12.5 g leaves -0.05 g).
 */
function describeOnHand(array $onHand)
{
    $parts = [];
    if ($onHand['growing'] > 0) {
        $parts[] = $onHand['growing'] . ($onHand['growing'] === 1 ? ' plant growing' : ' plants growing');
    }
    if ($onHand['drying'] > 0) {
        $parts[] = $onHand['drying'] . ($parts ? ' drying' : ($onHand['drying'] === 1 ? ' plant drying' : ' plants drying'));
    }
    $grams = (float) $onHand['flower'];
    $gramsText = rtrim(rtrim(number_format(round($grams, 1), 1, '.', ','), '0'), '.'); // 1234.5 -> "1,234.5", 70.0 -> "70"
    if ($grams > 0.0501) {
        $parts[] = "$gramsText g of flower";
    } elseif ($grams < -0.0501) {
        // Only from entries made before GRACe refused to go below zero
        $parts[] = "a flower balance of $gramsText g";
    }
    if (!$parts) {
        return null;
    }
    $last = array_pop($parts);
    return $parts ? implode(', ', $parts) . ' and ' . $last : $last;
}

/**
 * Retire a genetics: hide it from new entries. Only with nothing of it left
 * on site. Its records and every report stay exactly as they are.
 *
 * @return array{success: bool, message: string}
 */
function retireGenetics(PDO $pdo, $id)
{
    return withWriteLock($pdo, function (PDO $pdo) use ($id) {
        $stmt = $pdo->prepare("SELECT id, name, retired_at FROM Genetics WHERE id = ?");
        $stmt->execute([(int) $id]);
        $genetics = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$genetics) {
            return ['success' => false, 'message' => 'That genetics could not be found. Reload the page and try again.'];
        }
        if ($genetics['retired_at'] !== null) {
            return ['success' => true, 'message' => "{$genetics['name']} is already retired."];
        }
        $left = describeOnHand(geneticsOnHand($pdo, $genetics['id']));
        if ($left !== null) {
            return ['success' => false, 'message' => "{$genetics['name']} still has $left, so it can't be retired yet."];
        }
        $pdo->prepare("UPDATE Genetics SET retired_at = ? WHERE id = ?")->execute([ledgerTimestamp(), (int) $genetics['id']]);
        return ['success' => true, 'message' => "Retired {$genetics['name']}. It won't show when adding plants, recording flower or making a manifest."];
    });
}

/**
 * Bring a retired genetics back into the lists.
 *
 * @return array{success: bool, message: string}
 */
function unretireGenetics(PDO $pdo, $id)
{
    $stmt = $pdo->prepare("SELECT id, name, retired_at FROM Genetics WHERE id = ?");
    $stmt->execute([(int) $id]);
    $genetics = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$genetics) {
        return ['success' => false, 'message' => 'That genetics could not be found. Reload the page and try again.'];
    }
    if ($genetics['retired_at'] === null) {
        return ['success' => true, 'message' => "{$genetics['name']} is already in your lists."];
    }
    $pdo->prepare("UPDATE Genetics SET retired_at = NULL WHERE id = ?")->execute([(int) $genetics['id']]);
    return ['success' => true, 'message' => "Brought back {$genetics['name']}. It shows in your lists again."];
}

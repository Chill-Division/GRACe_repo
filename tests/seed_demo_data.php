<?php
/**
 * seed_demo_data.php — populate a local dev database with realistic demo data.
 *
 * Usage (from the repo root):
 *   php tests/seed_demo_data.php           # seeds an empty database
 *   php tests/seed_demo_data.php --force   # wipes /data/grace.db first, then seeds
 *
 * Requires the persistent dirs used by the app: /data and /data/uploads
 * (see DEVELOPMENT.md). Never run this against a real production database —
 * it refuses to touch a DB that already has company info unless --force is given.
 */

$publicDir = __DIR__ . '/../grace_addon/files/general/www/public';
$dbPath = '/data/grace.db';
$uploadDir = '/data/uploads/';

$force = in_array('--force', $argv ?? [], true);

if (!is_dir('/data') || !is_writable('/data')) {
    fwrite(STDERR, "ERROR: /data does not exist or is not writable.\n");
    fwrite(STDERR, "Create it first:  sudo mkdir -p /data/uploads && sudo chown -R $(whoami) /data\n");
    exit(1);
}

if ($force && file_exists($dbPath)) {
    unlink($dbPath);
    echo "Removed existing $dbPath\n";
}

require_once $publicDir . '/init_db.php';
$pdo = initializeDatabase();

// Safety check: don't seed on top of real data
$count = (int) $pdo->query("SELECT COUNT(*) FROM OwnCompany")->fetchColumn();
if ($count > 0) {
    fwrite(STDERR, "ERROR: database already has company data. Re-run with --force to wipe and reseed.\n");
    exit(1);
}

/** Build a minimal but valid one-page PDF so seeded documents actually open. */
function buildDemoPdf($title)
{
    $text = '(GRACe demo document: ' . str_replace(['\\', '(', ')'], '', $title) . ')';
    $objects = [
        "<</Type /Catalog /Pages 2 0 R>>",
        "<</Type /Pages /Kids [3 0 R] /Count 1>>",
        "<</Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources <</Font <</F1 5 0 R>>>>>>",
        null, // content stream, built below
        "<</Type /Font /Subtype /Type1 /BaseFont /Helvetica>>",
    ];
    $stream = "BT /F1 16 Tf 72 720 Td $text Tj ET";
    $objects[3] = "<</Length " . strlen($stream) . ">>\nstream\n$stream\nendstream";

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $body) {
        $offsets[$i + 1] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n$body\nendobj\n";
    }
    $xrefPos = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $off) {
        $pdf .= sprintf("%010d 00000 n \n", $off);
    }
    $pdf .= "trailer\n<</Size " . (count($objects) + 1) . " /Root 1 0 R>>\nstartxref\n$xrefPos\n%%EOF";
    return $pdf;
}

/** Insert a document row + matching file on disk so download links work. */
function seedDocument($pdo, $uploadDir, $category, $originalName, $daysAgoUploaded, $expiryDate = null)
{
    $uniqueName = uniqid('demo_', true) . '.pdf';
    $dir = $uploadDir . $category;
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($dir . '/' . $uniqueName, buildDemoPdf($originalName));

    $stmt = $pdo->prepare("INSERT INTO Documents (category, original_filename, unique_filename, upload_date, expiry_date, acknowledged)
                           VALUES (?, ?, ?, datetime('now', ?), ?, 0)");
    $stmt->execute([$category, $originalName, $uniqueName, "-$daysAgoUploaded days", $expiryDate]);
}

$pdo->beginTransaction();

// --- Own company -----------------------------------------------------------
$pdo->exec("INSERT INTO OwnCompany (company_name, company_license_number, company_address, primary_contact_email)
            VALUES ('Demo Cultivation Co', 'MCA-2026-0042', '123 Greenhouse Road, Nelson 7010', 'grower@demo.example')");

// --- External companies ----------------------------------------------------
$pdo->exec("INSERT INTO Companies (name, license_number, address, primary_contact_name, primary_contact_email, primary_contact_phone) VALUES
            ('Kiwi Cannabis Labs', 'MCA-LAB-117', '8 Science Drive, Wellington 6011', 'Tessa Trichome', 'lab@demo.example', '04 555 0117'),
            ('Aotearoa Offtake Partners', 'MCA-2026-0099', '45 Harbour View, Auckland 1010', 'Bud Buyer', 'buyer@demo.example', '09 555 0099'),
            ('South Island Genetics', 'MCA-2026-0007', '2 Alpine Way, Christchurch 8011', 'Clive Clone', 'genetics@demo.example', '03 555 0007')");

// --- Genetics ----------------------------------------------------------------
$pdo->exec("INSERT INTO Genetics (name, breeder, genetic_lineage) VALUES
            ('Northern Lights', 'Sensi Seeds', 'Afghani x Thai'),
            ('White Widow', 'Green House Seeds', 'Brazilian x South Indian'),
            ('GG4', 'GG Strains', 'Chem Sis x Sour Dubb x Chocolate Diesel'),
            ('Wedding Cake', 'Seed Junky', 'Triangle Kush x Animal Mints'),
            ('Aotearoa Haze', 'Local', 'NZ landrace cross')");

// --- Plants in a spread of statuses and ages -------------------------------
$plantRows = [
    // [genetics_id, status, created_days_ago, harvested_days_ago|null, company_id|null]
    [1, 'Growing', 95, null, null],   // mother
    [1, 'Growing', 34, null, null],
    [1, 'Growing', 34, null, null],
    [1, 'Growing', 34, null, null],
    [2, 'Growing', 21, null, null],
    [2, 'Growing', 21, null, null],
    [3, 'Growing', 60, null, null],
    [4, 'Growing', 12, null, null],
    [4, 'Growing', 12, null, null],
    [5, 'Growing', 7, null, null],
    [1, 'Harvested - Drying', 110, 6, null],
    [2, 'Harvested - Drying', 105, 4, null],
    [3, 'Harvested - Drying', 102, 2, null],
    [1, 'Harvested - Destroyed', 140, 45, null],
    [5, 'Destroyed', 30, 9, null],
    [3, 'Sent', 120, 8, 3],           // sent this month (if today >= 8th; harmless either way)
    [4, 'Sent', 130, 40, 2],          // sent last month
];
$stmt = $pdo->prepare("INSERT INTO Plants (genetics_id, status, date_created, date_harvested, company_id)
                       VALUES (?, ?, date('now', ?), CASE WHEN ? IS NULL THEN NULL ELSE date('now', ?) END, ?)");
foreach ($plantRows as $p) {
    $harvestOffset = $p[3] === null ? null : "-{$p[3]} days";
    $stmt->execute([$p[0], $p[1], "-{$p[2]} days", $harvestOffset, $harvestOffset, $p[4]]);
}

// --- Flower (dried inventory ledger) ----------------------------------------
$flowerRows = [
    // [genetics_id, weight, type, reason, days_ago, company_id|null]
    [1, 612.50, 'Add', 'Harvest', 40, null],
    [2, 480.00, 'Add', 'Harvest', 35, null],
    [3, 295.25, 'Add', 'Harvest', 18, null],
    [1, -25.00, 'Subtract', 'Testing', 33, 1],     // last month-ish
    [1, -150.00, 'Subtract', 'Send external', 31, 2],
    [2, -10.00, 'Subtract', 'Testing', 5, 1],      // this month
    [2, -200.00, 'Subtract', 'Send external', 3, 2],
    [3, -15.50, 'Subtract', 'Destroy', 2, null],
];
$stmt = $pdo->prepare("INSERT INTO Flower (genetics_id, weight, transaction_type, reason, transaction_date, company_id)
                       VALUES (?, ?, ?, ?, datetime('now', ?), ?)");
foreach ($flowerRows as $f) {
    $stmt->execute([$f[0], $f[1], $f[2], $f[3], "-{$f[4]} days", $f[5]]);
}

// --- Documents (with real files so downloads work) --------------------------
seedDocument($pdo, $uploadDir, 'licenses', 'cultivation-license-2026.pdf', 320, date('Y-m-d', strtotime('+10 days')));  // triggers the expiry banner
seedDocument($pdo, $uploadDir, 'licenses', 'supply-license-2026.pdf', 200, date('Y-m-d', strtotime('+9 months')));
seedDocument($pdo, $uploadDir, 'sops', 'SOP-pest-management.pdf', 90);
seedDocument($pdo, $uploadDir, 'sops', 'SOP-harvest-procedure.pdf', 60);
seedDocument($pdo, $uploadDir, 'offtakes', 'offtake-agreement-aotearoa.pdf', 150);
seedDocument($pdo, $uploadDir, 'other_records', 'police-vet-check-grower.pdf', 45);
seedDocument($pdo, $uploadDir, 'coc', 'coc-shipment-2026-05.pdf', 12);

$pdo->commit();

echo "Demo data seeded into $dbPath\n";
echo "  - 1 own company, 3 external companies, 5 genetics\n";
echo "  - " . count($plantRows) . " plants (growing / drying / destroyed / sent)\n";
echo "  - " . count($flowerRows) . " flower ledger entries\n";
echo "  - 7 documents with downloadable demo PDFs in {$uploadDir}\n";
echo "  - 1 license expiring in ~10 days (exercises the expiry banner + dashboard warning)\n";

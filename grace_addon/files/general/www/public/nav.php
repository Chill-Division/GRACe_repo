<?php
// Work out which top-level section the current page belongs to, for nav highlighting
$navCurrent = basename($_SERVER['SCRIPT_NAME'] ?? '', '.php');
$navSections = [
    'dashboard.php' => [
        'label' => 'Dashboard',
        'pages' => ['dashboard'],
    ],
    'tracking.php' => [
        'label' => 'Plant Tracking',
        'pages' => ['tracking', 'list_all_genetics', 'receive_genetics', 'harvest_plants', 'record_dry_weight', 'generate_shipping_manifest', 'complete_manifest', 'manifest_summary'],
    ],
    'reporting.php' => [
        'label' => 'Reporting',
        'pages' => ['reporting', 'current_dried_flower', 'current_plants', 'this_months_flower_transactions', 'last_months_flower_transactions', 'annual_stocktake'],
    ],
    'administration.php' => [
        'label' => 'Administration',
        'pages' => ['administration', 'add_verified_company', 'verified_companies', 'edit_verified_company', 'add_new_genetics', 'police_vet_check_records', 'sops', 'offtake_agreements', 'company_licenses', 'chain_of_custody_documents', 'own_company', 'admin_migrate_harvested'],
    ],
];
?>
        <nav class="app-nav">
            <a class="nav-brand" href="dashboard.php">
                <span class="nav-brand-mark" aria-hidden="true">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg>
                </span>
                <span class="nav-brand-text">
                    <strong>GRACe</strong>
                    <span class="nav-brand-sub">by Chill Division <small>v1.0.0</small></span>
                </span>
            </a>
            <input type="checkbox" id="nav-toggle" class="nav-toggle">
            <label for="nav-toggle" class="nav-toggle-label" aria-label="Toggle menu">
                <span class="hamburger"></span>
            </label>
            <ul class="nav-menu">
                <?php foreach ($navSections as $href => $section):
                    $isActive = in_array($navCurrent, $section['pages'], true); ?>
                <li><a href="<?php echo $href; ?>"<?php echo $isActive ? ' class="active" aria-current="page"' : ''; ?>><?php echo $section['label']; ?></a></li>
                <?php endforeach; ?>
                <li><a href="#" id="theme_switcher" class="theme-toggle" role="button" aria-label="Toggle light/dark theme"></a></li>
            </ul>
        </nav>
        <?php
        require_once 'init_db.php';
        require_once 'license_alerts_lib.php';
        try {
            $pdo_nav = initializeDatabase();
            // Banner for licenses expiring within 72 hours (or expired) that
            // haven't been acknowledged on the Company Licenses page
            $expiringDocs = getUnacknowledgedExpiringLicenses($pdo_nav, 3);

            if ($expiringDocs) {
                echo '<div class="license-alert" role="alert">';
                foreach ($expiringDocs as $doc) {
                    $fName = htmlspecialchars($doc['original_filename']);
                    $uDate = htmlspecialchars($doc['expiry_date']);
                    echo "<div><strong>Alert:</strong> License '$fName' is expiring soon or expired (Date: $uDate). Please renew. <a href='company_licenses.php'>Go to Licenses</a></div>";
                }
                echo '</div>';
            }
        } catch (Exception $e) {
            // checking db quiet fail in nav
        }
        ?>

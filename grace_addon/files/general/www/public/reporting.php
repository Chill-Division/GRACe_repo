<?php
$pageTitle = 'GRACe - Reporting';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Reporting</h1>
            <p>Inventory snapshots and pre-formatted reports, ready to send to the Medicinal Cannabis Agency.</p>
        </hgroup>

        <section class="hub-section">
            <h2>Inventory Reports</h2>
            <div class="card-grid">
                <a class="nav-card" href="current_dried_flower.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z"/><path d="M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Current Dried Flower</strong>
                        <small>Total dried flower weight on hand, by genetics</small>
                    </span>
                </a>
                <a class="nav-card" href="current_plants.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8Z"/><path d="M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2Z"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Current Plants</strong>
                        <small>Live plant counts on hand, by genetics</small>
                    </span>
                </a>
            </div>
        </section>

        <section class="hub-section">
            <h2>Transaction Reports</h2>
            <p>These are pre-formatted and ready to send to the Agency.</p>
            <div class="card-grid">
                <a class="nav-card" href="this_months_flower_transactions.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg></span>
                    <span class="nav-card-body">
                        <strong>This month's materials out</strong>
                        <small>Flower and plants sent out so far this month</small>
                    </span>
                </a>
                <a class="nav-card" href="last_months_flower_transactions.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Last month's materials out</strong>
                        <small>Flower and plants sent out in the previous month</small>
                    </span>
                </a>
                <a class="nav-card" href="annual_stocktake.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="3" width="20" height="5" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8"/><path d="M10 12h4"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Annual stocktake</strong>
                        <small>Full year in/out reconciliation for plants and flower</small>
                    </span>
                </a>
            </div>
        </section>

    </main>

<?php require 'footer.php'; ?>

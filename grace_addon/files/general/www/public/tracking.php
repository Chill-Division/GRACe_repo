<?php
$pageTitle = 'GRACe - Plant Tracking';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Plant Tracking</h1>
            <p>Day-to-day plant and dried flower movements — every change in your ledger starts here.</p>
        </hgroup>

        <section class="hub-section">
            <h2>Plant / Product Management</h2>
            <div class="card-grid">
                <a class="nav-card" href="list_all_genetics.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="M12 11h4"/><path d="M12 16h4"/><path d="M8 11h.01"/><path d="M8 16h.01"/></svg></span>
                    <span class="nav-card-body">
                        <strong>List all plants</strong>
                        <small>View current and historical plants, live / harvested / destroyed</small>
                    </span>
                </a>
                <a class="nav-card" href="receive_genetics.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.7-2 .4-3.5.4-4.8-.3-1.2-.6-2.3-1.9-3-4.2 2.8-.5 4.4 0 5.5.8Z"/><path d="M14.1 6a7 7 0 0 0-1.1 4c1.9-.1 3.3-.6 4.3-1.4 1-1 1.6-2.3 1.7-4.6-2.7.1-4 1-4.9 2Z"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Receive plants or take clones</strong>
                        <small>Newly added plants, such as from a license holder, Form D declaration, or clones taken from a mother plant</small>
                    </span>
                </a>
                <a class="nav-card" href="harvest_plants.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="6" cy="6" r="3"/><circle cx="6" cy="18" r="3"/><path d="M20 4 8.12 15.88"/><path d="M14.47 14.48 20 20"/><path d="M8.12 8.12 12 12"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Harvest / Destroy / Send plants</strong>
                        <small>Process whole plants out of the grow — harvest for drying, destroy, or send externally</small>
                    </span>
                </a>
                <a class="nav-card" href="record_dry_weight.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m16 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="m2 16 3-8 3 8c-.87.65-1.92 1-3 1s-2.13-.35-3-1Z"/><path d="M7 21h10"/><path d="M12 3v18"/><path d="M3 7h2c2 0 5-1 7-2 2 1 5 2 7 2h2"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Record dry weight change</strong>
                        <small>All dry weight flower changes, such as sending for testing, destruction, or harvesting into inventory</small>
                    </span>
                </a>
            </div>
        </section>

        <section class="hub-section">
            <h2>Shipping</h2>
            <div class="card-grid">
                <a class="nav-card" href="generate_shipping_manifest.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/><path d="M15 18H9"/><path d="M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.624l-3.48-4.35A1 1 0 0 0 17.52 8H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Generate Shipping Manifest</strong>
                        <small>Create a manifest PDF for plants or flower leaving your site</small>
                    </span>
                </a>
                <div class="nav-card is-disabled" aria-disabled="true">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Amend / Complete Manifest</strong>
                        <small>Update or finalise a manifest already in transit</small>
                        <span class="badge-soon">Coming soon</span>
                    </span>
                </div>
            </div>
        </section>

        <section class="hub-section">
            <h2>Recalls</h2>
            <div class="card-grid">
                <div class="nav-card is-disabled" aria-disabled="true">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Initiate Recall</strong>
                        <small>Start a product recall and notify affected parties</small>
                        <span class="badge-soon">Coming soon</span>
                    </span>
                </div>
            </div>
        </section>

    </main>

<?php require 'footer.php'; ?>

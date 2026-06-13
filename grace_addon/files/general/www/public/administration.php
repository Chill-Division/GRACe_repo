<?php
$pageTitle = 'GRACe - Administration';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Administration</h1>
            <p>Set up contacts, genetics, compliance records, and your company details.</p>
        </hgroup>

        <section class="hub-section">
            <h2>Record Management</h2>
            <div class="card-grid">
                <a class="nav-card" href="chain_of_custody_documents.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 17H7A5 5 0 0 1 7 7h2"/><path d="M15 7h2a5 5 0 1 1 0 10h-2"/><path d="M8 12h8"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Chain of Custody Documents</strong>
                        <small>Store signed CoC paperwork for every transfer</small>
                    </span>
                </a>
                <a class="nav-card" href="company_licenses.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="m9 15 2 2 4-4"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Company Licenses</strong>
                        <small>Upload licenses with expiry tracking and renewal alerts</small>
                    </span>
                </a>
                <a class="nav-card" href="sops.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Manage SOPs</strong>
                        <small>Upload and track your Standard Operating Procedures</small>
                    </span>
                </a>
                <a class="nav-card" href="police_vet_check_records.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1 1 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1Z"/><path d="m9 12 2 2 4-4"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Police Vet Check Records</strong>
                        <small>Store vetting records for staff and key personnel</small>
                    </span>
                </a>
                <a class="nav-card" href="offtake_agreements.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M16 13H8"/><path d="M16 17H8"/><path d="M10 9H8"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Offtake Agreements</strong>
                        <small>Keep signed buyer agreements on file</small>
                    </span>
                </a>
            </div>
        </section>

        <section class="hub-section">
            <h2>Contact Management</h2>
            <div class="card-grid">
                <a class="nav-card" href="add_verified_company.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 22V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v18Z"/><path d="M6 12H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h2"/><path d="M18 9h2a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2h-2"/><path d="M10 6h4"/><path d="M10 10h4"/><path d="M10 14h4"/><path d="M10 18h4"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Add Verified Company</strong>
                        <small>Add a company you'll send flower / plants to, such as an offtake buyer or testing lab</small>
                    </span>
                </a>
            </div>
        </section>

        <section class="hub-section">
            <h2>Genetics Management</h2>
            <div class="card-grid">
                <a class="nav-card" href="add_new_genetics.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/><path d="M12 8v8"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Add New Genetics</strong>
                        <small>Any genetics you'll have as either plants or flower</small>
                    </span>
                </a>
            </div>
        </section>

        <section class="hub-section">
            <h2>System Management</h2>
            <div class="card-grid">
                <a class="nav-card" href="own_company.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Update company information</strong>
                        <small>Enter your own company information, used to populate CoC docs and Agency emails</small>
                    </span>
                </a>
                <a class="nav-card" href="show_database.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Download backup</strong>
                        <small>Download the full ledger as a timestamped JSON file for backup or audit</small>
                    </span>
                </a>
                <a class="nav-card" href="admin_migrate_harvested.php">
                    <span class="nav-card-icon" aria-hidden="true"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg></span>
                    <span class="nav-card-body">
                        <strong>Migrate Legacy Harvest Status</strong>
                        <small>Update old "Harvested" records to "Harvested - Destroyed"</small>
                    </span>
                </a>
            </div>
        </section>
    </main>

<?php require 'footer.php'; ?>

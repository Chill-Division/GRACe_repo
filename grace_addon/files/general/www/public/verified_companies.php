<?php
require_once 'init_db.php';

$pdo = initializeDatabase();
$companies = $pdo->query("SELECT id, name, license_number, address, primary_contact_name, primary_contact_email, primary_contact_phone
                          FROM Companies ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'GRACe - Verified Companies';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Verified Companies</h1>
            <p>Every company you send flower or plants to. Select one to update its license number, address, or contact details. Companies can never be deleted, because your ledger and Chain of Custody history reference them.</p>
        </hgroup>

        <div class="toolbar">
            <a href="add_verified_company.php" role="button" class="button">Add Verified Company</a>
        </div>

        <figure class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>License #</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$companies): ?>
                    <tr><td colspan="6">No verified companies yet. Add your first one above.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($companies as $company): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($company['name']); ?></td>
                        <td><?php echo htmlspecialchars($company['license_number']); ?></td>
                        <td><?php echo htmlspecialchars($company['primary_contact_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($company['primary_contact_email'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($company['primary_contact_phone'] ?? ''); ?></td>
                        <td><a href="edit_verified_company.php?id=<?php echo (int) $company['id']; ?>">Edit</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </figure>
    </main>

<?php require 'footer.php'; ?>

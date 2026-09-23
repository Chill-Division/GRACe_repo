<?php
require_once 'init_db.php';

// Fetch existing company information
$companyInfo = null;

// Establish PDO connection
$pdo = initializeDatabase();

try {
    $sql = "SELECT * FROM OwnCompany LIMIT 1";
    $stmt = $pdo->query($sql);
    $companyInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    echo "Error fetching company information: " . htmlspecialchars($e->getMessage());
}

// A refused save comes back here with the reason and what was typed
$error = isset($_GET['error']) ? (string) $_GET['error'] : '';
$typed = $error !== '' ? json_decode((string) ($_GET['data'] ?? ''), true) : null;
if (is_array($typed)) {
    $companyInfo = [
        'company_name' => (string) ($typed['companyName'] ?? ''),
        'company_license_number' => (string) ($typed['companyLicense'] ?? ''),
        'company_address' => (string) ($typed['companyAddress'] ?? ''),
        'primary_contact_email' => (string) ($typed['primaryContactEmail'] ?? ''),
    ];
}

$pageTitle = 'GRACe - Your Company Information';
require 'header.php';
?>

    <main class="container">
        <?php if ($error !== ''): ?>
        <div class="status-message error" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <hgroup class="page-header">
            <h1>Your Company Information</h1>
            <p>Enter your own company information. This is used to generate information for emailing to the Agency, as well as Chain of Custody documents.</p>
        </hgroup>

        <article class="form-card">
            <form id="companyInfoForm" action="process_own_company.php" method="post" class="form">
                <label for="companyName">Company Name:</label>
                <input type="text" id="companyName" name="companyName" class="input" required value="<?php echo htmlspecialchars($companyInfo['company_name'] ?? ''); ?>">

                <label for="companyLicense">Company License #:</label>
                <input type="text" id="companyLicense" name="companyLicense" class="input" required value="<?php echo htmlspecialchars($companyInfo['company_license_number'] ?? ''); ?>">

                <label for="companyAddress">Company Address:</label>
                <textarea id="companyAddress" name="companyAddress" class="input" required><?php echo htmlspecialchars($companyInfo['company_address'] ?? ''); ?></textarea>

                <label for="primaryContactEmail">Primary Contact Email:</label>
                <input type="email" id="primaryContactEmail" name="primaryContactEmail" class="input" required value="<?php echo htmlspecialchars($companyInfo['primary_contact_email'] ?? ''); ?>">

                <button type="submit" class="button">Save</button>
            </form>
        </article>
    </main>
<?php require 'footer.php'; ?>

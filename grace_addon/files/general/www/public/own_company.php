<?php
require_once 'auth.php';
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
?>

<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css">
    <title>Own Company Information</title>
</head>
<body>
    <header class="container-fluid">
        <?php require_once 'nav.php'; ?>
    </header>
    <main class="container">
        <article class="" style="width: 60%;margin: auto;">
            <h2>Your Company Information</h2>

            <p><small>Enter your own company information. This is used to generate information for emailing to the Agency, as well as Chain of Custody documents.</small></p>

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
    <script src="js/growcart.js"></script>
</body>
</html>

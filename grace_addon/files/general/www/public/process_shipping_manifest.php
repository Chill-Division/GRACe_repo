<?php
require_once 'tcpdf/tcpdf.php'; // Ensure path to TCPDF is correct
require_once 'init_db.php'; // Initialize PDO and connect to your SQLite database

// Capture form data
$sendingCompany = $_POST['sendingChoice'] === 'us' ? 'Our Company Name' : $_POST['companyName'];
$sendingEmail = $_POST['contactEmail'];
$receivingCompany = $_POST['receivingChoice'] === 'us' ? 'Our Company Name' : $_POST['companyName'];
$productType = $_POST['productType'];
$quantity = $_POST['quantity'];
$geneticsName = $_POST['geneticsName'];
$datePrepared = date('Y-m-d H:i:s');

// HTML content for the manifest
$htmlContent = <<<EOD
<h1>Shipping Manifest</h1>
<h2>Sending Party</h2>
<ul>
    <li>Company Name: $sendingCompany</li>
    <li>Email: $sendingEmail</li>
    <li>Date Prepared: $datePrepared</li>
    <li>Product: $productType</li>
    <li>Genetics: $geneticsName</li>
    <li>Quantity/Weight: $quantity</li>
</ul>
<h2>Receiving Party</h2>
<ul>
    <li>Company Name: $receivingCompany</li>
</ul>
<p>Please scan/photo upon receipt of goods and send a copy to $sendingEmail</p>
EOD;

// Display on screen
echo $htmlContent;

// Generate PDF
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->AddPage();
$pdf->writeHTML($htmlContent, true, false, true, false, '');
$pdfOutput = $pdf->Output('shipping_manifest.pdf', 'S'); // 'S' returns the PDF as a string

// Offer download option
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shipping Manifest</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
</head>
<body>
    <main class="container">
        <h1>Your Shipping Manifest</h1>
        <div><?php echo $htmlContent; ?></div>
        <form method="post" action="download_pdf.php">
            <input type="hidden" name="pdfContent" value="<?php echo base64_encode($pdfOutput); ?>">
            <button type="submit" class="button">Download as PDF</button>
        </form>
    </main>
</body>
</html>

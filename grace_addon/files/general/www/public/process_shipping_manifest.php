<?php
session_start();
require_once 'tcpdf/tcpdf.php'; // Ensure path to TCPDF is correct
require_once 'init_db.php'; // Initialize PDO and connect to your SQLite database

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['formData'] = $_POST;
    $sendingChoice = $_POST['sendingChoice'];
    $sendingCompany = $_POST['sendingChoice'] === 'us' ? 'Our Company Name' : $_POST['companyName'];
    $sendingLicense = $_POST['sendingChoice'] === 'us' ? 'Our License Number' : $_POST['licenseNumber'];
    $sendingEmail = $_POST['contactEmail'];
    $receivingChoice = $_POST['receivingChoice'];
    $receivingCompany = $_POST['receivingChoice'] === 'us' ? 'Our Company Name' : $_POST['companyName'];
    $receivingLicense = $_POST['receivingChoice'] === 'us' ? 'Our License Number' : $_POST['licenseNumber'];
    $productType = $_POST['productType'];
    $quantity = $_POST['quantity'];
    $address = $_POST['address'];
    $geneticsName = $_POST['geneticsName'];
    $datePrepared = date('Y-m-d H:i:s');
} elseif (isset($_SESSION['formData'])) {
    $formData = $_SESSION['formData'];
    $sendingChoice = $formData['sendingChoice'];
    $sendingCompany = $formData['sendingChoice'] === 'us' ? 'Our Company Name' : $formData['companyName'];
    $sendingLicense = $formData['sendingChoice'] === 'us' ? 'Our License Number' : $formData['licenseNumber'];
    $sendingEmail = $formData['contactEmail'];
    $receivingChoice = $formData['receivingChoice'];
    $receivingCompany = $formData['receivingChoice'] === 'us' ? 'Our Company Name' : $formData['companyName'];
    $receivingLicense = $formData['receivingChoice'] === 'us' ? 'Our License Number' : $formData['licenseNumber'];
    $productType = $formData['productType'];
    $quantity = $formData['quantity'];
    $address = $formData['address'];
    $geneticsName = $formData['geneticsName'];
    $datePrepared = date('Y-m-d H:i:s');
} else {
    header("Location: process_shipping_manifest.php");
    exit();
}

// Create PDF object
$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('Chill Division');
$pdf->SetTitle('Shipping Manifest');
$pdf->SetMargins(15, 15, 15);
$pdf->SetHeaderMargin(15);
$pdf->AddPage();
// Add company logo
$logoPath = $_SERVER['DOCUMENT_ROOT'] . '/GRACe_repo/grace_addon/logo.png';
$logoWebPath = '/GRACe_repo/grace_addon/logo.png';
if (file_exists($logoPath)) {
    $pdf->Image($logoPath, 160, 22, 30, 0, 'PNG');
}

// Define HTML structure
$htmlContent = <<<EOD

    <style>
         h1 { color: #85bbe9; text-align: left; font-weight: bold; }
        table { width: 100%; }
        td { padding: 5px; vertical-align: middle; }
    </style>


    <div class="flex items-center justify-between">
        <div>
            <h1>Chill Division Universal Shipping Manifest</h1>
        </div>
    </div>
    
    <h2>Sending Party:</h2>
    <table >
        <tr><td><strong>Staff Name Preparing Shipment:</strong></td><td><u>{$sendingChoice}</u></td></tr>
        <tr><td><strong>Sending Company name + Licence #:</strong></td><td><u>{$sendingCompany} / {$sendingLicense}</u></td></tr>
        <tr><td><strong>Arrival Notification Email:</strong></td><td><u>{$sendingEmail}</u></td></tr>
        <tr><td><strong>Date / Time Prepared for shipment:</strong></td><td><u>{$datePrepared}</u></td></tr>
        <tr><td><strong>Product Type:</strong></td><td><u>{$productType}</u></td></tr>
        <tr><td><strong># of Items Sent:</strong></td><td><u>{$quantity}</u></td></tr>
        <tr><td><strong>Shipment Weight (Net / Gross):</strong></td><td>________________________</td></tr>
        <tr><td><strong>Recipient Staff Name:</strong></td><td>________________________</td></tr>
        <tr><td><strong>Recipient Company + Licence #:</strong></td><td><u>{$receivingCompany} / {$receivingLicense}</u></td></tr>
        <tr><td><strong>Destination Address:</strong></td><td><u>{$address}</u></td></tr>
    </table>

    <p class="signature"><strong>• Staff Signature:</strong> ________________________</p>
    
    <h2>Transit Chain of Custody (if applicable):</h2>
    <table class="ms-[30px]">
        <tr><td><strong>Collected from Facility By:</strong></td><td>________________________</td></tr>
        <tr><td><strong>Date / Time Shipment Collected:</strong></td><td>____/____/______ ____:____</td></tr>
        <tr><td><strong># of Items Collected:</strong></td><td>________________________</td></tr>
    </table>

     <p class="signature"><strong>• Collection Signature / Tracking Number:</strong> ________________________</p>


    <h2>Receiving Party:</h2>
    <table class="ms-[30px]">
        <tr><td><strong>Received By (Staff Name):</strong></td><td><u>{$receivingChoice}</u></td></tr>
        <tr><td><strong>Date / Time of Receipt:</strong></td><td><u>{$datePrepared}</u></td></tr>
        <tr><td><strong># of Items Received:</strong></td><td><u>{$quantity}</u></td></tr>
        <tr><td><strong>Shipment Weight (Net / Gross):</strong></td><td>________________________</td></tr>
    </table>

    <p class="signature"><strong>• Signature of Receiving Party:</strong>________________________</p>

    <p><em>Please scan/photo upon receipt of goods and send a copy to <strong>{$sendingEmail}</strong></em></p>
    EOD;

// Add content to PDF
// $pdfHtmlContent = str_replace("padding-top: 5px;", "", $htmlContent);
$pdf->writeHTML($htmlContent, true, false, true, false, '');
$pdfOutput = $pdf->Output('shipping_manifest.pdf', 'S');

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">  

    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">  

    <link rel="stylesheet" href="css/growcart.css">
    <title>GRACe - Plant Tracking</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body>
    <header class="container-fluid">
        <?php require_once 'nav.php'; ?>
    </header>
    <main class="container p-6  rounded-lg w-full max-w-2xl">
        <h1 class="text-[43px] font-bold text-center  mb-[40px]">Your Shipping Manifest</h1>
        <div class="p-6 border border-gray-200 rounded-lg text-gray-700">
            <p><?php echo $htmlContent; ?></p>
        </div>
        <form method="post" action="download_pdf.php" class="mt-6 flex justify-center">
            <input type="hidden" name="pdfContent" value="<?php echo base64_encode($pdfOutput); ?>">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 transition duration-300">
                Download as PDF
            </button>
        </form>
    </main>
    <script src="js/growcart.js"></script>
</body>

</html>
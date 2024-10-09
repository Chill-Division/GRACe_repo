<?php
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pdfContent'])) {
    $pdfContent = base64_decode($_POST['pdfContent']);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="shipping_manifest.pdf"');
    echo $pdfContent;
    exit;
}
?>

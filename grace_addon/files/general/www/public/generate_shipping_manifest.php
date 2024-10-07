<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">   

    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">   

    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - Generate Shipping Manifest</title> 
    <style>
        /* Additional styles for the manifest layout */
        #manifest {
            font-family: monospace; /* Use a monospace font for better alignment */
        }

        #manifest .field {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        #manifest .field label {
            flex: 0 0 auto; /* Prevent label from shrinking */
            width: 200px; /* Adjust as needed */
            margin-right: 1rem;
        }

        #manifest .field input[type="text"],
        #manifest .field input[type="date"],
        #manifest .field input[type="time"] {
            border: none;
            border-bottom: 1px solid var(--color);
            background-color: transparent;
            width: 100%;
            padding: 0.25rem 0;
        }

        #manifest .signature {
            border-bottom: 1px solid var(--color);
            width: 200px; /* Adjust as needed */
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Generate Shipping Manifest</h1>

        <div id="manifest">
            <h2>Chill Division Shipping Manifest</h2>

            <h3>Sending party:</h3>
            <div class="field">
                <label for="staffName">Staff name preparing shipment:</label>
                <input type="text" id="staffName" name="staffName">
            </div>
            <div class="field">
                <label for="sendingCompany">Sending company name + licence #:</label>
                <input type="text" id="sendingCompany" name="sendingCompany">
            </div>
            <div class="field">
                <label for="notificationEmail">Email address to be notified upon arrival:</label>
                <input type="text" id="notificationEmail" name="notificationEmail">
            </div>
            <div class="field">
                <label>Date / time prepared for shipment:</label>
                <input type="date" name="shipmentDate"> <input type="time" name="shipmentTime">
            </div>
            <div class="field">
                <label for="productType">Product (Dried flower, cuttings etc):</label>
                <input type="text" id="productType" name="productType">
            </div>
            <div class="field">
                <label for="itemCount"># of items sent:</label>
                <input type="text" id="itemCount" name="itemCount">
            </div>
            <div class="field">
                <label>Shipment weight, net / gross:</label>
                <input type="text" name="netWeight"> / <input type="text" name="grossWeight">
            </div>

            <h3>Recipient:</h3>
            <div class="field">
                <label for="recipientName">Recipient name:</label>
                <input type="text" id="recipientName" name="recipientName">
            </div>
            <div class="field">
                <label for="recipientCompany">Recipient company + licence #:</label>
                <input type="text" id="recipientCompany" name="recipientCompany">
            </div>
            <div class="field">
                <label for="addressLine1">Destination address:</label>
                <input type="text" id="addressLine1" name="addressLine1">
            </div>
            <div class="field">
                <label></label> <input type="text" name="addressLine2">
            </div>
            <div class="field">
                <label></label> <input type="text" name="addressLine3">
            </div>

            <p>Staff signature: <span class="signature">________________________________</span></p>

            <h3>Transit chain of custody (if applicable):</h3>
            <div class="field">
                <label for="collectedBy">Collected from facility by:</label>
                <input type="text" id="collectedBy" name="collectedBy">
            </div>
            <div class="field">
                <label>Date / time shipment collected:</label>
                <input type="date" name="collectionDate"> <input type="time" name="collectionTime">
            </div>
            <div class="field">
                <label for="collectedItemCount"># of items collected:</label>
                <input type="text" id="collectedItemCount" name="collectedItemCount">
            </div>

            <p>Collection signature / tracking number: <span class="signature">________________________________</span></p> 

            <h3>Receiving party:</h3>
            <div class="field">
                <label for="receivedBy">Received by (staff name):</label>
                <input type="text" id="receivedBy" name="receivedBy">
            </div>
            <div class="field">
                <label>Date / time of receipt:</label>
                <input type="date" name="receiptDate"> <input type="time" name="receiptTime">
            </div>
            <div class="field">
                <label for="receivedItemCount"># of items received:</label>
                <input type="text" id="receivedItemCount" name="receivedItemCount">
            </div>
            <div class="field">
                <label>Shipment weight (if applicable) net / gross:</label>
                <input type="text" name="receivedNetWeight"> / <input type="text" name="receivedGrossWeight">
            </div>

            <p>Signature of receiving party: <span class="signature">________________________________</span></p> 

            <p>Please scan / photo upon receipt of goods, and send a copy to the sender's email above.</p>
        </div>

        <button id="generateButton" class="button">Generate Manifest</button> 
    </main>

    <script src="js/growcart.js"></script> 
    <script>
        // Placeholder for generating the manifest (PDF or print)
        const generateButton = document.getElementById('generateButton');

        generateButton.addEventListener('click', () => {
            // Replace with actual logic to generate PDF or trigger printing
            alert('Manifest generation logic goes here!');
        });
    </script>
</body>
</html>

<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">   

    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">   

    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - Plant Tracking</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Plant Tracking</h1>

        <section>
            <h2>Plant / Product Management</h2>
            <ul>
                <li><a href="list_all_genetics.php">List All Genetics</a></li>
                <li><a href="receive_genetics.php">Receive Plants / Take Clones</a></li>
                <li><a href="harvest_plants.php">Harvest/Destroy plants</a></li>
                <li><a href="record_dry_weight.php">Record Dry Weight</a></li>
            </ul>
        </section>

        <section>
            <h2>Shipping</h2>
            <ul>
                <li><a href="generate_shipping_manifest.php">Generate Shipping Manifest</a></li>
                <li><a href="amend_complete_manifest.php">Amend / Complete Manifest</a></li>
            </ul>
        </section>

        <section>
            <h2>Recalls</h2>
            <ul>
                <li><a href="initiate_recall.php">Initiate Recall</a></li>
            </ul>
        </section>

    </main>

    <script src="js/growcart.js"></script> 
</body>
</html>

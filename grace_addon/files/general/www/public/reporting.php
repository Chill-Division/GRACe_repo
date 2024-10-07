<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - Reporting</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Reporting</h1>

        <section>
            <h2>Inventory Reports</h2>
            <ul>
                <li><a href="current_dried_flower.php">Current Dried Flower</a></li>
	        <li><a href="current_plants.php">Current Plants</a></li>
            </ul>
        </section>

        <section>
            <h2>Transaction Reports</h2>
            <ul>
                <li><a href="this_months_flower_transactions.php">This Month's Out Flower</a></li>
                <li><a href="last_months_flower_transactions.php">Last Month's Out Flower</a></li>
                <li><a href="annual_stocktake.php">Annual stocktake</a></li>
            </ul>
        </section>

        </main>

    <script src="js/growcart.js"></script> 
</body>
</html>

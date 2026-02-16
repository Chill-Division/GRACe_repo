<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - Current Dried Flower</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Current Dried Flower</h1>

        <div class="grid">
            <label for="hideZeroRowsCheckbox">
                <input type="checkbox" id="hideZeroRowsCheckbox" name="hideZeroRows">
                Hide rows with all zero values
            </label>
        </div>

        <table id="driedFlowerTable" class="table">
            <thead>
                <tr>
                    <th>Genetics Name</th>
                    <th>Total Weight (grams)</th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
        <p id="noDataMessage" style="display: none; text-align: center; font-style: italic;"></p>
    </main>

    <script src="js/growcart.js"></script> 
    <script src="js/reports.js"></script>
</body>
</html>

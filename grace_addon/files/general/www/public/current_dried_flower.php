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
    </main>

    <script src="js/growcart.js"></script> 
    <script>
        const driedFlowerTable = document.getElementById('driedFlowerTable').getElementsByTagName('tbody')[0];

        // Fetch dried flower data from the server
        fetch('get_current_dried_flower.php') // You'll need to create this script
            .then(response => response.json())
            .then(flowerData => {
                flowerData.forEach(item => {
                    const row = driedFlowerTable.insertRow();
                    const nameCell = row.insertCell();
                    const weightCell = row.insertCell();

                    nameCell.textContent = item.geneticsName;
                    weightCell.textContent = item.totalWeight;
                });
            })
            .catch(error => console.error('Error fetching dried flower data:', error));
    </script>
</body>
</html>

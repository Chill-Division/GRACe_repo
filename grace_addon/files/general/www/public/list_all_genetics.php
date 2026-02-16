<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">
    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - List All Genetics</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>List All Genetics</h1>

        <label for="statusFilter">Filter by Status:</label>
        <select id="statusFilter" name="statusFilter" class="input">
            <option value="">All</option>
            <option value="Harvested-all">Harvested (All)</option>
            <option value="Growing">Growing</option>
            <option value="Harvested">Harvested (Legacy)</option>
            <option value="Harvested - Drying">Harvested - Drying</option>
            <option value="Harvested - Destroyed">Harvested - Destroyed</option>
            <option value="Destroyed">Destroyed</option>
            <option value="Sent">Sent</option>
        </select>

        <table id="geneticsListTable" class="table">
            <thead>
                <tr>
                    <th>Genetics Name</th>
                    <th>Age (Days)</th> 
                    <th>Status</th> 
                </tr>
            </thead>
            <tbody>
                </tbody>
        </table>
    </main>

    <script src="js/growcart.js"></script> 
    <script src="js/genetics.js"></script>
</body>
</html>

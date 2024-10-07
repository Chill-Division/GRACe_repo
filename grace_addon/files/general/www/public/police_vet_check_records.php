<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">   

    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">   

    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - Police Vet Check Records</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Police Vet Check Records</h1>

        <section>
            <h2>Upload New Record</h2>
            <form id="uploadRecordForm" class="form">
                <input type="file" id="recordFile" name="recordFile" accept=".pdf" required>
                <button type="submit" class="button">Upload</button>
            </form>
        </section>

        <section>
            <h2>Existing Records</h2>
            <ul id="recordList">
                </ul>
        </section>
    </main>

    <script src="js/growcart.js"></script> 
    <script>
        // Placeholder to fetch and display existing records with datestamps
        const recordList = document.getElementById('recordList');

        // Simulate fetching records with dates (replace with actual logic)
        const records = [
            { filename: 'record1.pdf', timestamp: '2023-08-15' },
            { filename: 'record2.pdf', timestamp: '2024-02-20' },
            { filename: 'staff/record3.pdf', timestamp: '2023-11-03' }
        ];

        records.forEach(record => {
            const listItem = document.createElement('li');
            const link = document.createElement('a');
            link.href = `/police_vet_checks/${record.filename}`; // Adjust path as needed
            link.textContent = `${record.filename} (${record.timestamp})`;
            listItem.appendChild(link);
            recordList.appendChild(listItem);
        });
    </script>
</body>
</html>

<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">   

    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">   

    <link rel="stylesheet" href="css/growcart.css"> 
    <title>GRACe - Standard Operating Procedures (SOPs)</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Standard Operating Procedures (SOPs)</h1>

        <section>
            <h2>Upload New SOP</h2>
            <form id="uploadSopForm" class="form">
                <input type="file" id="sopFile" name="sopFile" accept=".pdf" required>
                <button type="submit" class="button">Upload</button>
            </form>
        </section>

        <section>
            <h2>Existing SOPs</h2>
            <ul id="sopList">
                </ul>
        </section>
    </main>

    <script src="js/growcart.js"></script> 
    <script>
        // Placeholder to fetch and display existing SOPs with datestamps
        const sopList = document.getElementById('sopList');

        // Simulate fetching SOPs with dates (replace with actual logic)
        const sops = [
            { filename: 'sop_cultivation.pdf', timestamp: '2023-06-30' },
            { filename: 'sop_harvesting.pdf', timestamp: '2024-03-12' },
            { filename: 'security/sop_access_control.pdf', timestamp: '2023-09-25' }
        ];

        sops.forEach(sop => {
            const listItem = document.createElement('li');
            const link = document.createElement('a');
            link.href = `/sops/${sop.filename}`; // Adjust path as needed
            link.textContent = `${sop.filename} (${sop.timestamp})`;
            listItem.appendChild(link);
            sopList.appendChild(listItem);
        });
    </script>
</body>
</html>

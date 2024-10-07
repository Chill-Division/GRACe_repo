<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">

    <link rel="stylesheet" href="css/growcart.css">
    <title>GRACe - Offtake Agreements</title>
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Offtake Agreements</h1>

        <section>
            <h2>Upload New Agreement</h2>
            <form id="uploadAgreementForm" class="form">
                <input type="file" id="agreementFile" name="agreementFile" accept=".pdf" required>
                <button type="submit" class="button">Upload</button>
            </form>
        </section>

        <section>
            <h2>Existing Agreements</h2>
            <ul id="agreementList">
                </ul>
        </section>
    </main>

    <script src="js/growcart.js"></script> 
    <script>
        // Placeholder to fetch and display existing agreements from /offtakes/
        const agreementList = document.getElementById('agreementList');

        // Simulate fetching agreements (replace with actual logic)
        const agreements = [
            'agreement1.pdf',
            'agreement2.pdf',
            'subfolder/agreement3.pdf'
        ];

        agreements.forEach(agreement => {
            const listItem = document.createElement('li');
            const link = document.createElement('a');
            link.href = `/offtakes/${agreement}`; // Assuming files are in /offtakes/
            link.textContent = agreement;
            listItem.appendChild(link);
            agreementList.appendChild(listItem);
        });
    </script>
</body>
</html>

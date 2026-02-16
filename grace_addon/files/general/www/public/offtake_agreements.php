<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">   

    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 

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
            <form id="uploadForm">
                <input type="file" name="file" required>
                <input type="hidden" name="category" value="offtakes">
                <button type="submit">Upload</button>
            </form>
        </section>

        <section>
            <h2>Existing Agreements</h2>
            <div id="sortContainer">
                <label>Sort by:</label>
                <select id="sortOrder">
                    <option value="date_desc">Newest First</option>
                    <option value="name_asc">Name A-Z</option>
                </select>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Upload Date</th>
                        <th>Download</th>
                    </tr>
                </thead>
                <tbody id="fileList">
                    <tr><td colspan="3">No records found.</td></tr>
                </tbody>
            </table>
        </section>
    </main>
    
    <script src="js/growcart.js"></script> 
    <script src="js/image-compress.js"></script>
    <script src="js/documents.js"></script>
    <script>
        $(document).ready(function() {
            initDocumentManager({
                category: 'offtakes',
                hasExpiry: false,
                hasAcknowledgment: false
            });
        });
    </script>
</body>
</html>

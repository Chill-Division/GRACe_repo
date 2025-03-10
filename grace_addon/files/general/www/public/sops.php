<?php require_once 'auth.php'; ?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">   

    <meta name="color-scheme" content="light dark">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@2/css/pico.min.css">  
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 

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
            <form id="uploadForm">
                <input type="file" name="file" required>
                <input type="hidden" name="category" value="sops">
                <button type="submit">Upload</button>
            </form>
        </section>

        <section>
            <h2>Existing SOPs</h2>
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

    <script>
        function loadFiles() {
            const order = $('#sortOrder').val();
            $.get('fetch_files.php', { category: 'sops', order: order }, function(files) {
                const fileList = $('#fileList');
                fileList.empty();
                if (files.length === 0) {
                    fileList.append('<tr><td colspan="3">No records found.</td></tr>');
                    $('#sortContainer').hide(); 
                } else {
                    $('#sortContainer').show(); 
                    files.forEach(file => {
                        fileList.append(`
                            <tr>
                                <td>${file.original_filename}</td>
                                <td>${file.upload_date}</td>
                                <td><a href="uploads/sops/${file.unique_filename}" download><i class="fa-solid fa-download"></i> Download</a></td>
                            </tr>
                        `);
                    });
                }
            }, 'json');
        }

        $('#sortOrder').change(loadFiles);

        $('#uploadForm').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            $.ajax({
                url:'upload.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function() {
                    alert('File uploaded');
                    $('#uploadForm')[0].reset(); 
                    loadFiles();
                }
            });
        });

        $(document).ready(loadFiles);
    </script>
</body>
</html>

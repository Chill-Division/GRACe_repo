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
    <title>GRACe - Company Licenses</title> 
</head>
<body>
    <header class="container-fluid">
	<?php require_once 'nav.php'; ?>
    </header>

    <main class="container">
        <h1>Company Licenses</h1>

        <section>
            <h2>Upload New License</h2>
            <form id="uploadForm">
                <label for="file">License File</label>
                <input type="file" name="file" id="file" required>
                
                <label for="expiry_date">Expiry Date (Max 12 months from now)</label>
                <input type="date" name="expiry_date" id="expiry_date" required>
                
                <input type="hidden" name="category" value="licenses">
                <button type="submit">Upload</button>
            </form>
        </section>

        <section>
            <h2>Existing Licenses</h2>
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
                        <th>Expiry Date</th>
                        <th>Download</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="fileList">
                    <tr><td colspan="5">No records found.</td></tr>
                </tbody>
            </table>
        </section>
    </main>
    
    <script src="js/growcart.js"></script> 
    <script src="js/image-compress.js"></script>

    <script>
        function loadFiles() {
            const order = $('#sortOrder').val();
            $.get('fetch_files.php', { category: 'licenses', order: order }, function(files) {
                const fileList = $('#fileList');
                fileList.empty();
                if (files.length === 0) {
                    fileList.append('<tr><td colspan="5">No records found.</td></tr>');
                    $('#sortContainer').hide(); 
                } else {
                    $('#sortContainer').show(); 
                    const today = new Date();
                    const warningDate = new Date();
                    warningDate.setDate(today.getDate() + 3);

                    files.forEach(file => {
                        let alertHtml = '';
                        // Check if acknowledged is 0 or null (falsy)
                        let isAck = file.acknowledged == 1;
                        let expiryDate = file.expiry_date ? new Date(file.expiry_date) : null;
                        let showAck = false;
                        let rowStyle = '';

                        if (expiryDate) {
                            if (expiryDate <= warningDate) {
                                rowStyle = 'style="background-color: rgba(217, 53, 38, 0.1);"'; // Light red hint
                                if (!isAck) {
                                    showAck = true;
                                }
                            }
                        }

                        let ackButton = showAck 
                            ? `<button class="secondary outline" onclick="acknowledge(${file.id})">Acknowledge Alert</button>` 
                            : (isAck ? '<small>Acknowledged</small>' : '');

                        fileList.append(`
                            <tr ${rowStyle}>
                                <td>${file.original_filename}</td>
                                <td>${file.upload_date}</td>
                                <td>${file.expiry_date || '-'}</td>
                                <td><a href="download.php?category=licenses&file=${encodeURIComponent(file.unique_filename)}" download><i class="fa-solid fa-download"></i> Download</a></td>
                                <td>${ackButton}</td>
                            </tr>
                        `);
                    });
                }
            }, 'json');
        }
        
        function acknowledge(id) {
            if(confirm('Acknowledge this expiry alert? It will disappear from the top banner.')) {
                $.post('acknowledge_license.php', { id: id }, function(res) {
                    const data = JSON.parse(res);
                    if(data.success) {
                        loadFiles(); // Reload to update UI
                        location.reload(); // Reload to update banner
                    } else {
                        alert('Error: ' + (data.message || 'Unknown error'));
                    }
                });
            }
        }

        $('#sortOrder').change(loadFiles);

        $('#uploadForm').submit(async function(e) {
            e.preventDefault();
            const form = this;
            const fileInput = form.querySelector('input[type="file"]');
            const expiryInput = form.querySelector('input[name="expiry_date"]');
            const submitButton = form.querySelector('button[type="submit"]');
            const originalButtonText = submitButton.textContent;
            
            if (!fileInput.files || !fileInput.files[0]) {
                alert('Please select a file to upload');
                return;
            }
            
            let file = fileInput.files[0];
            const originalSize = file.size;

            submitButton.disabled = true;
            submitButton.textContent = 'Processing...';
            
            try {
                if (file.type.match(/^image\//)) {
                    if (file.size > 1024 * 1024) {
                        submitButton.textContent = 'Compressing image...';
                        file = await compressImage(file, 1024 * 1024);
                        console.log(`Image compressed from ${formatFileSize(originalSize)} to ${formatFileSize(file.size)}`);
                    }
                }

                const formData = new FormData();
                formData.append('file', file, file.name);
                formData.append('category', 'licenses');
                if (expiryInput && expiryInput.value) {
                    formData.append('expiry_date', expiryInput.value);
                }
                
                submitButton.textContent = 'Uploading...';
                
                $.ajax({
                    url: 'upload.php',
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        const result = typeof response === 'string' ? JSON.parse(response) : response;
                        if (result.success) {
                            alert('File uploaded successfully');
                            form.reset();
                            loadFiles();
                        } else {
                            alert('Upload failed: ' + (result.message || 'Unknown error'));
                        }
                    },
                    error: function(xhr, status, error) {
                        let errorMsg = 'Upload failed';
                        if (xhr.responseText) {
                            try {
                                const response = JSON.parse(xhr.responseText);
                                errorMsg = response.message || errorMsg;
                            } catch (e) {
                                errorMsg = xhr.responseText || errorMsg;
                            }
                        }
                        alert('Upload error: ' + errorMsg);
                    },
                    complete: function() {
                        submitButton.disabled = false;
                        submitButton.textContent = originalButtonText;
                    }
                });
            } catch (error) {
                alert('Error processing file: ' + error.message);
                submitButton.disabled = false;
                submitButton.textContent = originalButtonText;
            }
        });

        $(document).ready(loadFiles);
    </script>
</body>
</html>
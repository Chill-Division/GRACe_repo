<?php
$pageTitle = 'GRACe - Company Licenses';
$useJquery = true;
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Company Licenses</h1>
            <p>Upload licenses with an expiry date — GRACe alerts you before they lapse.</p>
        </hgroup>

        <article class="form-card">
            <h2>Upload New License</h2>
            <form id="uploadForm">
                <label for="file">License File</label>
                <input type="file" name="file" id="file" required>

                <label for="expiry_date">Expiry Date (Max 12 months from now)</label>
                <input type="date" name="expiry_date" id="expiry_date" required>

                <input type="hidden" name="category" value="licenses">
                <button type="submit">Upload</button>
            </form>
        </article>

        <section>
            <h2>Existing Licenses</h2>
            <div id="sortContainer">
                <label>Sort by:</label>
                <select id="sortOrder">
                    <option value="date_desc">Newest First</option>
                    <option value="name_asc">Name A-Z</option>
                </select>
            </div>
            <figure class="table-wrap">
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
            </figure>
        </section>
    </main>

    <script src="js/image-compress.js"></script>
    <script src="js/documents.js"></script>
    <script>
        $(document).ready(function() {
            initDocumentManager({
                category: 'licenses',
                hasExpiry: true,
                hasAcknowledgment: true
            });
        });
    </script>
<?php require 'footer.php'; ?>

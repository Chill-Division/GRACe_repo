<?php
require_once 'auth.php';
$pageTitle = 'GRACe - Standard Operating Procedures (SOPs)';
$useJquery = true;
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Standard Operating Procedures (SOPs)</h1>
            <p>Upload and keep your SOPs on file. Images over 1MB are compressed automatically.</p>
        </hgroup>

        <article class="form-card">
            <h2>Upload New SOP</h2>
            <form id="uploadForm">
                <input type="file" name="file" required>
                <input type="hidden" name="category" value="sops">
                <button type="submit">Upload</button>
            </form>
        </article>

        <section>
            <h2>Existing SOPs</h2>
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
                            <th>Download</th>
                        </tr>
                    </thead>
                    <tbody id="fileList">
                        <tr><td colspan="3">No records found.</td></tr>
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
                category: 'sops',
                hasExpiry: false,
                hasAcknowledgment: false
            });
        });
    </script>
<?php require 'footer.php'; ?>

<?php
$pageTitle = 'GRACe - Offtake agreements';
$useJquery = true;
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Offtake agreements</h1>
            <p>Keep signed buyer agreements on file. Images over 1MB are compressed automatically.</p>
        </hgroup>

        <article class="form-card">
            <h2>Upload a new agreement</h2>
            <form id="uploadForm">
                <input type="file" name="file" required>
                <input type="hidden" name="category" value="offtakes">
                <button type="submit">Upload</button>
            </form>
        </article>

        <section>
            <h2>Existing agreements</h2>
            <div id="sortContainer">
                <label>Sort by</label>
                <select id="sortOrder">
                    <option value="date_desc">Newest first</option>
                    <option value="name_asc">Name A-Z</option>
                </select>
            </div>
            <figure class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>File name</th>
                            <th>Uploaded</th>
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

    <script src="js/image-compress.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
    <script src="js/documents.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
    <script>
        $(document).ready(function() {
            initDocumentManager({
                category: 'offtakes',
                hasExpiry: false,
                hasAcknowledgment: false
            });
        });
    </script>
<?php require 'footer.php'; ?>

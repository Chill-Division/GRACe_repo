<?php
require_once 'license_alerts_lib.php';

$pageTitle = 'GRACe - Company licences';
$useJquery = true;
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Company licences</h1>
            <p>Upload licences with an expiry date so GRACe can alert you before they lapse.</p>
        </hgroup>

        <article class="form-card">
            <h2>Upload a new licence</h2>
            <form id="uploadForm">
                <label for="file">Licence file</label>
                <input type="file" name="file" id="file" required>

                <label for="expiry_date">Expiry date (up to 15 months from today)</label>
                <input type="date" name="expiry_date" id="expiry_date" required max="<?php echo licenseMaxExpiryDate(); ?>">

                <input type="hidden" name="category" value="licenses">
                <button type="submit">Upload</button>
            </form>
        </article>

        <section>
            <h2>Existing licences</h2>
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
                            <th>Expiry date</th>
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

    <script src="js/image-compress.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
    <script src="js/documents.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
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

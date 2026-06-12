<?php
$pageTitle = 'GRACe - Current Dried Flower';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>Current Dried Flower</h1>
            <p>Total dried flower weight on hand, by genetics.</p>
        </hgroup>

        <div class="toolbar">
            <label for="hideZeroRowsCheckbox">
                <input type="checkbox" id="hideZeroRowsCheckbox" name="hideZeroRows">
                Hide rows with all zero values
            </label>
        </div>

        <figure class="table-wrap">
            <table id="driedFlowerTable" class="table">
                <thead>
                    <tr>
                        <th>Genetics Name</th>
                        <th>Total Weight (grams)</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </figure>
        <p id="noDataMessage" style="display: none; text-align: center; font-style: italic;"></p>
    </main>

    <script src="js/reports.js"></script>
<?php require 'footer.php'; ?>

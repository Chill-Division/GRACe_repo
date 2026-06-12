<?php
$pageTitle = 'GRACe - List All Genetics';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>List All Genetics</h1>
            <p>Current and historical plants — live, harvested, destroyed, and sent.</p>
        </hgroup>

        <div class="toolbar">
            <div>
                <label for="statusFilter">Filter by Status:</label>
                <select id="statusFilter" name="statusFilter" class="input">
                    <option value="">All</option>
                    <option value="Harvested-all">Harvested (All)</option>
                    <option value="Growing">Growing</option>
                    <option value="Harvested">Harvested (Legacy)</option>
                    <option value="Harvested - Drying">Harvested - Drying</option>
                    <option value="Harvested - Destroyed">Harvested - Destroyed</option>
                    <option value="Destroyed">Destroyed</option>
                    <option value="Sent">Sent</option>
                </select>
            </div>
        </div>

        <figure class="table-wrap">
            <table id="geneticsListTable" class="table">
                <thead>
                    <tr>
                        <th>Genetics Name</th>
                        <th>Age (Days)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </figure>
    </main>

    <script src="js/genetics.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initGeneticsList);
    </script>
<?php require 'footer.php'; ?>

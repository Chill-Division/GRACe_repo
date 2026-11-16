<?php
$pageTitle = 'GRACe - All plants';
require 'header.php';
?>

    <main class="container">
        <hgroup class="page-header">
            <h1>All plants</h1>
            <p>Every plant, current and past, with where it is now: growing, drying, harvested, destroyed or sent.</p>
        </hgroup>

        <div class="toolbar">
            <div>
                <label for="statusFilter">Show</label>
                <select id="statusFilter" name="statusFilter" class="input">
                    <option value="">All plants</option>
                    <option value="Harvested-all">Harvested (all)</option>
                    <option value="Growing">Growing</option>
                    <option value="Harvested">Harvested (legacy)</option>
                    <option value="Harvested - Drying">Harvested - Drying</option>
                    <option value="Harvested - Destroyed">Harvested - Destroyed</option>
                    <option value="Destroyed">Destroyed</option>
                    <option value="Sent">Sent</option>
                </select>
            </div>
        </div>

        <figure class="table-wrap">
            <table id="geneticsListTable" class="table wide-table">
                <thead>
                    <tr>
                        <th>Genetics</th>
                        <th class="num">Age (days)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                </tbody>
            </table>
        </figure>
    </main>

    <script src="js/genetics.js?v=<?php echo GRACE_ASSET_VERSION; ?>"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initGeneticsList);
    </script>
<?php require 'footer.php'; ?>

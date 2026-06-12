<?php
require_once 'auth.php';
$pageTitle = 'GRACe - Receive Genetics';
require 'header.php';
?>

    <main class="container">
        <div id="statusMessage" class="status-message" style="display: none;"></div>

        <hgroup class="page-header">
            <h1>Receive Genetics</h1>
            <p>Any time you're receiving or adding genetics, either through a Form D declaration, taking clones, or from another licensed cultivator, this is where you want to add them.</p>
        </hgroup>

        <article class="form-card">
            <form id="receiveGeneticsForm" class="form" action="handle_receive_genetics.php" method="post">

                <label for="plantCount">How many plants received / clones taken:</label>
                <input type="number" id="plantCount" name="plantCount" class="input" min="1" required>

                <label for="geneticsName">Genetics Name:</label>
                <select id="geneticsName" name="geneticsName" class="input" required>
                    <option value="" disabled selected>Select Genetics</option>
                </select>

                <button type="submit" class="button">Add plants</button>
            </form>
        </article>
    </main>

    <script src="js/genetics.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', initReceiveGenetics);
    </script>
<?php require 'footer.php'; ?>

        <?php
        $base_path = str_replace('\\', '/', realpath(__DIR__ . '/../public/')); 
        $base_url = $_SERVER['REQUEST_SCHEME'] . "://" . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '', $base_path) . "/";
        ?>
        <nav>
            <ul>
                <li><strong>GRACe by Chill Division</strong></li>
            </ul>
            <input type="checkbox" id="nav-toggle" class="nav-toggle">
            <label for="nav-toggle" class="nav-toggle-label">
                <span class="hamburger"></span>
            </label>
            <ul>
                <li><a href="<?php echo $base_url; ?>tracking.php">Plant Tracking</a></li>
                <li><a href="<?php echo $base_url; ?>reporting.php">Reporting</a></li>
                <li><a href="<?php echo $base_url; ?>administration.php">Administration</a></li>
                <li><a href="#" id="theme_switcher">Toggle theme</a></li>
            </ul>
        </nav>

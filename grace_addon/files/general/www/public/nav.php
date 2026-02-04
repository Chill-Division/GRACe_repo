        <nav>
            <ul>
                <li><strong>GRACe by Chill Division</strong></li>
            </ul>
            <input type="checkbox" id="nav-toggle" class="nav-toggle">
            <label for="nav-toggle" class="nav-toggle-label">
                <span class="hamburger"></span>
            </label>
            <ul>
                <li><a href="tracking.php">Plant Tracking</a></li>
                <li><a href="reporting.php">Reporting</a></li>
                <li><a href="administration.php">Administration</a></li>
                <li><a href="#" id="theme_switcher">Toggle theme</a></li>
            </ul>
        </nav>
        <?php
        require_once 'init_db.php';
        try {
            $pdo_nav = initializeDatabase();
            // Check for expiring licenses (within 72 hours) or expired, and not acknowledged
            // SQLite ignores time zone in string comparison if not strict, but 'now' is UTC usually. 
            // init_db sets timezone to Pacific/Auckland.
            // Using logic: expiry_date <= date('Y-m-d', strtotime('+72 hours'))
            
            $alertDate = date('Y-m-d', strtotime('+3 days'));
            $today = date('Y-m-d');
            
            $stmt = $pdo_nav->prepare("SELECT original_filename, expiry_date FROM Documents WHERE category = 'licenses' AND expiry_date IS NOT NULL AND expiry_date <= ? AND (acknowledged IS NULL OR acknowledged = 0)");
            $stmt->execute([$alertDate]);
            $expiringDocs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($expiringDocs) {
                echo '<div style="background-color: #d93526; color: white; padding: 10px; text-align: center; margin-top: 10px; border-radius: 5px;">';
                foreach ($expiringDocs as $doc) {
                    $fName = htmlspecialchars($doc['original_filename']);
                    $uDate = htmlspecialchars($doc['expiry_date']);
                    echo "<div><strong>Alert:</strong> License '$fName' is expiring soon or expired (Date: $uDate). Please renew. <a href='company_licenses.php' style='color: white; text-decoration: underline;'>Go to Licenses</a></div>";
                }
                echo '</div>';
            }
        } catch (Exception $e) {
            // checking db quiet fail in nav
        }
        ?>

<?php
/**
 * Shared page header.
 *
 * Set these before including:
 *   $pageTitle  - title shown in the browser tab (defaults to "GRACe")
 *   $useJquery  - set true on pages that use the jQuery document manager
 */
if (!defined('GRACE_ASSET_VERSION')) {
    define('GRACE_ASSET_VERSION', '0.18.0');
}
$pageTitle = $pageTitle ?? 'GRACe';
?>
<!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#10150f">
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%234ade80' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpath d='M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z'/%3E%3Cpath d='M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12'/%3E%3C/svg%3E">
    <script>
        // Apply the saved theme before first paint to avoid a flash of the wrong theme
        (function () {
            try {
                var theme = localStorage.getItem('grace-theme');
                if (theme !== 'light' && theme !== 'dark') {
                    theme = window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
                }
                document.documentElement.setAttribute('data-theme', theme);
            } catch (e) { /* stay on the default dark theme */ }
        })();
    </script>
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="icons/apple-touch-icon.png">
    <!-- Pico CSS and jQuery are self-hosted so the portal works on offline / air-gapped Home Assistant boxes -->
    <link rel="stylesheet" href="css/vendor/pico.min.css?v=<?php echo GRACE_ASSET_VERSION; ?>">
    <link rel="stylesheet" href="css/growcart.css?v=<?php echo GRACE_ASSET_VERSION; ?>">
    <?php if (!empty($useJquery)): ?>
    <script src="js/vendor/jquery-3.6.0.min.js"></script>
    <?php endif; ?>
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
</head>
<body>
    <header class="app-header">
        <?php require_once 'nav.php'; ?>
    </header>

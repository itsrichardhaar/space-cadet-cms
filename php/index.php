<?php
/**
 * Space Cadet CMS — Root Entry Point
 * Redirects to the installer (first run) or admin panel.
 */
require_once __DIR__ . '/config/app.php';

if (!file_exists(SC_INSTALLED_LOCK)) {
    header('Location: /install.php');
} else {
    header('Location: /admin.php');
}
exit;

<?php
/**
 * Space Cadet CMS — Test Site Root
 * Redirects to the installer (first run) or admin panel.
 */
require_once __DIR__ . '/space-cadet/config/app.php';

if (!file_exists(SC_INSTALLED_LOCK)) {
    header('Location: /space-cadet/install.php');
} else {
    header('Location: /admin.php');
}
exit;

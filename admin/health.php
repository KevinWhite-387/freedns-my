<?php
/**
 * freedns.my - the health report, reached from the back office navigation (/admin/health.php).
 *
 * The implementation lives in the site root (health.php) and is pulled in here, so the page answers at the
 * address the navigation links to. Its relative links therefore resolve inside /admin/, which is where the
 * queues it points at live.
 */

require __DIR__ . '/../health.php';

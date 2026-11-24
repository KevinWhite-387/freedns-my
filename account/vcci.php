<?php
declare(strict_types=1);

/**
 * freedns.my - alias of account/vcci-callback.php.
 *
 * The redirect URI registered at vc.ci is /account/vcci-callback.php; this file exists so that a
 * link to /account/vcci.php (documentation, older bookmarks) keeps working.
 */

require __DIR__ . '/vcci-callback.php';
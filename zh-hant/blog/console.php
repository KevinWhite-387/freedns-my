<?php
declare(strict_types=1);

/**
 * freedns.my - the console in zh-hant.
 *
 * The language switcher links to "/<lang>" + the current path, so /blog/console.php becomes
 * /zh-hant/blog/console.php. This shell only forces the language and hands the work to the real
 * page, the same way /zh-hant/index.php does for the registry.
 */

$FD_FORCE_LANG = 'zh-hant';
require dirname(__DIR__, 2) . '/blog/console.php';
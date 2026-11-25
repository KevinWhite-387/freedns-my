<?php
declare(strict_types=1);

/**
 * freedns.my - the blog in ja.
 *
 * The language switcher links to "/<lang>" + the current path, so /blog/index.php becomes
 * /ja/blog/index.php. This shell only forces the language and hands the work to the real
 * page, the same way /ja/index.php does for the registry.
 */

$FD_FORCE_LANG = 'ja';
require dirname(__DIR__, 2) . '/blog/index.php';
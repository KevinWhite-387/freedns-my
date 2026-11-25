<?php
/**
 * freedns.my - Chinese home page.
 *
 * The page logic lives in the site root; this wrapper only selects the language and makes the
 * address /zh/index.php work.
 */

$FD_FORCE_LANG = 'zh';
require dirname(__DIR__) . '/index.php';

<?php
/**
 * freedns.my - Chinese wrapper for ddns.php.
 *
 * the dynamic-update URL a router calls; it must resolve with the language prefix too
 */

$FD_FORCE_LANG = 'zh';
require dirname(__DIR__) . '/ddns.php';
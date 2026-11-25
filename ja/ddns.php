<?php
/**
 * freedns.my - Japanese wrapper for ddns.php.
 *
 * the dynamic-update URL a router calls; it must resolve with the language prefix too
 */

$FD_FORCE_LANG = 'ja';
require dirname(__DIR__) . '/ddns.php';
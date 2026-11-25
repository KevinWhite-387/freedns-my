<?php
/**
 * freedns.my - Chinese wrapper for api.php.
 *
 * the HTTP API answers JSON, so the language only affects the wording of an error
 */

$FD_FORCE_LANG = 'zh';
require dirname(__DIR__) . '/api.php';
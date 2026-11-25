<?php
/**
 * freedns.my - Chinese wrapper for the advertisement click counter.
 *
 * The counter itself is language-neutral: it adds one to the order that holds the slot and sends the visitor on
 * to the advertisement. This wrapper exists so that a link built inside a /zh/ page (url() prefixes the language)
 * still resolves to a real file instead of a 500.
 */

$FD_FORCE_LANG = 'zh';
require dirname(__DIR__) . '/ad-go.php';
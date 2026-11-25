<?php
declare(strict_types=1);

/*
 * ja wrapper. Generated to match the zh/ tree exactly: the page logic lives in the site root, and this file
 * only selects the language. The number of levels matters - one too few and the file includes itself, which
 * exhausts memory instead of rendering a page.
 */
$FD_FORCE_LANG = 'ja';
require dirname(__DIR__, 1) . '/manage.php';
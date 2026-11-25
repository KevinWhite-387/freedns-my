<?php
declare(strict_types=1);

/*
 * zh wrapper. The page logic lives in the site root; this file only selects the language.
 */
$FD_FORCE_LANG = 'zh';
require dirname(__DIR__) . '/language.php';

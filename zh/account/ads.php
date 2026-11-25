<?php
declare(strict_types=1);

/*
 * Chinese wrapper.
 *
 * NOTE the two levels: this file sits in zh/account/, not in zh/, so the page it includes lives two directories
 * up. With only one level it included ITSELF and died with "Allowed memory size of 134217728 bytes exhausted" -
 * which is exactly what happened once. Every other wrapper in zh/account/ already used the two level form.
 */
$FD_FORCE_LANG = 'zh';
require dirname(__DIR__, 2) . '/account/ads.php';

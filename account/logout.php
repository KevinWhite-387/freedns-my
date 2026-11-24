<?php
declare(strict_types=1);

/**
 * freedns.my - console sign-out. Clears the console session only; the old channel needs none.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();
user_logout();
flash(t('account.signed_out'), 'ok');

redirect(url('account/login.php'));

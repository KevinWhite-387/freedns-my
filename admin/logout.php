<?php
declare(strict_types=1);

/**
 * freedns.my - administrator sign-out.
 *
 * Signing out is a state change, so the per-session token must be present. That keeps
 * third-party pages from forcing a sign-out with a plain <img>/<a> request.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$token = (string)($_REQUEST['t'] ?? '');

if (!is_admin() || $token === '' || !hash_equals(logout_token(), $token)) {
    flash('That sign-out link is no longer valid. Please use the menu.', 'error');
    redirect(url('admin/index.php'));
}

admin_logout();
// The hidden entry is closed again with the sign-out, so the back office has to be opened with
// the secret address once more.
admin_gate_close();
flash('You have been signed out.', 'ok');
redirect(url('admin/login.php'));

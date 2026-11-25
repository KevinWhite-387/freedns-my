<?php
declare(strict_types=1);

/**
 * freedns.my - administrator sign-in.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

// This page renders a form with a CSRF token, so the session has to exist before any output.
fd_start_session();

// Hidden entry: without the secret address this page answers 404 like any missing file.
admin_gate_enforce();

// The gate form itself posts the pass phrase; the CSRF token is checked inside the sign-in
// handler below, and the gate keeps its own throttling.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['gate_pass'])) {
    csrf_verify();
}

if (is_admin()) {
    redirect(url('admin/index.php'));
}

$errors   = [];
$username = '';

/*
 * An entry key that belongs to one account may only sign in that account: a reviewer who knows the
 * owner's user name still cannot use it from his own door. The master entry (0) is not bound.
 */
$boundId  = admin_gate_bound_admin_id();
$boundRow = $boundId > 0 ? db_one('SELECT * FROM admins WHERE id = ?', [$boundId]) : null;
if ($boundId > 0 && $boundRow === null) {
    $errors[] = 'The account of this entry no longer exists. Ask the owner for a new entry address.';
}
if ($boundRow !== null) {
    $username = (string)$boundRow['username'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {

    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($boundRow !== null && strcasecmp($username, (string)$boundRow['username']) !== 0) {
        // Do not say which account the entry belongs to: the visitor is not signed in yet.
        $errors[] = 'This entry address is not meant for that account.';
    } elseif (login_throttled($username !== '' ? $username : '(empty)')) {
        $errors[] = 'Too many failed sign-in attempts. Please wait a few minutes and try again.';
    } elseif ($username === '' || $password === '') {
        $errors[] = 'Enter both the user name and the password.';
    } elseif (admin_login($username, $password)) {
        $target = safe_relative_url((string)($_SESSION['after_login'] ?? ''), url('admin/index.php'));
        unset($_SESSION['after_login']);
        flash('Signed in successfully.', 'ok');
        redirect($target);
    } else {
        $errors[] = 'Invalid user name or password.';
    }
}

page_header('Administrator sign-in');
?>
<p>
This area is restricted to the registry operators. Every administrative action is written to the audit log.
</p>

<?php if ($boundRow !== null): ?>
<p class="small">
This is a personal entry address: it signs in the account
<strong><?= h((string)$boundRow['username']) ?></strong> only.
</p>
<?php endif; ?>

<?= error_list($errors) ?>

<form method="post" action="login.php">
<?= csrf_field() ?>
<label for="username">User name</label>
<input type="text" id="username" name="username" value="<?= h($username) ?>" maxlength="32"
       autocomplete="username" required autofocus<?= $boundRow !== null ? ' readonly' : '' ?>>

<label for="password">Password</label>
<input type="password" id="password" name="password" autocomplete="current-password" required>

<p><input type="submit" value="Sign in"></p>
</form>

<h3>Lost the password?</h3>
<p class="small">
Open phpMyAdmin in aaPanel, empty the <code>admins</code> table, then open
<a href="<?= h(url('install.php')) ?>">install.php</a> again: with no administrator left it will let you
create a new account. Delete install.php afterwards.
</p>
<?php
page_footer();

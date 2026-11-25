<?php
declare(strict_types=1);

/**
 * freedns.my - back office: using a vault reset link, and setting the master password for the first time.
 *
 * The reset link is only ever mailed to postmaster@freedns.my (the second address is told that a reset
 * happened, without the link), so being signed in as an administrator is NOT enough to change a password:
 * the link has to be taken out of that mailbox. This page is where the link lands.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('The mail vault');
$errors = [];
$done   = '';

$token   = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$entryId = (int)($_GET['entry'] ?? $_POST['entry'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'master') {
        $first = trim((string)($_POST['password'] ?? ''));
        $again = trim((string)($_POST['password2'] ?? ''));
        if ($first !== $again) {
            $errors[] = 'The two passwords are not the same.';
        } elseif (!mailvault_master_set($first)) {
            $errors[] = 'The master password needs at least ten characters.';
        } else {
            log_action('vault_master_first', 'settings', null, 'set by ' . (string)$admin['username']);
            flash('The master password is set. Keep it somewhere safe - without it nothing can be opened.',
                'ok');
            redirect('vault-reset.php');
        }
    }

    if ($action === 'use') {
        $first = trim((string)($_POST['password'] ?? ''));
        $again = trim((string)($_POST['password2'] ?? ''));
        if ($first !== $again) {
            $errors[] = 'The two passwords are not the same.';
        } else {
            $applied = mailvault_reset_apply($token, $first);
            if ($applied['ok']) {
                $done = $entryId > 0
                    ? 'That message has a new password now.'
                    : 'The master password has been replaced.';
                $token = '';
            } else {
                $errors[] = $applied['error'];
            }
        }
    }
}

page_header('Mail vault - passwords');
?>
<p>
Every archived message is kept encrypted, and looking at one needs the <strong>master password</strong>; a
single message may carry <strong>its own password</strong> on top. Both are stored as hashes, so a copy of
the database opens nothing.
</p>
<p class="hint">
A reset link is mailed to <strong><?= h(mailvault_postmaster()) ?></strong> only - never to the second
address (<?= h(mailvault_notify_address() !== '' ? mailvault_notify_address() : 'not set') ?>), which is
merely told that a reset happened. Being signed in here is therefore not enough to change a password.
</p>
<?= error_list($errors) ?>
<?php if ($done !== ''): ?><div class="msg ok"><?= h($done) ?></div><?php endif; ?>

<?php if (!mailvault_table_ready()): ?>
<div class="msg error">The vault tables are missing; import <code>upgrade-mailvault.sql</code>
(the <a href="<?= h(url('selftest.php')) ?>">self test</a> can apply it for you).</div>
<?php else: ?>

<?php if (!mailvault_master_ready()): ?>
<h2>Set the master password</h2>
<p>Nothing in the vault can be opened before this exists. Choose something long, and store it outside this
server (a password manager): losing it does not open anything, it locks the archive - the reset link only
goes to <?= h(mailvault_postmaster()) ?>.</p>
<form method="post" action="vault-reset.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="master">
<label for="password">Master password (at least 10 characters)</label>
<input type="password" id="password" name="password" autocomplete="new-password">
<label for="password2">Again</label>
<input type="password" id="password2" name="password2" autocomplete="new-password">
<p><input type="submit" value="Set it"></p>
</form>
<?php endif; ?>

<h2>Use a reset link</h2>
<?php if ($token === ''): ?>
<p class="hint">Paste the token from the link that arrived at <?= h(mailvault_postmaster()) ?>.</p>
<form method="get" action="vault-reset.php">
<label for="token">Token from the link</label>
<input type="text" id="token" name="token" maxlength="80" value="">
<label for="entry">Message number (only for a message password)</label>
<input type="text" id="entry" name="entry" maxlength="10" value="">
<p><input type="submit" value="Continue"></p>
</form>
<?php else: ?>
<form method="post" action="vault-reset.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="use">
<input type="hidden" name="token" value="<?= h($token) ?>">
<input type="hidden" name="entry" value="<?= (int)$entryId ?>">
<p class="small">Token accepted, <?= $entryId > 0
    ? 'this sets a new password for message <strong>#' . (int)$entryId . '</strong>.'
    : 'this replaces the <strong>master password</strong>.' ?></p>
<label for="password">New password (at least 6 characters)</label>
<input type="password" id="password" name="password" autocomplete="new-password">
<label for="password2">Again</label>
<input type="password" id="password2" name="password2" autocomplete="new-password">
<p><input type="submit" value="Set the new password"></p>
</form>
<?php endif; ?>

<p class="small">
Setting the master password from here needs an existing link, which is the point: an attacker who steals an
administrator session still cannot open the archive, because the link goes to a mailbox they do not hold.
Every request, successful use and refusal is written to the audit log.
</p>
<?php endif; ?>
<?php
page_footer();

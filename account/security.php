<?php
declare(strict_types=1);

/** freedns.my - console: two-factor authentication (optional, per account). */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user   = require_user();
$errors = [];

$ready  = totp_columns_ready();
$secret = totp_user_secret($user);
$on     = totp_user_on($user);

// The setup is kept in the session until a code from it was confirmed.
fd_start_session();
$pending = (string)($_SESSION['fd_totp_new'] ?? '');

$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_verify();

    if ($action === 'begin') {
        $_SESSION['fd_totp_new'] = totp_secret_new();
        redirect(url('account/security.php'));
    }

    if ($action === 'cancel') {
        unset($_SESSION['fd_totp_new']);
        redirect(url('account/security.php'));
    }

    if ($action === 'confirm') {
        $candidate = (string)($_SESSION['fd_totp_new'] ?? '');
        if ($candidate === '') {
            $errors[] = t('totp.no_setup');
        } elseif (!totp_verify($candidate, (string)($_POST['code'] ?? ''))) {
            $errors[] = t('totp.bad_code');
        } else {
            totp_user_enable((int)$user['id'], $candidate);
            unset($_SESSION['fd_totp_new']);
            flash(t('totp.enabled'), 'ok');
            redirect(url('account/security.php'));
        }
    }

    if ($action === 'disable') {
        if (!totp_user_on($user)) {
            $errors[] = t('totp.not_on');
        } elseif (!totp_verify($secret, (string)($_POST['code'] ?? ''))) {
            $errors[] = t('totp.bad_code');
        } else {
            totp_user_disable((int)$user['id']);
            flash(t('totp.disabled'), 'ok');
            redirect(url('account/security.php'));
        }
    }
}

$pending = (string)($_SESSION['fd_totp_new'] ?? '');

page_header(t('totp.heading'));
?>
<p><?= h(t('totp.intro')) ?></p>
<?= error_list($errors) ?>

<?php if (!$ready): ?>
<div class="msg error"><?= h(t('totp.no_table')) ?></div>
<?php else: ?>

<table>
  <tr><th><?= h(t('totp.state')) ?></th><td>
      <?= $on
          ? '<span class="ok">' . h(t('totp.state_on', (string)($user['totp_at'] ?? ''))) . '</span>'
          : h(t('totp.state_off')) ?></td></tr>
  <tr><th><?= h(t('totp.account')) ?></th><td><?= h((string)$user['username']) ?></td></tr>
</table>

<?php if ($on): ?>
<h2><?= h(t('totp.turn_off')) ?></h2>
<p class="hint"><?= h(t('totp.turn_off_hint')) ?></p>
<form method="post" action="<?= h(url('account/security.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="disable">
<label for="code_off"><?= h(t('totp.code')) ?></label>
<input type="text" id="code_off" name="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" value="">
<p><input type="submit" value="<?= h(t('totp.disable')) ?>"></p>
</form>

<?php elseif ($pending !== ''): ?>
<h2><?= h(t('totp.setup')) ?></h2>
<ol>
  <li><?= h(t('totp.step_app')) ?></li>
  <li><?= h(t('totp.step_secret')) ?> <code><?= h($pending) ?></code></li>
  <li><?= h(t('totp.step_uri')) ?></li>
</ol>
<pre class="wrap code-block"><?= h(totp_uri((string)$user['email'], $pending)) ?></pre>
<form method="post" action="<?= h(url('account/security.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="confirm">
<label for="code_on"><?= h(t('totp.code')) ?></label>
<input type="text" id="code_on" name="code" maxlength="6" inputmode="numeric" autocomplete="one-time-code" value="">
<p><input type="submit" value="<?= h(t('totp.confirm')) ?>"></p>
</form>
<form method="post" action="<?= h(url('account/security.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="cancel">
<p><input type="submit" value="<?= h(t('totp.cancel')) ?>"></p>
</form>

<?php else: ?>
<h2><?= h(t('totp.turn_on')) ?></h2>
<p class="hint"><?= h(t('totp.turn_on_hint')) ?></p>
<form method="post" action="<?= h(url('account/security.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="begin">
<p><input type="submit" value="<?= h(t('totp.begin')) ?>"></p>
</form>
<?php endif; ?>

<p class="hint"><?= h(t('totp.recovery')) ?></p>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

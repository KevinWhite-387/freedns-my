<?php
declare(strict_types=1);

/**
 * freedns.my - console profile: user name, password, e-mail address and account deletion.
 *
 * Names are linked to the account by e-mail address, so changing the address also changes which
 * names the console shows; the page warns about that.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();

$user   = require_user();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');
    $current = (string)($_POST['current_password'] ?? '');

    // An account created through GitHub has no local password yet, so there is nothing to confirm.
    if (user_has_password($user)) {
        $wrong = !password_verify($current, (string)$user['password_hash']);
    } else {
        $wrong = false;
    }

    if ($wrong) {
        $errors[] = t('val.user_current_password');
    } elseif ($action === 'username') {
        $new = trim((string)($_POST['username'] ?? ''));
        $problem = username_problem($new);
        if ($problem !== '' && $problem !== 'val.username_taken') {
            $errors[] = t($problem);
        } elseif (strcasecmp($new, (string)$user['username']) !== 0) {
            $taken = db_one('SELECT id FROM users WHERE username_lower = ? AND id <> ?', [strtolower($new), (int)$user['id']]);
            if ($taken !== null) {
                $errors[] = t('val.username_taken');
            } else {
                db_query('UPDATE users SET username = ?, username_lower = ? WHERE id = ?', [
                    $new, strtolower($new), (int)$user['id'],
                ]);
                log_action('user_renamed', 'user', (int)$user['id'], (string)$user['username'] . ' -> ' . $new);
                unset($GLOBALS['FD_CURRENT_USER']);
                flash(t('account.profile_saved'), 'ok');
                redirect(url('account/profile.php'));
            }
        } else {
            flash(t('account.nothing_changed'), 'ok');
            redirect(url('account/profile.php'));
        }
    } elseif ($action === 'password') {
        $new     = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['new_password2'] ?? '');
        if ($confirm !== $new) {
            $errors[] = t('val.password_mismatch');
        } else {
            $problem = password_problem($new, (string)$user['username'], (string)$user['email']);
            if ($problem !== '') {
                $errors[] = t($problem);
            } else {
                db_query('UPDATE users SET password_hash = ? WHERE id = ?', [
                    password_hash($new, PASSWORD_DEFAULT), (int)$user['id'],
                ]);
                // The column only exists once upgrade-github.sql was imported.
                if (github_columns_ready()) {
                    db_query('UPDATE users SET has_password = 1 WHERE id = ?', [(int)$user['id']]);
                }
                log_action('user_password_changed', 'user', (int)$user['id'], (string)$user['username']);
                flash(t('account.password_changed'), 'ok');
                redirect(url('account/profile.php'));
            }
        }
    } elseif ($action === 'email') {
        $new = trim((string)($_POST['email'] ?? ''));
        if (!valid_email_addr($new)) {
            $errors[] = t('val.email_invalid');
        } else {
            $taken = db_one('SELECT id FROM users WHERE email_lower = ? AND id <> ?', [strtolower($new), (int)$user['id']]);
            if ($taken !== null) {
                $errors[] = t('val.user_email_taken');
            } elseif (strcasecmp($new, (string)$user['email']) === 0) {
                flash(t('account.nothing_changed'), 'ok');
                redirect(url('account/profile.php'));
            } else {
                db_query('UPDATE users SET email = ?, email_lower = ? WHERE id = ?', [
                    $new, strtolower($new), (int)$user['id'],
                ]);
                $fresh = db_one('SELECT * FROM users WHERE id = ?', [(int)$user['id']]);
                if ($fresh !== null) {
                    user_verify_send($fresh);
                }
                log_action('user_email_changed', 'user', (int)$user['id'], (string)$user['email'] . ' -> ' . $new);
                flash(t('account.email_changed', h($new)), 'ok');
                redirect(url('account/profile.php'));
            }
        }
    } elseif ($action === 'verify') {
        if (user_verify_send($user)) {
            flash(t('account.verify_resent'), 'ok');
        } else {
            flash(t('account.verify_mail_failed'), 'error');
        }
        redirect(url('account/profile.php'));
    } elseif ($action === 'unlink_github') {
        if (!github_available()) {
            $errors[] = t('val.github_off');
        } elseif (trim((string)($user['github_id'] ?? '')) === '') {
            $errors[] = t('val.github_not_linked');
        } elseif (!user_has_password($user)) {
            // Without a password and without GitHub the account could never be opened again.
            $errors[] = t('val.github_unlink_needs_password');
        } else {
            github_unlink_user((int)$user['id'], (string)($user['github_login'] ?? ''));
            unset($GLOBALS['FD_CURRENT_USER']);
            flash(t('account.github_unlinked'), 'ok');
            redirect(url('account/profile.php'));
        }
    } elseif ($action === 'delete') {
        if (!isset($_POST['confirm_delete'])) {
            $errors[] = t('val.user_delete_confirm');
        } else {
            $name = (string)$user['username'];
            db_query('DELETE FROM users WHERE id = ?', [(int)$user['id']]);
            log_action('user_deleted', 'user', (int)$user['id'], $name . ' <' . (string)$user['email'] . '>');
            user_logout();
            flash(t('account.deleted'), 'ok');
            redirect(url('index.php'));
        }
    }
}

$domains = users_table_ready() ? user_domains($user) : [];
$unverified = trim((string)($user['verify_token'] ?? '')) !== '';

// An account created through GitHub has no local password yet, so those forms ask for nothing.
$passwordRows = static function (string $id) use ($user): string {
    if (!user_has_password($user)) {
        return '';
    }

    return '<label for="' . $id . '">' . h(t('account.current_password')) . '</label>'
        . '<input type="password" id="' . $id . '" name="current_password" maxlength="200" required>';
};

page_header(t('account.profile_heading'));
?>
<p><?= t('account.profile_intro', h((string)$user['username'])) ?></p>

<?= error_list($errors) ?>

<?php if ($unverified): ?>
<div class="msg info">
  <?= t('account.email_unverified', h((string)$user['email'])) ?>
  <form method="post" action="<?= h(url('account/profile.php')) ?>" style="margin-top:.5em">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="verify">
    <?= $passwordRows('vpass') ?>
    <input type="submit" value="<?= h(t('account.verify_resend')) ?>">
  </form>
</div>
<?php endif; ?>

<h3><?= h(t('account.username')) ?></h3>
<form method="post" action="<?= h(url('account/profile.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="username">
<label for="username"><?= h(t('account.new_username')) ?></label>
<input type="text" id="username" name="username" value="<?= h((string)$user['username']) ?>" maxlength="32">
<p class="hint"><?= t('account.username_hint') ?></p>
<?= $passwordRows('upass') ?>
<p><input type="submit" value="<?= h(t('account.save')) ?>"></p>
</form>

<h3><?= h(t('account.change_password')) ?></h3>
<form method="post" action="<?= h(url('account/profile.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="password">
<?= $passwordRows('ppass') ?>
<label for="new_password"><?= h(t('account.new_password')) ?></label>
<input type="password" id="new_password" name="new_password" maxlength="200" required>
<p class="hint"><?= h(t('account.password_hint')) ?></p>
<label for="new_password2"><?= h(t('account.password_repeat')) ?></label>
<input type="password" id="new_password2" name="new_password2" maxlength="200" required>
<p><input type="submit" value="<?= h(t('account.save')) ?>"></p>
</form>

<h3><?= h(t('account.change_email')) ?></h3>
<p class="hint"><?= h(t('account.email_change_warning', (string)count($domains), (string)$user['email'])) ?></p>
<form method="post" action="<?= h(url('account/profile.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="email">
<label for="email"><?= h(t('account.new_email')) ?></label>
<input type="text" id="email" name="email" value="<?= h((string)$user['email']) ?>" maxlength="190">
<?= $passwordRows('epass') ?>
<p><input type="submit" value="<?= h(t('account.save')) ?>"></p>
</form>

<h3><?= h(t('account.github_heading')) ?></h3>
<?php if (trim((string)($user['github_id'] ?? '')) !== ''): ?>
<p><?= t('account.github_linked', h((string)($user['github_login'] ?? '')), h((string)($user['github_linked_at'] ?? ''))) ?></p>
<?php if (!user_has_password($user)): ?>
<p class="hint"><?= h(t('account.github_no_password_hint')) ?></p>
<?php endif; ?>
<form method="post" action="<?= h(url('account/profile.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="unlink_github">
<?= $passwordRows('gpass') ?>
<p><input type="submit" value="<?= h(t('account.github_unlink')) ?>"></p>
</form>
<?php else: ?>
<p><?= h(t('account.github_not_linked')) ?></p>
<p><?php if (github_available()): ?><a href="<?= h(url('account/github.php?action=link')) ?>"><?= h(t('account.github_link')) ?></a><?php else: ?><?= h(t('val.github_off')) ?><?php endif; ?></p>
<?php endif; ?>

<h3><?= h(t('account.delete_heading')) ?></h3>
<div class="msg error">
  <p><?= t('account.delete_warning', (string)count($domains)) ?></p>
  <form method="post" action="<?= h(url('account/profile.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete">
    <p>
      <input type="checkbox" id="confirm_delete" name="confirm_delete" value="1">
      <label class="inline" for="confirm_delete"><?= h(t('account.delete_confirm')) ?></label>
    </p>
    <?= $passwordRows('dpass') ?>
    <p><input type="submit" value="<?= h(t('account.delete_submit')) ?>"></p>
  </form>
</div>

<p>
<a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.console_heading')) ?></a> &middot;
<a href="<?= h(url('account/logout.php')) ?>"><?= h(t('nav.signout')) ?></a>
</p>
<?php
page_footer();

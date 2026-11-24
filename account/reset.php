<?php
declare(strict_types=1);

/**
 * freedns.my - sets a new password from a reset link.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();

$userId = (int)($_GET['u'] ?? $_POST['u'] ?? 0);
$token  = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$user   = user_by_reset_token($userId, $token);
$errors = [];
$done   = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user !== null) {
    csrf_verify();

    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password2'] ?? '');

    if ($confirm !== $password) {
        $errors[] = t('val.password_mismatch');
    } else {
        $error = user_reset_apply($user, $token, $password);
        if ($error === '') {
            $done = true;
        } else {
            $errors[] = t($error);
        }
    }
}

page_header(t('account.reset_heading'));

if ($done):
?>
<div class="msg ok"><strong><?= h(t('account.reset_done_title')) ?></strong></div>
<p><?= t('account.reset_done_text', '<a href="' . h(url('account/login.php')) . '">'
    . h(t('account.login_heading')) . '</a>') ?></p>
<?php
elseif ($user === null):
?>
<div class="msg error"><strong><?= h(t('account.reset_bad_title')) ?></strong></div>
<p><?= t('account.reset_bad_text', '<a href="' . h(url('account/forgot.php')) . '">'
    . h(t('account.forgot_heading')) . '</a>') ?></p>
<?php
else:
?>
<p><?= t('account.reset_intro', h((string)$user['username'])) ?></p>

<?= error_list($errors) ?>

<form method="post" action="<?= h(url('account/reset.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="u" value="<?= (int)$user['id'] ?>">
<input type="hidden" name="token" value="<?= h($token) ?>">

<label for="password"><?= h(t('account.password')) ?></label>
<input type="password" id="password" name="password" maxlength="200" autocomplete="new-password" required>
<p class="hint"><?= h(t('account.password_hint')) ?></p>

<label for="password2"><?= h(t('account.password_repeat')) ?></label>
<input type="password" id="password2" name="password2" maxlength="200" autocomplete="new-password" required>

<p><input type="submit" value="<?= h(t('account.reset_submit')) ?>"></p>
</form>
<?php
endif;

page_footer();

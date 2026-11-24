<?php
declare(strict_types=1);

/**
 * freedns.my - asks for a password reset link. The answer never reveals whether an address is
 * registered.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();

$sent  = false;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim((string)($_POST['email'] ?? ''));
    user_reset_send($email);
    $sent = true;
}

page_header(t('account.forgot_heading'));

if ($sent):
?>
<div class="msg ok"><strong><?= h(t('account.forgot_sent_title')) ?></strong></div>
<p><?= t('account.forgot_sent_text', h($email)) ?></p>
<p>
<a href="<?= h(url('account/login.php')) ?>"><?= h(t('account.login_heading')) ?></a> &middot;
<a href="<?= h(url('account/register.php')) ?>"><?= h(t('account.register_heading')) ?></a>
</p>
<?php
else:
?>
<p><?= h(t('account.forgot_intro')) ?></p>
<form method="post" action="<?= h(url('account/forgot.php')) ?>">
<?= csrf_field() ?>
<label for="email"><?= h(t('account.email')) ?></label>
<input type="text" id="email" name="email" maxlength="190" autocomplete="email" required>
<p><input type="submit" value="<?= h(t('account.forgot_submit')) ?>"></p>
</form>
<p><a href="<?= h(url('account/login.php')) ?>"><?= h(t('account.login_heading')) ?></a></p>
<?php
endif;

page_footer();

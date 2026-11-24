<?php
declare(strict_types=1);

/**
 * freedns.my - console sign-in. The old "request code + e-mail" channel needs no account and
 * keeps working; this is only the console.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();

if (current_user() !== null) {
    redirect(url('account/index.php'));
}

$login     = '';
$errors    = [];
$resendFor = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? 'login');

    if ($action === 'resend') {
        $email = trim((string)($_POST['email'] ?? ''));
        $user  = $email === '' ? null : user_by_login($email);
        if ($user !== null && (string)$user['status'] === 'pending' && user_verify_send($user)) {
            flash(t('account.resend_done', h((string)$user['email'])), 'ok');
        } else {
            flash(t('account.resend_failed'), 'error');
        }
        redirect(url('account/login.php'));
    }

    $login    = trim((string)($_POST['login'] ?? ''));
    $password = (string)($_POST['password'] ?? '');

    if ($login === '' || $password === '') {
        $errors[] = t('val.user_credentials');
    } else {
        $result = user_login($login, $password);
        if (!empty($result['ok'])) {
            $target = safe_relative_url((string)($_SESSION['after_user_login'] ?? ''), url('account/index.php'));
            unset($_SESSION['after_user_login']);
            flash(t('account.welcome_back'), 'ok');
            redirect($target);
        }

        $errors[] = t((string)$result['error']);
        if ((string)$result['error'] === 'val.user_pending') {
            $resendFor = (string)($result['user']['email'] ?? '');
        }
    }
}

page_header(t('account.login_heading'));
?>
<p><?= h(t('account.login_intro')) ?></p>

<?= error_list($errors) ?>

<?php if (!users_table_ready()): ?>
<div class="msg error"><?= h(t('val.user_no_table')) ?></div>
<?php endif; ?>

<?php if ($resendFor !== null): ?>
<div class="msg info">
  <p><?= t('account.pending_notice', h($resendFor)) ?></p>
  <form method="post" action="<?= h(url('account/login.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="resend">
    <input type="hidden" name="email" value="<?= h($resendFor) ?>">
    <p><input type="submit" value="<?= h(t('account.resend_submit')) ?>"></p>
  </form>
</div>
<?php endif; ?>

<form method="post" action="<?= h(url('account/login.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="login">

<label for="login"><?= h(t('account.login_id')) ?></label>
<input type="text" id="login" name="login" value="<?= h($login) ?>" maxlength="190" autocomplete="username" required>
<p class="hint"><?= h(t('account.login_id_hint')) ?></p>

<label for="password"><?= h(t('account.password')) ?></label>
<input type="password" id="password" name="password" maxlength="200" autocomplete="current-password" required>

<p><input type="submit" value="<?= h(t('account.login_submit')) ?>"></p>
</form>

<?php if (github_available()): ?>
<h3><?= h(t('account.github_heading')) ?></h3>
<p><?= h(t('account.github_login_hint')) ?></p>
<p>
  <a href="<?= h(url('account/github.php')) ?>"><?= h(t('account.github_signin')) ?></a>
  <?php if (setting_bool('github_allow_signup', true)): ?>
  &middot; <?= h(t('account.github_or')) ?>
  <a href="<?= h(url('account/github.php')) ?>"><?= h(t('account.github_signup')) ?></a>
  <?php endif; ?>
</p>
<?php endif; ?>

<p>
<?php if (vcci_configured() && vcci_columns_ready()): ?>
<h3><?= h(is_zh() ? '用 vc.ci 登录' : 'Sign in with vc.ci') ?></h3>
<p><?= h(is_zh()
    ? '如果你有 vc.ci 账户，可以直接用它登录本站；第一次登录会自动建号，之后可在控制台绑定或解绑。'
    : 'If you have a vc.ci account you can sign in with it. The first sign-in creates a local account.') ?></p>
<p><a href="<?= h(url('account/vcci-callback.php?do=login')) ?>"><?= h(is_zh() ? '用 vc.ci 登录' : 'Sign in with vc.ci') ?></a></p>
<?php endif; ?>

<a href="<?= h(url('account/register.php')) ?>"><?= h(t('account.no_account')) ?></a> &middot;
<a href="<?= h(url('account/forgot.php')) ?>"><?= h(t('account.forgot_link')) ?></a>
</p>

<h3><?= h(t('account.old_channel_heading')) ?></h3>
<p><?= t('account.old_channel_text',
    '<a href="' . h(url('status.php')) . '">' . h(t('nav.status')) . '</a>',
    '<a href="' . h(url('manage.php')) . '">' . h(t('nav.manage')) . '</a>') ?></p>
<?php
page_footer();

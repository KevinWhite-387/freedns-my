<?php
declare(strict_types=1);

/**
 * freedns.my - confirms the e-mail address of a console account.
 *
 * The confirmation runs on POST on purpose: mail scanners and link previewers fetch the URL in
 * the message, and a GET that consumed the token would leave the real click with a dead link.
 *
 * The first visit therefore shows a single button instead of an error - the wording used to say
 * "verification failed", which made a perfectly good link look broken.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();

$userId = (int)($_GET['u'] ?? $_POST['u'] ?? 0);
$token  = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
$zh     = function_exists('lang') && is_zh();
$done   = false;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $done = user_verify($userId, $token);
    if ($done && current_user() === null) {
        // Sign the fresh account in right away.
        $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
        if ($user !== null && (string)$user['status'] === 'active') {
            session_regenerate_id(true);
            $_SESSION[FD_USER_SESSION] = (int)$user['id'];
            unset($GLOBALS['FD_CURRENT_USER']);
        }
    }
}

$linkLooksOk = $userId > 0 && $token !== '';

page_header(t('account.verify_heading'));

if ($done):
    ?>
<div class="msg ok"><strong><?= h(t('account.verify_ok')) ?></strong></div>
<p><?= t('account.verify_ok_text', '<a href="' . h(url('account/index.php')) . '">'
    . h(t('account.console_heading')) . '</a>', '<a href="' . h(url('account/login.php')) . '">'
    . h(t('account.login_heading')) . '</a>') ?></p>
    <?php
elseif ($linkLooksOk):
    /* A good link, one click short of being confirmed. */
    ?>
<div class="msg info"><strong><?= h($zh ? '还差一步：请点击下面的按钮完成邮箱验证。'
    : 'One more step: press the button below to confirm your e-mail address.') ?></strong></div>
<p class="small"><?= h($zh
    ? '这一步是为了防止邮箱服务商自动打开链接时把你的验证码用掉，所以需要你亲自点一下。'
    : 'This step exists so that a mail scanner opening the link cannot use up your confirmation code.') ?></p>
<form method="post" action="<?= h(url('account/verify.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="u" value="<?= (int)$userId ?>">
  <input type="hidden" name="token" value="<?= h($token) ?>">
  <p><input type="submit" value="<?= h($zh ? '确认验证 / Confirm' : 'Confirm now') ?>"></p>
</form>
<p class="small"><?= h($zh
    ? '如果你已经验证过，直接去登录即可。'
    : 'If you already confirmed this address, simply sign in.') ?></p>
<p>
<a href="<?= h(url('account/login.php')) ?>"><?= h(t('account.login_heading')) ?></a> &middot;
<a href="<?= h(url('account/forgot.php')) ?>"><?= h(t('account.forgot_heading')) ?></a>
</p>
    <?php
else:
    /* No usable token: the link is truncated or the account is already active. */
    ?>
<div class="msg error"><strong><?= h(t('account.verify_fail')) ?></strong></div>
<p><?= h(t('account.verify_fail_text')) ?></p>
<p class="small"><?= h($zh
    ? '可能是链接被截断了，或者这个邮箱已经验证过。可以重新登录试试，或者让系统重发一封验证邮件。'
    : 'The link may be truncated, or the address is already confirmed. Try signing in, or ask for a new mail.') ?></p>
<p>
<a href="<?= h(url('account/login.php')) ?>"><?= h(t('account.login_heading')) ?></a> &middot;
<a href="<?= h(url('account/forgot.php')) ?>"><?= h(t('account.forgot_heading')) ?></a>
</p>
    <?php
endif;

page_footer();
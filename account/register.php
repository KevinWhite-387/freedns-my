<?php
declare(strict_types=1);

/**
 * freedns.my - registrant registration for the user console.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();

if (current_user() !== null) {
    redirect(url('account/index.php'));
}

$values = ['username' => '', 'email' => '', 'invite' => ''];
$errors = [];
$done   = false;
$mailed = false;

/*
 * ------------------------------------------------------------------ the door, before anything else
 *
 * Registration is the one action that must be impossible while the registry is closed, and it has to hold no
 * matter how the visitor got here: the plain form, a hand-made POST, a language wrapper, an admin who is exempt
 * from the maintenance page elsewhere. So the refusal lives here, above the form and above the handler, and it
 * does not care which switch was used - the holding page (hold_mode) or maintenance mode (maintenance_on) both
 * mean the same thing to somebody trying to open an account.
 *
 * Unlike the rest of the site this blocks the operator too, on purpose: "can I still register?" must have one
 * answer, and the way to test registration is to switch maintenance off.
 */
$fdBlocked = '';
if (function_exists('maintenance_active') && maintenance_active()) {
    $fdBlocked = 'maintenance';
} elseif (setting_bool('hold_mode', false)) {
    $fdBlocked = 'hold';
} elseif (!setting_bool('user_registration_open', true)) {
    $fdBlocked = 'closed';
}

if ($fdBlocked !== '') {
    $zh = function_exists('lang') && is_zh();

    if ($fdBlocked === 'closed') {
        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('X-Robots-Tag: noindex');
        }
    } else {
        if (!headers_sent()) {
            http_response_code(503);
            header('Cache-Control: no-store');
            header('Retry-After: 600');
            header('X-Robots-Tag: noindex');
        }
        if (function_exists('log_action')) {
            log_action('register_blocked', 'user', null,
                'refused: registration during ' . $fdBlocked . ' mode');
        }
    }

    page_header($zh ? '暂时不能注册' : 'Registration is closed for now');
    echo '<div class="msg error"><strong>'
        . h($zh ? '暂时不能注册新账户' : 'New accounts cannot be opened at the moment') . "</strong><br>\n";
    if ($fdBlocked === 'closed') {
        echo nl2br(h($zh
            ? '本站目前暂停接受新注册。已有账户不受影响，可以正常登录。恢复时间请留意首页公告。'
            : 'This registry is not taking new registrations at the moment. Existing accounts are unaffected and '
              . 'can sign in as usual; watch the front page for the date it reopens.'));
    } else {
        echo nl2br(h($zh
            ? '本站正在维护，维护期间不创建新账户。已有账户仍可登录，并管理已有域名的解析记录、使用已有邮箱。'
            : 'The registry is under maintenance, and no new account is created while it lasts. Existing accounts '
              . 'can still sign in, change the records of names they hold, and use their mailboxes.'));
        $window = function_exists('maintenance_window') ? maintenance_window() : '';
        $reason = function_exists('maintenance_reason') ? maintenance_reason() : '';
        if ($window !== '') {
            echo '<br>' . h(($zh ? '维护时间：' : 'Window: ') . $window);
        }
        if ($reason !== '') {
            echo '<br>' . h(($zh ? '原因：' : 'Reason: ') . $reason);
        }
    }
    echo "</div>\n";

    echo '<p>' . h($zh
        ? '如果你是站长，并且想测试注册流程：维护期间注册对所有人关闭（包括你），请先在后台关闭维护，再测试。'
        : 'If you are the operator and want to test the flow: registration is closed for everybody during '
          . 'maintenance, you included. Switch maintenance off first.') . "</p>\n";

    echo '<p><a href="' . h(url('account/login.php')) . '">' . h($zh ? '已有账户，去登录' : 'I have an account - sign in')
        . '</a> &middot; <a href="' . h(url('index.php')) . '">' . h($zh ? '返回首页' : 'Back to the front page')
        . '</a> &middot; <a href="' . h(url('status.php')) . '">' . h($zh ? '站点状态' : 'Site status') . "</a></p>\n";
    page_footer();
    exit;
}

/*
 * Daily allowance. 45 accounts a day by default, set with the "register_daily_limit" setting
 * (0 removes the cap). The block sits next to the maintenance one for the same reason: the form
 * must not even render when there is nothing left to give out today.
 */
if (function_exists('user_registrations_full') && user_registrations_full()) {
    $zh    = function_exists('lang') && is_zh();
    $limit = user_register_daily_limit();
    $used  = user_registrations_today();
    $mid   = date('Y-m-d H:i:s', strtotime('tomorrow'));

    if (!headers_sent()) {
        http_response_code(429);
        header('Cache-Control: no-store');
        header('Retry-After: ' . max(60, strtotime('tomorrow') - time()));
        header('X-Robots-Tag: noindex');
    }
    if (function_exists('log_action')) {
        log_action('register_daily_full', 'user', null, 'page shown: ' . $used . '/' . $limit);
    }

    page_header($zh ? '今日名额已满' : 'Today\'s allowance is used up');
    echo '<div class="msg error"><strong>'
        . h($zh ? '今日名额已满，请明天再试试看' : 'Today\'s allowance is used up - please try again tomorrow')
        . "</strong><br>\n";
    echo nl2br(h($zh
        ? '本站每天最多创建 ' . $limit . ' 个新账户，今天已经用完（' . $used . '/' . $limit . '）。'
          . '名额会在每天 00:00 重置，届时再来注册即可。已有账户不受影响，可以正常登录。'
        : 'This registry opens at most ' . $limit . ' new accounts per day, and today\'s are gone ('
          . $used . '/' . $limit . '). The allowance resets at midnight server time ('
          . $mid . '). Existing accounts are unaffected and can sign in as usual.'));
    echo "</div>\n";
    echo '<p><a href="' . h(url('account/login.php')) . '">' . h($zh ? '已有账户，去登录' : 'I have an account - sign in')
        . '</a> &middot; <a href="' . h(url('index.php')) . '">' . h($zh ? '返回首页' : 'Back to the front page')
        . "</a></p>\n";
    page_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach (array_keys($values) as $field) {
        $values[$field] = trim((string)($_POST[$field] ?? ''));
    }
    $password = (string)($_POST['password'] ?? '');
    $confirm  = (string)($_POST['password2'] ?? '');

    if (trim((string)($_POST['website'] ?? '')) !== '') {
        $errors[] = t('val.spam');
    }
    if (!setting_bool('user_registration_open', true)) {
        $errors[] = t('val.user_closed');
    }
    if (!users_table_ready()) {
        $errors[] = t('val.user_no_table');
    }
    $limit = max(1, (int)setting('max_user_registrations_per_ip_per_day', '3'));
    if (user_registrations_from_ip(client_ip()) >= $limit) {
        $errors[] = t('val.user_limit');
    }
    if (function_exists('user_registrations_full') && user_registrations_full()) {
        $errors[] = t('val.register_daily_full');
    }

    if ($confirm !== $password) {
        $errors[] = t('val.password_mismatch');
    }
    if (!isset($_POST['policy'])) {
        $errors[] = t('val.user_policy');
    }


    if ($errors === []) {
    // Invitation gate. Runs before anything is written, and the code is spent only after the account
    // exists, so a failed registration never burns an invitation.
    $inviteRow = null;
    if (invite_required()) {
        $checkInvite = invite_check((string)$values['invite']);
        if (!$checkInvite['ok']) {
            $errors[] = t($checkInvite['error']);
        } else {
            $inviteRow = $checkInvite['row'];
        }
    }

        $result = user_register($values['username'], $values['email'], $password, lang());
        if (!empty($result['ok'])) {
            $done   = true;
            if (!empty($inviteRow)) {
                invite_consume((int)$inviteRow['id']);
                try {
                    db_query('UPDATE users SET invite_used = ? WHERE id = ?',
                        [(string)$inviteRow['code'], (int)$result['user']['id']]);
                } catch (Throwable $e) {
                    // the account exists; a missing column must not undo the registration
                }
                log_action('invite_used', 'user', (int)$result['user']['id'],
                    (string)$inviteRow['code'] . ' by ' . (string)$values['username']);
            }
            $mailed = !empty($result['mailed']);
        } else {
            $errors[] = t((string)$result['error']);
        }
    }
}

page_header(t('account.register_heading'));

if ($done):
?>
<div class="msg ok"><strong><?= h(t('account.registered_title')) ?></strong></div>
<p><?= t('account.registered_body', h($values['email'])) ?></p>
<?php if (!$mailed): ?>
<div class="msg error"><?= h(t('account.verify_mail_failed')) ?></div>
<?php endif; ?>
<p><?= t('account.registered_next', '<a href="' . h(url('account/login.php')) . '">'
    . h(t('account.login_heading')) . '</a>') ?></p>
<?php
else:
?>
<p><?= h(t('account.register_intro')) ?></p>

<?= error_list($errors) ?>

<?php if (!setting_bool('user_registration_open', true)): ?>
<div class="msg error"><?= h(t('account.closed')) ?></div>
<?php endif; ?>

<form method="post" action="<?= h(url('account/register.php')) ?>">
<?= csrf_field() ?>

<label for="username"><?= h(t('account.username')) ?></label>
<input type="text" id="username" name="username" value="<?= h($values['username']) ?>" maxlength="32"
       autocomplete="username" required>
<p class="hint"><?= t('account.username_hint') ?></p>

<label for="email"><?= h(t('account.email')) ?></label>
<input type="text" id="email" name="email" value="<?= h($values['email']) ?>" maxlength="190"
<label for="invite"><?= h(t('invite.code')) ?></label>
<input type="text" id="invite" name="invite" maxlength="32" autocomplete="off"
<p class="hint">
  <?php $fdInviteUrl = trim((string)setting('invite_github_url', '')); ?>
  <?= h(function_exists('is_zh') && is_zh()
      ? '本站目前仅限邀请注册。请到 GitHub 提交申请，审批通过后我们会把一次性邀请码发到你填写的邮箱。'
      : 'Registration is by invitation only. Request one on GitHub and we will mail a single-use code to the address you give there.') ?>
  <?php if ($fdInviteUrl !== ''): ?>
  <br><a href="<?= h($fdInviteUrl) ?>" rel="nofollow noopener" target="_blank"><?= h($fdInviteUrl) ?></a>
  <?php endif; ?>
</p>
<p class="hint"><?= h(t('invite.code_hint')) ?></p>

       autocomplete="email" required>
<p class="hint"><?= t('account.email_hint') ?></p>

<label for="password"><?= h(t('account.password')) ?></label>
<input type="password" id="password" name="password" maxlength="200" autocomplete="new-password" required>
<p class="hint"><?= h(t('account.password_hint')) ?></p>

<label for="password2"><?= h(t('account.password_repeat')) ?></label>
<input type="password" id="password2" name="password2" maxlength="200" autocomplete="new-password" required>

<p>
  <input type="checkbox" id="policy" name="policy" value="1"<?= isset($_POST['policy']) ? ' checked' : '' ?>>
  <label class="inline" for="policy"><?= t(
      'request.policy_accept',
      '<a href="' . h(url('doc.php')) . '" target="_blank">' . h(t('request.policy_link')) . '</a>'
  ) ?></label>
</p>


<div style="position:absolute;left:-9999px" aria-hidden="true">
  <label for="website">Leave this field empty</label>
  <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
</div>

<p><input type="submit" value="<?= h(t('account.register_submit')) ?>"></p>
</form>

<?php if (github_available() && setting_bool('github_allow_signup', true)): ?>
<h3><?= h(t('account.github_heading')) ?></h3>
<p><?= h(t('account.github_signup_hint')) ?></p>
<p><a href="<?= h(url('account/github.php')) ?>"><?= h(t('account.github_signup')) ?></a></p>
<?php endif; ?>

<p>
<a href="<?= h(url('account/login.php')) ?>"><?= h(t('account.have_account')) ?></a> &middot;
<a href="<?= h(url('status.php')) ?>"><?= h(t('nav.status')) ?></a>
</p>
<?php
endif;

page_footer();

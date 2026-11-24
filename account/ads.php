<?php
declare(strict_types=1);

/**
 * freedns.my - console: advertising (a personal setting).
 *
 * A signed-in holder may switch the advertising off for himself. Visitors cannot, and that is on purpose: for
 * a visitor the choice would have to live in a cookie, and this site does not set a cookie just to hide an
 * advertisement. For a holder it is remembered in his session, so it lasts while he is signed in.
 *
 * What it does: the two side rails, the box under the application form and the box inside the console all
 * disappear. Nothing else changes - the site keeps working exactly the same, and the advertising slots are
 * still there for everybody else.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user = require_user();
$zh   = function_exists('lang') && is_zh();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $want = strtolower((string)($_POST['action'] ?? ''));

    if ($want === 'hide') {
        $_SESSION['fd_hide_ads'] = 1;
        log_action('ads_hidden', 'user', (int)$user['id'], 'advertising switched off by the holder himself');
        flash($zh ? '已关闭广告显示。' : 'Advertising is now hidden.', 'ok');
        redirect(url('account/ads.php'));
    }
    if ($want === 'show') {
        unset($_SESSION['fd_hide_ads']);
        log_action('ads_shown', 'user', (int)$user['id'], 'advertising switched back on by the holder himself');
        flash($zh ? '已恢复显示广告。' : 'Advertising is shown again.', 'ok');
        redirect(url('account/ads.php'));
    }
}

$hidden = function_exists('ads_hidden') && ads_hidden();

page_header($zh ? '广告显示' : 'Advertising');
?>
<p><?= $zh
    ? '本站的广告位用于支付服务器、DNS 与邮件服务的开销。广告不影响任何审核结果，也不会追踪你。'
    : 'The advertising on this site pays for the servers, the DNS service and the mail service. It has no '
      . 'influence on any review, and it does not track you.' ?></p>

<div class="msg <?= $hidden ? 'info' : 'ok' ?>">
  <?= $hidden
      ? ($zh ? '你已关闭广告显示。' : 'Advertising is currently hidden for you.')
      : ($zh ? '广告当前正常显示。' : 'Advertising is currently shown.') ?>
</div>

<h2><?= $zh ? '你的选择' : 'Your choice' ?></h2>
<?php if ($hidden): ?>
<p><?= $zh
    ? '要重新看到广告（也就等于继续支持这个免费项目）请点下面的按钮。'
    : 'Press the button below to see the advertising again - which is also what keeps this free project going.' ?></p>
<form method="post" action="<?= h(url('account/ads.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="show">
<p><input type="submit" value="<?= $zh ? '恢复显示广告' : 'Show advertising again' ?>"></p>
</form>
<?php else: ?>
<p><?= $zh
    ? '如果你不想看到广告，可以在这里关掉。关掉之后：两侧固定栏、申请页表单下方与控制台中部的那块都会消失，'
      . '其余功能完全不受影响。'
    : 'If you would rather not see advertising, switch it off here. What disappears: the two side rails, the box '
      . 'under the application form, and the one inside the console. Nothing else changes.' ?></p>
<form method="post" action="<?= h(url('account/ads.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="hide">
<p><input type="submit" value="<?= $zh ? '关闭广告显示' : 'Hide advertising' ?>"></p>
</form>
<?php endif; ?>

<h2><?= $zh ? '说明' : 'Good to know' ?></h2>
<ul>
  <li><?= $zh ? '这个选择记在当前登录会话里：登录期间一直有效，退出登录后再打开站点会恢复显示。'
               : 'The choice is kept in your current signed-in session: it holds while you are signed in, and '
                 . 'after signing out the site shows advertising again.' ?></li>
  <li><?= $zh ? '只有登录用户能关闭：访客要记住这个选择就得写 Cookie，而本站不为隐藏广告而写 Cookie。'
               : 'Only a signed-in holder can switch it off: for a visitor the choice would need a cookie, and '
                 . 'this site does not set one just to hide an advertisement.' ?></li>
  <li><?= $zh ? '关闭广告不会减少任何配额，也不会影响你的域名、邮箱或服务器功能。'
               : 'Hiding advertising costs you no quota and changes nothing about your names, mailboxes or '
                 . 'servers.' ?></li>
  <li><?= $zh ? '广告主的钱照付，只是你看不到；如果你愿意让广告显示，就是对项目最直接的支持。'
               : 'Advertisers pay either way and simply are not shown to you; leaving them on is the most direct '
                 . 'way to support the project.' ?></li>
</ul>
<p class="hint"><?= $zh
    ? '想长期保存这个偏好（换设备也生效）告我们一声，我们把它记到账号里。'
    : 'Want this remembered across devices? Tell us and we store it on the account instead.' ?></p>
<?php
page_footer();

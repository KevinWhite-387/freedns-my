<?php
declare(strict_types=1);

/**
 * freedns.my - the user console: every name of this account in one place.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();

$user = require_user();
shop_attribute_user($user);
$user = current_user() ?? $user;

if (!users_table_ready()) {
    page_header(t('account.console_heading'));

    echo '<div class="msg error">' . h(t('val.user_no_table')) . '</div>';
    page_footer();
    exit;
}

$domains = user_domains($user);

$statusCount = [];
$counts = ['all' => 0, 'active' => 0, 'pending' => 0, 'changes' => 0];
foreach ($domains as $domain) {
    $counts['all']++;
    $status = (string)$domain['status'];
    if ($status === 'active') {
        $counts['active']++;
    }
    if ($status === 'pending') {
        $counts['pending']++;
    }
    $counts['changes'] += (int)$domain['change_count'];
    $statusCount[(string)$status] = ($statusCount[(string)$status] ?? 0) + 1;
}

$pendingVerification = trim((string)($user['verify_token'] ?? '')) !== '';

page_header(t('account.console_heading'));
?>
<?php $fdZhC = function_exists('lang') && is_zh(); ?>
<?php /* The five-item menu hides the rest; the console surfaces it here. */ ?>
<p class="small">
  <strong><?= h($fdZhC ? '我的域名' : 'My names') ?></strong>
  &middot; <a href="<?= h(url('account/manage.php')) ?>"><?= h($fdZhC ? '更改解析记录' : 'Edit records') ?></a>
  &middot; <a href="<?= h(url('account/profile.php')) ?>"><?= h($fdZhC ? '个人信息' : 'Profile') ?></a>
  &middot; <a href="<?= h(url('account/apps.php')) ?>"><?= h($fdZhC ? '开放平台绑定' : 'App bindings') ?></a>
  &middot; <a href="<?= h(url('account/api.php')) ?>"><?= h($fdZhC ? 'API 设置' : 'API settings') ?></a>
  &middot; <a href="<?= h(url('account/nameservers.php')) ?>"><?= h($fdZhC ? '名称服务器' : 'Name servers') ?></a>
</p>
<?php if ($statusCount !== []): ?>
<p class="small">
  <?= h($fdZhC ? '按状态：' : 'By status:') ?>
  <?php $fdFirst = true; foreach ($statusCount as $fdSt => $fdN): ?>
    <?php if (!$fdFirst): ?> &middot; <?php endif; ?><?php $fdFirst = false; ?>
    <a href="<?= h(url('account/index.php?status=' . urlencode((string)$fdSt))) ?>"><?= h($fdZhC ? ([ 'active' => '生效', 'pending' => '审核中', 'rejected' => '已拒绝', 'suspended' => '已暂停', 'deleted' => '已删除' ][$fdSt] ?? (string)$fdSt) : ucfirst((string)$fdSt)) ?></a>
    <?= (int)$fdN ?>
  <?php endforeach; ?>
</p>
<?php endif; ?>

<p><?= t('account.console_intro', h((string)$user['username'])) ?></p>

<?php if ($pendingVerification): ?>
<div class="msg info">
  <?= t('account.email_unverified', h((string)$user['email'])) ?>
</div>
<?php endif; ?>

<table>
  <tr><th><?= h(t('account.username')) ?></th><td><?= h((string)$user['username']) ?></td></tr>
  <tr><th><?= h(t('account.email')) ?></th><td><?= h((string)$user['email']) ?></td></tr>
  <tr><th><?= h(t('account.member_since')) ?></th><td><?= h(format_date((string)$user['created_at'])) ?></td></tr>
  <tr><th><?= h(t('account.last_login')) ?></th><td><?= h(format_date((string)$user['last_login_at'])) ?></td></tr>
  <tr><th><?= h(t('account.counts')) ?></th>
      <td><?= h(t('account.counts_value', $counts['all'], $counts['active'], $counts['pending'], $counts['changes'])) ?></td></tr>
</table>

<h3><?= h(t('account.my_domains')) ?></h3>

<?php if ($domains === []): ?>
<p><?= t('account.no_domains', h((string)$user['email'])) ?></p>
<?php else: ?>
<table>
  <tr>
    <th><?= h(t('manage.name_label')) ?></th>
    <th><?= h(t('status.zone')) ?></th>
    <th><?= h(t('status.status')) ?></th>
    <th><?= h(t('status.records')) ?></th>
    <th><?= h(t('account.col_change')) ?></th>
    <th><?= h(t('account.col_actions')) ?></th>
  </tr>
  <?php foreach ($domains as $domain): ?>
  <tr>
    <td><code><?= h((string)$domain['name']) ?></code>
        <?php if (!empty($domain['reserved'])): ?><span class="err"><?= h(t('account.reserved_mark')) ?></span><?php endif; ?>
    </td>
    <td><?= h((string)$domain['zone']) ?></td>
    <td><?= h(status_text((string)$domain['status'])) ?></td>
    <td><?= (int)$domain['record_count'] ?></td>
    <td><?= (int)$domain['change_count'] > 0
        ? '<span class="ok">' . h(t('account.queued')) . '</span>'
        : h(t('common.none')) ?></td>
    <td class="actions">
      <?php if (in_array((string)$domain['status'], ['active', 'pending'], true)
          && setting_bool('self_service', true)): ?>
      <a href="<?= h(url('account/manage.php?id=' . (int)$domain['id'])) ?>"><?= h(t('account.action_manage')) ?></a>
      <?php else: ?>
      <a href="<?= h(url('status.php')) ?>"><?= h(t('account.action_status')) ?></a>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h3><?= h(t('account.what_now')) ?></h3>
<ul>
  <li><?= t('account.link_new', '<a href="' . h(url('request.php?email=' . rawurlencode((string)$user['email'])))
      . '">' . h(t('nav.request')) . '</a>') ?></li>
  <li><?= t('account.link_profile', '<a href="' . h(url('account/profile.php')) . '">'
      . h(t('account.profile_heading')) . '</a>') ?></li>
  <li><?= t('account.link_export', '<a href="' . h(url('account/export.php')) . '">'
      . h(t('account.export_heading')) . '</a>') ?></li>
  <li><?= t('account.link_old', '<a href="' . h(url('manage.php')) . '">' . h(t('nav.manage')) . '</a>',
      '<a href="' . h(url('status.php')) . '">' . h(t('nav.status')) . '</a>') ?></li>
</ul>



<h2><?= h(t('share.heading')) ?></h2>
<p><?= h(t('share.intro')) ?></p>
<p><a href="<?= h(url('account/shared.php')) ?>"><?= h(t('share.heading')) ?></a></p>




<?php $fdZh = function_exists('is_zh') && is_zh(); ?>
<h2><?= h($fdZh ? 'vc.ci 账户' : 'vc.ci account') ?></h2>
<?php
$fdVcciId    = trim((string)($user['vcci_id'] ?? ''));
$fdVcciEmail = trim((string)($user['vcci_email'] ?? ''));
$fdVcciOn    = function_exists('vcci_configured') && vcci_configured();
?>
<?php if (!$fdVcciOn): ?>
<p class="hint"><?= h($fdZh ? 'vc.ci 单点登录尚未配置。' : 'vc.ci single sign-on is not configured yet.') ?></p>
<?php else: ?>
<p><?= $fdZh ? '状态：' : 'Status: ' ?><?= $fdVcciId !== '' ? '<strong class="ok">' . h(($fdVcciEmail !== '' ? $fdVcciEmail : $fdVcciId)) . '</strong>' : '<span class="hint">' . h($fdZh ? '未绑定' : 'not linked') . '</span>' ?></p>
<p><a href="<?= h(url('account/vcci.php')) ?>"><?= h($fdZh ? '管理绑定（绑定 / 解绑）' : 'Manage the link (link / unlink)') ?></a></p>
<?php endif; ?>

<h2><?= h($fdZh ? '域名赠与' : 'Give a name away') ?></h2>
<p><?= h($fdZh
    ? '不需要某个域名了？可以在「域名赠与」里公开挂牌，让别人填写理由申请；你审核通过后，域名会立刻转到对方的账号（此操作不可逆）。'
    : 'Done with a name? List it in "Give a name away" and let others apply with a reason. Approving moves the name to their account immediately - it cannot be undone.') ?></p>
<p><a href="<?= h(url('market-giveaway.php')) ?>"><?= h($fdZh ? '挂牌 / 查看别人的挂牌 / 审批申请' : 'List a name / see listings / decide applications') ?></a></p>

<h2><?= h(t('close.heading')) ?></h2>
<p><?= h(t('close.intro')) ?></p>
<p><a href="<?= h(url('account/close.php?export=1')) ?>"><?= h(t('close.export_link')) ?></a>
&middot; <a href="<?= h(url('account/close.php')) ?>"><?= h(t('close.close_heading')) ?></a></p>

<h2><?= h(t('totp.heading')) ?></h2>
<p><?= h(t('totp.intro')) ?></p>
<p><a href="<?= h(url('account/security.php')) ?>"><?= h(t('totp.open')) ?></a></p>


<h2><?= h(t('api.heading')) ?></h2>
<p><?= h(t('api.intro')) ?></p>
<p><a href="<?= h(url('account/api.php')) ?>"><?= h(t('api.open')) ?></a></p>

<?php
/*
 * The open platform entry. It is written inline rather than through t() on purpose: adding keys to the catalogues
 * would make every other language look "incomplete" until they were all translated, and this one section is worth
 * less than that noise. English and Chinese, like the partners page.
 */
$fdZh = function_exists('is_zh') && is_zh(); ?>

<?php
/*
 * Which interface is in force, in one line. The full explanation and the switch live on
 * account/theme.php; this is here so nobody has to guess why the site looks different elsewhere.
 */
$fdUiNow  = function_exists('theme_active') ? theme_active() : 'classic';
$fdUiName = function_exists('theme_label') ? theme_label($fdUiNow) : $fdUiNow;
?>
<h2><?= h($fdZh ? '界面' : 'Interface') ?></h2>
<p><?= $fdZh ? '你正在使用：' : 'You are using: ' ?><strong><?= h($fdUiName) ?></strong></p>
<p><a href="<?= h(url('account/theme.php')) ?>"><?= h($fdZh ? '切换界面（旧版 / 2.0）' : 'Switch interface (classic / 2.0)') ?></a></p>

<h2><?= h($fdZh ? '开放平台' : 'Open platform') ?></h2>
<p><?= h($fdZh
    ? '让别的网站用「freedns.my 账号登录」，并读取你允许的域名、解析记录或邮箱。你的密码永远不会交给对方，你可以随时断开。'
    : 'Let other sites sign users in with a freedns.my account, and read the names, records or mailboxes you '
      . 'allow. Your password is never handed over, and you can cut any of them off.') ?></p>
<p><a href="<?= h(url('account/apps.php')) ?>"><?= h($fdZh ? '管理开放平台与已连接的应用' : 'Manage the open platform and connected applications') ?></a>
&middot; <a href="<?= h(url('developers.php')) ?>"><?= h($fdZh ? '开发者文档（英文）' : 'Documentation for developers') ?></a></p>

<h2><?= h(t('ddns.heading')) ?></h2>
<p><?= h(t('ddns.intro')) ?></p>
<p><a href="<?= h(url('account/ddns.php')) ?>"><?= h(t('ddns.open')) ?></a></p>

<h2><?= h(t('chat.heading')) ?></h2>
<p><?= h(t('chat.intro')) ?></p>
<p><a href="<?= h(url('chat.php')) ?>"><?= h(t('chat.heading')) ?></a>
<?php $chatThread = chat_table_ready() ? chat_thread_for_user($user, false) : null; ?>
<?= ($chatThread !== null && (int)$chatThread['unread_user'] > 0)
    ? ' &middot; <span class="err">' . (int)$chatThread['unread_user'] . '</span>' : '' ?></p>


<h2><?= h(t('ns.heading')) ?></h2>
<p><?= h(t('ns.intro')) ?></p>
<p><a href="<?= h(url('account/nameservers.php')) ?>"><?= h(t('ns.open')) ?></a></p>

<h2><?= h(t('market.mine_heading')) ?></h2>
<p><?= h(t('market.mine_intro')) ?></p>
<p><a href="<?= h(url('account/market.php')) ?>"><?= h(t('market.new_heading')) ?></a>
&middot; <a href="<?= h(url('market.php')) ?>"><?= h(t('market.heading')) ?></a></p>

<h2><?= h(t('transfer.heading')) ?></h2>
<p><?= t('transfer.intro') ?></p>
<p><a href="<?= h(url('account/transfers.php')) ?>"><?= h(t('transfer.new_heading')) ?></a>
<?php $myTickets = transfer_list_for_email((string)$user['email']); ?>
<?= $myTickets === [] ? '' : '&middot; ' . h(t('transfer.mine')) . ' (' . count($myTickets) . ')' ?></p>

<p>
<a href="<?= h(url('account/profile.php')) ?>"><?= h(t('account.profile_heading')) ?></a> &middot;
<a href="<?= h(url('account/logout.php')) ?>"><?= h(t('nav.signout')) ?></a>
</p>
<?php
page_footer();

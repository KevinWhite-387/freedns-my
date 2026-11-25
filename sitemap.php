<?php
declare(strict_types=1);

/**
 * freedns.my - site map.
 *
 * Every page of the site, grouped so a visitor can find things instead of guessing addresses. Two layouts:
 *   * the OLD one: one plain list per area, the way the site has always looked - this is what a visitor who
 *     is not signed in gets;
 *   * the NEW one: the same links, but grouped into cards with a short explanation, and only shown to a
 *     signed-in holder, who may switch between the two.
 *
 * The list lives in one array below, so adding a page later is a one-line change. Nothing here talks to the
 * database beyond finding out whether somebody is signed in.
 */

require __DIR__ . '/inc/bootstrap.php';

$zh    = function_exists('lang') && is_zh();
$user  = function_exists('current_user') ? current_user() : null;
$style = 'new';   // the new cards are what everybody gets by default

// A signed-in holder may switch to the plain list; the choice is remembered for the session.
if ($user !== null) {
    $wanted = strtolower(trim((string)($_GET['style'] ?? '')));
    if (in_array($wanted, ['new', 'old'], true)) {
        $_SESSION['fd_sitemap_style'] = $wanted;
        $style = $wanted;
    } else {
        $style = in_array((string)($_SESSION['fd_sitemap_style'] ?? ''), ['new', 'old'], true)
            ? (string)$_SESSION['fd_sitemap_style']
            : 'new';
    }
}

/**
 * The map: area => [ title (en, zh), [ [path, label en, label zh, what it is for en, zh], ... ] ]
 */
$map = [
    'names' => [
        ['Registered names', '域名'],
        [
            ['request.php', 'Apply for a name', '申请域名', 'Ask for a free name; the registry reviews it by hand.', '申请免费域名，注册局人工审核。'],
            ['account/index.php', 'My names', '我的域名', 'The names you hold, with their state and records.', '你持有的域名、状态与记录。'],
            ['account/manage.php', 'Manage records', '管理记录', 'Add, change and delete DNS records, with TTL and proxy.', '增删改 DNS 记录，含 TTL 与代理。'],
            ['status.php', 'Check a request', '查询申请进度', 'Look up a change or a request by its code.', '用编号查询申请或变更进度。'],
            ['account/nameservers.php', 'My name servers', '我的域名服务器', 'Use your own name servers (delegation) for a suffix that allows it.', '在允许的后缀上使用你自己的域名服务器（委派）。'],
            ['account/ddns.php', 'Dynamic DNS', '动态解析', 'Keep a name pointing at a changing address.', '让域名跟随变化的 IP 地址。'],
            ['account/shared.php', 'Shared with me', '共享给我的域名', 'Names another holder let you look at (read only).', '别人共享给你查看的域名（只读）。'],
        ],
    ],
    'mail' => [
        ['Mail', '邮件'],
        [
            ['account/email.php', 'Email (new)', '我的邮箱（新版）', 'Real mailboxes on our own server: send and receive, with the settings your mail client needs.', '自建服务器上的真实邮箱：可发可收，含客户端配置。'],
            ['account/forward.php', 'Mail Forwarding (Old)', '邮件转发（旧版）', 'The old forwarding (receive only). Being retired - the new Email service is recommended.', '旧的转发（仅收信）。正在退场，推荐使用新版 Email。'],
            ['account/notify.php', 'Notification choices', '通知偏好', 'Choose which e-mails the registry sends you.', '选择注册局给你发哪些邮件。'],
        ],
    ],
    'account' => [
        ['Account', '账号'],
        [
            ['account/index.php', 'My console', '控制台首页', 'Everything about your account in one place.', '账号相关的入口汇总。'],
            ['account/security.php', 'Two-step sign-in', '两步验证', 'Optional authenticator code on top of your password.', '可选的动态验证码二次验证。'],
            ['account/api.php', 'API keys', '接口密钥', 'Manage DNS from your own scripts.', '用自己的脚本管理解析。'],
            ['account/shop.php', 'Shop and rewards', '配额商店', 'Quota, invitations and rewards.', '配额、邀请与奖励。'],
            ['account/bonus.php', 'My bonus', '我的奖励', 'What you earned and how it is used.', '已获得的奖励与使用情况。'],
            ['account/servers.php', 'Cloud servers', '我的云服务器', 'Servers the registry assigned to you, if any.', '注册局指派给你的服务器（如有）。'],
            ['account/export.php', 'Download my data', '导出我的数据', 'A copy of everything the registry holds about you.', '导出注册局保存的你的全部数据。'],
            ['account/close.php', 'Close my account', '注销账号', 'Remove the account; the audit log stays.', '注销账号（审计日志保留）。'],
        ],
    ],
    'community' => [
        ['Community', '社区'],
        [
            ['market.php', 'Domain market', '域名市场', 'Names offered by other holders, with the risk notice.', '其他持有人出售的域名，含风险声明。'],
            ['chat.php', 'Live chat', '在线咨询', 'Talk to the registry; the page refreshes when you ask it to.', '与注册局沟通；需要时点按钮刷新。'],
            ['changelog.php', 'What changed', '更新日志', 'What was added or fixed, newest first.', '新增与修复记录，最新在前。'],
            ['health.php', 'Service status', '运行状态', 'Whether publishing and the database are working.', '发布服务与数据库是否正常。'],
            ['whois.php', 'WHOIS lookup', 'WHOIS 查询', 'Look up a name through this site or the public registry.', '在本站或公共注册局查询域名。'],
        ],
    ],
    'help' => [
        ['Help and rules', '帮助与规则'],
        [
            ['doc.php', 'How it works', '使用说明', 'The rules, the limits and how records are published.', '规则、限制与记录发布方式。'],
            ['legal.php', 'Terms and privacy', '条款与隐私', 'The terms of service and what happens to your data.', '服务条款与数据处理说明。'],
            ['abuse.php', 'Report abuse', '滥用举报', 'Report a name that is used for something harmful.', '举报被用于有害用途的域名。'],
            ['sitemap.php', 'Site map', '站点地图', 'This page.', '本页。'],
            ['advertise.php', 'Advertise here', '广告投放',
             'The advertising slots, their prices, and the form to order one.',
             '本站广告位、价格与下单表单。'],
            ['jobs.php', 'Work with us', '加入我们',
             'What the project needs help with, and how to get in touch.',
             '项目需要哪些帮忙，以及如何联系我们。'],
            ['about.php', 'About us', '关于我们',
             'What this registry is, and where the money comes from and goes - donations and advertising income, '
             . 'listed one by one.',
             '这个注册局是什么，以及钱从哪来、花到哪里 —— 捐赠与广告收入逐笔公开。'],
            ['reviews.php', 'Rate this registry', '评价本站',
             'Score freedns.my from one to five stars and say why. One vote per account, changeable at any time.',
             '给 freedns.my 打 1~5 星并说明理由。每个账号一票，随时可改。'],
            ['partners.php', 'Other registries', '其它平台',
             'Other places to get a name: free second level domains, DNS hosting, dynamic DNS and commercial '
             . 'registrars - with a disclaimer, because they are not affiliated with us.',
             '其它可用的域名与 DNS 平台：免费二级域名、DNS 托管、动态 DNS 与收费注册商 —— 附免责声明，它们与本站无隶属关系。'],
            ['account/apps.php', 'Open platform', '开放平台',
             'Let other sites sign users in with a freedns.my account, and see who is connected to yours - with a '
             . 'disconnect button.',
             '让别的网站用 freedns.my 账号登录，并管理谁连着你的账号（可一键断开）。'],
            ['developers.php', 'Open platform documentation', '开放平台文档',
             'Endpoints, scopes, error codes and a curl quick start (English).',
             '接口、权限范围、错误码与 curl 快速开始（英文）。'],
        ],
    ],
    'experimental' => [
        ['Experimental', '实验性功能'],
        [
            ['experimental.php', 'Experimental features', '实验性功能',
             'What is still rough, and the rules that come with it: unstable, reported problems wanted, not guaranteed to stay.',
             '还在打磨的功能与相应规则：不稳定、欢迎上报问题、不保证长期保留。'],
            ['account/theme.php', 'New interface 1.0 (experimental)', '新版界面 1.0（实验）',
             'The control-panel look. Development has stopped (2.0 is being built) - try it with care, and one click goes back.',
             '控制台风格界面。已停止进一步开发（2.0 正在开发中），请谨慎体验，随时可一键回到旧版。'],
            ['account/api.php', 'API keys', 'API 密钥',
             'Manage DNS from your own scripts; the interface is still moving.',
             '用脚本管理解析；接口仍在调整中。'],
            ['api.php', 'API endpoint', 'API 端点',
             'The JSON endpoint programs call.', '程序调用的 JSON 接口入口。'],
        ],
    ],
];

$total = 0;
// Withdrawn features are dropped here rather than deleted from the array above: cutting lines
// out of a nested literal is how this file got a syntax error once already.
if (function_exists('retired_url')) {
    foreach ($map as $fdAreaKey => $fdArea) {
        $fdKept = [];
        foreach ((array)($fdArea[1] ?? []) as $fdItem) {
            if (!retired_url((string)($fdItem[0] ?? ''))) {
                $fdKept[] = $fdItem;
            }
        }
        $map[$fdAreaKey][1] = $fdKept;
    }
    unset($fdAreaKey, $fdArea, $fdKept, $fdItem);
}

foreach ($map as $area) {
    $total += count($area[1]);
}

page_header($zh ? '站点地图' : 'Site map');
?>
<p><?= $zh
    ? '下面是本站的全部页面，按用途分组，' . $total . ' 个链接。'
    : 'Every page of this site, grouped by what it is for - ' . $total . ' links.' ?></p>

<?php if ($user !== null): ?>
<p class="small">
  <?= $zh ? '显示方式：' : 'Showing:' ?>
  <?php if ($style === 'new'): ?>
  <strong><?= $zh ? '新版' : 'New' ?></strong> &middot;
  <a href="<?= h(url('sitemap.php?style=old')) ?>"><?= $zh ? '切换到旧版' : 'switch to the old list' ?></a>
  <?php else: ?>
  <a href="<?= h(url('sitemap.php?style=new')) ?>"><?= $zh ? '切换到新版' : 'switch to the new cards' ?></a>
  &middot; <strong><?= $zh ? '旧版' : 'Old' ?></strong>
  <?php endif; ?>
</p>
<?php else: ?>
<p class="hint"><?= $zh
    ? '这是旧版列表。登录后可以切换到新版（分类卡片 + 说明）。'
    : 'This is the old list. Once you sign in you can switch to the new one (cards with a short explanation).' ?></p>
<?php endif; ?>

<?php if ($style === 'old'): /* -------------------------------------------------- old: plain lists */ ?>
<?php foreach ($map as $area): ?>
<h2><?= h($zh ? $area[0][1] : $area[0][0]) ?></h2>
<ul>
  <?php foreach ($area[1] as $item): ?>
  <li><a href="<?= h(url($item[0])) ?>"><?= h($zh ? $item[2] : $item[1]) ?></a></li>
  <?php endforeach; ?>
</ul>
<?php endforeach; ?>

<?php else: /* --------------------------------------------------------- new: cards with a note */ ?>
<?php foreach ($map as $area): ?>
<h2><?= h($zh ? $area[0][1] : $area[0][0]) ?></h2>
<table>
  <?php foreach ($area[1] as $item): ?>
  <tr>
    <td style="white-space:nowrap"><a href="<?= h(url($item[0])) ?>"><strong><?= h($zh ? $item[2] : $item[1]) ?></strong></a></td>
    <td><?= h($zh ? $item[4] : $item[3]) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endforeach; ?>
<?php endif; ?>

<p class="hint"><?= $zh
    ? '找不到你要的功能？用页面底部的联系方式，或加入我们的群组。'
    : 'Cannot find what you need? Use the contact details in the footer, or join our groups.' ?></p>
<?php
page_footer();

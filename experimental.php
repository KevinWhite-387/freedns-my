<?php
declare(strict_types=1);

/**
 * freedns.my - experimental features.
 *
 * One place that lists what is still rough, states plainly that it may change or disappear, and asks for a
 * report when something breaks. What lives here today: the new interface and the API.
 *
 * A visitor can read this page: no session is started and no cookie is written for it.
 */

require __DIR__ . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
新版界面（1.0）
');

$zh     = function_exists('lang') && is_zh();
$user   = function_exists('current_user') ? current_user() : null;
$modern = function_exists('theme_is_modern') && theme_is_modern();

$items = [
    [
        'account/theme.php',
        $zh ? '新版界面 1.0（实验）' : 'New interface 1.0 (experimental)',
        $zh
            ? '控制台风格的新版界面，版本 <strong>1.0</strong>。<strong>已停止进一步开发</strong>（后续的 2.0 正在开发中），仅供体验：可能出现错位或显示问题，请谨慎使用，随时可以一键回到旧版。'
            : 'The control-panel style interface, version <strong>1.0</strong>. <strong>No further development</strong> is planned - version 2.0 is being built instead. It is for trying out, may look broken, and should be used with care. One click takes you back to the classic look.',
        $user === null,
    ],
    [
        'account/api.php',
        $zh ? 'API 密钥' : 'API keys',
        $zh
            ? '用你自己的脚本管理解析。接口仍在调整中，字段与返回内容可能变化，请以页面上的说明为准。'
            : 'Manage DNS from your own scripts. The interface is still moving: fields and answers may change, so follow what the page says.',
        false,
    ],
    [
        'api.php',
        $zh ? 'API 端点（程序调用）' : 'API endpoint (for programs)',
        $zh
            ? '程序调用的 JSON 接口入口。请先阅读说明与限制；接口不承诺长期兼容。'
            : 'The JSON endpoint programs call. Read the notes and limits first; long-term compatibility is not promised.',
        false,
    ],
];

page_header($zh ? '实验性功能' : 'Experimental features');
?>
<div class="msg error">
  <strong><?= $zh ? '请先阅读这段声明' : 'Please read this first' ?></strong>
  <ul>
    <li><?= $zh ? '这里的功能够不上“稳定”：可能随时出问题、行为可能变化、也可能在没有任何预告的情况下被改掉或移除。'
                : 'What is listed here is not stable: it may break, behave differently from one week to the next, or be changed and removed without notice.' ?></li>
    <li><?= $zh ? '请不要把实验性功能用在重要用途上（例如生产环境的解析、唯一的联系方式）。'
                : 'Do not rely on an experimental feature for anything important (production DNS, your only contact address).' ?></li>
    <li><?= $zh ? '遇到问题<strong>请及时上报</strong>：把页面地址、你做了什么、看到了什么写清楚，发到页脚的邮箱或群组。'
                : '<strong>Please report problems</strong>: the page address, what you did and what you saw - by mail or in the groups linked in the footer.' ?></li>
    <li><?= $zh ? '实验性功能<strong>不一定会一直保留</strong>；我们保留随时停止它们的权利。'
                : 'Experimental features are <strong>not guaranteed to stay</strong>; the registry may stop offering them at any time.' ?></li>
  </ul>
</div>

<h2><?= $zh ? '目前包含' : 'What is here today' ?></h2>
<table>
  <tr><th><?= $zh ? '功能' : 'Feature' ?></th><th><?= $zh ? '说明' : 'What it is' ?></th><th></th></tr>
  <?php foreach ($items as $item): ?>
  <tr>
    <td style="white-space:nowrap"><strong><?= h($item[1]) ?></strong></td>
    <td><?= $item[2] ?></td>
    <td class="actions">
      <?php if ($item[3]): ?>
      <span class="hint"><?= $zh ? '需要登录' : 'sign-in needed' ?></span>
      <?php endif; ?>
      <a href="<?= h(url($item[0])) ?>"><?= $zh ? '打开' : 'Open' ?></a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<h2><?= $zh ? '新版界面现在的状态' : 'Where the new interface stands' ?></h2>
<div class="msg info">
  <?= $zh
      ? '新版界面 <strong>1.0</strong> <strong>已停止进一步开发</strong>（后续的 <strong>2.0 正在开发中</strong>）：不再新增功能，已知的样式问题也不保证修复。它只对开启它的登录用户可见，访客与其他用户始终是旧版界面；顶部横幅随时可以一键回到旧版。'
      : 'New interface <strong>1.0</strong> is <strong>no longer being developed</strong> - <strong>version 2.0 is being built</strong> instead. No new features, and known layout problems are not guaranteed to be fixed. It is visible only to the signed-in holder who turned it on; visitors and everybody else keep the classic interface. The bar at the top takes you back at any time.' ?>
</div>
<?php if ($user !== null): ?>
<p>
  <a href="<?= h(url('account/theme.php')) ?>"><?= $zh ? '打开界面切换' : 'Open the interface switch' ?></a>
  <?php if ($modern): ?>
  &middot; <a href="<?= h(url('account/theme.php?back=1')) ?>"><?= $zh ? '一键回到旧版' : 'back to the classic look' ?></a>
  <?php endif; ?>
</p>
<?php else: ?>
<p class="hint"><?= $zh
    ? '新版界面只能由登录用户在控制台开启（需三次确认）。'
    : 'The new interface can only be turned on by a signed-in holder from the console (three confirmations).' ?></p>
<?php endif; ?>
<?php
page_footer();

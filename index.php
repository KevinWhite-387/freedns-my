<?php
declare(strict_types=1);

/**
 * freedns.my - home page.
 *
 * This is the English page; /zh/index.php includes it with the language forced to Chinese.
 * All user visible texts come from inc/lang/*.php through t().
 */

require __DIR__ . '/inc/bootstrap.php';

$zones     = zone_list();
$openZones = zone_list(true);
// Optional display override from the back office (admin/home-stats.php): 0 keeps the real count.
$fdActiveReal  = names_active_real();
$fdActiveShown = (int)setting('home_names_shown', '0');
$active        = names_active_display();
$pending   = (int)db_value("SELECT COUNT(*) FROM domains WHERE status = 'pending'", [], 0);
$open      = setting_bool('requests_open', true) && $openZones !== [];
$zoneText  = zones_sentence(true);

// Real per-suffix counts plus the overrides from admin/home-stats.php.
$countsByZone = [];
foreach (home_zone_rows() as $row) {
    $countsByZone[(string)$row['zone']] = (int)$row['n'];
}

// The group links appear twice below (the block near the top and nothing else), so the address is
// kept in one place.
$fdTelegram = 'https://t.me/freednsmy';

page_header(t('index.heading'));
?>

<?php /* 交流群组 —— 紧跟页面头部（banner）之后、浏览器提示之前 ✓ */ ?>
<h2><?= h(t('index.group_heading')) ?></h2>
<p><?= t('index.group_text', '<a href="' . h($fdTelegram) . '" rel="nofollow noopener">t.me/freednsmy</a>') ?></p>
<p class="small">
  QQ<?= (function_exists('is_zh') && is_zh()) ? '群' : ' group' ?>：
  <a href="https://qm.qq.com/cgi-bin/qm/qr?k=111812136" rel="nofollow noopener">111812136</a>
</p>

<?php /* 浏览器提示 —— 文案走 ui()，六种语言一起换 ✓ */ ?>
<div style="border:1px solid #000;padding:.5em .8em;margin:0 0 1.1em 0;line-height:1.55">
<?= h(ui('home.browser_notice')) ?>
</div>

<?php
$zhHome = function_exists('lang') && is_zh();

/*
 * The reviews module is loaded here as well, otherwise review_stats() would not exist on this page and the score
 * row would always claim there are no reviews. require_once, so a second include elsewhere stays harmless.
 *
 * A row of pointers for visitors: what people think of the registry (a score, when there is one), where else to
 * look for a name, and how the project is paid for. The average is read straight from the reviews, so it appears
 * by itself once reviews exist and stays away when there are none.
 */
$fdStats  = function_exists('review_stats') ? review_stats() : ['count' => 0, 'average' => 0.0];
// The reviews feature was withdrawn, so there is no score: the variable stays empty so
// the markup that prints it keeps working.
$fdScore  = '';
?>
<div class="msg info">
  <?= $fdScore ?>
  &middot; <a href="<?= h(url('partners.php')) ?>"><?= $zhHome ? '其它免费域名与 DNS 平台' : 'Other registries and DNS services' ?></a>
  &middot; <a href="<?= h(url('about.php')) ?>"><?= $zhHome ? '资金公开' : 'Where the money goes' ?></a>
</div>
<p>
<?= t('index.intro1', h((string)setting_lang('site_title')), h($zoneText)) ?>
</p>

<p>
<?= t('index.intro_records') ?>
<?= t('index.intro_manage', '<a href="' . h(url('manage.php')) . '">' . h(t('index.manage_link')) . '</a>') ?>
<?= t('index.intro_psl', '<a href="' . h(url('doc.php')) . '">' . h(t('index.psl_link')) . '</a>') ?>
</p>

<h2><?= h(t('index.announcement')) ?></h2>
<?= text_block((string)setting_lang('announcement')) ?>

<?php if (function_exists('user_registrations_full') && user_registrations_full()): ?>
<p class="msg info">
  <?= h((function_exists('is_zh') && is_zh())
      ? '今日注册名额已满，新账户将在每天 00:00 恢复注册。'
      : 'Today\'s registration allowance is used up; new accounts open again at midnight.') ?>
</p>
<?php endif; ?>


<h2><?= h(t('index.zones')) ?></h2>
<?php if ($zones === []): ?>
<p class="err"><?= t('index.no_zone') ?></p>
<?php else: ?>
<table>
  <tr>
    <th><?= h(t('index.col_zone')) ?></th>
    <th><?= h(t('index.col_requests')) ?></th>
    <th><?= h(t('index.col_active')) ?></th>
    <th><?= h(t('index.col_notes')) ?></th>
  </tr>
  <?php foreach ($zones as $zone): ?>
  <tr>
    <td><code><?= h((string)$zone['name']) ?></code></td>
    <td><?= (int)$zone['is_open'] === 1
        ? '<span class="ok">' . h(t('index.open')) . '</span>'
        : '<span class="err">' . h(t('index.closed')) . '</span>' ?></td>
    <td><?= number_format($countsByZone[(string)$zone['name']] ?? 0) ?></td>
    <td class="small"><?= h(shorten(zone_description_text($zone), 140)) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= h(t('index.status_h')) ?></h2>
<table>
  <tr><th><?= h(t('index.form')) ?></th><td><?= $open ? t('index.form_open') : t('index.form_closed') ?></td></tr>
  <tr><th><?= h(t('index.active_names')) ?></th><td><?= number_format($active) ?></td></tr>
  <tr><th><?= h(t('index.awaiting')) ?></th><td><?= number_format($pending) ?></td></tr>
  <tr><th><?= h(t('index.review_time')) ?></th><td><?= h(t('index.review_time_v')) ?></td></tr>
  <tr><th><?= h(t('index.price')) ?></th><td><?= h(t('index.price_v')) ?></td></tr>
</table>

<h2><?= h(t('index.howto')) ?></h2>
<ol>
  <li><?= t('index.step1') ?></li>
  <li><?= t('index.step2', '<a href="' . h(url('request.php')) . '">' . h(t('nav.request')) . '</a>') ?></li>
  <li><?= t('index.step3') ?></li>
  <li><?= t('index.step4') ?></li>
  <li><?= t(
      'index.step5',
      '<a href="' . h(url('manage.php')) . '">' . h(t('index.manage_link')) . '</a>',
      '<a href="' . h(url('status.php')) . '">' . h(t('nav.status')) . '</a>'
  ) ?></li>
</ol>

<h2><?= h(t('index.documents')) ?></h2>
<p>
<?= t('index.documents_text', '<a href="' . h(url('doc.php')) . '">' . h(t('index.docs_link')) . '</a>') ?>
</p>

<h2><?= h(t('index.contact')) ?></h2>
<?php $contact = trim((string)setting('contact_email', '')); ?>
<p>
<?php if ($contact !== ''): ?>
<?= t('index.contact_q', '<a href="mailto:' . h($contact) . '">' . h($contact) . '</a>') ?>
<?php else: ?>
<?= t('index.contact_none') ?>
<?php endif; ?>
</p>

<p class="small"><?= t('index.small_print') ?></p>
<?php
page_footer();

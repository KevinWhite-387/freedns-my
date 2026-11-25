<?php
declare(strict_types=1);

/**
 * freedns.my - back office: registration charts.
 *
 * Three views, all drawn with plain CSS (no JavaScript, consistent with the rest of the site):
 *
 *   1. names per suffix, as horizontal bars scaled to the biggest suffix;
 *   2. new names per day for the last 30 days, as vertical columns;
 *   3. a small table with the exact figures, so nothing depends on reading a bar by eye.
 *
 * Every number is a live COUNT() over `domains` / `records`. Nothing here is estimated or cached,
 * and the display override used on the home page (home_names_shown) is deliberately ignored: this
 * page exists to show what is actually in the database.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_owner('Registration charts');

$zh = function_exists('lang') && is_zh();

/* ---- per suffix -------------------------------------------------------- */
$perZone = db_all(
    "SELECT d.zone,
            COUNT(*) AS total,
            SUM(d.status = 'active')    AS active_n,
            SUM(d.status = 'pending')   AS pending_n,
            SUM(d.status = 'rejected')  AS rejected_n,
            SUM(d.status = 'suspended') AS suspended_n,
            SUM(d.status = 'deleted')   AS deleted_n,
            SUM(EXISTS (SELECT 1 FROM records r WHERE r.domain_id = d.id)) AS with_records
       FROM domains d
      GROUP BY d.zone
      ORDER BY total DESC"
);

/* ---- last 30 days ------------------------------------------------------ */
$days = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime('-' . $i . ' day'));
    $days[$d] = 0;
}
$rows = db_all(
    "SELECT DATE(created_at) AS d, COUNT(*) AS n
       FROM domains
      WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
      GROUP BY DATE(created_at)"
);
foreach ($rows as $r) {
    $key = (string)$r['d'];
    if (array_key_exists($key, $days)) {
        $days[$key] = (int)$r['n'];
    }
}
$peak = max(1, max($days));
$sum30 = array_sum($days);

/* ---- totals ------------------------------------------------------------ */
$totalAll   = 0;
$totalWithR = 0;
foreach ($perZone as $z) {
    $totalAll   += (int)$z['total'];
    $totalWithR += (int)$z['with_records'];
}
$maxZone = 1;
foreach ($perZone as $z) {
    $maxZone = max($maxZone, (int)$z['total']);
}

page_header($zh ? '注册统计图表' : 'Registration charts');
?>
<h1><?= h($zh ? '注册统计图表' : 'Registration charts') ?></h1>
<hr>

<p class="small"><?= h($zh
    ? '数据全部来自数据库的实时统计；首页那个「显示数字」的覆盖值在这里不生效，这里只看真实值。'
    : 'Live COUNT() over the database. The home-page display override is ignored here on purpose: this page shows what is really stored.') ?></p>

<h2><?= h($zh ? '一、每个后缀注册了多少' : '1. Names per suffix') ?></h2>

<table>
  <thead>
    <tr>
      <th style="width:9em"><?= h($zh ? '后缀' : 'Suffix') ?></th>
      <th><?= h($zh ? '图形（按最大后缀等比）' : 'Scale (relative to the biggest)') ?></th>
      <th style="width:6em"><?= h($zh ? '合计' : 'Total') ?></th>
      <th style="width:6em"><?= h($zh ? '已生效' : 'Active') ?></th>
      <th style="width:6em"><?= h($zh ? '待审核' : 'Pending') ?></th>
      <th style="width:6em"><?= h($zh ? '有记录' : 'With records') ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($perZone as $z): ?>
    <?php
      $pct = (int)round(((int)$z['total'] * 100) / $maxZone);
      $recPct = (int)$z['total'] > 0 ? (int)round(((int)$z['with_records'] * 100) / (int)$z['total']) : 0;
    ?>
    <tr>
      <td><code><?= h((string)$z['zone']) ?></code></td>
      <td>
        <div style="background:#eee;height:14px;width:100%;position:relative">
          <div style="background:#1a5fd0;height:14px;width:<?= $pct ?>%"></div>
        </div>
        <span class="small"><?= (int)$z['with_records'] ?> / <?= (int)$z['total'] ?>
          <?= h($zh ? '条有记录' : 'have records') ?>（<?= $recPct ?>%）</span>
      </td>
      <td><strong><?= (int)$z['total'] ?></strong></td>
      <td><?= (int)$z['active_n'] ?></td>
      <td><?= (int)$z['pending_n'] ?></td>
      <td><?= (int)$z['with_records'] ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if ($perZone === []): ?>
    <tr><td colspan="6"><?= h($zh ? '还没有任何域名。' : 'No name registered yet.') ?></td></tr>
  <?php endif; ?>
  </tbody>
</table>

<p class="small">
  <?= h($zh ? '合计：' : 'Total: ') ?><strong><?= $totalAll ?></strong>
  <?= h($zh ? ' 个域名，其中 ' : ' names, of which ') ?><strong><?= $totalWithR ?></strong>
  <?= h($zh ? ' 个至少有一条记录。' : ' have at least one record.') ?>
</p>

<hr>
<h2><?= h($zh ? '二、近 30 天每天新增' : '2. New names per day (last 30 days)') ?></h2>

<div style="display:flex;align-items:flex-end;gap:3px;height:150px;border-bottom:1px solid #999;margin:1em 0 0">
  <?php foreach ($days as $d => $n): ?>
    <?php $h = $n > 0 ? max(2, (int)round($n * 140 / $peak)) : 0; ?>
    <div title="<?= h($d) ?>: <?= $n ?>"
         style="flex:1;background:<?= $n > 0 ? '#1a5fd0' : '#e5e5e5' ?>;height:<?= $h ?>px;min-height:<?= $n > 0 ? 2 : 1 ?>px"></div>
  <?php endforeach; ?>
</div>
<div style="display:flex;gap:3px;margin-bottom:.6em">
  <?php $keys = array_keys($days); ?>
  <?php foreach ($keys as $i => $d): ?>
    <div style="flex:1;font-size:.62em;color:#666;text-align:center">
      <?= ($i % 5 === 0) ? h(substr($d, 5)) : '&nbsp;' ?>
    </div>
  <?php endforeach; ?>
</div>

<p class="small">
  <?= h($zh ? '最高一天 ' : 'Peak day ') ?><strong><?= $peak ?></strong>
  <?= h($zh ? ' 个；近 30 天合计 ' : '; last 30 days: ') ?><strong><?= $sum30 ?></strong>
  <?= h($zh ? ' 个。柱高按最高一天等比。' : ' in total. Bars are scaled to the peak.') ?>
</p>

<hr>
<h2><?= h($zh ? '三、明细表' : '3. Exact figures') ?></h2>
<table>
  <thead>
    <tr>
      <th><?= h($zh ? '后缀' : 'Suffix') ?></th>
      <th><?= h($zh ? '合计' : 'Total') ?></th>
      <th><?= h($zh ? '已生效' : 'Active') ?></th>
      <th><?= h($zh ? '待审核' : 'Pending') ?></th>
      <th><?= h($zh ? '已拒绝' : 'Rejected') ?></th>
      <th><?= h($zh ? '已暂停' : 'Suspended') ?></th>
      <th><?= h($zh ? '已删除' : 'Deleted') ?></th>
      <th><?= h($zh ? '有记录' : 'With records') ?></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($perZone as $z): ?>
    <tr>
      <td><code><?= h((string)$z['zone']) ?></code></td>
      <td><?= (int)$z['total'] ?></td>
      <td><?= (int)$z['active_n'] ?></td>
      <td><?= (int)$z['pending_n'] ?></td>
      <td><?= (int)$z['rejected_n'] ?></td>
      <td><?= (int)$z['suspended_n'] ?></td>
      <td><?= (int)$z['deleted_n'] ?></td>
      <td><?= (int)$z['with_records'] ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>

<?php page_footer(); ?>
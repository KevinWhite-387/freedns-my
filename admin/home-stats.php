<?php
declare(strict_types=1);

/**
 * freedns.my - back office: the numbers the home page prints.
 *
 * Two things can be set here:
 *
 *   1. one number per suffix - the home page prints it instead of the real count for that suffix;
 *   2. one number for the headline total - optional, it wins over the sum when filled.
 *
 * Both are display only. Nothing is written to `domains`, no status changes, and leaving a field
 * empty (or 0) puts the real count back for that suffix.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_owner('Home page counters');

$zh    = function_exists('lang') && is_zh();
$error = '';

$realByZone = names_zone_real();
$shownMap   = home_zone_shown_map();
$totalNow   = (int)setting('home_names_shown', '0');

/* every suffix the registry knows, plus any suffix that only carries an override */
$allZones = [];
foreach (zone_list() as $z) {
    $allZones[] = (string)$z['name'];
}
foreach (array_keys($shownMap) as $z) {
    if (!in_array($z, $allZones, true)) {
        $allZones[] = (string)$z;
    }
}
sort($allZones);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();

    $posted = (array)($_POST['zone'] ?? []);
    $new    = [];
    $bad    = '';
    foreach ($allZones as $zone) {
        $raw = trim((string)($posted[$zone] ?? ''));
        if ($raw === '' || $raw === '0') {
            continue;
        }
        if (!preg_match('/^\d{1,9}$/', $raw)) {
            $bad = $zone;
            break;
        }
        $new[$zone] = (int)$raw;
    }

    $totalRaw = trim((string)($_POST['home_names_shown'] ?? '0'));
    if ($bad !== '') {
        $error = $zh ? ('后缀 ' . $bad . ' 只能填 0 或正整数。') : ('Suffix ' . $bad . ': enter 0 or a positive whole number.');
    } elseif ($totalRaw !== '' && !preg_match('/^\d{1,9}$/', $totalRaw)) {
        $error = $zh ? '总数只能填 0 或正整数。' : 'The total must be 0 or a positive whole number.';
    } else {
        set_setting('home_zone_shown', $new === [] ? '' : (string)json_encode($new, JSON_UNESCAPED_UNICODE));
        set_setting('home_names_shown', (string)(int)$totalRaw);
        log_action('home_zone_shown', 'settings', 0,
            'per-suffix display: ' . ($new === [] ? '(real)' : json_encode($new, JSON_UNESCAPED_UNICODE))
            . '; total: ' . (int)$totalRaw);
        flash($zh ? '已保存。首页的显示数字已更新。' : 'Saved. The home page numbers are updated.', 'ok');
        redirect(url('admin/home-stats.php'));
    }
}

page_header($zh ? '首页计数' : 'Home page counters');
?>
<h1><?= h($zh ? '首页计数' : 'Home page counters') ?></h1>
<hr>

<?php if ($error !== ''): ?>
<p class="msg error"><?= h($error) ?></p>
<?php endif; ?>

<p class="small"><?= h($zh
    ? '每个后缀可以单独指定首页显示的数字；留空或填 0 = 显示该后缀的真实统计。'
    : 'Give each suffix the number the home page should print; empty or 0 means the real count for that suffix.') ?></p>

<form method="post" action="<?= h(url('admin/home-stats.php')) ?>">
  <?= csrf_field() ?>

  <table>
    <thead>
      <tr>
        <th><?= h($zh ? '后缀' : 'Suffix') ?></th>
        <th><?= h($zh ? '真实数' : 'Real') ?></th>
        <th><?= h($zh ? '首页显示（留空=真实）' : 'Shown (empty = real)') ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($allZones as $zone): ?>
      <tr>
        <td><code><?= h($zone) ?></code></td>
        <td><?= (int)($realByZone[$zone] ?? 0) ?></td>
        <td>
          <input type="text" name="zone[<?= h($zone) ?>]" maxlength="9" size="10"
                 value="<?= isset($shownMap[$zone]) ? (int)$shownMap[$zone] : '' ?>">
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($allZones === []): ?>
      <tr><td colspan="3"><?= h($zh ? '还没有任何后缀。' : 'No suffix yet.') ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>

  <h2><?= h($zh ? '首页总数（可选）' : 'Headline total (optional)') ?></h2>
  <p>
    <label for="home_names_shown"><?= h($zh ? '标题里的「已注册 N 个域名」' : 'The "N names registered" figure') ?></label><br>
    <input type="text" id="home_names_shown" name="home_names_shown" maxlength="9" size="12"
           value="<?= $totalNow > 0 ? $totalNow : 0 ?>">
    <span class="small"><?= h($zh ? '填 0 = 用上面各后缀之和' : '0 = use the sum of the suffixes above') ?></span>
  </p>

  <p><input type="submit" value="<?= h($zh ? '保存' : 'Save') ?>"></p>
</form>

<hr>
<h2><?= h($zh ? '现在的效果' : 'What the home page shows now') ?></h2>
<table>
  <tr><th><?= h($zh ? '标题总数' : 'Headline') ?></th><td><code><?= (int)names_active_display() ?></code></td></tr>
  <tr><th><?= h($zh ? '真实总数' : 'Real active names') ?></th><td><code><?= (int)names_active_real() ?></code></td></tr>
  <?php foreach (home_zone_rows() as $row): ?>
    <tr>
      <th><code><?= h((string)$row['zone']) ?></code></th>
      <td><?= (int)$row['n'] ?>
        <?php if (isset($shownMap[(string)$row['zone']])): ?>
          <span class="small">(<?= h($zh ? '指定值' : 'set by hand') ?>)</span>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
</table>

<p class="small"><?= h($zh
    ? '只影响首页的显示，不会改动数据库里的域名记录，也不影响审核、发布或配额。'
    : 'Display only: no domains row is touched, and review, publishing and quotas are unaffected.') ?></p>

<?php page_footer(); ?>
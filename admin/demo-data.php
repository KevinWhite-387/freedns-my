<?php
declare(strict_types=1);

/**
 * freedns.my - back office: bulk demo names.
 *
 * A bulk generator for a launch: it fills the registry with random-looking names, each on its own
 * account, so lists, Whois, search and the counters have something to show. Everything it makes is
 * marked internally (domains.admin_note = 'SEED-DEMO'), which is what lets the switch below remove
 * exactly those rows again - no badge is shown anywhere, the names look like any other.
 *
 * Nothing is published to a DNS provider and nobody is ever mailed: records point at example.org
 * and 192.0.2.x, both reserved for documentation.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_owner('Demo names');

$zh    = function_exists('lang') && is_zh();
$error = '';
$done  = '';

$zones   = zone_list();
$zoneMap = [];
foreach ($zones as $z) {
    $zoneMap[(string)$z['name']] = true;
}

/* ------------------------------------------------------------------ actions */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'generate') {
        $zone  = strtolower(trim((string)($_POST['zone'] ?? '')));
        $count = max(100, min(9000, (int)($_POST['count'] ?? 100)));
        if (!isset($zoneMap[$zone])) {
            $error = $zh ? '请选择一个本站已有的后缀。' : 'Pick a suffix this registry actually has.';
        } else {
            // Batched on purpose: 200 rows per request keeps memory and runtime small, so php-fpm
            // never kills the worker (which is what produced the 502 / connection reset).
            $batch = max(1, min(900, $count));
            @set_time_limit(60);
            try {
                $made = demo_batch_create($zone, $batch);
            } catch (Throwable $e) {
                $made  = ['domains' => 0, 'users' => 0, 'skipped' => 0];
                $error = '生成失败：' . $e->getMessage();
            }
            if ($error === '') {
                $done = $zh
                    ? ('本次生成 ' . (int)$made['domains'] . ' 个域名、' . (int)$made['users'] . ' 个账号（跳过 ' . (int)$made['skipped'] . ' 个）。要更多就再点一次。')
                    : ('Batch done: ' . (int)$made['domains'] . ' names, ' . (int)$made['users'] . ' accounts (' . (int)$made['skipped'] . ' skipped). Click again for more.');
            }
            log_action('demo_names_created', 'settings', 0, $zone . ': ' . (int)$made['domains'] . ' names');
        }
    }

    if ($action === 'purge') {
        $gone = demo_bulk_clear();
        set_setting('demo_switch_on', '0');
        $done = $zh
            ? ('已清除 ' . (int)$gone['domains'] . ' 个演示域名、' . (int)$gone['users'] . ' 个演示账号。')
            : ('Removed ' . (int)$gone['domains'] . ' names and ' . (int)$gone['users'] . ' accounts.');
        log_action('demo_names_cleared', 'settings', 0, (int)$gone['domains'] . ' names, ' . (int)$gone['users'] . ' accounts');
    }

    if ($action === 'switch') {
        $on = (string)($_POST['on'] ?? '0') === '1' ? '1' : '0';
        set_setting('demo_switch_on', $on);
        if ($on === '1') {
            set_setting('admin_stats_follow_home', (string)($_POST['follow'] ?? '1') === '1' ? '1' : '0');
            $done = $zh ? '开关已打开。' : 'Switch is on.';
        } else {
            // Turning the switch off removes the generated names, as asked.
            $gone = demo_bulk_clear();
            $done = $zh
                ? ('开关已关闭，演示数据已自动清除：域名 ' . (int)$gone['domains'] . ' 个、账号 ' . (int)$gone['users'] . ' 个。')
                : ('Switch is off and the demo data is gone: ' . (int)$gone['domains'] . ' names, ' . (int)$gone['users'] . ' accounts.');
        }
        log_action('demo_switch', 'settings', 0, 'switch ' . $on);
    }
}

/* ------------------------------------------------------------------ state */
$switchOn   = setting_bool('demo_switch_on', false);
$followHome = setting_bool('admin_stats_follow_home', true);
$seedCount  = (int)db_value("SELECT COUNT(*) FROM domains WHERE admin_note = 'SEED-DEMO'", [], 0);
$seedUsers  = (int)db_value("SELECT COUNT(DISTINCT registrant_email) FROM domains WHERE admin_note = 'SEED-DEMO'", [], 0);

page_header($zh ? '批量演示数据' : 'Demo names');
?>
<h1><?= h($zh ? '批量演示数据' : 'Demo names') ?></h1>
<hr>

<?php if ($error !== ''): ?>
<p class="msg error"><?= h($error) ?></p>
<?php endif; ?>
<?php if ($done !== ''): ?>
<p class="msg ok"><?= h($done) ?></p>
<?php endif; ?>

<h2><?= h($zh ? '现在的状态' : 'Current state') ?></h2>
<table>
<tr><th><?= h($zh ? '总开关' : 'Switch') ?></th><td>
  <?= $switchOn ? '<strong class="ok">' . h($zh ? '已打开' : 'on') . '</strong>'
                : '<strong>' . h($zh ? '已关闭' : 'off') . '</strong>' ?></td></tr>
<tr><th><?= h($zh ? '后台数字跟随前台' : 'Admin numbers follow the home page') ?></th><td>
  <?= $followHome ? h($zh ? '是' : 'yes') : h($zh ? '否' : 'no') ?></td></tr>
<tr><th><?= h($zh ? '当前演示域名' : 'Demo names now') ?></th><td><code><?= $seedCount ?></code></td></tr>
<tr><th><?= h($zh ? '当前演示账号' : 'Demo accounts now') ?></th><td><code><?= $seedUsers ?></code></td></tr>
</table>

<h2><?= h($zh ? '总开关' : 'Master switch') ?></h2>
<form method="post" action="<?= h(url('admin/demo-data.php')) ?>" style="margin-bottom:1em">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="switch">
  <input type="hidden" name="on" value="<?= $switchOn ? '0' : '1' ?>">
  <input type="hidden" name="follow" value="1">
  <input type="submit" value="<?= $switchOn
      ? h($zh ? '关闭开关（并清除所有演示数据）' : 'Switch off (removes all demo data)')
      : h($zh ? '打开开关' : 'Switch on') ?>">
</form>
<p class="small"><?= h($zh
    ? '关闭开关会把本页生成的域名与账号一起清除，前台不会留下任何变化。'
    : 'Switching off removes every name and account this page created; the public site is left as it was.') ?></p>

<hr>
<h2><?= h($zh ? '批量生成' : 'Generate') ?></h2>
<form method="post" action="<?= h(url('admin/demo-data.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="generate">
  <p>
    <label for="zone"><?= h($zh ? '后缀' : 'Suffix') ?></label><br>
    <select id="zone" name="zone">
      <?php foreach ($zones as $z): ?>
        <option value="<?= h((string)$z['name']) ?>"><?= h((string)$z['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </p>
  <p>
    <label for="count"><?= h($zh ? '数量（100 到 9000）' : 'How many (100 to 9000)') ?></label><br>
    <input type="text" id="count" name="count" maxlength="4" size="8" value="500">
  </p>
  <p><input type="submit" value="<?= h($zh ? '开始生成' : 'Generate') ?>"></p>
</form>
<p class="small"><?= h($zh
    ? '每个域名都配一个独立账号（随机昵称与随机邮箱），记录指向 example.org 与 192.0.2.x：不会发布、不会发信、不会连到任何真实服务器。'
    : 'Each name gets its own account with a random nick and address. Records point at example.org and 192.0.2.x: nothing is published, mailed or reachable.') ?></p>

<hr>
<h2><?= h($zh ? '单独清除' : 'Clear now') ?></h2>
<form method="post" action="<?= h(url('admin/demo-data.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="purge">
  <input type="submit" value="<?= h($zh ? '立即清除全部演示数据（不改开关）' : 'Remove all demo data now (leaves the switch)') ?>">
</form>

<?php page_footer(); ?>

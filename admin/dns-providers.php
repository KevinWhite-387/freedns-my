<?php
declare(strict_types=1);

/**
 * freedns.my - back office: extra DNS providers (Spaceship, ANYNS).
 *
 * A page of its own on purpose: admin/dns.php handles Cloudflare and DNSHE and is large;
 * adding two more providers in there would have meant surgery on 687 lines. Everything
 * about the two new providers lives here instead, and nothing else had to change.
 *
 * What it does:
 *   * the global on/off switch of each provider (settings spaceship_enabled / anyns_enabled)
 *   * per suffix credentials - the whole point, since each suffix may live in a different account
 *   * a test button per row that only READS from the provider (never writes a record)
 *
 * Permissions: owner only, same as the other DNS pages.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

require_owner('Extra DNS providers');

$zh     = function_exists('lang') && is_zh();
$notice = '';
$error  = '';

$needSql = !spaceship_columns_ready() || !anyns_columns_ready();

/* ---------------- actions ---------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'toggles') {
        foreach (['spaceship_enabled', 'anyns_enabled'] as $k) {
            $want = isset($_POST[$k]) ? '1' : '0';
            if ((string)setting($k, '0') !== $want) {
                set_setting($k, $want);
                log_action('settings_updated', 'settings', null, 'dns providers: ' . $k . ' = ' . $want);
            }
        }
        $notice = $zh ? '开关已保存。' : 'Switches saved.';
    } elseif ($action === 'save_zone') {
        $zone = trim((string)($_POST['zone'] ?? ''));
        $row  = $zone === '' ? null : db_one('SELECT * FROM zones WHERE name = ?', [$zone]);
        if ($row === null) {
            $error = $zh ? '找不到这个后缀。' : 'Unknown suffix.';
        } else {
            $spKey = trim((string)($_POST['spaceship_key'] ?? ''));
            $spSec = trim((string)($_POST['spaceship_secret'] ?? ''));
            $anTok = trim((string)($_POST['anyns_token'] ?? ''));

            /* An empty field means "leave it alone" only when the clear box is not ticked;
             * ticking clear empties it on purpose. */
            $clearSp = isset($_POST['clear_spaceship']);
            $clearAn = isset($_POST['clear_anyns']);

            $sql  = [];
            $args = [];
            if (spaceship_columns_ready()) {
                if ($clearSp) {
                    $sql[] = 'spaceship_key = NULL, spaceship_secret = NULL';
                } elseif ($spKey !== '' || $spSec !== '') {
                    $sql[]  = 'spaceship_key = ?, spaceship_secret = ?';
                    $args[] = $spKey !== '' ? $spKey : (string)($row['spaceship_key'] ?? '');
                    $args[] = $spSec !== '' ? $spSec : (string)($row['spaceship_secret'] ?? '');
                }
            }
            if (anyns_columns_ready()) {
                if ($clearAn) {
                    $sql[] = 'anyns_token = NULL';
                } elseif ($anTok !== '') {
                    $sql[]  = 'anyns_token = ?';
                    $args[] = $anTok;
                }
            }
            if ($sql === []) {
                $notice = $zh ? '没有需要保存的内容。' : 'Nothing to save.';
            } else {
                $args[] = $zone;
                try {
                    db_query('UPDATE zones SET ' . implode(', ', $sql) . ', updated_at = NOW() WHERE name = ?', $args);
                    log_action('settings_updated', 'settings', null, 'dns providers: credentials for ' . $zone);
                    $notice = ($zh ? '已保存 ' : 'Saved ') . $zone;
                } catch (Throwable $e) {
                    $error = $zh ? '保存失败：' . $e->getMessage() : 'Save failed: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'test') {
        $zone     = trim((string)($_POST['zone'] ?? ''));
        $provider = strtolower(trim((string)($_POST['provider'] ?? '')));
        if ($provider === 'spaceship') {
            $r = spaceship_check($zone);
        } elseif ($provider === 'anyns') {
            $r = anyns_check($zone);
        } else {
            $r = ['ok' => false, 'error' => 'Unknown provider.'];
        }
        if (!empty($r['ok'])) {
            $notice = ($zh ? '测试通过 ✓ ' : 'Test passed ✓ ') . $provider . ' · ' . $zone
                . (isset($r['zones']) ? (' · zones: ' . (int)$r['zones']) : '');
        } else {
            $error = ($zh ? '测试失败：' : 'Test failed: ') . $provider . ' · ' . $zone . ' — ' . (string)($r['error'] ?? '');
        }
        log_action('dns_provider_test', 'settings', null, $provider . ' ' . $zone . ' ' . (empty($r['ok']) ? 'fail' : 'ok'));
    }
}

$zones = [];
try {
    $zones = db_all('SELECT * FROM zones ORDER BY sort_order ASC, name ASC');
} catch (Throwable $e) {
    $zones = [];
}

page_header($zh ? '额外的 DNS 服务商' : 'Extra DNS providers');
?>
<h1><?= h($zh ? '额外的 DNS 服务商' : 'Extra DNS providers') ?></h1>
<hr>

<?php if ($notice !== ''): ?>
<p class="msg ok"><?= h($notice) ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
<p class="msg error"><?= h($error) ?></p>
<?php endif; ?>

<?php if ($needSql): ?>
<p class="msg error">
<?= h($zh
    ? '数据库还缺列：请先导入 upgrade-dns-providers.sql，本页才能保存凭据。'
    : 'The database is missing the new columns: import upgrade-dns-providers.sql first.') ?>
</p>
<?php endif; ?>

<h2><?= h($zh ? '总开关' : 'Switches') ?></h2>
<form method="post" action="<?= h(url('admin/dns-providers.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="toggles">
<p><label class="inline">
  <input type="checkbox" name="spaceship_enabled" value="1"<?= setting_bool('spaceship_enabled', false) ? ' checked' : '' ?>>
  Spaceship <?= h($zh ? '启用' : 'enabled') ?>
</label></p>
<p><label class="inline">
  <input type="checkbox" name="anyns_enabled" value="1"<?= setting_bool('anyns_enabled', false) ? ' checked' : '' ?>>
  ANYNS <?= h($zh ? '启用' : 'enabled') ?>
</label></p>
<p><button class="btn" type="submit"><?= h($zh ? '保存开关' : 'Save switches') ?></button></p>
</form>

<h2><?= h($zh ? '每个后缀的凭据' : 'Credentials per suffix') ?></h2>
<p class="small">
<?= h($zh
    ? '每个后缀可以单独填一套凭据（不填就回落下面"全局"那一套）。空格子表示不改动。'
    : 'Each suffix may use its own credentials. Empty fields leave the stored value alone.') ?>
</p>

<table>
<tr>
  <th><?= h($zh ? '后缀' : 'Suffix') ?></th>
  <th><?= h($zh ? '当前服务商' : 'Provider') ?></th>
  <th>Spaceship Key / Secret</th>
  <th>ANYNS Token</th>
  <th><?= h($zh ? '操作' : 'Actions') ?></th>
</tr>
<?php foreach ($zones as $z): ?>
<tr>
  <td><code><?= h((string)$z['name']) ?></code></td>
  <td><?= h((string)($z['provider'] ?? '') !== '' ? (string)$z['provider'] : ($zh ? '（未设置）' : '(none)')) ?></td>
  <td>
    <form method="post" action="<?= h(url('admin/dns-providers.php')) ?>" style="margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_zone">
      <input type="hidden" name="zone" value="<?= h((string)$z['name']) ?>">
      <input type="text" name="spaceship_key" maxlength="190" placeholder="API Key"
             value="" style="width:100%;max-width:220px">
      <input type="text" name="spaceship_secret" maxlength="190" placeholder="API Secret"
             value="" style="width:100%;max-width:260px;margin-top:4px">
      <div class="small">
        <?= h($zh ? '已保存' : 'saved') ?>:
        <?= (string)($z['spaceship_key'] ?? '') !== '' ? h(substr((string)$z['spaceship_key'], 0, 4) . '****') : '—' ?>
        <label class="inline"><input type="checkbox" name="clear_spaceship" value="1"> <?= h($zh ? '清空' : 'clear') ?></label>
      </div>
      <button class="btn" type="submit" style="margin-top:4px"><?= h($zh ? '保存' : 'Save') ?></button>
    </form>
  </td>
  <td>
    <form method="post" action="<?= h(url('admin/dns-providers.php')) ?>" style="margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_zone">
      <input type="hidden" name="zone" value="<?= h((string)$z['name']) ?>">
      <input type="text" name="anyns_token" maxlength="190" placeholder="adns_…"
             value="" style="width:100%;max-width:260px">
      <div class="small">
        <?= h($zh ? '已保存' : 'saved') ?>:
        <?= (string)($z['anyns_token'] ?? '') !== '' ? h(substr((string)$z['anyns_token'], 0, 8) . '****') : '—' ?>
        <label class="inline"><input type="checkbox" name="clear_anyns" value="1"> <?= h($zh ? '清空' : 'clear') ?></label>
      </div>
      <button class="btn" type="submit" style="margin-top:4px"><?= h($zh ? '保存' : 'Save') ?></button>
    </form>
  </td>
  <td class="small">
    <form method="post" action="<?= h(url('admin/dns-providers.php')) ?>" style="margin:0 0 4px 0">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <input type="hidden" name="zone" value="<?= h((string)$z['name']) ?>">
      <input type="hidden" name="provider" value="spaceship">
      <button class="btn" type="submit"><?= h($zh ? '测试 Spaceship' : 'Test Spaceship') ?></button>
    </form>
    <form method="post" action="<?= h(url('admin/dns-providers.php')) ?>" style="margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="test">
      <input type="hidden" name="zone" value="<?= h((string)$z['name']) ?>">
      <input type="hidden" name="provider" value="anyns">
      <button class="btn" type="submit"><?= h($zh ? '测试 ANYNS' : 'Test ANYNS') ?></button>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>

<h2><?= h($zh ? '怎么让某个后缀走新服务商' : 'How to publish a suffix with a new provider') ?></h2>
<ol class="small">
<li><?= h($zh
    ? '在上面给该后缀填好凭据，点「测试」确认通过。'
    : 'Fill the credentials above and press Test.') ?></li>
<li><?= h($zh
    ? '把该后缀的 provider 设成 spaceship 或 anyns（数据库 zones.provider，或用下面的 SQL）。'
    : 'Set zones.provider to spaceship or anyns (SQL below).') ?></li>
<li><?= h($zh
    ? '确认该域名在服务商那边真的存在（Spaceship：域名列表里能看到；ANYNS：/v1/zones 里能看到对应区域）。'
    : 'Make sure the domain really exists there (Spaceship: in the domain list; ANYNS: a zone in /v1/zones).') ?></li>
<li><?= h($zh
    ? '然后在「域名审核」里批准记录，记录就会发到新的服务商。'
    : 'Approve records as usual; they go to the new provider.') ?></li>
</ol>

<pre class="mono-box">-- 让 vc.ci 用 ANYNS 发布（示例）
UPDATE zones SET provider = 'anyns' WHERE name = 'vc.ci';
-- 让某个后缀用 Spaceship
UPDATE zones SET provider = 'spaceship' WHERE name = 'kz.ci';
-- 查看当前设置
SELECT name, provider,
       (spaceship_key IS NOT NULL) AS has_spaceship,
       (anyns_token IS NOT NULL)    AS has_anyns
FROM zones ORDER BY name;</pre>

<p class="small">
<?= h($zh ? '提示：' : 'Note: ') ?>
<?= h($zh
    ? 'Spaceship 的 Key 需要有 dnsrecords:read 与 dnsrecords:write 权限；ANYNS 的 Token 以 adns_ 开头。'
    : 'The Spaceship key needs dnsrecords:read and dnsrecords:write; the ANYNS token starts with adns_.') ?>
</p>

<?php page_footer(); ?>
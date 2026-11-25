<?php
declare(strict_types=1);

/**
 * freedns.my - back office: adopt names that already exist at DNSHE.
 *
 * When the registry's own tables are lost but the DNS service still holds the subdomains, every
 * holder ends up unable to sign in as the owner of a name that is plainly still working. This page
 * lists what DNSHE actually has for a suffix, marks which of those names the registry already
 * knows, and lets the operator adopt the rest into an existing account in one click: the domain row
 * is created (status active), the DNSHE subdomain id is remembered, and the records DNSHE already
 * answers for are copied into `records` so the holder sees them on the manage page.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_owner('DNSHE name binding');

$zh    = function_exists('lang') && is_zh();
$error = '';
$done  = '';

/* ---- which suffixes does DNSHE publish? ---- */
$zones = [];
foreach (zone_list() as $z) {
    if (publish_provider((string)$z['name']) === 'dnshe') {
        $zones[] = (string)$z['name'];
    }
}
$zone = strtolower(trim((string)($_GET['zone'] ?? $_POST['zone'] ?? '')));
if ($zone === '' || !in_array($zone, $zones, true)) {
    $zone = $zones[0] ?? '';
}

/* ---- adopt -------------------------------------------------------------- */
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string)($_POST['action'] ?? '') === 'adopt') {
    csrf_verify();
    $label = strtolower(trim((string)($_POST['label'] ?? '')));
    $email = trim((string)($_POST['email'] ?? ''));
    $ref   = (int)($_POST['ref'] ?? 0);
    $fqdn  = $label . '.' . $zone;

    $account = $email === '' ? null : db_one('SELECT id, username, email FROM users WHERE email_lower = ? LIMIT 1', [strtolower($email)]);
    if ($zone === '' || $label === '') {
        $error = $zh ? '请选择一个后缀与一个名字。' : 'Pick a suffix and a name.';
    } elseif ($account === null) {
        $error = $zh
            ? ('找不到邮箱为 ' . $email . ' 的账号。请让用户先注册，或换一个邮箱。')
            : ('No account with the e-mail ' . $email . '. Ask them to register first, or use another address.');
    } elseif (db_one('SELECT id FROM domains WHERE name = ? LIMIT 1', [$fqdn]) !== null) {
        $error = $zh ? ($fqdn . ' 已经在本站注册过了。') : ($fqdn . ' is already in this registry.');
    } else {
        try {
            db_query(
                'INSERT INTO domains (name, label, zone, language, reserved, proxied, delegated,
                    status, request_code, provider_ref, provider_owned, registrant_name, registrant_email,
                    justification, admin_note, created_at, updated_at, decided_at, decided_by, created_ip)
                 VALUES (?, ?, ?, ?, 0, 0, 0,
                    ?, ?, ?, 1, ?, ?,
                    ?, ?, NOW(), NOW(), NOW(), ?, ?)',
                [
                    $fqdn, $label, $zone, 'en',
                    'active',
                    'ADOPT-' . strtoupper(substr(sha1('adopt|' . $fqdn . '|' . microtime()), 0, 10)),
                    $ref > 0 ? (string)$ref : null,
                    (string)$account['username'],
                    (string)$account['email'],
                    'Adopted from DNSHE after data loss',
                    'ADOPTED-DNSHE',
                    (string)$admin['username'],
                    '127.0.0.1',
                ]
            );
            $domainId = (int)db()->lastInsertId();

            /* copy the records DNSHE already answers for */
            $copied = 0;
            if ($ref > 0) {
                $existing = dnshe_records_of_subdomain($ref, $zone, $label);
                $position = 0;
                foreach ($existing as $rec) {
                    $sub   = (string)($rec['sub'] ?? '');
                    $type  = strtoupper((string)($rec['type'] ?? ''));
                    $value = (string)($rec['value'] ?? '');
                    if ($type === '' || $value === '' || !in_array($type, ['A', 'AAAA', 'CNAME', 'TXT', 'MX', 'SRV', 'CAA'], true)) {
                        continue;
                    }
                    $position++;
                    try {
                        db_query(
                            'INSERT IGNORE INTO records (domain_id, sub, type, value, ttl, position, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
                            [$domainId, $sub, $type, $value, (int)($rec['ttl'] ?? 3600), $position]
                        );
                        $copied++;
                    } catch (Throwable $e) {
                        // one bad row must never stop the adoption
                    }
                }
            }

            log_action('dnshe_adopted', 'domain', $domainId,
                $fqdn . ' -> ' . (string)$account['email'] . ' (' . $copied . ' record(s) copied)');
            $done = $zh
                ? ('已绑定 ' . $fqdn . ' 到 ' . (string)$account['email'] . '，并从 DNSHE 复制了 ' . $copied . ' 条记录。')
                : ('Adopted ' . $fqdn . ' into ' . (string)$account['email'] . ', copying ' . $copied . ' record(s).');
        } catch (Throwable $e) {
            $error = ($zh ? '绑定失败：' : 'Adoption failed: ') . $e->getMessage();
        }
    }
}

/* ---- list -------------------------------------------------------------- */
$rows    = [];
$listing = '';
if ($zone !== '') {
    $answer = dnshe_request('subdomains', 'list', ['domain' => $zone, 'per_page' => 200], 'GET', $zone);
    if (!$answer['ok']) {
        $listing = (string)$answer['error'];
    } else {
        foreach ((array)($answer['data']['subdomains'] ?? []) as $row) {
            $label = strtolower(trim((string)($row['subdomain'] ?? '')));
            if ($label === '') {
                continue;
            }
            $fqdn = $label . '.' . $zone;
            $known = db_one('SELECT id, status, registrant_email FROM domains WHERE name = ? LIMIT 1', [$fqdn]);
            $rows[] = [
                'label'   => $label,
                'fqdn'    => $fqdn,
                'ref'     => (int)($row['id'] ?? $row['subdomain_id'] ?? 0),
                'bound'   => $known !== null,
                'status'  => $known === null ? '' : (string)$known['status'],
                'owner'   => $known === null ? '' : (string)$known['registrant_email'],
            ];
        }
        usort($rows, static fn($a, $b) => $a['bound'] === $b['bound'] ? strcmp($a['fqdn'], $b['fqdn']) : ($a['bound'] ? 1 : -1));
    }
}

$total  = count($rows);
$unbound = 0;
foreach ($rows as $r) {
    if (!$r['bound']) {
        $unbound++;
    }
}

page_header($zh ? 'DNSHE 域名绑定' : 'DNSHE name binding');
?>
<h1><?= h($zh ? 'DNSHE 域名绑定' : 'DNSHE name binding') ?></h1>
<hr>

<?php if ($error !== ''): ?>
<p class="msg error"><?= h($error) ?></p>
<?php endif; ?>
<?php if ($done !== ''): ?>
<p class="msg ok"><?= h($done) ?></p>
<?php endif; ?>
<?php if ($listing !== ''): ?>
<p class="msg error"><?= h(($zh ? '读取 DNSHE 失败：' : 'Reading DNSHE failed: ') . $listing) ?></p>
<?php endif; ?>

<p class="small"><?= h($zh
    ? '这里显示 DNSHE 上真实存在的子域名。标「未绑定」的是 DNSHE 有、但本站数据库里没有的（数据丢失后遗留）。绑定后会：① 建域名行（状态 active）② 记住 DNSHE 的子域名 id ③ 把 DNSHE 现有记录复制进本站。'
    : 'This lists the subdomains DNSHE actually holds. "Unbound" means DNSHE has it but our tables do not (left over from the data loss). Adopting creates the domain row (active), remembers the DNSHE subdomain id and copies the existing records in.') ?></p>

<?php if ($zones === []): ?>
  <p class="msg error"><?= h($zh ? '没有任何后缀把服务商设成 DNSHE。先到 域名 → 后缀 里设置。' : 'No suffix uses DNSHE as its provider yet. Set that in Domains -> Suffixes.') ?></p>
<?php else: ?>
<form method="get" action="<?= h(url('admin/dnshe-bind.php')) ?>">
  <p>
    <?= h($zh ? '后缀' : 'Suffix') ?>
    <select name="zone" onchange="this.form.submit()">
      <?php foreach ($zones as $z): ?>
        <option value="<?= h($z) ?>"<?= $z === $zone ? ' selected' : '' ?>><?= h($z) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="submit" value="<?= h($zh ? '刷新' : 'Refresh') ?>">
  </p>
</form>

<p class="small">
  <?= h($zh ? '共 ' : 'Total: ') ?><strong><?= $total ?></strong>
  <?= h($zh ? ' 个，其中未绑定 ' : ' names, unbound: ') ?><strong><?= $unbound ?></strong>
</p>

<table>
<thead>
<tr>
  <th><?= h($zh ? '名字' : 'Name') ?></th>
  <th><?= h($zh ? 'DNSHE id' : 'DNSHE id') ?></th>
  <th><?= h($zh ? '状态' : 'State') ?></th>
  <th><?= h($zh ? '归属账号' : 'Owner') ?></th>
  <th><?= h($zh ? '操作' : 'Action') ?></th>
</tr>
</thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
  <td><code><?= h($r['fqdn']) ?></code></td>
  <td class="small"><?= $r['ref'] > 0 ? (int)$r['ref'] : '—' ?></td>
  <td>
    <?php if ($r['bound']): ?>
      <span class="ok"><?= h($zh ? '已绑定' : 'bound') ?></span>
      <span class="small">(<?= h($r['status']) ?>)</span>
    <?php else: ?>
      <strong><?= h($zh ? '未绑定' : 'unbound') ?></strong>
    <?php endif; ?>
  </td>
  <td class="small"><?= $r['bound'] ? h($r['owner']) : '—' ?></td>
  <td>
    <?php if ($r['bound']): ?>
      <a href="<?= h(url('admin/domains.php?q=' . urlencode($r['fqdn']))) ?>"><?= h($zh ? '查看' : 'Open') ?></a>
    <?php else: ?>
      <form method="post" action="<?= h(url('admin/dnshe-bind.php')) ?>" style="display:inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="adopt">
        <input type="hidden" name="zone" value="<?= h($zone) ?>">
        <input type="hidden" name="label" value="<?= h($r['label']) ?>">
        <input type="hidden" name="ref" value="<?= (int)$r['ref'] ?>">
        <input type="text" name="email" size="24" placeholder="<?= h($zh ? '账号邮箱' : 'account e-mail') ?>" required>
        <input type="submit" value="<?= h($zh ? '绑定' : 'Adopt') ?>">
      </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
<?php if ($rows === []): ?>
<tr><td colspan="5"><?= h($zh ? '这个后缀在 DNSHE 上没有任何子域名。' : 'DNSHE holds no subdomain for this suffix.') ?></td></tr>
<?php endif; ?>
</tbody>
</table>
<?php endif; ?>

<?php page_footer(); ?>

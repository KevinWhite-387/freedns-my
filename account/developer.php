<?php
declare(strict_types=1);

/**
 * freedns.my - console: developer status and requests for gated record types.
 *
 * Open types (A AAAA TXT MX CNAME SRV) never come here - they are submitted directly. This page is
 * for the rest: NS PTR CAA TLSA SVCB HTTPS CERT DNSKEY DS LOC NAPTR OPENPGPKEY SMIMEA SSHFP URL.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user = require_user();
$zh   = function_exists('lang') && is_zh();
$uid  = (int)$user['id'];
$notice = '';
$error  = '';

$fresh = db_one('SELECT * FROM users WHERE id = ?', [$uid]);
$isDev = user_is_developer($fresh);
$pendingStatus = developer_status_pending($uid);

/* ------------------------------------------------------------------ actions */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'apply') {
        $res = developer_apply($uid, trim((string)($_POST['reason'] ?? '')));
        if (!empty($res['ok'])) {
            flash($zh ? '申请已提交，等管理员审核。' : 'Request submitted.', 'ok');
            redirect(url('account/developer.php'));
        }
        $error = (string)($res['error'] ?? '');
    } elseif ($action === 'request') {
        $domainId = (int)($_POST['domain_id'] ?? 0);
        $type     = strtoupper(trim((string)($_POST['record_type'] ?? '')));
        $sub      = trim((string)($_POST['sub'] ?? ''));
        $value    = trim((string)($_POST['value'] ?? ''));
        $reason   = trim((string)($_POST['reason'] ?? ''));
        $res = developer_request_record($uid, $domainId, $type, $sub, $value, $reason);
        if (!empty($res['ok'])) {
            flash($zh ? '记录申请已提交，批准后会自动写入并发布。' : 'Record request submitted.', 'ok');
            redirect(url('account/developer.php'));
        }
        $error = (string)($res['error'] ?? '');
    }
}

/* My names, for the record request form. */
$myDomains = [];
try {
    $myDomains = db_all(
        'SELECT id, name, zone, status FROM domains WHERE registrant_email = ? AND status IN (\'active\',\'pending\')
         ORDER BY name ASC LIMIT 200',
        [(string)$user['email']]
    );
} catch (Throwable $e) {
    $myDomains = [];
}

$requests = developer_requests_of_user($uid, 40);
$approvedTypes = developer_types_for_user($uid);

/* Which gated types can actually be used, per suffix. */
$gatedByZone = [];
foreach ($myDomains as $d) {
    $zone = (string)$d['zone'];
    if (isset($gatedByZone[$zone])) {
        continue;
    }
    $gated = [];
    foreach (zone_record_types($zone) as $t) {
        if (!record_type_is_open($t)) {
            $gated[] = $t;
        }
    }
    $gatedByZone[$zone] = $gated;
}

page_header($zh ? '开发者' : 'Developer');
?>
<h1><?= h($zh ? '开发者' : 'Developer') ?></h1>
<hr>

<?php if ($error !== ''): ?>
<p class="msg error"><?= h($error) ?></p>
<?php endif; ?>

<?php if (!developer_requests_ready()): ?>
<p class="msg error"><?= h($zh
    ? '数据库还缺表：请先导入 upgrade-record-types.sql。'
    : 'The developer_requests table is missing: import upgrade-record-types.sql first.') ?></p>
<?php endif; ?>

<h2><?= h($zh ? '状态' : 'Status') ?></h2>
<table>
<tr><th><?= h($zh ? '账号' : 'Account') ?></th><td><code><?= h((string)$fresh['username']) ?></code></td></tr>
<tr><th><?= h($zh ? '开发者' : 'Developer') ?></th><td>
<?php if ($isDev): ?>
  <span class="ok"><?= h($zh ? '是' : 'yes') ?></span>
  <span class="small"><?= h((string)($fresh['developer_since'] ?? '')) ?></span>
<?php elseif ($pendingStatus): ?>
  <span class="hint"><?= h($zh ? '已申请，等待审核' : 'requested, waiting') ?></span>
<?php else: ?>
  <span class="hint"><?= h($zh ? '否' : 'no') ?></span>
<?php endif; ?>
</td></tr>
<tr><th><?= h($zh ? '公开类型' : 'Open types') ?></th><td><code><?= h(implode(' ', record_types_open())) ?></code></td></tr>
<?php if ($approvedTypes !== []): ?>
<tr><th><?= h($zh ? '已批准的额外类型' : 'Extra types approved') ?></th><td><code><?= h(implode(' ', $approvedTypes)) ?></code></td></tr>
<?php endif; ?>
</table>

<?php if (!$isDev && !$pendingStatus): ?>
<h2><?= h($zh ? '申请开发者身份' : 'Apply for developer status') ?></h2>
<p><?= h($zh
    ? '公开类型（上面那几个）人人都能用。其余类型（NS / PTR / CAA / TLSA / SVCB / HTTPS / CERT / DNSKEY / DS / LOC / NAPTR / OPENPGPKEY / SMIMEA / SSHFP / URL）需要开发者身份，并且还要看该后缀的服务商是否支持。'
    : 'The open types above need no permission. Everything else needs a developer account, and the suffix provider must support it.') ?></p>
<form method="post" action="<?= h(url('account/developer.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="apply">
  <p><label for="reason"><?= h($zh ? '用途说明（必填，写清楚你要做什么记录）' : 'What you need it for') ?></label><br>
  <textarea id="reason" name="reason" rows="4" style="width:100%;max-width:640px" required></textarea></p>
  <p><input type="submit" value="<?= h($zh ? '提交申请' : 'Submit request') ?>"></p>
</form>
<?php endif; ?>

<?php if ($isDev && $myDomains !== []): ?>
<h2><?= h($zh ? '申请写入一条非公开记录' : 'Request one gated record') ?></h2>
<p class="small"><?= h($zh
    ? '批准后会自动写入并发布到 DNS 服务商。服务商不支持的类型不会出现在下拉里。'
    : 'Once approved the record is written and published. Types the provider does not accept are not offered.') ?></p>
<form method="post" action="<?= h(url('account/developer.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="request">
  <p><label for="domain_id"><?= h($zh ? '域名' : 'Name') ?></label><br>
  <select id="domain_id" name="domain_id" required>
    <?php foreach ($myDomains as $d): ?>
      <option value="<?= (int)$d['id'] ?>"><?= h((string)$d['name']) ?> · <?= h((string)$d['status']) ?></option>
    <?php endforeach; ?>
  </select></p>

  <p><label for="record_type"><?= h($zh ? '类型' : 'Type') ?></label><br>
  <select id="record_type" name="record_type" required>
    <?php
    $offered = [];
    foreach ($gatedByZone as $zone => $types) {
        foreach ($types as $t) {
            $offered[$t] = $zone;
        }
    }
    ksort($offered);
    foreach ($offered as $t => $zone):
    ?>
      <option value="<?= h($t) ?>"><?= h($t) ?> (<?= h($zone) ?>)</option>
    <?php endforeach; ?>
    <?php if ($offered === []): ?>
      <option value=""><?= h($zh ? '（没有可用类型：先给后缀配置服务商）' : '(none: configure a provider first)') ?></option>
    <?php endif; ?>
  </select></p>

  <p><label for="sub"><?= h($zh ? '主机记录（根域留空或 @）' : 'Host (empty or @ for the apex)') ?></label><br>
  <input type="text" id="sub" name="sub" maxlength="63" style="width:220px" placeholder="@"></p>

  <p><label for="value"><?= h($zh ? '记录值' : 'Value') ?></label><br>
  <textarea id="value" name="value" rows="2" style="width:100%;max-width:640px" required></textarea>
  <span class="small"><?= h($zh
      ? 'MX 写「10 mx1.example.com」；TLSA/SVCB/HTTPS 按 RFC 的字段顺序写'
      : 'MX as "10 mx1.example.com"; TLSA/SVCB/HTTPS in the RFC field order') ?></span></p>

  <p><label for="reason"><?= h($zh ? '理由' : 'Reason') ?></label><br>
  <textarea id="reason" name="reason" rows="2" style="width:100%;max-width:640px"></textarea></p>

  <p><input type="submit" value="<?= h($zh ? '提交记录申请' : 'Submit record request') ?>"></p>
</form>
<?php endif; ?>

<h2><?= h($zh ? '我的申请' : 'My requests') ?></h2>
<?php if ($requests === []): ?>
<p class="hint"><?= h($zh ? '还没有申请。' : 'Nothing yet.') ?></p>
<?php else: ?>
<table>
<tr><th>#</th><th><?= h($zh ? '类型' : 'Type') ?></th><th><?= h($zh ? '域名' : 'Name') ?></th>
    <th><?= h($zh ? '状态' : 'Status') ?></th><th><?= h($zh ? '提交' : 'Sent') ?></th><th><?= h($zh ? '备注' : 'Note') ?></th></tr>
<?php foreach ($requests as $r): ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><code><?= h((string)$r['record_type'] === '*' ? ($zh ? '开发者身份' : 'developer') : (string)$r['record_type']) ?></code></td>
  <td><?= h((string)$r['domain_name']) ?></td>
  <td><?php
      $st = (string)$r['status'];
      $cls = $st === 'approved' ? 'ok' : ($st === 'rejected' ? 'err' : 'hint');
      echo '<span class="' . $cls . '">' . h($zh
          ? ($st === 'approved' ? '已批准' : ($st === 'rejected' ? '已驳回' : '等待中'))
          : $st) . '</span>';
  ?></td>
  <td class="small"><?= h((string)$r['created_at']) ?></td>
  <td class="small"><?= h((string)($r['admin_note'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<hr>
<p class="small"><?= h($zh
    ? '公开类型可以随时直接改，不需要来这里。这里只处理需要授权的记录类型。'
    : 'The open types can be edited at any time without this page.') ?></p>

<?php page_footer(); ?>

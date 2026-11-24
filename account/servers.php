<?php
declare(strict_types=1);

/**
 * freedns.my - console: the cloud servers bound to this account (RainYun).
 *
 * The holder hands over his own API key, the registry stores it encrypted and reads the instance with it:
 * name, specs, state and the expiry date, with a reminder by mail before it runs out. Starting, stopping,
 * rebooting and reinstalling use the same key and are written to the audit log; reinstalling is off unless
 * the operator switches it on, because it destroys the data on the instance.
 *
 * The page speaks Chinese and English on its own (the wording is written here), so it works even when a
 * language file is newer or older than this feature.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
我的云服务器
');

$user   = require_user();
$zh     = is_zh();
$errors = [];
$notice = '';

$T = $zh ? [
    'heading' => '我的云服务器',
    'intro'   => '把你在雨云购买的服务器绑定到这里：可以看状态与到期时间、到期前收到邮件提醒，也可以开关机、重启，必要时重装系统。服务器始终属于你的雨云账号，注册局不代付、不托管。',
    'add'     => '绑定一台服务器',
    'key'     => '雨云 API 密钥',
    'key_hint' => '在雨云控制台「API 密钥」里创建，权限给只读就够；密钥会以 AES-256-GCM 加密保存，页面上只显示尾 4 位，任何时候都不会回显明文。',
    'instance' => '实例 ID',
    'instance_hint' => '雨云控制台里实例的 ID（数字）。绑定时会立刻用它拉取一次信息，验证密钥与实例是否匹配。',
    'label'   => '备注名（可选）',
    'save'    => '绑定',
    'mine'    => '已绑定的服务器',
    'none'    => '还没有绑定任何服务器。',
    'name'    => '实例',
    'specs'   => '规格',
    'state'   => '状态',
    'expires' => '到期时间',
    'left'    => '剩余',
    'days'    => '%s 天',
    'expired' => '已过期',
    'synced'  => '上次同步',
    'sync'    => '同步',
    'power_on' => '开机',
    'power_off' => '关机',
    'reboot'  => '重启',
    'reinstall' => '重装系统',
    'reinstall_warn' => '重装会清除实例上的全部数据，且不可恢复。请输入下面这句话确认：',
    'reinstall_phrase' => '确认重装',
    'os'      => '要安装的系统',
    'unbind'  => '解除绑定',
    'unbind_hint' => '解除绑定只会删除我们保存的密钥与记录，不会影响你的服务器。',
    'key_never' => '密钥不会显示；如需更换，直接重新绑定该实例即可。',
    'error'   => '雨云返回的错误',
] : [
    'heading' => 'My cloud servers',
    'intro'   => 'Bind the servers you bought at RainYun here: see their state and expiry date, get a reminder by mail before they run out, and start, stop, reboot or reinstall them when needed. The server stays in your RainYun account - the registry neither pays for it nor holds it.',
    'add'     => 'Bind a server',
    'key'     => 'RainYun API key',
    'key_hint' => 'Create it under "API key" in the RainYun console; read-only rights are enough. It is stored with AES-256-GCM, only the last four characters are shown, and it is never printed again.',
    'instance' => 'Instance ID',
    'instance_hint' => 'The numeric id of the instance in the RainYun console. Binding fetches it once, to check that the key and the instance belong together.',
    'label'   => 'Your own name for it (optional)',
    'save'    => 'Bind it',
    'mine'    => 'Bound servers',
    'none'    => 'Nothing bound yet.',
    'name'    => 'Instance',
    'specs'   => 'Specs',
    'state'   => 'State',
    'expires' => 'Expires',
    'left'    => 'Left',
    'days'    => '%s day(s)',
    'expired' => 'expired',
    'synced'  => 'Last sync',
    'sync'    => 'Sync',
    'power_on' => 'Start',
    'power_off' => 'Stop',
    'reboot'  => 'Restart',
    'reinstall' => 'Reinstall',
    'reinstall_warn' => 'Reinstalling wipes everything on the instance and cannot be undone. Type the phrase below to confirm:',
    'reinstall_phrase' => 'CONFIRM REINSTALL',
    'os'      => 'System to install',
    'unbind'  => 'Unbind',
    'unbind_hint' => 'Unbinding only removes the key and the record stored here; your server is untouched.',
    'key_never' => 'The key is never shown; to replace it, bind the instance again.',
    'error'   => 'RainYun answered',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && rainyun_tables_ready()) {
    csrf_verify();

    /*
     * While the feature is frozen, nothing may be written - not even by a request that was put together by
     * hand. The buttons are hidden on the page as well, but that is only a courtesy: this is the check that
     * actually protects the setting.
     */
    if (rainyun_readonly()) {
        log_action('srv_refused_readonly', 'server', null,
            'a write request was refused because the feature is read-only (action: '
            . substr((string)($_POST['action'] ?? '?'), 0, 32) . ')');
        flash($zh
            ? '出于安全原因，雨云服务器功能正在全新升级维护中，暂时只读，无法进行任何修改。'
            : 'For security reasons the cloud server feature is being rebuilt and is read-only; nothing can be changed for now.', 'error');
        redirect(url('account/servers.php'));
    }

    $action = (string)($_POST['action'] ?? '');
    $mine   = rainyun_bindings_of((int)$user['id']);
    $binding = null;
    foreach ($mine as $candidate) {
        if ((int)$candidate['id'] === (int)($_POST['id'] ?? 0)) {
            $binding = $candidate;
        }
    }

    if ($action === 'request') {
        $result = rainyun_request_submit($user, (string)($_POST['remote_id'] ?? ''),
            (string)($_POST['purpose'] ?? ''));
        if ($result['ok']) {
            flash($zh ? '申请已提交，注册局会审核并通知你。' : 'The request was sent; the registry reviews it and tells you.', 'ok');
            redirect(url('account/servers.php'));
        }
        $errors[] = $result['error'];
    } elseif ($action === 'withdraw') {
        $req = db_one('SELECT * FROM server_requests WHERE id = ?', [(int)($_POST['request_id'] ?? 0)]);
        if ($req !== null && rainyun_request_withdraw($req, $user)) {
            flash($zh ? '已撤回申请。' : 'The request was withdrawn.', 'ok');
            redirect(url('account/servers.php'));
        }
        $errors[] = $zh ? '无法撤回该申请。' : 'That request cannot be withdrawn.';
    } elseif ($action === 'bind') {
        // Self-service binding: the holder's own RainYun key. An approved request is still required - that is
        // the gate the registry keeps - but the instance no longer has to be created for him by hand.
        $result = rainyun_bind_self($user, (string)($_POST['api_key'] ?? ''), (string)($_POST['remote_id'] ?? ''),
            trim((string)($_POST['label'] ?? '')));
        if ($result['ok']) {
            flash($zh ? '绑定成功，已读取实例信息。' : 'Bound, and the instance details were read.', 'ok');
            redirect(url('account/servers.php'));
        }
        $errors[] = $result['error'];
        $result = rainyun_bind($user, (string)($_POST['api_key'] ?? ''), (string)($_POST['remote_id'] ?? ''),
            trim((string)($_POST['label'] ?? '')));
        if ($result['ok']) {
            flash($zh ? '已绑定。' : 'Bound.', 'ok');
            redirect(url('account/servers.php'));
        }
        $errors[] = $result['error'];
    } elseif ($binding === null) {
        $errors[] = $zh ? '该服务器不在你的账号下。' : 'That server is not bound to your account.';
    } elseif ($action === 'sync') {
        $result = rainyun_sync($binding, true);
        if ($result['ok']) {
            flash($zh ? '已同步。' : 'Synced.', 'ok');
            redirect(url('account/servers.php'));
        }
        $errors[] = $result['error'];
    } elseif (in_array($action, ['start', 'stop', 'reboot'], true)) {
        $result = rainyun_action($binding, $action);
        if ($result['ok']) {
            flash($zh ? '指令已发送。' : 'The command was sent.', 'ok');
            redirect(url('account/servers.php'));
        }
        $errors[] = $result['error'];
    } elseif ($action === 'unbind') {
        rainyun_unbind($binding);
        flash($zh ? '已解除绑定。' : 'Unbound.', 'ok');
        redirect(url('account/servers.php'));
    } elseif (in_array($action, ['fw_mode', 'fw_add', 'fw_delete', 'fw_move', 'fw_sync'], true)) {
        if ($action === 'fw_mode') {
            $result = rainyun_firewall_set_mode($binding, strtolower(trim((string)($_POST['mode'] ?? ''))));
        } elseif ($action === 'fw_add') {
            $result = rainyun_firewall_rule($binding, 'add', [
                'protocol' => (string)($_POST['protocol'] ?? 'tcp'),
                'port'     => (string)($_POST['port'] ?? ''),
                'action'   => (string)($_POST['rule_action'] ?? 'accept'),
                'ip'       => (string)($_POST['ip'] ?? ''),
                'remark'   => (string)($_POST['remark'] ?? ''),
            ]);
        } elseif ($action === 'fw_delete') {
            $result = rainyun_firewall_rule($binding, 'delete', [], (string)($_POST['rule_id'] ?? ''));
        } elseif ($action === 'fw_move') {
            $result = rainyun_firewall_rule($binding, 'move', [], (string)($_POST['rule_id'] ?? ''),
                (int)($_POST['pos'] ?? 0));
        } else {
            $result = rainyun_firewall_sync_time($binding);
        }

        if ($result['ok']) {
            flash($zh ? '防火墙设置已提交。' : 'The firewall was updated.', 'ok');
            redirect(url('account/servers.php'));
        }
        $errors[] = $result['error'];
    } elseif (in_array($action, ['backup_create', 'backup_restore', 'backup_delete', 'backup_cancel'], true)) {
        $what     = substr($action, 7);            // create | restore | delete | cancel
        $backupId = trim((string)($_POST['backup_id'] ?? ''));
        $phrase   = trim((string)($_POST['phrase'] ?? ''));

        if (in_array($what, ['restore', 'delete'], true) && $phrase !== ($zh ? '确认恢复' : 'CONFIRM RESTORE')) {
            $errors[] = $zh ? '确认短语不正确，未执行该操作。' : 'The confirmation phrase is wrong; nothing was done.';
        } else {
            $result = rainyun_backup_action($binding, $what, $backupId);
            if ($result['ok']) {
                flash($zh ? '备份指令已发送。' : 'The backup command was sent.', 'ok');
                redirect(url('account/servers.php'));
            }
            $errors[] = $result['error'];
        }
    } elseif ($action === 'reinstall') {
        $phrase = trim((string)($_POST['phrase'] ?? ''));
        $osId   = trim((string)($_POST['os_id'] ?? ''));
        if ($phrase !== $T['reinstall_phrase']) {
            $errors[] = $zh ? '确认短语不正确，未执行重装。' : 'The confirmation phrase is wrong; nothing was done.';
        } elseif (!setting_bool('rainyun_allow_reinstall', false)) {
            $errors[] = $zh ? '重装功能当前已关闭（需管理员在后台开启）。' : 'Reinstalling is switched off (the operator has to enable it).';
        } else {
            $result = rainyun_action($binding, 'changeos', $osId === '' ? [] : ['os_id' => (int)$osId]);
            if ($result['ok']) {
                flash($zh ? '重装指令已发送，请稍后同步查看状态。' : 'The reinstall was requested; sync in a moment to see the state.', 'ok');
                redirect(url('account/servers.php'));
            }
            $errors[] = $result['error'];
        }
    }
}
?>

<?php $bindings   = rainyun_tables_ready() ? rainyun_bindings_of((int)$user['id']) : []; ?>
<?php $reinstall  = setting_bool('rainyun_allow_reinstall', false); ?>
<?php $power      = setting_bool('rainyun_allow_power', true); ?>
<?php $canFull    = false; foreach ($bindings as $fdRow) { if (rainyun_may($fdRow, 'full')) { $canFull = true; } } ?>

<?php
page_header($T['heading']);
?>
<p><?= h($T['intro']) ?></p>
<?php if (rainyun_readonly()): ?>
<div class="msg error" id="srv-readonly-flag">
  <strong><?= $zh ? '维护中（只读）' : 'Under maintenance (read-only)' ?></strong><br>
  <?= h(rainyun_readonly_note()) ?>
</div>
<?php
/*
 * Every form that follows this notice is hidden, and so is anything inside it - the page keeps showing the
 * servers and their state, but offers nothing to press. This is only a courtesy: the write paths themselves
 * refuse the request as well (see the POST handler above), because hiding a button protects nothing.
 */
?>
<style>
  #srv-readonly-flag ~ form,
  #srv-readonly-flag ~ * form,
  #srv-readonly-flag ~ table form,
  #srv-readonly-flag ~ div form,
  #srv-readonly-flag ~ details form { display: none !important; }
</style>
<?php endif; ?>
<?= error_list($errors) ?>
<?php if ($notice !== ''): ?><div class="msg info"><?= h($notice) ?></div><?php endif; ?>

<?php if (!rainyun_tables_ready()): ?>
<div class="msg error"><?= h($zh
    ? '数据库缺少本功能所需表，请导入 upgrade-servers.sql（自检页可以直接帮你执行）。'
    : 'The database is missing the tables of this feature; import upgrade-servers.sql (the self test can apply it).') ?></div>
<?php else: ?>

<h2><?= h($T['mine']) ?></h2>
<?php if ($bindings === []): ?>
<p><?= h($T['none']) ?></p>
<?php else: ?>
<table>
  <tr><th>#</th><th><?= h($T['name']) ?></th><th><?= h($T['specs']) ?></th><th><?= h($T['state']) ?></th>
      <th><?= h($T['expires']) ?></th><th><?= h($T['left']) ?></th><th><?= h($T['synced']) ?></th><th></th></tr>
  <?php foreach ($bindings as $row): ?>
  <?php $left = rainyun_days_left($row['expires_at'] === null ? null : (string)$row['expires_at']); ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><code><?= h((string)($row['name'] ?? $row['remote_id'])) ?></code>
        <?php if (trim((string)($row['label'] ?? '')) !== ''): ?>
        <br><span class="small"><?= h((string)$row['label']) ?></span>
        <?php endif; ?>
        <br><span class="small">ID <?= h((string)$row['remote_id']) ?> &middot;
        <?= rainyun_may($row, 'full')
            ? h($zh ? '可操作' : 'you may operate it')
            : h($zh ? '只读' : 'read-only') ?></span></td>
    <td class="small"><?= h((string)($row['specs'] ?? '')) ?></td>
    <td><?= h((string)($row['status'] ?? '')) ?></td>
    <td><?= h((string)($row['expires_at'] ?? '-')) ?></td>
    <td><?= $left === null ? '-' : ($left < 0
        ? '<span class="err">' . h($T['expired']) . '</span>'
        : '<span class="' . ($left < 7 ? 'err' : 'ok') . '">' . h(sprintf($T['days'], (string)$left)) . '</span>') ?></td>
    <td class="small"><?= h((string)($row['last_synced_at'] ?? '-')) ?>
        <?php if (trim((string)($row['sync_error'] ?? '')) !== ''): ?>
        <br><span class="err"><?= h((string)$row['sync_error']) ?></span>
        <?php endif; ?></td>
    <td class="actions">
      <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="sync">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="<?= h($T['sync']) ?>">
      </form>
      <?php if ($power && rainyun_may($row, 'full')): ?>
      <?php foreach (['start' => $T['power_on'], 'reboot' => $T['reboot'], 'stop' => $T['power_off']] as $op => $label): ?>
      <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="<?= h($op) ?>">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="<?= h($label) ?>">
      </form>
      <?php endforeach; ?>
      <?php endif; ?>
      <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="unbind">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="<?= h($T['unbind']) ?>">
      </form>
    </td>
  </tr>
  <tr>
    <td></td>
    <td colspan="7" class="small">
      <?php if ($reinstall): ?>
      <form method="post" action="<?= h(url('account/servers.php')) ?>">
        <?= csrf_field() ?><input type="hidden" name="action" value="reinstall">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <span class="err"><?= h($T['reinstall_warn']) ?></span>
        <?php $templates = rainyun_os_templates($row); ?>
        <?php if ($templates !== []): ?>
        <label for="os<?= (int)$row['id'] ?>"><?= h($T['os']) ?></label>
        <select id="os<?= (int)$row['id'] ?>" name="os_id">
          <?php foreach ($templates as $osId => $osName): ?>
          <option value="<?= h((string)$osId) ?>"><?= h($osName) ?></option>
          <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <label for="ph<?= (int)$row['id'] ?>"><?= h($T['reinstall_phrase']) ?></label>
        <input type="text" id="ph<?= (int)$row['id'] ?>" name="phrase" value="" autocomplete="off">
        <input type="submit" value="<?= h($T['reinstall']) ?>">
      </form>
      <?php else: ?>
      <span><?= h($zh ? '重装功能未开启（管理员可在后台按需打开）。' : 'Reinstalling is not enabled (the operator can switch it on).') ?></span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint"><?= h($T['unbind_hint']) ?> <?= h($T['key_never']) ?></p>
<?php endif; ?>

<h2><?= h(t('srv.usage')) ?></h2>
<p class="hint"><?= h(t('srv.usage_hint')) ?></p>
<?php $vncWanted = (int)($_GET['vnc'] ?? 0); ?>
<?php foreach ($bindings as $row): ?>
<?php $usage = rainyun_usage($row); $maint = rainyun_maintenance($row); ?>
<details>
  <summary><code><?= h((string)($row['name'] ?? $row['remote_id'])) ?></code>
    <?php if ($maint['state'] !== '' || $maint['items'] !== []): ?>
    &mdash; <span class="err"><?= h(t('srv.maintenance')) ?></span>
    <?php endif; ?></summary>

  <?php if ($usage['values'] === []): ?>
  <p class="hint"><?= h($zh ? '没有读到数值（该接口可能不提供，或密钥权限不足）。' : 'No value could be read (the API may not offer it, or the key lacks the right).') ?></p>
  <?php else: ?>
  <table>
    <tr><th><?= h($zh ? '指标' : 'Metric') ?></th><th><?= h($zh ? '数值' : 'Value') ?></th></tr>
    <?php foreach ($usage['values'] as $label => $value): ?>
    <tr><td><?= h((string)$label) ?></td><td><?= h((string)$value) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <?php if ($usage['raw'] !== ''): ?>
  <details><summary><?= h(t('srv.raw')) ?></summary>
    <pre class="wrap code-block"><?= h($usage['raw']) ?></pre></details>
  <?php endif; ?>

  <h4><?= h(t('srv.maintenance')) ?></h4>
  <?php if ($maint['items'] === [] && $maint['state'] === ''): ?>
  <p class="hint"><?= h(t('srv.no_maintenance')) ?></p>
  <?php else: ?>
  <?php if ($maint['state'] !== ''): ?>
  <p><strong><?= h($zh ? '状态' : 'State') ?>:</strong> <?= h($maint['state']) ?></p>
  <?php endif; ?>
  <?php if ($maint['items'] !== []): ?>
  <table>
    <tr><th><?= h($zh ? '时间' : 'Time') ?></th><th><?= h($zh ? '说明' : 'Remark') ?></th></tr>
    <?php foreach ($maint['items'] as $item): ?>
    <tr><td class="small"><?= h($item['time']) ?></td><td class="small"><?= h($item['remark']) ?></td></tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <?php endif; ?>

  <p>
    <a href="<?= h(url('account/servers.php?vnc=' . (int)$row['id'])) ?>"><?= h(t('srv.vnc')) ?></a>
    <span class="hint"><?= h(t('srv.vnc_hint')) ?></span>
  </p>
  <?php if ($vncWanted === (int)$row['id']): ?>
  <?php $ticket = rainyun_vnc($row); ?>
  <?php if ($ticket['ok']): ?>
  <div class="msg info">
    <strong><?= h($zh ? '一次性 VNC 地址（请立即使用，离开本页即视为放弃）' : 'One-time VNC address (use it now; leaving this page gives it up)') ?></strong>
    <p><a href="<?= h($ticket['url']) ?>" rel="noopener noreferrer"><?= h($ticket['url']) ?></a></p>
  </div>
  <?php else: ?>
  <div class="msg error"><?= h($ticket['error']) ?></div>
  <?php endif; ?>
  <?php endif; ?>
</details>
<?php endforeach; ?>

<h2><?= h($zh ? '防火墙' : 'Firewall') ?></h2>
<p class="hint"><?= h($zh
    ? '规则由雨云在实例上执行。注意：写错规则可能把你自己关在服务器外面（例如把 SSH 端口拦掉），页面无法替你判断这一点。'
    : 'The provider applies these rules on the instance. Careful: a wrong rule can lock you out of your own server (blocking the SSH port, for instance) - nothing here can detect that for you.') ?></p>
<?php foreach ($bindings as $row): ?>
<?php $fw = rainyun_firewall($row); ?>
<details>
  <summary><code><?= h((string)($row['name'] ?? $row['remote_id'])) ?></code>
    &mdash; <?= h($zh ? '模式' : 'mode') ?>: <strong><?= h($fw['mode'] === '' ? '?' : $fw['mode']) ?></strong>,
    <?= (int)count($fw['rules']) ?> <?= h($zh ? '条规则' : 'rule(s)') ?></summary>

  <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="fw_mode">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <?php foreach (['off', 'white', 'black'] as $mode): ?>
    <input type="submit" name="mode" value="<?= h($mode) ?>"<?= $fw['mode'] === $mode ? ' disabled' : '' ?>>
    <?php endforeach; ?>
  </form>
  <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="fw_sync">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <input type="submit" value="<?= h($zh ? '从实例同步' : 'Sync from the instance') ?>">
  </form>

  <?php if ($fw['rules'] === []): ?>
  <p class="hint"><?= h($zh ? '没有规则（或读取失败）。' : 'No rule (or reading them failed).') ?></p>
  <?php else: ?>
  <table>
    <tr><th><?= h($zh ? '位置' : 'Pos') ?></th><th><?= h($zh ? '协议' : 'Protocol') ?></th>
        <th><?= h($zh ? '端口' : 'Port') ?></th><th><?= h($zh ? '动作' : 'Action') ?></th>
        <th><?= h($zh ? '来源' : 'Source') ?></th><th><?= h($zh ? '备注' : 'Remark') ?></th><th></th></tr>
    <?php foreach ($fw['rules'] as $rule): ?>
    <tr>
      <td><?= h($rule['pos']) ?></td>
      <td><?= h(strtoupper($rule['protocol'])) ?></td>
      <td><?= h($rule['port']) ?></td>
      <td><?= h($rule['action']) ?></td>
      <td class="small"><?= h($rule['ip']) ?></td>
      <td class="small"><?= h($rule['remark']) ?></td>
      <td>
        <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="fw_move">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <input type="hidden" name="rule_id" value="<?= h($rule['id']) ?>">
          <input type="text" name="pos" value="" maxlength="3" size="3" placeholder="pos">
          <input type="submit" value="<?= h($zh ? '移到' : 'Move') ?>">
        </form>
        <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="fw_delete">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <input type="hidden" name="rule_id" value="<?= h($rule['id']) ?>">
          <input type="submit" value="<?= h($zh ? '删除' : 'Delete') ?>">
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>

  <form method="post" action="<?= h(url('account/servers.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="fw_add">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <label for="p<?= (int)$row['id'] ?>"><?= h($zh ? '协议' : 'Protocol') ?></label>
    <select id="p<?= (int)$row['id'] ?>" name="protocol">
      <?php foreach (['tcp', 'udp', 'icmp', 'all'] as $protocol): ?>
      <option value="<?= h($protocol) ?>"><?= h(strtoupper($protocol)) ?></option>
      <?php endforeach; ?>
    </select>
    <label for="pt<?= (int)$row['id'] ?>"><?= h($zh ? '端口（如 22 或 8000-8100）' : 'Port (22, or 8000-8100)') ?></label>
    <input type="text" id="pt<?= (int)$row['id'] ?>" name="port" value="" maxlength="64">
    <label for="a<?= (int)$row['id'] ?>"><?= h($zh ? '动作' : 'Action') ?></label>
    <select id="a<?= (int)$row['id'] ?>" name="rule_action">
      <option value="accept">accept</option>
      <option value="drop">drop</option>
    </select>
    <label for="s<?= (int)$row['id'] ?>"><?= h($zh ? '来源（默认 0.0.0.0/0）' : 'Source (0.0.0.0/0 by default)') ?></label>
    <input type="text" id="s<?= (int)$row['id'] ?>" name="ip" value="" maxlength="64">
    <label for="r<?= (int)$row['id'] ?>"><?= h($zh ? '备注' : 'Remark') ?></label>
    <input type="text" id="r<?= (int)$row['id'] ?>" name="remark" value="" maxlength="64">
    <input type="submit" value="<?= h($zh ? '添加规则' : 'Add the rule') ?>">
  </form>
</details>
<?php endforeach; ?>

<h2><?= h($zh ? '备份' : 'Backups') ?></h2>
<p class="hint"><?= h($zh
    ? '每台服务器下可以创建快照、恢复或删除。恢复会覆盖磁盘上的全部数据，因此与重装共用同一把开关（默认关闭），并且需要输入确认短语。'
    : 'Create a snapshot per server, restore it or throw it away. Restoring overwrites everything on the disk, so it shares the switch with reinstalling (off by default) and asks for a confirmation phrase.') ?></p>
<?php foreach ($bindings as $row): ?>
<?php $backups = rainyun_backups($row); ?>
<details>
  <summary><code><?= h((string)($row['name'] ?? $row['remote_id'])) ?></code>
    &mdash; <?= (int)count($backups) ?> <?= h($zh ? '个备份' : 'backup(s)') ?></summary>
  <?php if ($backups === []): ?>
  <p class="hint"><?= h($zh ? '还没有备份。' : 'No backup yet.') ?></p>
  <?php else: ?>
  <table>
    <tr><th>ID</th><th><?= h($zh ? '备注' : 'Name') ?></th><th><?= h($zh ? '时间' : 'Created') ?></th>
        <th><?= h($zh ? '大小' : 'Size') ?></th><th><?= h($zh ? '状态' : 'State') ?></th>
        <th></th><th></th></tr>
    <?php foreach ($backups as $backup): ?>
    <tr>
      <td><?= h($backup['id']) ?></td>
      <td><?= h($backup['name']) ?></td>
      <td class="small"><?= h($backup['created']) ?></td>
      <td><?= h($backup['size']) ?></td>
      <td><?= h($backup['state']) ?></td>
      <td colspan="2">
        <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="backup_restore">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <input type="hidden" name="backup_id" value="<?= h($backup['id']) ?>">
          <input type="text" name="phrase" value="" placeholder="<?= h($zh ? '确认恢复' : 'CONFIRM RESTORE') ?>"
                 maxlength="20" autocomplete="off">
          <input type="submit" value="<?= h($zh ? '恢复' : 'Restore') ?>">
        </form>
        <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="backup_delete">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <input type="hidden" name="backup_id" value="<?= h($backup['id']) ?>">
          <input type="text" name="phrase" value="" placeholder="<?= h($zh ? '确认恢复' : 'CONFIRM RESTORE') ?>"
                 maxlength="20" autocomplete="off">
          <input type="submit" value="<?= h($zh ? '删除' : 'Delete') ?>">
        </form>
        <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="backup_cancel">
          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
          <input type="hidden" name="backup_id" value="<?= h($backup['id']) ?>">
          <input type="submit" value="<?= h($zh ? '取消' : 'Cancel') ?>">
        </form>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
  <?php endif; ?>
  <form method="post" action="<?= h(url('account/servers.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="backup_create">
    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
    <input type="submit" value="<?= h($zh ? '立即创建备份' : 'Create a backup now') ?>">
  </form>
</details>
<?php endforeach; ?>

<h2><?= h($zh ? '用你自己的雨云 API Key 绑定' : 'Bind with your own RainYun API key') ?></h2>
<p class="hint"><?= $zh
    ? '需要先获得批准（申请通过后即可使用）。密钥只用于读取与操作你这台服务器，会以加密方式保存，页面上只会显示尾 4 位；任何时候都不会明文回显。'
    : 'An approved request is needed first. The key is used only for your own instance, is stored encrypted, and only its last four characters are ever shown back.' ?></p>
<form method="post" action="<?= h(url('account/servers.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="bind">
<label for="api_key"><?= $zh ? '雨云 API Key' : 'RainYun API key' ?></label>
<input type="text" id="api_key" name="api_key" maxlength="190" autocomplete="off">
<label for="bind_remote"><?= $zh ? '实例 ID' : 'Instance id' ?></label>
<input type="text" id="bind_remote" name="remote_id" maxlength="64" autocomplete="off">
<label for="bind_label"><?= $zh ? '备注名（可留空）' : 'Your own name for it (optional)' ?></label>
<input type="text" id="bind_label" name="label" maxlength="64">
<p><input type="submit" value="<?= $zh ? '绑定' : 'Bind it' ?>"></p>
</form>

<h2><?= h($zh ? '申请绑定服务器' : 'Ask for a server') ?></h2>
<p class="hint"><?= h($zh
    ? '服务器由注册局在后台登记并指派给你，因此这里不需要填写任何密钥。你可以两种方式申请：已经买好的实例就填实例 ID；还没买就只写用途，由注册局为你挑一台。'
    : 'The registry registers the server and assigns it to you, so no key is needed here. Ask in either way: name the instance you already bought, or leave the id empty and describe what you need.') ?></p>
<form method="post" action="<?= h(url('account/servers.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="request">
<label for="remote_id"><?= h($zh ? '实例 ID（已买好才填，可留空）' : 'Instance id (only if you already bought one)') ?></label>
<input type="text" id="remote_id" name="remote_id" value="" maxlength="64" autocomplete="off">
<label for="purpose"><?= h($zh ? '用途说明（必填，至少 10 个字）' : 'What you want it for (required, at least 10 characters)') ?></label>
<textarea id="purpose" name="purpose" rows="4"></textarea>
<p class="hint"><?= h($zh
    ? '注册局不代付、不代购：服务器费用与责任仍在你自己的雨云账号。批准后你会在本页看到它。'
    : 'The registry does not pay for or buy anything: the cost and the responsibility stay with your own RainYun account. Once it is approved you see it on this page.') ?></p>
<p><input type="submit" value="<?= h($zh ? '提交申请' : 'Send the request') ?>"></p>
</form>

<?php $requests = rainyun_requests_of((int)$user['id']); ?>
<?php if ($requests !== []): ?>
<h3><?= h($zh ? '我的申请' : 'My requests') ?></h3>
<table>
  <tr><th><?= h($zh ? '时间' : 'When') ?></th><th><?= h($zh ? '实例 ID' : 'Instance') ?></th>
      <th><?= h($zh ? '用途' : 'Purpose') ?></th><th><?= h($zh ? '状态' : 'State') ?></th><th></th></tr>
  <?php foreach ($requests as $req): ?>
  <tr>
    <td class="small"><?= h((string)$req['created_at']) ?></td>
    <td class="small"><?= h((string)($req['remote_id'] ?? '-')) ?></td>
    <td class="small"><?= h(shorten((string)$req['purpose'], 120)) ?></td>
    <td><?= h($zh
        ? ['pending' => '待审核', 'approved' => '已批准', 'rejected' => '已拒绝', 'withdrawn' => '已撤回'][(string)$req['status']] ?? (string)$req['status']
        : (string)$req['status']) ?>
        <?= trim((string)($req['reason'] ?? '')) === '' ? '' : '<br><span class="small err">' . h((string)$req['reason']) . '</span>' ?></td>
    <td class="actions">
      <?php if ((string)$req['status'] === 'pending'): ?>
      <form class="inline" method="post" action="<?= h(url('account/servers.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="withdraw">
        <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
        <input type="submit" value="<?= h($zh ? '撤回' : 'Withdraw') ?>">
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

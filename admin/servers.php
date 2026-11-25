<?php
declare(strict_types=1);

/**
 * freedns.my - back office: the cloud servers holders bound (RainYun). Owner only.
 *
 * The operator sees every binding with its expiry, can remove one, fills in the platform key and decides
 * which operations a holder may perform. Anything that costs money is only ever done here, never from a
 * holder's page; and reinstalling is off unless it is switched on below, because it destroys the data on
 * the instance.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
我的云服务器
');

$admin = require_admin();
require_owner('Bound cloud servers');
$errors = [];

$ready = function_exists('rainyun_tables_ready') && rainyun_tables_ready();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_verify();

    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'save') {
        $changed = 0;
        foreach ([
            'rainyun_platform_key' => null,                   // free text
            'rainyun_remind_days'  => [1, 60],
        ] as $key => $range) {
            $value = (string)($_POST[$key] ?? '');
            if (is_array($range)) {
                $number = (int)$value;
                if ($number < $range[0] || $number > $range[1]) {
                    $errors[] = $key . ' must be between ' . $range[0] . ' and ' . $range[1] . '.';
                    continue;
                }
                $value = (string)$number;
            }
            if ((string)setting($key, '') !== $value) {
                set_setting($key, $value);
                $changed++;
            }
        }
        foreach (['rainyun_enabled', 'rainyun_allow_power', 'rainyun_allow_reinstall', 'rainyun_allow_backup',
            'rainyun_allow_firewall'] as $key) {
            $value = isset($_POST[$key]) ? '1' : '0';
            if ((string)setting($key, $key === 'rainyun_allow_reinstall' ? '0' : '1') !== $value) {
                set_setting($key, $value);
                $changed++;
            }
        }
        if ($errors === []) {
            log_action('settings_updated', 'settings', null, 'cloud servers: ' . $changed . ' value(s)');
            flash($changed === 0 ? 'Nothing changed.' : 'Saved.', 'ok');
            redirect('servers.php');
        }
    }

    if ($action === 'test') {
        $key = trim((string)setting('rainyun_platform_key', ''));
        if ($key === '') {
            $errors[] = 'Fill in the platform key first (and save).';
        } else {
            // The list call needs "options", whose value the documentation never shows: the helper tries
            // several spellings and remembers the one that works.
            $answer = rainyun_list_instances($key);
            if ($answer['ok']) {
                $hint = $answer['options'] === ''
                    ? 'it answers without "options"'
                    : 'it wants options=' . $answer['options'];
                flash('The platform key works: ' . count($answer['rows']) . ' instance(s) visible, ' . $hint . '.', 'ok');
                log_action('rainyun_key_test', 'settings', null, 'ok (' . $hint . ')');
                redirect('servers.php');
            }
            $errors[] = 'The platform key was refused. Every spelling of "options" was tried:'
                . "\n" . $answer['error'];
            log_action('rainyun_key_test', 'settings', null, 'refused: ' . substr($answer['error'], 0, 200));
        }
    }

    if ($action === 'attach') {
        $result = rainyun_attach((string)($_POST['remote_id'] ?? ''), trim((string)($_POST['label'] ?? '')),
            (string)$admin['username']);
        if ($result['ok']) {
            flash('The instance was registered. Assign it to an account below.', 'ok');
            redirect('servers.php');
        }
        $errors[] = $result['error'];
    }

    if ($action === 'assign') {
        $binding = rainyun_binding_by_id((int)($_POST['id'] ?? 0));
        if ($binding === null) {
            $errors[] = 'That instance is not registered.';
        } else {
            $result = rainyun_assign($binding, (int)($_POST['user_id'] ?? 0),
                strtolower((string)($_POST['level'] ?? 'read')), (string)$admin['username']);
            if ($result['ok']) {
                flash((int)($_POST['user_id'] ?? 0) > 0
                    ? 'Assigned. The holder sees it now.'
                    : 'Taken back: nobody sees that instance any more.', 'ok');
                redirect('servers.php');
            }
            $errors[] = $result['error'];
        }
    }

    if ($action === 'approve' || $action === 'reject') {
        $requestId = (int)($_POST['request_id'] ?? 0);
        $binding   = rainyun_binding_by_id((int)($_POST['binding_id'] ?? 0));
        $result = rainyun_request_decide($requestId, $action === 'approve',
            trim((string)($_POST['reason'] ?? '')), (string)$admin['username'],
            $action === 'approve' ? $binding : null, strtolower((string)($_POST['level'] ?? 'read')));
        if ($result['ok']) {
            flash($action === 'approve' ? 'Approved and assigned; the holder was told by mail.'
                : 'Rejected; the holder was told by mail.', 'ok');
            redirect('servers.php');
        }
        $errors[] = $result['error'];
    }

    if ($action === 'sync' || $action === 'unbind') {
        $binding = rainyun_binding_by_id((int)($_POST['id'] ?? 0));
        if ($binding === null) {
            $errors[] = 'That binding does not exist.';
        } elseif ($action === 'sync') {
            $result = rainyun_sync($binding, true);
            if ($result['ok']) {
                flash('Synced: ' . (string)($binding['name'] ?? '') . '.', 'ok');
                redirect('servers.php');
            }
            $errors[] = 'Sync failed: ' . $result['error'];
        } else {
            rainyun_unbind($binding, true);
            log_action('server_unbind_admin', 'server', (int)$binding['id'],
                'Removed by ' . (string)$admin['username']);
            flash('The binding was removed (the server itself is untouched).', 'ok');
            redirect('servers.php');
        }
    }
}

if (!$ready) {
    page_header('Bound cloud servers');
    echo '<div class="msg error">The tables of this feature are missing; import '
        . '<code>upgrade-servers.sql</code> (the <a href="' . h(url('selftest.php')) . '">self test</a> can '
        . 'apply it for you).</div>';
    page_footer();
    exit;
}

$bindings = rainyun_bindings_all();
$actions  = rainyun_actions(60);
$soon     = 0;
foreach ($bindings as $row) {
    $left = rainyun_days_left($row['expires_at'] === null ? null : (string)$row['expires_at']);
    if ($left !== null && $left <= (int)setting('rainyun_remind_days', '7')) {
        $soon++;
    }
}
$cryptoOk = rainyun_crypto_ready();

page_header('Bound cloud servers');
?>
<p>
Servers the holders bound with their own RainYun key. The registry stores that key encrypted, reads the
instance with it (state, specs, expiry), reminds the holder before it runs out, and - if you allow it -
starts, stops, reboots or reinstalls the instance. <strong>The platform never buys or renews anything.</strong>
</p>
<?php if (!$cryptoOk): ?>
<div class="msg error">OpenSSL with AES-256-GCM is not available in this PHP build, so no key can be stored
safely and binding is refused. Ask your host to enable the openssl extension.</div>
<?php endif; ?>
<?= error_list($errors) ?>

<h2>Settings</h2>
<form method="post" action="servers.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">
<p>
  <input type="checkbox" id="rainyun_enabled" name="rainyun_enabled" value="1"<?= setting_bool('rainyun_enabled', true) ? ' checked' : '' ?>>
  <label class="inline" for="rainyun_enabled">Offer "My cloud servers" in the console</label>
</p>
<p>
  <input type="checkbox" id="rainyun_allow_power" name="rainyun_allow_power" value="1"<?= setting_bool('rainyun_allow_power', true) ? ' checked' : '' ?>>
  <label class="inline" for="rainyun_allow_power">A holder may start, stop and restart his own instance</label>
</p>
<p>
  <input type="checkbox" id="rainyun_allow_reinstall" name="rainyun_allow_reinstall" value="1"<?= setting_bool('rainyun_allow_reinstall', false) ? ' checked' : '' ?>>
  <label class="inline" for="rainyun_allow_reinstall">A holder may reinstall / change the system, restore or
  delete a backup (destroys data - leave this off unless you mean it)</label>
</p>
<p>
  <input type="checkbox" id="rainyun_allow_backup" name="rainyun_allow_backup" value="1"<?= setting_bool('rainyun_allow_backup', true) ? ' checked' : '' ?>>
  <label class="inline" for="rainyun_allow_backup">A holder may create a backup and cancel a running one
  (safe; restoring and deleting stay behind the switch above)</label>
</p>
<p>
  <input type="checkbox" id="rainyun_allow_firewall" name="rainyun_allow_firewall" value="1"<?= setting_bool('rainyun_allow_firewall', true) ? ' checked' : '' ?>>
  <label class="inline" for="rainyun_allow_firewall">A holder may change the firewall of his instance
  (his own rules - a wrong one can lock him out of his server, so every change is logged)</label>
</p>
<label for="rainyun_remind_days">Remind this many days before the expiry (1-60)</label>
<input type="text" id="rainyun_remind_days" name="rainyun_remind_days" maxlength="2"
       value="<?= (int)setting('rainyun_remind_days', '7') ?>">
<label for="rainyun_platform_key">Platform API key (yours; read-only rights are enough)</label>
<input type="text" id="rainyun_platform_key" name="rainyun_platform_key" maxlength="190" autocomplete="off"
       value="<?= h((string)setting('rainyun_platform_key', '')) ?>">
<p class="hint">
This key is kept in the settings table and is only used by the back office (for example the connection test
below). A holder's own key is stored separately, encrypted with AES-256-GCM, and is never shown again.
</p>
<p><input type="submit" value="Save these settings"></p>
</form>
<form method="post" action="servers.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="test">
  <input type="submit" value="Test the platform key">
</form>

<h2>Register an instance</h2>
<p class="hint">
Paste the instance id from the RainYun console. The name, specs, state and expiry date are read with the
platform key; the instance is registered <strong>without a holder</strong> and is assigned further down.
Holders never hand over a key any more.
</p>
<form method="post" action="servers.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="attach">
<label for="remote_id">Instance id</label>
<input type="text" id="remote_id" name="remote_id" maxlength="64" autocomplete="off" placeholder="12345">
<label for="label">Your own name for it (optional)</label>
<input type="text" id="label" name="label" maxlength="64" placeholder="web-01">
<p><input type="submit" value="Register it"></p>
</form>

<?php $pendingRequests = rainyun_requests_pending(); ?>
<h2>Requests from holders (<?= count($pendingRequests) ?>)</h2>
<?php if ($pendingRequests === []): ?>
<p>Nothing waiting.</p>
<?php else: ?>
<?php $allBindings = rainyun_bindings_all(500); ?>
<?php foreach ($pendingRequests as $req): ?>
<div class="msg info">
  <strong><?= h((string)$req['username']) ?></strong> &lt;<?= h((string)$req['email']) ?>&gt;
  &middot; <?= h((string)$req['created_at']) ?>
  <div class="small">
    instance he names: <?= trim((string)($req['remote_id'] ?? '')) === ''
        ? '<em>none - pick one for him</em>' : '<code>' . h((string)$req['remote_id']) . '</code>' ?>
  </div>
  <div><?= nl2br(h(shorten((string)$req['purpose'], 500))) ?></div>
</div>
<form method="post" action="servers.php">
  <?= csrf_field() ?>
  <input type="hidden" name="request_id" value="<?= (int)$req['id'] ?>">
  <label for="b<?= (int)$req['id'] ?>">Hand over which instance?</label>
  <select id="b<?= (int)$req['id'] ?>" name="binding_id">
    <option value="0">- choose -</option>
    <?php foreach ($allBindings as $option): ?>
    <option value="<?= (int)$option['id'] ?>"<?= trim((string)($req['remote_id'] ?? '')) !== ''
        && (string)$option['remote_id'] === trim((string)$req['remote_id']) ? ' selected' : '' ?>>
      #<?= (int)$option['id'] ?> <?= h((string)($option['name'] ?? '')) ?>
      (<?= h((string)$option['remote_id']) ?>)<?= (int)($option['user_id'] ?? 0) > 0
          ? ' - already with ' . h((string)($option['username'] ?? ('#' . (int)$option['user_id']))) : '' ?>
    </option>
    <?php endforeach; ?>
  </select>
  <label for="l<?= (int)$req['id'] ?>">Level</label>
  <select id="l<?= (int)$req['id'] ?>" name="level">
    <option value="read">read-only (he may look, not touch)</option>
    <option value="full">full (he may start, stop, back up, firewall)</option>
  </select>
  <input type="submit" name="action" value="approve">
  <input type="text" name="reason" maxlength="190" placeholder="reason, if rejected">
  <input type="submit" name="action" value="reject">
</form>
<?php endforeach; ?>
<p class="hint">Approving assigns the instance to that account straight away and mails the holder; rejecting
mails your reason. Reinstalling and restoring stay behind the switches above.</p>
<?php endif; ?>

<h2>Bound servers (<?= count($bindings) ?><?= $soon > 0 ? ', ' . $soon . ' expiring soon' : '' ?>)</h2>
<?php if ($bindings === []): ?>
<p>Nobody bound a server yet.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Account</th><th>Instance</th><th>Specs</th><th>State</th><th>Expires</th><th>Left</th>
      <th>Key</th><th>Synced</th><th></th></tr>
  <?php foreach ($bindings as $row): ?>
  <?php
  $left = rainyun_days_left($row['expires_at'] === null ? null : (string)$row['expires_at']);
  $warn = $left !== null && $left <= (int)setting('rainyun_remind_days', '7');
  ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><?= h((string)($row['username'] ?? '')) ?><br><span class="small"><?= h((string)($row['email'] ?? '')) ?></span></td>
    <td><code><?= h((string)($row['name'] ?? '')) ?></code><br>
        <span class="small">ID <?= h((string)$row['remote_id']) ?>
        <?= trim((string)($row['label'] ?? '')) === '' ? '' : ' &middot; ' . h((string)$row['label']) ?></span></td>
    <td class="small"><?= h((string)($row['specs'] ?? '')) ?></td>
    <td><?= h((string)($row['status'] ?? '')) ?></td>
    <td><?= h((string)($row['expires_at'] ?? '-')) ?></td>
    <td><?= $left === null ? '-' : ($left < 0
        ? '<span class="err">expired</span>'
        : '<span class="' . ($warn ? 'err' : 'ok') . '">' . (int)$left . ' d</span>') ?></td>
    <td><code>…<?= h((string)($row['key_hint'] ?? '')) ?></code></td>
    <td class="small"><?= h((string)($row['last_synced_at'] ?? '-')) ?>
        <?php if (trim((string)($row['sync_error'] ?? '')) !== ''): ?>
        <br><span class="err"><?= h((string)$row['sync_error']) ?></span>
        <?php endif; ?></td>
    <td class="actions">
      <?php $accountsReady = true; ?>
      <form class="inline" method="post" action="servers.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="assign">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="text" name="user_id" value="<?= (int)($row['user_id'] ?? 0) ?>" maxlength="6" size="6"
               placeholder="account">
        <select name="level">
          <option value="read"<?= strtolower((string)($row['access_level'] ?? 'read')) === 'read' ? ' selected' : '' ?>>read</option>
          <option value="full"<?= strtolower((string)($row['access_level'] ?? '')) === 'full' ? ' selected' : '' ?>>full</option>
        </select>
        <input type="submit" value="Assign">
      </form>
      <form class="inline" method="post" action="servers.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="sync">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Sync">
      </form>
      <form class="inline" method="post" action="servers.php">
        <?= csrf_field() ?><input type="hidden" name="action" value="unbind">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Remove">
      </form>
    </td>
  </tr>
  <?php if (trim((string)($row['detail'] ?? '')) !== ''): ?>
  <tr><td></td><td colspan="9" class="small">
    <details><summary>raw answer from RainYun (check the field names here if the expiry stays empty)</summary>
    <pre class="wrap code-block"><?= h(shorten((string)$row['detail'], 1500)) ?></pre></details></td></tr>
  <?php endif; ?>
  <?php endforeach; ?>
</table>
<p class="hint">
The reminder goes out from the same cron call as the publishing watchdog:
<code>/alert-cron.php?key=&lt;cron key&gt;</code>. A holder is mailed at most once every three days per
instance.
</p>
<?php endif; ?>

<h2>What was done to them (latest 60)</h2>
<?php if ($actions === []): ?>
<p>Nothing yet.</p>
<?php else: ?>
<table>
  <tr><th>When</th><th>Account</th><th>Instance</th><th>Action</th><th>Result</th><th>Message</th></tr>
  <?php foreach ($actions as $row): ?>
  <tr>
    <td class="small"><?= h((string)$row['created_at']) ?></td>
    <td class="small"><?= h((string)($row['username'] ?? ('#' . (int)$row['user_id']))) ?></td>
    <td class="small">#<?= (int)$row['binding_id'] ?></td>
    <td><?= h((string)$row['action']) ?></td>
    <td><?= (string)$row['result'] === 'ok' ? '<span class="ok">ok</span>' : '<span class="err">' . h((string)$row['result']) . '</span>' ?></td>
    <td class="small"><?= h((string)($row['message'] ?? '')) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();

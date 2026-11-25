<?php
declare(strict_types=1);

/**
 * freedns.my - anti-flood protection: settings, the blocked addresses and manual blocks.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('Rate limits');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'save') {
        $limit   = (int)($_POST['flood_limit'] ?? 60);
        $window  = (int)($_POST['flood_window'] ?? 60);
        $minutes = (int)($_POST['flood_block_minutes'] ?? 10);

        if ($limit < 5 || $limit > 100000) {
            $errors[] = 'The request limit must be between 5 and 100000.';
        }
        if ($window < 5 || $window > 3600) {
            $errors[] = 'The window must be between 5 and 3600 seconds.';
        }
        if ($minutes < 1 || $minutes > 10080) {
            $errors[] = 'The first block must be between 1 minute and 7 days.';
        }

        $enabled = isset($_POST['flood_enabled']);
        $trust   = isset($_POST['trust_proxy']);

        // Never switch the protection on while the visitor address cannot be trusted: behind a CDN
        // every visitor would share one address and one block would hit them all.
        if ($enabled && !$trust && !setting_bool('trust_proxy', false)) {
            $errors[] = 'Turn on "Trust reverse-proxy headers" below first (or use the button): without '
                . 'it the registry sees the address of Cloudflare for every visitor, and one block '
                . 'would lock everybody out.';
        }

        if ($errors === []) {
            set_setting('flood_limit', (string)$limit);
            set_setting('flood_window', (string)$window);
            set_setting('flood_block_minutes', (string)$minutes);
            set_setting('flood_escalate', isset($_POST['flood_escalate']) ? '1' : '0');
            set_setting('flood_whitelist', trim((string)($_POST['flood_whitelist'] ?? '')));
            set_setting('flood_enabled', $enabled ? '1' : '0');
            set_setting('trust_proxy', $trust ? '1' : '0');
            log_action('settings_updated', 'settings', null, 'flood: limit ' . $limit . '/' . $window
                . 's, block ' . $minutes . 'm, enabled ' . ($enabled ? 'yes' : 'no'));
            flash('Rate limit settings saved.', 'ok');
            redirect('limits.php');
        }
    }

    if ($action === 'block') {
        $ip      = trim((string)($_POST['ip'] ?? ''));
        $minutes = (int)($_POST['minutes'] ?? 60);

        if ($ip === '' || @inet_pton($ip) === false) {
            $errors[] = 'Enter a single IPv4 or IPv6 address (a network is only allowed in the whitelist).';
        } else {
            flood_block_now($ip, $minutes, 'manual', true);
            log_action('flood_block', 'rate', null, $ip . ' blocked manually for ' . $minutes . ' minute(s)');
            flash($ip . ' is blocked for ' . $minutes . ' minute(s).', 'ok');
            redirect('limits.php');
        }
    }

    if ($action === 'unblock') {
        $ip = trim((string)($_POST['ip'] ?? ''));
        if ($ip === '') {
            $errors[] = 'No address given.';
        } else {
            flood_unblock($ip);
            log_action('flood_unblock', 'rate', null, $ip . ' released');
            flash($ip . ' can visit the site again.', 'ok');
            redirect('limits.php');
        }
    }

    if ($action === 'clear') {
        if (!flood_table_ready()) {
            $errors[] = 'The rate_limits table is missing; import upgrade-flood.sql first.';
        } else {
            db_query('DELETE FROM rate_limits');
            log_action('flood_clear', 'rate', null, 'all counters and blocks cleared');
            flash('All counters and blocks were cleared.', 'ok');
            redirect('limits.php');
        }
    }
}

$rows    = flood_table_ready() ? db_all('SELECT * FROM rate_limits ORDER BY (blocked_until IS NULL), blocked_until DESC, hits DESC LIMIT 300') : [];
$blocked = 0;
$tracked = 0;
$now     = time();
foreach ($rows as $row) {
    $tracked++;
    if (flood_block_remaining($row) > 0) {
        $blocked++;
    }
}

$recent = db_all("SELECT * FROM audit_log WHERE action LIKE 'flood\\_%' ORDER BY id DESC LIMIT 15");

page_header('Rate limits');
?>
<p>
Protection against floods and scrapers: the address of every visitor is counted in a sliding window, and
an address that goes over the limit is shown a "restricted" page instead of the site. The back office,
static files and the cron entry point are never counted, and a signed-in administrator is exempt.
</p>

<table>
  <tr><th>Protection</th><td><strong><?= setting_bool('flood_enabled', false) ? 'on' : 'off' ?></strong></td></tr>
  <tr><th>Visitor address</th><td><?= flood_trust_ok()
      ? 'trusted (reverse-proxy headers are used)'
      : '<span class="err">not trusted - the protection cannot be switched on</span>' ?></td></tr>
  <tr><th>Limit</th><td><?= flood_limit() ?> requests in <?= flood_window() ?> seconds</td></tr>
  <tr><th>First block, then</th><td><?= (int)setting('flood_block_minutes', '10') ?> minute(s)<?= setting_bool('flood_escalate', true)
      ? ', then 1 hour, then 24 hours' : ' (no escalation)' ?></td></tr>
  <tr><th>Whitelist</th><td><?= count(flood_whitelist()) ?> entr(y/ies)</td></tr>
  <tr><th>Counters</th><td><?= $tracked ?> address(es) tracked, <?= $blocked ?> blocked</td></tr>
</table>

<?= error_list($errors) ?>

<?php if (!flood_table_ready()): ?>
<div class="msg error">
  The <code>rate_limits</code> table does not exist yet; import <code>upgrade-flood.sql</code>
  (the <a href="selftest.php">self test</a> prints the SQL).
</div>
<?php endif; ?>

<form method="post" action="limits.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">

<h2>Settings</h2>
<p>
  <input type="checkbox" id="flood_enabled" name="flood_enabled" value="1"<?= setting_bool('flood_enabled', false) ? ' checked' : '' ?>>
  <label class="inline" for="flood_enabled">Switch the protection on</label>
</p>
<p>
  <input type="checkbox" id="trust_proxy" name="trust_proxy" value="1"<?= flood_trust_ok() ? ' checked' : '' ?>>
  <label class="inline" for="trust_proxy">Trust reverse-proxy headers (required behind Cloudflare)</label>
</p>
<p class="hint">
With Cloudflare in front of the site the registry only learns the real visitor address from the
<code>CF-Connecting-IP</code> / <code>X-Forwarded-For</code> header, so this has to be on before the
protection can work. To make the header trustworthy, let your firewall or nginx accept connections to the
origin only from Cloudflare's addresses.
</p>

<label for="flood_limit">Requests allowed per visitor in the window</label>
<input type="text" id="flood_limit" name="flood_limit" maxlength="6" value="<?= flood_limit() ?>">

<label for="flood_window">Window (seconds)</label>
<input type="text" id="flood_window" name="flood_window" maxlength="4" value="<?= flood_window() ?>">

<label for="flood_block_minutes">First block (minutes)</label>
<input type="text" id="flood_block_minutes" name="flood_block_minutes" maxlength="4"
       value="<?= (int)setting('flood_block_minutes', '10') ?>">

<p>
  <input type="checkbox" id="flood_escalate" name="flood_escalate" value="1"<?= setting_bool('flood_escalate', true) ? ' checked' : '' ?>>
  <label class="inline" for="flood_escalate">Escalate for repeat offenders (1 hour, then 24 hours)</label>
</p>

<label for="flood_whitelist">Exempt addresses (one per line, single address or network)</label>
<textarea id="flood_whitelist" name="flood_whitelist" rows="5"
          placeholder="203.0.113.7&#10;2001:db8::/32"><?= h((string)setting('flood_whitelist', '')) ?></textarea>
<p class="hint">
Your own address belongs here, and the address of any monitoring service you use. A signed-in
administrator is exempt anyway; a visitor who is only browsing needs a few requests per minute, so the
defaults leave plenty of room.
</p>

<p><input type="submit" value="Save rate limit settings"></p>
</form>

<h2>Block an address by hand</h2>
<form method="post" action="limits.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="block">
<label for="ip">IP address</label>
<input type="text" id="ip" name="ip" maxlength="45" placeholder="203.0.113.7">
<label for="minutes">Minutes</label>
<input type="text" id="minutes" name="minutes" maxlength="5" value="60">
<p><input type="submit" value="Block this address"></p>
</form>

<h2>Tracked addresses</h2>
<?php if (!flood_table_ready()): ?>
<p>The counters table is missing.</p>
<?php elseif ($rows === []): ?>
<p>Nothing recorded yet.</p>
<?php else: ?>
<table>
  <tr>
    <th>Address</th><th>Requests</th><th>Window started</th><th>Blocked until</th><th>Left</th>
    <th>Strikes</th><th>Note</th><th>Last seen</th><th></th>
  </tr>
  <?php foreach ($rows as $row): ?>
  <?php $remaining = flood_block_remaining($row); ?>
  <tr>
    <td><code><?= h((string)$row['ip']) ?></code></td>
    <td><?= (int)$row['hits'] ?></td>
    <td class="small"><?= h((string)$row['window_started']) ?></td>
    <td class="small"><?= h((string)($row['blocked_until'] ?? '-')) ?></td>
    <td class="small"><?= $remaining > 0 ? h((string)ceil($remaining / 60)) . ' min' : '-' ?></td>
    <td><?= (int)$row['strikes'] ?></td>
    <td class="small"><?= h((string)($row['note'] ?? '')) ?></td>
    <td class="small"><?= h((string)$row['last_seen']) ?></td>
    <td class="actions">
      <?php if ($remaining > 0): ?>
      <form method="post" action="limits.php" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="unblock">
        <input type="hidden" name="ip" value="<?= h((string)$row['ip']) ?>">
        <input type="submit" value="Unblock">
      </form>
      <?php else: ?>
      <form method="post" action="limits.php" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="unblock">
        <input type="hidden" name="ip" value="<?= h((string)$row['ip']) ?>">
        <input type="submit" value="Forget">
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<form method="post" action="limits.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="clear">
<p><input type="submit" value="Clear all counters and blocks"></p>
</form>

<h2>Recent activity</h2>
<?php if ($recent === []): ?>
<p>Nothing recorded yet.</p>
<?php else: ?>
<table>
  <tr><th>When</th><th>Action</th><th>Detail</th></tr>
  <?php foreach ($recent as $entry): ?>
  <tr>
    <td class="small"><?= h((string)$entry['created_at']) ?></td>
    <td><code><?= h((string)$entry['action']) ?></code></td>
    <td class="small"><?= h(shorten((string)($entry['detail'] ?? ''), 160)) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();

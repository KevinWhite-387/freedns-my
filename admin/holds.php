<?php
declare(strict_types=1);

/**
 * freedns.my - back office: the holding page and the reference codes it handed out. Owner only.
 *
 * When the site cannot serve normally, hold.php gives every visitor a code and writes down what it knows
 * about that visit. This page is where those codes are looked up: a user mails the code in, the operator
 * pastes it here and sees the time, the address, the page asked for and the browser.
 *
 * It also carries the switch itself: turning the holding page on takes the site down for visitors (the
 * back office keeps working, otherwise nobody could turn it off again).
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Holding page and codes');
$errors = [];

$ready = false;
try {
    $ready = db_one("SHOW TABLES LIKE 'hold\\_events'") !== null;
} catch (Throwable $e) {
    $ready = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'save') {
        $changed = 0;
        foreach (['hold_message', 'hold_message_zh', 'hold_eta', 'hold_eta_zh'] as $key) {
            $value = trim((string)($_POST[$key] ?? ''));
            if ((string)setting($key, '') !== $value) {
                set_setting($key, $value);
                $changed++;
            }
        }
        $mode = isset($_POST['hold_mode']) ? '1' : '0';
        if ((string)setting('hold_mode', '0') !== $mode) {
            set_setting('hold_mode', $mode);
            $changed++;
            log_action('hold_mode', 'settings', null,
                $mode === '1' ? 'The holding page was switched ON' : 'The holding page was switched off');
        }
        flash($changed === 0 ? 'Nothing changed.' : 'Saved (' . $changed . ' value(s)).', 'ok');
        redirect('holds.php');
    }

    if ($action === 'done' || $action === 'open') {
        $id   = (int)($_POST['id'] ?? 0);
        $note = trim((string)($_POST['note'] ?? ''));
        if (db_one('SELECT id FROM hold_events WHERE id = ?', [$id]) === null) {
            $errors[] = 'That record does not exist.';
        } else {
            db_query('UPDATE hold_events SET status = ?, note = ?, handled_by = ?, handled_at = NOW() WHERE id = ?',
                [$action === 'done' ? 'done' : 'open',
                 $note === '' ? null : substr($note, 0, 255), (string)$admin['username'], $id]);
            log_action('hold_' . $action, 'settings', null, 'record ' . $id . ($note === '' ? '' : ': ' . $note));
            flash('Saved.', 'ok');
            redirect('holds.php?code=' . rawurlencode((string)($_POST['code'] ?? '')));
        }
    }

    if ($action === 'prune') {
        $days = max(1, min(365, (int)($_POST['days'] ?? 7)));
        $gone = db_all('SELECT id FROM hold_events WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)', [$days]);
        foreach ($gone as $row) {
            db_query('DELETE FROM hold_events WHERE id = ?', [(int)$row['id']]);
        }
        log_action('hold_prune', 'settings', null, count($gone) . ' record(s) older than ' . $days . ' day(s) removed');
        flash(count($gone) . ' old record(s) removed.', 'ok');
        redirect('holds.php');
    }

    if ($action === 'test') {
        // Hand out a code exactly like a visitor would get one, so the flow can be checked without an outage.
        $code = 'HOLD-TEST-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        db_query(
            'INSERT INTO hold_events (code, reason, path, ip, agent, referer, created_at)
             VALUES (?, \'hold\', \'/selftest-of-the-operator\', ?, ?, ?, NOW())',
            [$code, client_ip(), 'back office test', rtrim(app_base_url(), '/') . '/admin/holds.php']
        );
        flash('A test code was created: ' . $code, 'ok');
        redirect('holds.php?code=' . rawurlencode($code));
    }
}

$code   = strtoupper(trim((string)($_GET['code'] ?? '')));
$single = null;
if ($ready && $code !== '') {
    $single = db_one('SELECT * FROM hold_events WHERE code = ?', [$code]);
    if ($single !== null && (string)$single['status'] === 'open') {
        db_query("UPDATE hold_events SET status = 'seen' WHERE id = ?", [(int)$single['id']]);
        $single['status'] = 'seen';
    }
}

$status = strtolower((string)($_GET['status'] ?? 'all'));
if (!in_array($status, ['all', 'open', 'seen', 'done'], true)) {
    $status = 'all';
}

$rows = [];
$counts = ['open' => 0, 'seen' => 0, 'done' => 0];
if ($ready) {
    foreach (db_all('SELECT status, COUNT(*) AS n FROM hold_events GROUP BY status') as $row) {
        $counts[(string)$row['status']] = (int)$row['n'];
    }
    $rows = $status === 'all'
        ? db_all('SELECT * FROM hold_events ORDER BY id DESC LIMIT 100')
        : db_all('SELECT * FROM hold_events WHERE status = ? ORDER BY id DESC LIMIT 100', [$status]);
}

$mode = setting_bool('hold_mode', false);

page_header('Holding page');
?>
<p>
When the site cannot serve normally, <code>hold.php</code> gives every visitor a reference code and writes
down the visit (time, address, page, browser) under it. A user mails the code in and you paste it below.
<strong>Turning the switch on takes the site down for visitors</strong>; the back office and the sign-in page
keep working, otherwise nobody could switch it off again.
</p>

<?php if ($mode): ?>
<div class="msg error"><strong>The holding page is ON</strong> - visitors see it right now. Switch it off below
when the site is healthy again.</div>
<?php endif; ?>
<?= error_list($errors) ?>

<?php if (!$ready): ?>
<div class="msg error">The <code>hold_events</code> table is missing; import <code>upgrade-hold.sql</code>
(the <a href="<?= h(url('selftest.php')) ?>">self test</a> can apply it for you).</div>
<?php else: ?>

<h2>Look up a code</h2>
<form method="get" action="holds.php">
<label for="code">Reference code (as mailed in)</label>
<input type="text" id="code" name="code" maxlength="32" value="<?= h($code) ?>" placeholder="HOLD-XXXX-XXXX">
<p><input type="submit" value="Look it up">
<a href="holds.php">all records</a></p>
</form>

<?php if ($code !== '' && $single === null): ?>
<div class="msg error">No record with the code <code><?= h($code) ?></code>. It may have been cleaned up; the
list below holds the latest 100 visits.</div>
<?php endif; ?>

<?php if ($single !== null): ?>
<h2><?= h((string)$single['code']) ?></h2>
<table>
  <tr><th>When</th><td><?= h((string)$single['created_at']) ?></td></tr>
  <tr><th>Reason</th><td><?= h((string)$single['reason']) ?></td></tr>
  <tr><th>Page asked for</th><td><code><?= h((string)($single['path'] ?? '')) ?></code></td></tr>
  <tr><th>Address</th><td><?= h((string)($single['ip'] ?? '')) ?></td></tr>
  <tr><th>Browser</th><td class="small"><?= h((string)($single['agent'] ?? '')) ?></td></tr>
  <tr><th>Came from</th><td class="small"><?= h((string)($single['referer'] ?? '')) ?></td></tr>
  <tr><th>Account</th><td><?= (int)($single['user_id'] ?? 0) > 0
      ? '<a href="users.php?edit=' . (int)$single['user_id'] . '">account #' . (int)$single['user_id'] . '</a>'
      : '<span class="small">not signed in</span>' ?></td></tr>
  <tr><th>Status</th><td><?= h((string)$single['status']) ?>
      <?= trim((string)($single['note'] ?? '')) === '' ? '' : ' &middot; ' . h((string)$single['note']) ?>
      <?= trim((string)($single['handled_by'] ?? '')) === '' ? '' : ' &middot; ' . h((string)$single['handled_by'])
          . ' ' . h((string)($single['handled_at'] ?? '')) ?></td></tr>
</table>
<form method="post" action="holds.php">
<?= csrf_field() ?>
<input type="hidden" name="code" value="<?= h((string)$single['code']) ?>">
<input type="hidden" name="id" value="<?= (int)$single['id'] ?>">
<label for="note">Note (what you found, or what you answered)</label>
<input type="text" id="note" name="note" maxlength="255" value="<?= h((string)($single['note'] ?? '')) ?>">
<p>
  <input type="submit" name="action" value="done">
  <input type="submit" name="action" value="open">
  <span class="hint">"done" closes it, "open" puts it back in the queue.</span>
</p>
</form>
<?php endif; ?>

<h2>Recent visits (<?= count($rows) ?>)</h2>
<p class="small">
<a href="holds.php?status=all">all (<?= array_sum($counts) ?>)</a> &middot;
<a href="holds.php?status=open">open (<?= (int)$counts['open'] ?>)</a> &middot;
<a href="holds.php?status=seen">seen (<?= (int)$counts['seen'] ?>)</a> &middot;
<a href="holds.php?status=done">done (<?= (int)$counts['done'] ?>)</a>
</p>
<?php if ($rows === []): ?>
<p>Nothing recorded yet.</p>
<?php else: ?>
<table>
  <tr><th>Code</th><th>When</th><th>Reason</th><th>Page</th><th>Address</th><th>Status</th><th></th></tr>
  <?php foreach ($rows as $row): ?>
  <tr>
    <td><code><?= h((string)$row['code']) ?></code></td>
    <td class="small"><?= h((string)$row['created_at']) ?></td>
    <td><?= h((string)$row['reason']) ?></td>
    <td class="small"><code><?= h(shorten((string)($row['path'] ?? ''), 40)) ?></code></td>
    <td class="small"><?= h((string)($row['ip'] ?? '')) ?></td>
    <td><?= (string)$row['status'] === 'open'
        ? '<span class="err">open</span>' : h((string)$row['status']) ?></td>
    <td class="actions"><a href="holds.php?code=<?= h(urlencode((string)$row['code'])) ?>">open</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Settings</h2>
<form method="post" action="holds.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">
<p>
  <input type="checkbox" id="hold_mode" name="hold_mode" value="1"<?= $mode ? ' checked' : '' ?>>
  <label class="inline" for="hold_mode"><strong>Show the holding page to every visitor</strong>
  (the back office keeps working)</label>
</p>
<label for="hold_message">Message on the page (English, optional)</label>
<textarea id="hold_message" name="hold_message" rows="3"><?= h((string)setting('hold_message', '')) ?></textarea>
<label for="hold_eta">How long it may take (English, optional)</label>
<input type="text" id="hold_eta" name="hold_eta" maxlength="190" value="<?= h((string)setting('hold_eta', '')) ?>">
<label for="hold_message_zh">Message on the page (Chinese, optional)</label>
<textarea id="hold_message_zh" name="hold_message_zh" rows="3"><?= h((string)setting('hold_message_zh', '')) ?></textarea>
<label for="hold_eta_zh">How long it may take (Chinese, optional)</label>
<input type="text" id="hold_eta_zh" name="hold_eta_zh" maxlength="190" value="<?= h((string)setting('hold_eta_zh', '')) ?>">
<p class="hint">The text appears in the box under the greeting; leave it empty to show only the default wording.</p>
<p><input type="submit" value="Save"></p>
</form>

<form method="post" action="holds.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="test">
  <p><input type="submit" value="Create a test code"></p>
</form>
<form method="post" action="holds.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="prune">
  <label for="days">Remove records older than (days)</label>
  <input type="text" id="days" name="days" maxlength="3" value="7">
  <input type="submit" value="Clean up">
</form>
<?php endif; ?>
<?php
page_footer();

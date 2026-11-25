<?php
declare(strict_types=1);

/**
 * freedns.my - back office: requests for mail forwarding, and the quota each account was granted.
 *
 * Forwarding is not open to everybody: a holder sends a request explaining what the addresses are for, and
 * the operator approves it and decides HOW MANY addresses that account may keep (users.forward_quota). The
 * library refuses to create an address beyond that number, so this page is the only way to hand the right
 * out - and the only way to take it back.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
邮件转发（旧版）
');

$admin  = require_admin();
require_owner('Mail forwarding requests');
$errors = [];

$ready = false;
try {
    $ready = db_one("SHOW TABLES LIKE 'mail\\_forward\\_requests'") !== null;
} catch (Throwable $e) {
    $ready = false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'grant') {
        // Direct grant: no request needed when the operator already knows what he wants to hand out.
        $userId = (int)($_POST['user_id'] ?? 0);
        $quota  = max(0, min(50, (int)($_POST['quota'] ?? 0)));
        if ($userId <= 0 || db_one('SELECT id FROM users WHERE id = ?', [$userId]) === null) {
            $errors[] = 'That account does not exist.';
        } else {
            db_query('UPDATE users SET forward_quota = ? WHERE id = ?', [$quota, $userId]);
            log_action('forward_grant', 'settings', $userId,
                'account #' . $userId . ' may keep ' . $quota . ' forwarding address(es) (by '
                . (string)$admin['username'] . ')');
            flash('Account #' . $userId . ' may now keep ' . $quota . ' forwarding address(es).', 'ok');
            redirect('forward-requests.php');
        }
    }

    if ($action === 'approve' || $action === 'reject') {
        $id      = (int)($_POST['id'] ?? 0);
        $request = db_one('SELECT * FROM mail_forward_requests WHERE id = ?', [$id]);
        if ($request === null) {
            $errors[] = 'That request does not exist.';
        } elseif ((string)$request['status'] !== 'pending') {
            $errors[] = 'That request was already answered.';
        } else {
            $reason = trim((string)($_POST['reason'] ?? ''));
            $quota  = max(0, min(50, (int)($_POST['quota'] ?? 0)));

            if ($action === 'approve') {
                if ($quota <= 0) {
                    $errors[] = 'Give a number of addresses above zero when approving.';
                } else {
                    // The granted amount is what the account may keep in total.
                    db_query('UPDATE users SET forward_quota = ? WHERE id = ?', [$quota, (int)$request['user_id']]);
                    db_query(
                        "UPDATE mail_forward_requests SET status = 'approved', quota = ?, decided_by = ?,
                            decided_at = NOW() WHERE id = ?",
                        [$quota, (string)$admin['username'], $id]
                    );
                    log_action('forward_approved', 'settings', (int)$request['user_id'],
                        'request ' . $id . ' approved with ' . $quota . ' address(es)');
                    flash('Approved: the account may keep ' . $quota . ' forwarding address(es).', 'ok');
                }
            } else {
                if ($reason === '') {
                    $errors[] = 'Give a reason when rejecting.';
                } else {
                    db_query(
                        "UPDATE mail_forward_requests SET status = 'rejected', reason = ?, decided_by = ?,
                            decided_at = NOW() WHERE id = ?",
                        [substr($reason, 0, 255), (string)$admin['username'], $id]
                    );
                    log_action('forward_rejected', 'settings', (int)$request['user_id'],
                        'request ' . $id . ' rejected: ' . $reason);
                    flash('Rejected.', 'ok');
                }
            }

            if ($errors === []) {
                // Tell the holder either way, in the same words the console shows.
                $to = (string)db_value('SELECT email FROM users WHERE id = ?', [(int)$request['user_id']], '');
                if ($to !== '') {
                    $previous = lang_switch('en');
                    send_mail($to, $action === 'approve'
                            ? 'Mail forwarding was granted'
                            : 'Your mail forwarding request was answered',
                        implode("\n", [
                            t('mail.dear', $to), '',
                            $action === 'approve'
                                ? 'You may now keep ' . $quota . ' forwarding address(es).'
                                : 'Your request was not approved.',
                            $action === 'approve' ? '' : 'Reason: ' . $reason,
                            '',
                            'Each forwarded message is archived and checked before it goes on; anything that '
                            . 'looks like a threat is held for a human instead of being forwarded.',
                            '',
                            rtrim(app_base_url(), '/') . '/account/mailroute.php',
                        ]));
                    lang_switch($previous);
                }
                redirect('forward-requests.php');
            }
        }
    }
}

$pending = [];
$recent  = [];
$granted = [];
if ($ready) {
    $pending = db_all(
        "SELECT r.*, u.username, u.email, u.forward_quota FROM mail_forward_requests r
         JOIN users u ON u.id = r.user_id WHERE r.status = 'pending' ORDER BY r.id ASC LIMIT 200"
    );
    $recent = db_all(
        "SELECT r.*, u.username FROM mail_forward_requests r JOIN users u ON u.id = r.user_id
         WHERE r.status <> 'pending' ORDER BY r.decided_at DESC LIMIT 50"
    );
    $granted = db_all(
        'SELECT id, username, email, forward_quota FROM users WHERE forward_quota > 0
         ORDER BY forward_quota DESC, id ASC LIMIT 200'
    );
}

page_header('Mail forwarding');
?>
<p>
Forwarding is granted here. A holder asks from his console; approving sets <strong>how many addresses that
account may keep</strong>, and the library refuses to create one beyond that number. Every message that is
forwarded is archived and checked first; anything that looks like a threat or extortion is held for a human
instead of being passed on.
</p>

<?php if (!$ready): ?>
<div class="msg error">The <code>mail_forward_requests</code> table is missing (import
<code>upgrade-mailvault.sql</code>, or use the <a href="<?= h(url('selftest.php')) ?>">self test</a>).</div>
<?php else: ?>

<?= error_list($errors) ?>

<h2>Waiting for an answer (<?= count($pending) ?>)</h2>
<?php if ($pending === []): ?>
<p>Nothing waiting.</p>
<?php else: ?>
<?php foreach ($pending as $row): ?>
<div class="msg info">
  <strong><?= h((string)$row['username']) ?></strong> &lt;<?= h((string)$row['email']) ?>&gt;
  &middot; asked <?= h((string)$row['created_at']) ?>
  &middot; currently granted: <?= (int)$row['forward_quota'] ?>
  <div class="small">wants <?= (int)$row['wanted'] ?> address(es)</div>
  <div><?= nl2br(h(shorten((string)$row['purpose'], 500))) ?></div>
</div>
<form method="post" action="forward-requests.php">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
  <label for="q<?= (int)$row['id'] ?>">How many addresses may this account keep?</label>
  <input type="text" id="q<?= (int)$row['id'] ?>" name="quota" maxlength="2" size="3"
         value="<?= max(1, (int)$row['wanted']) ?>">
  <input type="submit" name="action" value="approve">
  <input type="text" name="reason" maxlength="190" placeholder="reason, if rejected">
  <input type="submit" name="action" value="reject">
</form>
<?php endforeach; ?>
<?php endif; ?>

<h2>Grant or take back, without a request</h2>
<form method="post" action="forward-requests.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="grant">
<label for="user_id">Account number</label>
<input type="text" id="user_id" name="user_id" maxlength="8" size="8">
<label for="quota">Addresses it may keep (0 = take the right away)</label>
<input type="text" id="quota" name="quota" maxlength="2" size="3" value="1">
<p class="hint">Existing addresses are not deleted by this; it only decides whether new ones may be created.</p>
<p><input type="submit" value="Set it"></p>
</form>

<h2>Accounts that may forward (<?= count($granted) ?>)</h2>
<?php if ($granted === []): ?>
<p>Nobody yet.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Account</th><th>E-mail</th><th>May keep</th><th>In use</th><th></th></tr>
  <?php foreach ($granted as $row): ?>
  <?php $used = (int)db_value('SELECT COUNT(*) FROM mail_routes WHERE user_id = ?', [(int)$row['id']], 0); ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><?= h((string)$row['username']) ?></td>
    <td class="small"><?= h((string)$row['email']) ?></td>
    <td><?= (int)$row['forward_quota'] ?></td>
    <td><?= $used ?><?= $used >= (int)$row['forward_quota'] ? ' <span class="err">(full)</span>' : '' ?></td>
    <td class="actions">
      <form class="inline" method="post" action="forward-requests.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="grant">
        <input type="hidden" name="user_id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="quota" value="0">
        <input type="submit" value="Take back">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Already answered (<?= count($recent) ?>)</h2>
<?php if ($recent === []): ?>
<p>Nothing decided yet.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Account</th><th>Asked for</th><th>Granted</th><th>State</th><th>Reason</th><th>By</th></tr>
  <?php foreach ($recent as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><?= h((string)$row['username']) ?></td>
    <td><?= (int)$row['wanted'] ?></td>
    <td><?= (int)$row['quota'] ?></td>
    <td><?= h((string)$row['status']) ?></td>
    <td class="small"><?= h((string)($row['reason'] ?? '')) ?></td>
    <td class="small"><?= h((string)($row['decided_by'] ?? '')) ?><br><?= h((string)($row['decided_at'] ?? '')) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php endif; ?>
<?php
page_footer();

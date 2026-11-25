<?php
declare(strict_types=1);

/**
 * freedns.my - back office: transfer tickets (owner only).
 *
 * Approving pastes the transfer code the operator obtained manually in the DNSHE panel; the registry
 * relays it to the recipient and starts the 72 hour clock. The ownership moves when the recipient
 * confirms in the console, or when the operator marks the ticket completed.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Transfer tickets');
$errors = [];

if (!transfer_table_ready()) {
    page_header('Transfer tickets');
    echo '<div class="msg error">The <code>transfer_requests</code> table is missing; import '
        . '<code>upgrade-transfer.sql</code> (the <a href="' . h(url('selftest.php')) . '">self test</a> '
        . 'prints the SQL).</div>';
    page_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = strtolower((string)($_POST['action'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);
    $note   = trim((string)($_POST['note'] ?? ''));
    $staff  = (string)$admin['username'];

    if ($action === 'reply') {
        if ($note === '') {
            $errors[] = 'Write something first.';
        } else {
            $request = transfer_by_id($id);
            transfer_message_add($id, 'staff', $staff, $note, true);
            if ($request !== null) {
                $domain   = db_one('SELECT * FROM domains WHERE id = ?', [(int)$request['domain_id']]);
                $language = (string)($domain['language'] ?? 'en');
                transfer_mail(
                    (string)$request['requester_email'],
                    'mail.transfer_reply_subject',
                    [(string)($domain['name'] ?? '')],
                    $note,
                    $language
                );
            }
            log_action('transfer_message', 'domain', $request === null ? null : (int)$request['domain_id'],
                'Staff replied on ticket ' . $id);
            flash('The reply was saved and mailed.', 'ok');
            redirect('tickets.php?id=' . $id);
        }
    }

    if ($action === 'approve') {
        $result = transfer_approve($id, trim((string)($_POST['transfer_code'] ?? '')), $note, $staff);
        if ($result['ok']) {
            flash('Ticket approved: the code was mailed to the recipient, who has 72 hours to accept it.',
                'ok');
            redirect('tickets.php?id=' . $id);
        }
        $errors[] = $result['error'];
    }

    if ($action === 'reject') {
        $result = transfer_reject($id, $note, $staff);
        if ($result['ok']) {
            flash('Ticket rejected; the holder was informed.', 'ok');
            redirect('tickets.php');
        }
        $errors[] = $result['error'];
    }

    if ($action === 'complete') {
        $result = transfer_complete($id, $staff, false);
        if ($result['ok']) {
            flash('Ownership moved to the recipient.', 'ok');
            redirect('tickets.php?id=' . $id);
        }
        $errors[] = $result['error'];
    }

    if ($action === 'expire') {
        db_query("UPDATE transfer_requests SET status = 'expired', updated_at = NOW() WHERE id = ?", [$id]);
        log_action('transfer_expired', 'domain', null, 'Ticket ' . $id . ' closed as expired by ' . $staff);
        flash('The ticket was closed as expired.', 'ok');
        redirect('tickets.php');
    }
}

$swept  = transfer_sweep();
$id     = (int)($_GET['id'] ?? 0);
$ticket = $id > 0 ? transfer_by_id($id) : null;

$rows = db_all(
    "SELECT r.*, d.name, d.zone FROM transfer_requests r JOIN domains d ON d.id = r.domain_id
     ORDER BY (r.status IN ('open', 'approved')) DESC, r.id DESC LIMIT 200"
);

page_header('Transfer tickets');
?>
<p>
A holder can ask for a name to be transferred. Approving pastes the transfer code you obtained in the
<strong>DNSHE panel</strong>: the registry relays it to the recipient by mail and the clock runs for
72 hours. The ownership moves when the recipient confirms in the console (with the account whose e-mail the
ticket names) or when you mark it completed here. While a ticket is open or approved, the records of that
name cannot be changed.
</p>
<?php if ($swept > 0): ?>
<div class="msg info"><?= (int)$swept ?> ticket(s) passed their 72 hours and were closed as expired.</div>
<?php endif; ?>
<?= error_list($errors) ?>

<?php if ($ticket !== null): ?>
<?php
$domain   = db_one('SELECT * FROM domains WHERE id = ?', [(int)$ticket['domain_id']]);
$thread   = transfer_messages((int)$ticket['id']);
$left     = transfer_seconds_left($ticket);
?>
<h2>Ticket #<?= (int)$ticket['id'] ?>:
    <code><?= h((string)($domain['name'] ?? '?')) ?></code></h2>
<table>
  <tr><th>Status</th><td><?= h(transfer_status_text((string)$ticket['status'])) ?>
      <?php if ($left > 0): ?>&middot; <?= (int)ceil($left / 3600) ?> hour(s) left<?php endif; ?></td></tr>
  <tr><th>Holder</th><td><?= h((string)$ticket['requester_email']) ?></td></tr>
  <tr><th>Recipient</th><td><?= h((string)$ticket['recipient_name']) ?>
      &lt;<?= h((string)$ticket['recipient_email']) ?>&gt;</td></tr>
  <?php if (trim((string)($ticket['transfer_code'] ?? '')) !== ''): ?>
  <tr><th>Transfer code (from DNSHE)</th><td><code><?= h((string)$ticket['transfer_code']) ?></code></td></tr>
  <?php endif; ?>
  <?php if (trim((string)($ticket['decided_by'] ?? '')) !== ''): ?>
  <tr><th>Decided by</th><td><?= h((string)$ticket['decided_by']) ?></td></tr>
  <?php endif; ?>
  <tr><th>Opened</th><td><?= h((string)$ticket['created_at']) ?></td></tr>
  <?php if (trim((string)($ticket['reason'] ?? '')) !== ''): ?>
  <tr><th>Reason</th><td><?= nl2br(h((string)$ticket['reason'])) ?></td></tr>
  <?php endif; ?>
</table>

<h3>Conversation</h3>
<?php foreach ($thread as $message): ?>
<div class="msg <?= (string)$message['author'] === 'staff' ? 'info' : '' ?>">
  <strong><?= (string)$message['author'] === 'staff' ? 'Registry' : 'Applicant' ?></strong>
  <span class="small"><?= h((string)$message['created_at']) ?>
    <?= (int)$message['mailed'] === 1 ? '&middot; mailed' : '' ?></span>
  <div><?= nl2br(h((string)$message['body'])) ?></div>
</div>
<?php endforeach; ?>
<?php if ($thread === []): ?><p>Nothing written yet.</p><?php endif; ?>

<?php if ((string)$ticket['status'] === 'open'): ?>
<h3>Approve</h3>
<p class="hint">
Open the name in the <strong>DNSHE panel</strong>, start the transfer there and copy the code; paste it
here. The recipient receives it by mail together with the 72 hour deadline.
</p>
<form method="post" action="tickets.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="approve">
<input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
<label for="transfer_code">Transfer code from DNSHE</label>
<input type="text" id="transfer_code" name="transfer_code" maxlength="190" value="" autocomplete="off">
<label for="note">Note for the recipient (optional, also stored in the ticket)</label>
<textarea id="note" name="note" rows="3"></textarea>
<p><input type="submit" value="Approve and mail the code"></p>
</form>
<?php endif; ?>

<?php if (in_array((string)$ticket['status'], ['open', 'approved'], true)): ?>
<h3>Answer</h3>
<form method="post" action="tickets.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="reply">
<input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
<label for="reply">Message to the holder (mailed)</label>
<textarea id="reply" name="note" rows="4"></textarea>
<p><input type="submit" value="Send the reply"></p>
</form>
<?php endif; ?>

<?php if ((string)$ticket['status'] === 'approved'): ?>
<h3>Close it</h3>
<form method="post" action="tickets.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="complete">
  <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
  <input type="submit" value="The recipient accepted - move ownership">
</form>
<form method="post" action="tickets.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="expire">
  <input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
  <input type="submit" value="Close as expired">
</form>
<?php endif; ?>

<?php if ((string)$ticket['status'] === 'open'): ?>
<h3>Reject</h3>
<form method="post" action="tickets.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="reject">
<input type="hidden" name="id" value="<?= (int)$ticket['id'] ?>">
<label for="reject_note">Reason (mailed to the holder)</label>
<textarea id="reject_note" name="note" rows="3"></textarea>
<p><input type="submit" value="Reject the transfer"></p>
</form>
<?php endif; ?>

<p><a href="tickets.php">All tickets</a> &middot; <a href="index.php">Dashboard</a></p>

<?php else: ?>

<h2>Tickets</h2>
<?php if ($rows === []): ?>
<p>No transfer request yet.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Name</th><th>Holder</th><th>Recipient</th><th>Status</th><th>Opened</th><th></th></tr>
  <?php foreach ($rows as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><code><?= h((string)$row['name']) ?></code><br><span class="small"><?= h((string)$row['zone']) ?></span></td>
    <td><?= h((string)$row['requester_email']) ?></td>
    <td><?= h((string)$row['recipient_name']) ?><br><span class="small"><?= h((string)$row['recipient_email']) ?></span></td>
    <td><?= h(transfer_status_text((string)$row['status'])) ?></td>
    <td><?= h((string)$row['created_at']) ?></td>
    <td class="actions"><a href="tickets.php?id=<?= (int)$row['id'] ?>">open</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php endif; ?>
<?php
page_footer();

<?php
declare(strict_types=1);

/** freedns.my - console: transfer requests (tickets) of this account. */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user   = require_user();
$errors = [];
transfer_sweep();

$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if ($action === 'create') {
        $domain = null;
        foreach (user_domains($user) as $candidate) {
            if ((int)$candidate['id'] === (int)($_POST['domain_id'] ?? 0)) {
                $domain = $candidate;
            }
        }
        if ($domain === null) {
            $errors[] = t('val.transfer_own');
        } else {
            $result = transfer_create(
                $domain,
                $user,
                trim((string)($_POST['recipient_name'] ?? '')),
                trim((string)($_POST['recipient_email'] ?? '')),
                trim((string)($_POST['reason'] ?? ''))
            );
            if ($result['ok']) {
                flash(t('transfer.created'), 'ok');
                redirect(url('account/transfers.php?id=' . (int)$result['id']));
            }
            $errors[] = $result['error'];
        }
    }

    if ($action === 'message') {
        $id      = (int)($_POST['id'] ?? 0);
        $request = transfer_by_id($id);
        $body    = trim((string)($_POST['body'] ?? ''));
        if ($request === null || strcasecmp((string)$request['requester_email'], (string)$user['email']) !== 0) {
            $errors[] = t('val.transfer_own');
        } elseif ($body === '') {
            $errors[] = t('val.transfer_empty');
        } else {
            transfer_message_add($id, 'applicant', (string)$user['username'], $body, false);
            log_action('transfer_message', 'domain', (int)$request['domain_id'],
                'Applicant wrote on ticket ' . $id);
            flash(t('transfer.message_saved'), 'ok');
            redirect(url('account/transfers.php?id=' . $id));
        }
    }

    if ($action === 'confirm') {
        $id      = (int)($_POST['id'] ?? 0);
        $request = transfer_by_id($id);
        if ($request === null) {
            $errors[] = t('val.transfer_own');
        } elseif (strcasecmp((string)$request['recipient_email'], (string)$user['email']) !== 0) {
            $errors[] = t('val.transfer_not_recipient');
        } elseif (transfer_seconds_left($request) <= 0) {
            $errors[] = t('val.transfer_expired');
        } else {
            $done = transfer_complete($id, (string)$user['username'], true);
            if ($done['ok']) {
                flash(t('transfer.confirmed'), 'ok');
                unset($GLOBALS['FD_CURRENT_USER']);
                redirect(url('account/transfers.php?id=' . $id));
            }
            $errors[] = $done['error'];
        }
    }

    if ($action === 'cancel') {
        $id      = (int)($_POST['id'] ?? 0);
        $request = transfer_by_id($id);
        if ($request !== null && strcasecmp((string)$request['requester_email'], (string)$user['email']) === 0
            && (string)$request['status'] === 'open') {
            db_query("UPDATE transfer_requests SET status = 'cancelled', updated_at = NOW() WHERE id = ?", [$id]);
            log_action('transfer_cancelled', 'domain', (int)$request['domain_id'], 'Ticket ' . $id . ' withdrawn');
            flash(t('transfer.cancelled'), 'ok');
            redirect(url('account/transfers.php'));
        }
        $errors[] = t('val.transfer_own');
    }
}

$id      = (int)($_GET['id'] ?? 0);
$current = $id > 0 ? transfer_by_id($id) : null;
if ($current !== null
    && strcasecmp((string)$current['requester_email'], (string)$user['email']) !== 0
    && strcasecmp((string)$current['recipient_email'], (string)$user['email']) !== 0) {
    $current = null;
}

$mine    = transfer_list_for_email((string)$user['email']);
$thread  = $current === null ? [] : transfer_messages((int)$current['id']);
$name    = $current === null ? '' : (string)db_value('SELECT name FROM domains WHERE id = ?',
    [(int)$current['domain_id']], '');
$isRecipient = $current !== null && strcasecmp((string)$current['recipient_email'], (string)$user['email']) === 0;

// Names of this account that may be transferred at all.
$transferable = [];
foreach (user_domains($user) as $candidate) {
    if (transfer_suffix_ok((string)$candidate['zone']) && (string)$candidate['status'] === 'active'
        && transfer_active_for_domain((int)$candidate['id']) === null) {
        $transferable[] = $candidate;
    }
}

page_header(t('transfer.heading'));
?>
<p><?= h(t('transfer.intro')) ?></p>
<?= error_list($errors) ?>

<?php if ($current !== null): ?>
<h2><?= h($name) ?> &middot; <?= h(t('transfer.ticket', (string)(int)$current['id'])) ?></h2>
<table>
  <tr><th><?= h(t('transfer.status_heading')) ?></th>
      <td><?= h(transfer_status_text((string)$current['status'])) ?>
      <?php $left = transfer_seconds_left($current); ?>
      <?php if ($left > 0): ?>
      &middot; <?= h(t('transfer.hours_left', (string)(int)ceil($left / 3600))) ?>
      <?php endif; ?></td></tr>
  <tr><th><?= h(t('transfer.requester')) ?></th><td><?= h((string)$current['requester_email']) ?></td></tr>
  <tr><th><?= h(t('transfer.recipient')) ?></th>
      <td><?= h((string)$current['recipient_name']) ?> &lt;<?= h((string)$current['recipient_email']) ?>&gt;</td></tr>
  <tr><th><?= h(t('transfer.opened')) ?></th><td><?= h((string)$current['created_at']) ?></td></tr>
  <?php if (trim((string)($current['reason'] ?? '')) !== ''): ?>
  <tr><th><?= h(t('transfer.reason')) ?></th><td><?= nl2br(h((string)$current['reason'])) ?></td></tr>
  <?php endif; ?>
</table>

<h3><?= h(t('transfer.thread')) ?></h3>
<?php if ($thread === []): ?>
<p><?= h(t('transfer.no_messages')) ?></p>
<?php else: ?>
<?php foreach ($thread as $message): ?>
<div class="msg <?= (string)$message['author'] === 'staff' ? 'info' : '' ?>">
  <strong><?= h((string)$message['author'] === 'staff'
      ? t('transfer.from_staff')
      : t('transfer.from_applicant', (string)($message['author_name'] ?? ''))) ?></strong>
  <span class="small"><?= h((string)$message['created_at']) ?></span>
  <div><?= nl2br(h((string)$message['body'])) ?></div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php if (strcasecmp((string)$current['requester_email'], (string)$user['email']) === 0
    && in_array((string)$current['status'], ['open', 'approved'], true)): ?>
<h3><?= h(t('transfer.write')) ?></h3>
<form method="post" action="<?= h(url('account/transfers.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="message">
<input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
<label for="body"><?= h(t('transfer.message')) ?></label>
<textarea id="body" name="body" rows="4"></textarea>
<p><input type="submit" value="<?= h(t('transfer.send')) ?>"></p>
</form>
<?php if ((string)$current['status'] === 'open'): ?>
<form method="post" action="<?= h(url('account/transfers.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="cancel">
<input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
<p><input type="submit" value="<?= h(t('transfer.withdraw')) ?>"></p>
</form>
<?php endif; ?>
<?php endif; ?>

<?php if ($isRecipient && (string)$current['status'] === 'approved'): ?>
<h3><?= h(t('transfer.accept_heading')) ?></h3>
<p><?= h(t('transfer.accept_text')) ?></p>
<form method="post" action="<?= h(url('account/transfers.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="confirm">
<input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
<p><input type="submit" value="<?= h(t('transfer.accept_submit')) ?>"></p>
</form>
<?php endif; ?>

<p><a href="<?= h(url('account/transfers.php')) ?>"><?= h(t('transfer.back')) ?></a></p>

<?php else: ?>

<h2><?= h(t('transfer.mine')) ?></h2>
<?php if ($mine === []): ?>
<p><?= h(t('transfer.none')) ?></p>
<?php else: ?>
<table>
  <tr><th>#</th><th><?= h(t('transfer.name')) ?></th><th><?= h(t('transfer.status_heading')) ?></th>
      <th><?= h(t('transfer.role')) ?></th><th><?= h(t('transfer.opened')) ?></th><th></th></tr>
  <?php foreach ($mine as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><code><?= h((string)$row['name']) ?></code></td>
    <td><?= h(transfer_status_text((string)$row['status'])) ?></td>
    <td><?= h(strcasecmp((string)$row['requester_email'], (string)$user['email']) === 0
        ? t('transfer.role_holder') : t('transfer.role_recipient')) ?></td>
    <td><?= h((string)$row['created_at']) ?></td>
    <td class="actions"><a href="<?= h(url('account/transfers.php?id=' . (int)$row['id'])) ?>">
        <?= h(t('transfer.open')) ?></a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= h(t('transfer.new_heading')) ?></h2>
<?php if (!transfer_enabled()): ?>
<p><?= h(t('transfer.off')) ?></p>
<?php elseif ($transferable === []): ?>
<p><?= h(t('transfer.nothing_to_transfer')) ?></p>
<?php else: ?>
<p><?= h(t('transfer.new_intro')) ?></p>
<form method="post" action="<?= h(url('account/transfers.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="create">
<label for="domain_id"><?= h(t('transfer.name')) ?></label>
<select id="domain_id" name="domain_id">
  <?php foreach ($transferable as $candidate): ?>
  <option value="<?= (int)$candidate['id'] ?>"><?= h((string)$candidate['name']) ?></option>
  <?php endforeach; ?>
</select>
<label for="recipient_name"><?= h(t('transfer.recipient_name')) ?></label>
<input type="text" id="recipient_name" name="recipient_name" maxlength="190" value="">
<label for="recipient_email"><?= h(t('transfer.recipient_email')) ?></label>
<input type="text" id="recipient_email" name="recipient_email" maxlength="190" value="">
<label for="reason"><?= h(t('transfer.reason')) ?></label>
<textarea id="reason" name="reason" rows="3"></textarea>
<p><input type="submit" value="<?= h(t('transfer.submit')) ?>"></p>
</form>
<p class="hint"><?= h(t('transfer.hint')) ?></p>
<?php endif; ?>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

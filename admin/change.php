<?php
declare(strict_types=1);

/**
 * freedns.my - administration: review one record change request.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
$errors = [];

$id     = (int)($_REQUEST['id'] ?? 0);
$change = $id > 0 ? change_request_by_id($id) : null;

if ($change === null) {
    flash('That change request does not exist.', 'error');
    redirect('changes.php');
}

$domain = domain_by_id((int)$change['domain_id']);
if ($domain === null) {
    flash('The domain of that change request no longer exists.', 'error');
    redirect('changes.php');
}
if (!admin_may_zone((string)$domain['zone'])) {
    admin_deny(
        'Change request ' . $id . ' belongs to ' . (string)$domain['name'] . ' (suffix '
        . (string)$domain['zone'] . '), which is not assigned to your account.'
    );
}

$proposed = change_snapshot_decode_strict((string)$change['snapshot']);
$live     = domain_records((int)$domain['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');
    $note   = trim((string)($_POST['admin_note'] ?? ''));

    if ((string)$change['status'] !== 'pending') {
        $errors[] = 'This change request has already been decided.';
    } elseif (!in_array($action, ['approve', 'reject'], true)) {
        $errors[] = 'Unknown action requested.';
    } else {
        try {
            if ($action === 'approve') {
                // Publish at Cloudflare first: with the strict policy a failed call must not
                // publish the change locally.
                $cfRows    = null;
                $isLive    = (string)$domain['status'] === 'active';
                $zoneName  = (string)$domain['zone'];
                $syncError = '';

                if ($isLive && cf_enabled() && cf_zone_id($zoneName) !== '') {
                    $sync = publish_sync_domain(
                        (int)$domain['id'],
                        (string)$domain['name'],
                        $zoneName,
                        publish_records_of($proposed ?? []),
                        !empty($domain['proxied'])
                    );
                    if ($sync['ok']) {
                        $cfRows = $sync['rows'];
                        log_action(
                            'cf_synced',
                            'domain',
                            (int)$domain['id'],
                            (string)$domain['name'] . ': ' . $sync['created'] . ' created, ' . $sync['updated']
                                . ' updated, ' . $sync['deleted'] . ' removed (change ' . $id . ')'
                        );
                    } else {
                        $syncError = $sync['error'];
                        log_action('cf_sync_failed', 'domain', (int)$domain['id'], (string)$domain['name'] . ': ' . $sync['error']);
                    }
                }

                if ($syncError !== '' && cf_strict()) {
                    $errors[] = (function_exists('publish_provider_label') ? publish_provider_label((string)publish_provider((string)($domain['zone'] ?? ''))) : 'The provider') . ' refused the change, so nothing was published: ' . $syncError;
                } else {
                    if ($syncError !== '') {
                        $errors[] = (function_exists('publish_provider_label') ? publish_provider_label((string)publish_provider((string)($domain['zone'] ?? ''))) : 'The provider') . ' refused the change (published locally anyway): ' . $syncError;
                    }

                    try {
                        apply_change_request($change, (string)$admin['username'], $note);
                    } catch (Throwable $e) {
                        if ($cfRows !== null) {
                            // The records are already at Cloudflare but the local change did not
                            // happen; the reconciler puts this right.
                            log_action(
                                'cf_sync_orphaned',
                                'domain',
                                (int)$domain['id'],
                                (string)$domain['name'] . ': Cloudflare updated, local change failed - run a full sync'
                            );
                        }
                        throw $e;
                    }

                    if ($cfRows !== null) {
                        publish_store_mapping((int)$domain['id'], $cfRows);
                    }

                    $change = change_request_by_id($id) ?? $change;
                    notify_applicant_change($domain, $change, domain_records((int)$domain['id']), true);
                    log_action(
                        'change_approved',
                        'domain',
                        (int)$domain['id'],
                        'Change request ' . $id . ' approved for ' . (string)$domain['name']
                            . ($note === '' ? '' : ' | note: ' . $note)
                    );
                    flash('The change has been approved and published.', 'ok');
                    redirect('changes.php');
                }
            } else {
                reject_change_request($change, (string)$admin['username'], $note);
                $change = change_request_by_id($id) ?? $change;
                notify_applicant_change($domain, $change, $proposed ?? [], false);
                log_action(
                    'change_rejected',
                    'domain',
                    (int)$domain['id'],
                    'Change request ' . $id . ' rejected for ' . (string)$domain['name']
                        . ($note === '' ? '' : ' | note: ' . $note)
                );
                flash('The change has been rejected; the live records were left untouched.', 'ok');
            }
            redirect('changes.php');
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        } catch (Throwable $e) {
            $errors[] = 'The decision could not be saved because of a server error.';
        }
    }
}

$diff = records_diff($live, $proposed ?? []);
$domainName = (string)$domain['name'];

page_header('Change request ' . (int)$change['id']);
?>
<p>
<?= h((string)$domain['name']) ?> &middot; change status
<strong><?= h(status_text((string)$change['status'])) ?></strong>
&middot; name status <strong><?= h(status_text((string)$domain['status'])) ?></strong>
&middot; submitted <?= h((string)$change['submitted_at']) ?> from
<?= h((string)($change['submitted_ip'] ?? 'unknown')) ?>
&middot; applicant <?= h((string)$domain['registrant_name']) ?>
&lt;<?= h((string)$domain['registrant_email']) ?>&gt;
</p>
<p class="small">
<a href="edit.php?id=<?= (int)$domain['id'] ?>">open the domain record</a> &middot;
<a href="changes.php">back to the queue</a>
</p>

<?= error_list($errors) ?>

<?php $applicantNote = trim((string)($change['applicant_note'] ?? '')); ?>
<?php if ($applicantNote !== ''): ?>
<h2>Note from the applicant</h2>
<?= text_block($applicantNote) ?>
<?php endif; ?>

<h2>What changes</h2>
<table>
  <tr><th>Change</th><th>Records</th></tr>
  <tr>
    <td>added</td>
    <td><?= $diff['added'] === []
        ? '-'
        : '<pre class="wrap">' . h(implode("\n", record_zone_lines($diff['added'], $domainName))) . '</pre>' ?></td>
  </tr>
  <tr>
    <td>removed</td>
    <td><?= $diff['removed'] === []
        ? '-'
        : '<pre class="wrap">' . h(implode("\n", record_zone_lines($diff['removed'], $domainName))) . '</pre>' ?></td>
  </tr>
  <tr>
    <td>unchanged</td>
    <td><?= count($diff['kept']) ?> record(s)</td>
  </tr>
</table>

<h2>Records live now</h2>
<pre class="wrap code-block"><?= h($live === []
    ? '; no record on file'
    : implode("\n", record_zone_lines($live, $domainName))) ?></pre>

<h2>Proposed records</h2>
<?php if ($proposed === null): ?>
<div class="msg error">
The stored change cannot be read (damaged snapshot). It cannot be approved; reject it and ask the
applicant to submit the change again.
</div>
<?php else: ?>
<pre class="wrap code-block"><?= h($proposed === []
    ? '; the applicant proposes to remove every record'
    : implode("\n", record_zone_lines($proposed, $domainName))) ?></pre>
<?php endif; ?>

<?php $decidedNote = trim((string)($change['admin_note'] ?? '')); ?>
<?php if ((string)$change['status'] !== 'pending'): ?>
<h2>Decision</h2>
<p>
<?= h(status_text((string)$change['status'])) ?> by <?= h((string)($change['decided_by'] ?? '')) ?>
on <?= h(format_date((string)($change['decided_at'] ?? ''))) ?>.
</p>
<?php if ($decidedNote !== ''): ?>
<?= text_block($decidedNote) ?>
<?php endif; ?>
<?php else: ?>
<h2>Decision</h2>
<form method="post" action="change.php?id=<?= (int)$change['id'] ?>">
  <?= csrf_field() ?>
  <label for="admin_note">Note for the applicant (optional)</label>
  <input type="text" id="admin_note" name="admin_note" maxlength="500" value="<?= h($decidedNote) ?>">
  <p class="hint">
    Approving replaces the live records of <code><?= h($domainName) ?></code> with the proposed set and
    publishes them. Rejecting leaves the live records untouched. Either way the applicant is notified if
    notifications are enabled. The proposed set is validated again when it is applied.
  </p>
  <p>
    <select name="action">
      <option value="approve">Approve and publish</option>
      <option value="reject">Reject</option>
    </select>
    <input type="submit" value="Apply decision">
  </p>
</form>
<?php endif; ?>
<?php
page_footer();

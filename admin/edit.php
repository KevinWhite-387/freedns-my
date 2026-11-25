<?php
declare(strict_types=1);

/**
 * freedns.my - administration: view and edit a single domain record (including its records).
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();

$id     = (int)($_REQUEST['id'] ?? 0);
$domain = $id > 0 ? domain_by_id($id) : null;

if ($domain === null) {
    flash('That domain record does not exist.', 'error');
    redirect(url('admin/domains.php'));
}
if (!admin_may_zone((string)$domain['zone'])) {
    admin_deny(
        'The name ' . (string)$domain['name'] . ' belongs to the suffix ' . (string)$domain['zone']
        . ', which is not assigned to your account.'
    );
}

// A reviewer may read a name but not change it: the editor is replaced by a read-only view and every
// save is refused.
$canEdit = is_owner();

$zone   = (string)$domain['zone'];
$errors = [];

$field = [
    'label'            => (string)$domain['label'],
    'registrant_name'  => (string)$domain['registrant_name'],
    'registrant_email' => (string)$domain['registrant_email'],
    'organization'     => (string)($domain['organization'] ?? ''),
    'country'          => (string)($domain['country'] ?? ''),
    'justification'    => (string)($domain['justification'] ?? ''),
    'admin_note'       => (string)($domain['admin_note'] ?? ''),
    'public_note'      => (string)($domain['public_note'] ?? ''),
    'status'           => (string)$domain['status'],
    'proxied'          => (int)($domain['proxied'] ?? 0),
];

$liveRecords = domain_records($id);

/** Rows shown in the editor: the submitted ones after a failed POST, otherwise the live records. */
$editorRows = [];
foreach ($liveRecords as $record) {
    $editorRows[] = [
        'sub'   => (string)$record['sub'],
        'type'  => strtoupper((string)$record['type']),
        'value' => (string)$record['value'],
    ];
}
if ($editorRows === []) {
    $editorRows[] = ['sub' => '', 'type' => 'A', 'value' => ''];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$canEdit) {
    // Refuse everything a reviewer might try to send; the page then shows the read-only view.
    $errors[] = 'Your account is a reviewer: records of this name can be read but not changed.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canEdit) {
    csrf_verify();

    $field['label']            = strtolower(trim((string)($_POST['label'] ?? '')));
    $field['registrant_name']  = trim((string)($_POST['registrant_name'] ?? ''));
    $field['registrant_email'] = trim((string)($_POST['registrant_email'] ?? ''));
    $field['organization']     = trim((string)($_POST['organization'] ?? ''));
    $field['country']          = trim((string)($_POST['country'] ?? ''));
    $field['justification']    = trim((string)($_POST['justification'] ?? ''));
    $field['admin_note']       = trim((string)($_POST['admin_note'] ?? ''));
    $field['public_note']      = trim((string)($_POST['public_note'] ?? ''));
    $field['status']           = (string)($_POST['status'] ?? 'pending');
    $field['proxied']          = isset($_POST['proxied']) ? 1 : 0;

    $editorRows = raw_records_from_post();
    if ($editorRows === []) {
        $editorRows[] = ['sub' => '', 'type' => 'A', 'value' => ''];
    }

    $collected     = collect_records_from_post((string)$domain['name'], $zone, max_records_per_domain($zone));
    $cleanRecords  = $collected['records'];
    foreach ($collected['errors'] as $error) {
        $errors[] = $error;
    }

    if ($field['label'] === '' || !valid_label($field['label'])) {
        $errors[] = 'The label is not valid: use only a-z, 0-9 and the hyphen, without a leading or trailing hyphen.';
    }
    if (strlen($field['label']) < 2 || strlen($field['label']) > 63) {
        $errors[] = 'The label must be between 2 and 63 characters long.';
    }
    if (in_array($field['label'], reserved_labels(), true) && $field['label'] !== (string)$domain['label']) {
        $errors[] = 'The label "' . $field['label'] . '" is reserved for registry use.';
    }
    if ($field['registrant_name'] === '') {
        $errors[] = 'The applicant name is required.';
    } elseif (strlen($field['registrant_name']) > 190) {
        $errors[] = 'The applicant name is too long (190 characters maximum).';
    }
    if (!valid_email_addr($field['registrant_email'])) {
        $errors[] = 'The applicant e-mail address is not valid.';
    }
    if (strlen($field['organization']) > 190) {
        $errors[] = 'The organization name is too long (190 characters maximum).';
    }
    if (strlen($field['country']) > 64) {
        $errors[] = 'The country field is too long (64 characters maximum).';
    }
    if (strlen($field['justification']) > 2000) {
        $errors[] = 'The purpose text is too long (2000 characters maximum).';
    }
    if (!in_array($field['status'], status_options(), true)) {
        $errors[] = 'Unknown status.';
    }

    $newName = $field['label'] . '.' . $zone;
    if ($field['label'] !== '' && $newName !== (string)$domain['name']) {
        $clash = db_one('SELECT id FROM domains WHERE name = ? AND id <> ?', [$newName, $id]);
        if ($clash !== null) {
            $errors[] = 'The name ' . $newName . ' is already used by another record.';
        }
    }

    if ($errors === []) {
        $oldStatus    = (string)$domain['status'];
        $newStatus    = $field['status'];
        $recordsDirty = $cleanRecords !== array_map(
            static fn($row) => ['sub' => $row['sub'], 'type' => $row['type'], 'value' => $row['value']],
            $liveRecords
        );
        $pdo   = db();
        $saved = false;

        // Publish at Cloudflare first: with the strict policy a failed call must not save anything.
        $mappingAction = '';
        $mappingRows   = [];
        $cfWarning     = '';
        if (publish_zone_ready($zone)) {
            if ($newStatus === 'active') {
                $sync = publish_sync_domain($id, $newName, $zone, publish_records_of($cleanRecords), (bool)$field['proxied']);
                if ($sync['ok']) {
                    $mappingAction = 'store';
                    $mappingRows   = $sync['rows'];
                    log_action(
                        'cf_synced',
                        'domain',
                        $id,
                        $newName . ': ' . $sync['created'] . ' created, ' . $sync['updated'] . ' updated, '
                            . $sync['deleted'] . ' removed (record edited)'
                    );
                }
            } else {
                $sync = publish_remove_domain($id, $zone, (string)$field['status'] === 'deleted');
                if ($sync['ok']) {
                    $mappingAction = 'clear';
                }
            }

            if (!$sync['ok']) {
                log_action('cf_sync_failed', 'domain', $id, $newName . ': ' . $sync['error']);
                if (cf_strict()) {
                    $errors[] = (function_exists('publish_provider_label') ? publish_provider_label((string)publish_provider($zone)) : 'The provider') . ' refused the records, so nothing was saved: ' . $sync['error'];
                } else {
                    $cfWarning = (function_exists('publish_provider_label') ? publish_provider_label((string)publish_provider($zone)) : 'The provider') . ' refused the records (saved locally anyway): ' . $sync['error'];
                }
            }
        }

        if ($errors === []) {
            try {
                $pdo->beginTransaction();

                if ($mappingAction === 'store') {
                    publish_store_mapping($id, (array)($mappingRows ?? []));
                } elseif ($mappingAction === 'clear') {
                    publish_forget_mapping($id);
                }

                db_query(
                    'UPDATE domains SET name = ?, label = ?, status = ?, registrant_name = ?, registrant_email = ?,
                        organization = ?, country = ?, justification = ?, admin_note = ?, public_note = ?,
                        proxied = ?, updated_at = NOW() WHERE id = ?',
                    [
                        $newName,
                        $field['label'],
                        $newStatus,
                        $field['registrant_name'],
                        $field['registrant_email'],
                        $field['organization'] === '' ? null : $field['organization'],
                        $field['country'] === '' ? null : $field['country'],
                        $field['justification'] === '' ? null : $field['justification'],
                        $field['admin_note'] === '' ? null : $field['admin_note'],
                        $field['public_note'] === '' ? null : $field['public_note'],
                        (int)$field['proxied'],
                        $id,
                    ]
                );

                if ($recordsDirty) {
                    replace_records($id, $cleanRecords);
                }

                if ($oldStatus !== $newStatus) {
                    if (in_array($newStatus, ['active', 'rejected', 'suspended'], true)) {
                        db_query(
                            'UPDATE domains SET decided_at = NOW(), decided_by = ? WHERE id = ?',
                            [(string)$admin['username'], $id]
                        );
                    } else {
                        db_query('UPDATE domains SET decided_at = NULL, decided_by = NULL WHERE id = ?', [$id]);
                    }
                    if ($newStatus !== 'active') {
                        // A name that is no longer active cannot carry a queued record change.
                        drop_pending_changes($id);
                    }
                }

                $pdo->commit();
                $saved = true;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $errors[] = 'The record could not be saved because of a server error. '
                    . 'Nothing was changed; please try again.';
            }
        }

        if ($cfWarning !== '' && $saved) {
            flash($cfWarning, 'error');
        }

        if ($saved) {
            if ($oldStatus !== $newStatus) {
                $updated = domain_by_id($id);
                if ($updated !== null && in_array($newStatus, ['active', 'rejected', 'suspended'], true)) {
                    notify_applicant_decision($updated, domain_records($id));
                }
                log_action(
                    'decision_' . $newStatus,
                    'domain',
                    $id,
                    (string)$domain['name'] . ': ' . $oldStatus . ' -> ' . $newStatus . ' (record edited)'
                );
            } else {
                log_action(
                    'domain_updated',
                    'domain',
                    $id,
                    'Record of ' . $newName . ' edited'
                        . ($recordsDirty ? ' (records: ' . count($cleanRecords) . ')' : '')
                );
            }

            flash('The record has been saved.' . ($recordsDirty ? ' The records were updated.' : ''), 'ok');
            redirect('edit.php?id=' . $id);
        }
    }
}

$pendingChange = pending_change_for_domain($id);
$blankRows     = max(3, 10 - count($editorRows));
$history       = db_all(
    "SELECT * FROM audit_log WHERE target_type = 'domain' AND target_id = ? ORDER BY id DESC LIMIT 50",
    [$id]
);

page_header('Domain ' . (string)$domain['name']);
?>
<p>
Record <?= (int)$domain['id'] ?> &middot; request code <strong><?= h((string)$domain['request_code']) ?></strong>
&middot; zone <code><?= h($zone) ?></code>
&middot; submitted <?= h((string)$domain['created_at']) ?>
by <?= h((string)($domain['created_ip'] ?? 'unknown')) ?>.
</p>

<?= error_list($errors) ?>

<?php if ($pendingChange !== null): ?>
<div class="msg info">
  A record change from the applicant is waiting for review (submitted
  <?= h((string)$pendingChange['submitted_at']) ?>).
  <a href="change.php?id=<?= (int)$pendingChange['id'] ?>">Review it</a> &mdash; approving replaces the
  records below with the proposed set.
</div>
<?php endif; ?>

<?php if (!$canEdit): ?>
<div class="msg info">
  <strong>Read only:</strong> your account is a reviewer, so this name can be read but not changed.
  Approving requests and record changes, suspending and deleting are allowed for
  <?= h(implode(', ', admin_scope_zones())) ?>.
</div>
<table>
  <tr><th>Name</th><td><code><?= h((string)$domain['name']) ?></code></td></tr>
  <tr><th>Status</th><td><?= h(status_text((string)$domain['status'])) ?></td></tr>
  <tr><th>Zone</th><td><code><?= h($zone) ?></code></td></tr>
  <tr><th>Request code</th><td><code><?= h((string)$domain['request_code']) ?></code></td></tr>
  <tr><th>Applicant</th><td><?= h((string)$domain['registrant_name']) ?>
      &lt;<?= h((string)$domain['registrant_email']) ?>&gt;
      <?= trim((string)($domain['organization'] ?? '')) === '' ? '' : ' &middot; ' . h((string)$domain['organization']) ?>
      <?= trim((string)($domain['country'] ?? '')) === '' ? '' : ' &middot; ' . h((string)$domain['country']) ?></td></tr>
  <tr><th>Purpose</th><td><?= nl2br(h((string)($domain['justification'] ?? ''))) ?></td></tr>
  <tr><th>Cloudflare proxy</th><td><?= (int)($domain['proxied'] ?? 0) === 1 ? 'yes (orange cloud)' : 'no (DNS only)' ?></td></tr>
  <tr><th>Submitted</th><td><?= h((string)$domain['created_at']) ?>
      from <?= h((string)($domain['created_ip'] ?? 'unknown')) ?></td></tr>
  <tr><th>Updated</th><td><?= h((string)$domain['updated_at']) ?></td></tr>
  <tr><th>Note to the applicant</th><td><?= nl2br(h((string)($domain['public_note'] ?? ''))) ?></td></tr>
  <tr><th>Internal note</th><td><?= nl2br(h((string)($domain['admin_note'] ?? ''))) ?></td></tr>
</table>

<h2>Records in force</h2>
<?php if ($liveRecords === []): ?>
<p>No record is stored.</p>
<?php else: ?>
<pre class="wrap code-block"><?= h(implode("\n", record_zone_lines($liveRecords, (string)$domain['name']))) ?></pre>
<?php endif; ?>

<h2>Decision</h2>
<?php if ((string)$domain['status'] === 'pending'): ?>
<form class="inline" method="post" action="decision.php">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
  <input type="hidden" name="back" value="<?= h('/admin/edit.php?id=' . (int)$domain['id']) ?>">
  <input type="hidden" name="action" value="approve">
  <input type="submit" value="Approve this request">
</form>
<form class="inline" method="post" action="decision.php">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
  <input type="hidden" name="back" value="<?= h('/admin/edit.php?id=' . (int)$domain['id']) ?>">
  <input type="hidden" name="action" value="reject">
  <input type="submit" value="Reject this request">
</form>
<?php else: ?>
<form class="inline" method="post" action="decision.php">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
  <input type="hidden" name="back" value="<?= h('/admin/edit.php?id=' . (int)$domain['id']) ?>">
  <input type="hidden" name="action" value="<?= (string)$domain['status'] === 'suspended' ? 'reactivate' : 'suspend' ?>">
  <input type="submit" value="<?= (string)$domain['status'] === 'suspended' ? 'Reactivate this name' : 'Suspend this name' ?>">
</form>
<form class="inline" method="post" action="decision.php">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
  <input type="hidden" name="back" value="<?= h('/admin/edit.php?id=' . (int)$domain['id']) ?>">
  <input type="hidden" name="action" value="delete">
  <input type="submit" value="Delete this name">
</form>
<?php endif; ?>
<p><a href="domains.php">Back to the list</a> &middot; <a href="changes.php">Change queue</a></p>
<?php
page_footer();
exit;
endif;
?>

<form method="post" action="edit.php?id=<?= (int)$domain['id'] ?>">
<?= csrf_field() ?>
<input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">

<h2>The name</h2>
<?php if (!empty($domain['reserved'])): ?>
<div class="msg info">
  This request came in through the <strong>reserved-name option</strong>: the name is on the reserved
  list, a description was mandatory and a single-character label is allowed.
  <a href="reserved.php?zone=<?= h(urlencode($zone)) ?>">Reserved list</a>
</div>
<?php endif; ?>
<label for="label">Label (the part in front of .<?= h($zone) ?>)</label>
<input type="text" id="label" name="label" value="<?= h($field['label']) ?>" maxlength="63">
<p class="hint">Resolves to: <code><?= h((string)$field['label'] . '.' . $zone) ?></code>.
The zone itself cannot be changed here.</p>

<label for="status">Status</label>
<select id="status" name="status">
  <?php foreach (status_options() as $option): ?>
  <option value="<?= h($option) ?>"<?= $field['status'] === $option ? ' selected' : '' ?>>
    <?= h(status_text($option)) ?>
  </option>
  <?php endforeach; ?>
</select>
<p class="hint">
ACTIVE publishes the name and its records in the public list and in the zone export. REJECTED and
SUSPENDED also send a message to the applicant, as does a change to ACTIVE. Changing the status here has
the same effect as the buttons on the dashboard.
</p>

<?php if (publish_zone_ready($zone)): ?>
<p>
  <input type="checkbox" id="proxied" name="proxied" value="1"<?= $field['proxied'] ? ' checked' : '' ?>>
  <label class="inline" for="proxied">Publish through the Cloudflare proxy (orange cloud)</label>
</p>
<p class="hint">
Off = DNS only, which is what a registry normally wants. On = Cloudflare proxies the traffic of
A/AAAA/CNAME records (TXT records never are). The applicant can choose this on the request form, and you
can correct it here.
</p>
<?php endif; ?>

<h2>Records</h2>
<p class="hint">
Empty <em>name</em> means the registered name itself; one label (<code>www</code>, <code>mail</code>, ...)
creates <code>www.<?= h((string)$domain['name']) ?></code>. The registered name always needs at least one
A or AAAA record, and a CNAME must be the only record of its name. At most
<?= (int)max_records_per_domain($zone) ?> records per name. Changes made here are published immediately
(this is the registry operator's editor, not the review queue).
</p>
<table>
  <tr><th>#</th><th>Name</th><th>Type</th><th>Value</th><th>TTL</th><th>Proxy</th><th>Remove</th></tr>
  <?php foreach ($editorRows as $index => $row): ?>
  <tr>
    <td><?= $index + 1 ?></td>
    <td>
      <input type="hidden" name="row_sub[]" value="<?= h($row['sub']) ?>">
      <code><?= h(record_sub_display((string)$row['sub'])) ?></code>
    </td>
    <td>
      <select name="row_type[]">
        <?php foreach (record_types() as $type): ?>
        <option value="<?= h($type) ?>"<?= $row['type'] === $type ? ' selected' : '' ?>><?= h($type) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="text" name="row_value[]" value="<?= h($row['value']) ?>" maxlength="255"></td>
    <td><input type="text" name="row_ttl[<?= $index ?>]" value="<?= (int)($row['ttl'] ?? default_ttl()) ?>" maxlength="5"
               size="5" title="<?= h(t('rec.ttl_hint')) ?>"></td>
    <td><input type="checkbox" name="row_proxied[<?= $index ?>]" value="1"<?= empty($row['proxied']) ? '' : ' checked' ?>></td>
    <td><input type="checkbox" name="row_delete[]" value="<?= $index ?>"></td>
  </tr>
  <?php endforeach; ?>
</table>

<h3>Add records</h3>
<p class="hint">
Enter <code>@</code> for the registered name itself, or one label (<code>www</code>, <code>mail</code>, ...)
for a sub-name.
</p>
<table>
  <tr><th>Name</th><th>Type</th><th>Value</th></tr>
  <?php for ($i = 0; $i < $blankRows; $i++): ?>
  <tr>
    <td><input type="text" name="new_sub[]" maxlength="63" placeholder="@ or www"></td>
    <td>
      <select name="new_type[]">
        <?php foreach (record_types() as $type): ?>
        <option value="<?= h($type) ?>"><?= h($type) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="text" name="new_value[]" maxlength="255"
               placeholder="IPv4/IPv6 address, host name or text"></td>
  </tr>
  <?php endfor; ?>
</table>

<h2>Applicant</h2>
<label for="registrant_name">Full name</label>
<input type="text" id="registrant_name" name="registrant_name" value="<?= h($field['registrant_name']) ?>" maxlength="190">

<label for="registrant_email">E-mail address</label>
<input type="text" id="registrant_email" name="registrant_email" value="<?= h($field['registrant_email']) ?>" maxlength="190">

<label for="organization">Organization</label>
<input type="text" id="organization" name="organization" value="<?= h($field['organization']) ?>" maxlength="190">

<label for="country">Country</label>
<input type="text" id="country" name="country" value="<?= h($field['country']) ?>" maxlength="64">

<label for="justification">Purpose given by the applicant</label>
<textarea id="justification" name="justification" rows="4"><?= h($field['justification']) ?></textarea>

<h2>Notes</h2>
<label for="public_note">Note visible to the applicant</label>
<textarea id="public_note" name="public_note" rows="4"><?= h($field['public_note']) ?></textarea>

<label for="admin_note">Internal note (never published, never mailed)</label>
<textarea id="admin_note" name="admin_note" rows="4"><?= h($field['admin_note']) ?></textarea>

<p><input type="submit" value="Save record"></p>
</form>

<h2>Quick decisions</h2>
<p class="small">These post immediately and send the applicant a message.</p>
<?php foreach ([
    'approve'    => 'Approve (ACTIVE)',
    'reject'     => 'Reject',
    'suspend'    => 'Suspend',
    'reactivate' => 'Reactivate',
    'pending'    => 'Back to PENDING',
    'delete'     => 'Delete from registry',
] as $action => $label): ?>
<form class="inline" method="post" action="decision.php">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$domain['id'] ?>">
  <input type="hidden" name="action" value="<?= h($action) ?>">
  <input type="hidden" name="back" value="/admin/edit.php?id=<?= (int)$domain['id'] ?>">
  <input type="submit" value="<?= h($label) ?>">
</form>
<?php endforeach; ?>

<h2>History of this record</h2>
<?php if ($history === []): ?>
<p>No administrative action recorded yet.</p>
<?php else: ?>
<table>
  <tr><th>When</th><th>Administrator</th><th>Action</th><th>Detail</th><th>From</th></tr>
  <?php foreach ($history as $entry): ?>
  <tr>
    <td><?= h((string)$entry['created_at']) ?></td>
    <td><?= h((string)$entry['admin_username']) ?></td>
    <td><?= h((string)$entry['action']) ?></td>
    <td class="small"><?= h(shorten((string)($entry['detail'] ?? ''), 200)) ?></td>
    <td class="small"><?= h((string)($entry['ip'] ?? '')) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();

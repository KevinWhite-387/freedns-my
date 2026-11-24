<?php
declare(strict_types=1);

/**
 * freedns.my - console record manager for one name.
 *
 * Same rules as the old page: while a request is still pending the records are saved at once,
 * once the name is active a change is queued for review. The applicant is identified by the
 * console session instead of a request code.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();

$user = require_user();

$id      = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$domain  = user_domain($user, $id);

if ($domain === null) {
    page_header(t('account.manage_heading'));
    echo '<div class="msg error">' . h(t('account.manage_not_found')) . '</div>';
    echo '<p><a href="' . h(url('account/index.php')) . '">' . h(t('account.console_heading')) . '</a></p>';
    page_footer();
    exit;
}

$errors        = [];
$notice        = '';
$proposed      = null;
$liveRecords   = domain_records($id);
$pendingChange = pending_change_for_domain($id);
$note          = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? 'propose');
    $note   = trim((string)($_POST['applicant_note'] ?? ''));

    if ($action === 'withdraw') {
        if ($pendingChange === null) {
            $errors[] = t('change.nothing_pending');
        } else {
            db_query('DELETE FROM change_requests WHERE id = ?', [(int)$pendingChange['id']]);
            log_action(
                'change_withdrawn',
                'domain',
                $id,
                (string)$domain['name'] . ': ' . (string)$user['username'] . ' withdrew change request '
                    . (int)$pendingChange['id']
            );
            flash(t('change.withdrawn'), 'ok');
            redirect(url('account/manage.php?id=' . $id));
        }
    }

    if ($action === 'propose') {
        $status     = (string)$domain['status'];
        // Record review is gone: an approved (active) name is edited directly, exactly like a
        // pending one. The queue below is kept only as a fallback for future use.
        $directEdit = in_array($status, ['pending', 'active'], true);
        $mayEdit    = in_array($status, ['pending', 'active'], true);

        if (!setting_bool('self_service', true)) {
            $errors[] = t('change.disabled');
        } elseif (ns_domain_delegated((int)$domain['id'])) {
            // The name uses its own name servers, so this registry must not touch its records.
            $errors[] = t('ns.read_only');
        } elseif (transfer_domain_locked((int)$domain['id'])) {
            // A transfer is running: the records stay untouched until it is done or expired.
            $errors[] = t('transfer.locked');
        } elseif (!$mayEdit) {
            $errors[] = t('change.not_editable', status_text($status));
        } else {
            $collected = collect_records_from_post(
                (string)$domain['name'],
                (string)$domain['zone'],
                max_records_per_domain((string)$domain['zone'])
            );
            $candidate = $collected['records'];
            foreach ($collected['errors'] as $error) {
                $errors[] = $error;
            }

            if ($errors === [] && $directEdit) {
                $pdo = db();
                try {
                    $pdo->beginTransaction();
                    db_query('SELECT id FROM domains WHERE id = ? FOR UPDATE', [$id]);
                    replace_records($id, $candidate);
                    db_query(
                        "INSERT INTO change_requests (domain_id, status, snapshot, applicant_note,
                            admin_note, submitted_at, submitted_ip, decided_at, decided_by)
                         VALUES (?, 'approved', ?, ?,
                            'Saved by the applicant while the request was still pending',
                            NOW(), ?, NOW(), 'applicant')",
                        [
                            $id,
                            change_snapshot_encode($candidate),
                            $note === '' ? null : limit_chars($note, 500),
                            client_ip(),
                        ]
                    );
                    $pdo->commit();
                    $liveRecords = domain_records($id);

                    /* Publish immediately: with record review gone there is no admin step left that
                     * would push the change, so the console does what admin/decision.php used to do. */
                    if ($status === 'active' && function_exists('publish_zone_ready')
                        && publish_zone_ready((string)$domain['zone'])) {
                        try {
                            publish_sync_domain(
                                $id,
                                (string)$domain['name'],
                                (string)$domain['zone'],
                                publish_records_of($liveRecords),
                                !empty($domain['proxied'])
                            );
                        } catch (Throwable $e) {
                            /* The records are saved; a publish failure must not undo that. The next
                             * reconcile run picks it up again. */
                            log_action('publish_failed', 'domain', $id,
                                (string)$domain['name'] . ': ' . $e->getMessage());
                        }
                    }
                    log_action(
                        'records_updated',
                        'domain',
                        $id,
                        (string)$domain['name'] . ': ' . (string)$user['username']
                            . ' updated the records of a pending request'
                    );
                    flash(t('change.updated_pending'), 'ok');
                    redirect(url('account/manage.php?id=' . $id));
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $errors[] = t('change.save_error');
                }
            }

            if ($errors === [] && !$directEdit) {
                $pdo = db();
                try {
                    $pdo->beginTransaction();
                    db_query('SELECT id FROM domains WHERE id = ? FOR UPDATE', [$id]);
                    if ($pendingChange !== null) {
                        db_query('DELETE FROM change_requests WHERE id = ?', [(int)$pendingChange['id']]);
                    }
                    db_query(
                        "INSERT INTO change_requests (domain_id, status, snapshot, applicant_note,
                            submitted_at, submitted_ip)
                         VALUES (?, 'pending', ?, ?, NOW(), ?)",
                        [
                            $id,
                            change_snapshot_encode($candidate),
                            $note === '' ? null : limit_chars($note, 500),
                            client_ip(),
                        ]
                    );
                    $changeId = (int)$pdo->lastInsertId();
                    $pdo->commit();
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $changeId = 0;
                    $errors[] = t('change.save_error');
                }

                if ($errors === []) {
                    $pendingChange = change_request_by_id($changeId);
                    log_action(
                        'change_submitted',
                        'domain',
                        $id,
                        (string)$domain['name'] . ': change request ' . $changeId . ' from '
                            . (string)$user['username']
                    );
                    if ($pendingChange !== null) {
                        notify_admin_new_change($domain, $pendingChange, $candidate);
                    }
                    $notice = t('manage.submitted_title');
                }
            }
        }
    }
}

// A proposal that failed validation: show the submitted rows again so nothing is lost.
if ($errors !== [] && (string)($_POST['action'] ?? '') === 'propose') {
    $proposed = raw_records_from_post();
}
if ($proposed === null && $pendingChange !== null) {
    // Continue from the change that is already waiting.
    $proposed = change_snapshot_decode((string)$pendingChange['snapshot']);
}

$source     = $proposed === null ? $liveRecords : $proposed;
$editorRows = [];
foreach ($source as $record) {
    $editorRows[] = [
        'sub'   => (string)($record['sub'] ?? ''),
        'type'  => strtoupper((string)($record['type'] ?? 'A')),
        'value' => (string)($record['value'] ?? ''),
    ];
}
$blankRows = max(3, 10 - count($editorRows));

page_header(t('account.manage_heading'));
?>
<p>
<?= h(t('manage.name_label')) ?> <code><?= h((string)$domain['name']) ?></code> &middot;
<?= h(t('manage.code_label')) ?> <strong><?= h((string)$domain['request_code']) ?></strong> &middot;
<?= h(t('status.status')) ?> <?= h(status_text((string)$domain['status'])) ?> &middot;
<a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.console_heading')) ?></a>
</p>

<?= error_list($errors) ?>

<?php if ($notice !== ''): ?>
<div class="msg ok"><strong><?= h($notice) ?></strong></div>
<?php endif; ?>

<?php if ($pendingChange !== null): ?>
<div class="msg info">
  <?= t('manage.queued_notice', h((string)$pendingChange['submitted_at'])) ?>
  <form method="post" action="<?= h(url('account/manage.php?id=' . $id)) ?>" style="margin-top:.5em">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">
    <input type="hidden" name="action" value="withdraw">
    <input type="submit" value="<?= h(t('manage.withdraw_queued')) ?>">
  </form>
</div>
<?php endif; ?>

<?php if (!setting_bool('self_service', true)): ?>
<div class="msg error"><?= h(t('manage.disabled')) ?></div>
<?php elseif (!in_array((string)$domain['status'], ['pending', 'active'], true)): ?>
<div class="msg error"><?= t('manage.not_editable', h(status_text((string)$domain['status']))) ?></div>
<?php else: ?>

<?php if ((string)$domain['status'] === 'pending'): ?>
<div class="msg info"><?= h(t('manage.pending_notice')) ?></div>
<?php else: ?>
<div class="msg info"><?= h(t('manage.active_notice')) ?></div>
<?php endif; ?>

<form method="post" action="<?= h(url('account/manage.php?id=' . $id)) ?>">
<?= csrf_field() ?>
<input type="hidden" name="id" value="<?= $id ?>">
<input type="hidden" name="action" value="propose">

<h3><?= t('manage.records_of', h((string)$domain['name'])) ?></h3>
<p class="hint"><?= t('manage.rows_hint') ?></p>

<table>
  <tr>
    <th>#</th>
    <th><?= h(t('manage.col_name')) ?></th>
    <th><?= h(t('manage.col_type')) ?></th>
    <th><?= h(t('manage.col_value')) ?></th>
    <th><?= h(t('manage.col_ttl')) ?></th>
    <th><?= h(t('manage.col_proxy')) ?></th>
    <th><?= h(t('manage.col_remove')) ?></th>
  </tr>
  <?php foreach ($editorRows as $index => $row): ?>
  <tr>
    <td><?= $index + 1 ?></td>
    <td>
      <input type="hidden" name="row_sub[]" value="<?= h((string)$row['sub']) ?>">
      <code><?= h(record_sub_display((string)$row['sub'])) ?></code>
    </td>
    <td>
      <select name="row_type[]">
        <?php foreach (record_types() as $type): ?>
        <option value="<?= h($type) ?>"<?= strtoupper((string)$row['type']) === $type ? ' selected' : '' ?>>
          <?= h($type) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="text" name="row_value[]" value="<?= h((string)$row['value']) ?>" maxlength="255"></td>
    <td><input type="text" name="row_ttl[<?= $index ?>]" value="<?= (int)($row['ttl'] ?? 0) ?>"
               maxlength="5" size="5" title="<?= h(t('rec.ttl_hint')) ?>"></td>
    <td><input type="checkbox" name="row_proxied[<?= $index ?>]" value="1"
               <?= empty($row['proxied']) ? '' : 'checked' ?>></td>
    <td><input type="checkbox" name="row_delete[]" value="<?= $index ?>"></td>
  </tr>
  <?php endforeach; ?>
  <?php for ($i = 0; $i < $blankRows; $i++): ?>
  <tr>
    <td><?= count($editorRows) + $i + 1 ?></td>
    <td><input type="text" name="new_sub[]" maxlength="63"
               placeholder="<?= h(t('manage.name_placeholder')) ?>"></td>
    <td>
      <select name="new_type[]">
        <?php foreach (record_types() as $type): ?>
        <option value="<?= h($type) ?>"><?= h($type) ?></option>
        <?php endforeach; ?>
      </select>
    </td>
    <td><input type="text" name="new_value[]" maxlength="255"
               placeholder="<?= h(t('manage.value_placeholder')) ?>"></td>
    <td></td>
  </tr>
  <?php endfor; ?>
</table>
<p class="hint"><?= t('manage.limits', max_records_per_domain((string)$domain['zone'])) ?></p>

<label for="applicant_note"><?= h(t('manage.note_label')) ?></label>
<input type="text" id="applicant_note" name="applicant_note" value="<?= h($note) ?>" maxlength="500">

<p><input type="submit" value="<?= h(t('manage.submit')) ?>"></p>
</form>
<?php endif; ?>

<h3><?= h(t('account.records_now')) ?></h3>
<pre class="wrap code-block"><?= h($liveRecords === []
    ? t('status.no_record')
    : implode("\n", record_zone_lines($liveRecords, (string)$domain['name']))) ?></pre>

<?php if ($pendingChange !== null): ?>
<h3><?= h(t('status.change_title')) ?></h3>
<p><?= t('status.change_text', h((string)$pendingChange['submitted_at'])) ?></p>
<pre class="wrap code-block"><?= h(implode("\n", record_zone_lines(
    change_snapshot_decode((string)$pendingChange['snapshot']),
    (string)$domain['name']
))) ?></pre>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.console_heading')) ?></a></p>
<?php
page_footer();

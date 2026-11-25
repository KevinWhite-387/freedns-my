<?php
declare(strict_types=1);

/**
 * freedns.my - applicant self-service: propose record changes for a name.
 *
 * While a request is still pending the records are saved immediately (nothing is published yet).
 * Once the name is active the proposed record set is stored as a change request and an operator
 * approves or rejects it in the back office.
 */

require __DIR__ . '/inc/bootstrap.php';

// The form carries a CSRF token, so the session must exist before the first byte of output.
fd_start_session();

/*
 * The operator can close this page for visitors who are not signed in (Settings -> Manage Records).
 * Checked before anything is processed, so a closed page cannot change records either; a signed-in
 * console user is not affected.
 */
$manageNotice = trim(setting_lang('manage_notice'));
if (setting_bool('manage_closed', false) && current_user() === null) {
    page_header(t('manage.heading'));
    ?>
    <?php if ($manageNotice !== ''): ?>
    <div class="msg info">
      <strong><?= h(t('manage.notice_heading')) ?></strong>
      <div><?= nl2br(h($manageNotice)) ?></div>
    </div>
    <?php endif; ?>

    <div class="msg error"><strong><?= h(t('manage.closed_title')) ?></strong></div>
    <p><?= h(t('manage.closed_text')) ?></p>
    <?php
    $_SESSION['after_user_login'] = safe_relative_url((string)($_SERVER['REQUEST_URI'] ?? '/manage.php'), '/');
    ?>
    <p>
      <a href="<?= h(url('account/login.php')) ?>"><?= h(t('manage.closed_login')) ?></a> &middot;
      <a href="<?= h(url('account/register.php')) ?>"><?= h(t('manage.closed_register')) ?></a> &middot;
      <a href="<?= h(url('status.php')) ?>"><?= h(t('nav.status')) ?></a>
    </p>
    <?php
    page_footer();
    exit;
}

$errors        = [];
$domain        = null;
$liveRecords   = [];
$pendingChange = null;
$proposed      = null;
$mode          = 'lookup';

$code   = '';
$email  = '';
$note   = '';
$action = 'lookup';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $code   = strtoupper(trim((string)($_POST['code'] ?? '')));
    $email  = trim((string)($_POST['email'] ?? ''));
    $action = (string)($_POST['action'] ?? 'lookup');
    $note   = trim((string)($_POST['applicant_note'] ?? ''));
    $key    = portal_key($code, $email);

    if ($code === '' || $email === '') {
        $errors[] = t('val.lookup_missing');
    } elseif (!valid_email_addr($email)) {
        $errors[] = t('val.email_invalid');
    } elseif (portal_throttled($key)) {
        $errors[] = t('val.throttled');
    } else {
        $domain = db_one(
            'SELECT * FROM domains WHERE request_code = ? AND registrant_email = ?',
            [$code, $email]
        );

        if ($domain === null) {
            record_portal_attempt($key, false);
            $errors[] = t('val.lookup_none');
            $domain   = null;
        } else {
            record_portal_attempt($key, true);
            $liveRecords   = domain_records((int)$domain['id']);
            $pendingChange = pending_change_for_domain((int)$domain['id']);

            if ($action === 'withdraw') {
                if ($pendingChange === null) {
                    $errors[] = t('change.nothing_pending');
                } else {
                    db_query('DELETE FROM change_requests WHERE id = ?', [(int)$pendingChange['id']]);
                    log_action(
                        'change_withdrawn',
                        'domain',
                        (int)$domain['id'],
                        (string)$domain['name'] . ': applicant withdrew change request '
                            . (int)$pendingChange['id']
                    );
                    flash(t('change.withdrawn'), 'ok');
                    $pendingChange = null;
                }
            }

            if ($action === 'propose') {
                $status     = (string)$domain['status'];
                // Record review is gone: an approved (active) name is edited directly, exactly like a
        // pending one. The queue below is kept only as a fallback for future use.
        $directEdit = in_array($status, ['pending', 'active'], true);      // nothing is published yet, so no review needed
                $mayEdit    = in_array($status, ['pending', 'active'], true);

                if (setting_bool('self_service', true) === false) {
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
                        // The request has not been reviewed yet: update the records at once, and keep
                        // an approved change row so the operator sees what was changed and when.
                        $pdo = db();
                        try {
                            $pdo->beginTransaction();
                            db_query('SELECT id FROM domains WHERE id = ? FOR UPDATE', [(int)$domain['id']]);
                            replace_records((int)$domain['id'], $candidate);
                            db_query(
                                "INSERT INTO change_requests (domain_id, status, snapshot, applicant_note,
                                    admin_note, submitted_at, submitted_ip, decided_at, decided_by)
                                 VALUES (?, 'approved', ?, ?,
                                    'Saved by the applicant while the request was still pending',
                                    NOW(), ?, NOW(), 'applicant')",
                                [
                                    (int)$domain['id'],
                                    change_snapshot_encode($candidate),
                                    $note === '' ? null : limit_chars($note, 500),
                                    client_ip(),
                                ]
                            );
                            $pdo->commit();
                            $liveRecords = domain_records((int)$domain['id']);

                            /* Record review is gone: push the change to the provider right away,
                             * the same way admin/decision.php used to do after approval. */
                            if ((string)$domain['status'] === 'active' && function_exists('publish_zone_ready')
                                && publish_zone_ready((string)$domain['zone'])) {
                                try {
                                    publish_sync_domain(
                                        (int)$domain['id'],
                                        (string)$domain['name'],
                                        (string)$domain['zone'],
                                        publish_records_of($liveRecords),
                                        !empty($domain['proxied'])
                                    );
                                } catch (Throwable $e) {
                                    log_action('publish_failed', 'domain', (int)$domain['id'],
                                        (string)$domain['name'] . ': ' . $e->getMessage());
                                }
                            }
                            log_action(
                                'records_updated',
                                'domain',
                                (int)$domain['id'],
                                (string)$domain['name'] . ': applicant updated the records of a pending '
                                    . 'request from ' . client_ip()
                            );
                            flash(t('change.updated_pending'), 'ok');
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
                            db_query('SELECT id FROM domains WHERE id = ? FOR UPDATE', [(int)$domain['id']]);
                            if ($pendingChange !== null) {
                                db_query('DELETE FROM change_requests WHERE id = ?', [(int)$pendingChange['id']]);
                            }
                            db_query(
                                "INSERT INTO change_requests (domain_id, status, snapshot, applicant_note,
                                    submitted_at, submitted_ip)
                                 VALUES (?, 'pending', ?, ?, NOW(), ?)",
                                [
                                    (int)$domain['id'],
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
                            $proposed      = $candidate;
                            log_action(
                                'change_submitted',
                                'domain',
                                (int)$domain['id'],
                                (string)$domain['name'] . ': change request ' . $changeId
                                    . ' from ' . client_ip()
                            );
                            if ($pendingChange !== null) {
                                notify_admin_new_change($domain, $pendingChange, $candidate);
                            }
                            $mode = 'submitted';
                        }
                    }
                }
            }
        }
    }
}

if ($mode !== 'submitted' && $domain !== null && $errors === []) {
    $mode = 'editor';
}

// A proposal that failed validation: show the submitted rows again so nothing is lost.
if ($mode === 'lookup' && $domain !== null && $errors !== [] && $action === 'propose') {
    $mode     = 'editor';
    $proposed = raw_records_from_post();
}

// A failed withdrawal: show the editor with the records that are on file.
if ($mode === 'lookup' && $domain !== null && $errors !== [] && $action === 'withdraw') {
    $mode = 'editor';
}

if ($mode === 'editor' && $pendingChange !== null && $proposed === null) {
    // Continue from the change that is already waiting, so nothing gets lost.
    $proposed = change_snapshot_decode((string)$pendingChange['snapshot']);
}

$editorRows = [];
if ($mode === 'editor') {
    // null = nothing submitted, [] = the applicant removed every row (the validator refuses that).
    $source = $proposed === null ? $liveRecords : $proposed;
    foreach ($source as $record) {
        $editorRows[] = [
            'sub'   => (string)($record['sub'] ?? ''),
            'type'  => strtoupper((string)($record['type'] ?? 'A')),
            'value' => (string)($record['value'] ?? ''),
        ];
    }
}
$blankRows = max(3, 10 - count($editorRows));

page_header(t('manage.heading'));
?>
<?php if ($manageNotice !== ''): ?>
<div class="msg info">
  <strong><?= h(t('manage.notice_heading')) ?></strong>
  <div><?= nl2br(h($manageNotice)) ?></div>
</div>
<?php endif; ?>
<p><?= h(t('manage.intro')) ?></p>

<?= error_list($errors) ?>

<?php if ($mode === 'submitted' && $domain !== null && $pendingChange !== null): ?>
<div class="msg ok"><strong><?= h(t('manage.submitted_title')) ?></strong></div>

<p>
<?= h(t('manage.name_label')) ?> <code><?= h((string)$domain['name']) ?></code> &middot;
<?= h(t('manage.code_label')) ?> <strong><?= h((string)$domain['request_code']) ?></strong> &middot;
<?= h(t('manage.submitted_label')) ?> <?= h((string)$pendingChange['submitted_at']) ?>
</p>

<p><?= h(t('manage.proposed')) ?></p>
<pre class="wrap code-block"><?= h(implode("\n", record_zone_lines(
    change_snapshot_decode((string)$pendingChange['snapshot']),
    (string)$domain['name']
))) ?></pre>

<?php $diff = records_diff($liveRecords, change_snapshot_decode((string)$pendingChange['snapshot'])); ?>
<h3><?= h(t('manage.what_changes')) ?></h3>
<table>
  <tr><th><?= h(t('manage.col_change')) ?></th><th><?= h(t('manage.col_records')) ?></th></tr>
  <tr>
    <td><?= h(t('manage.added')) ?></td>
    <td><?= $diff['added'] === []
        ? h(t('common.none'))
        : '<pre class="wrap">' . h(implode("\n", record_zone_lines($diff['added'], (string)$domain['name']))) . '</pre>' ?></td>
  </tr>
  <tr>
    <td><?= h(t('manage.removed')) ?></td>
    <td><?= $diff['removed'] === []
        ? h(t('common.none'))
        : '<pre class="wrap">' . h(implode("\n", record_zone_lines($diff['removed'], (string)$domain['name']))) . '</pre>' ?></td>
  </tr>
  <tr>
    <td><?= h(t('manage.kept')) ?></td>
    <td><?= h(t('manage.kept_n', count($diff['kept']))) ?></td>
  </tr>
</table>

<p><?= h(t('manage.stay_live')) ?></p>
<form method="post" action="<?= h(url('manage.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="code" value="<?= h($code) ?>">
  <input type="hidden" name="email" value="<?= h($email) ?>">
  <input type="hidden" name="action" value="withdraw">
  <p><input type="submit" value="<?= h(t('manage.withdraw')) ?>"></p>
</form>

<p>
<a href="<?= h(url('status.php')) ?>"><?= h(t('manage.check_status')) ?></a> &middot;
<a href="<?= h(url('manage.php')) ?>"><?= h(t('manage.manage_again')) ?></a>
</p>

<?php elseif ($mode === 'editor' && $domain !== null): ?>

<?php if ($pendingChange !== null): ?>
<div class="msg info">
  <?= t('manage.queued_notice', h((string)$pendingChange['submitted_at'])) ?>
  <form method="post" action="<?= h(url('manage.php')) ?>" style="margin-top:.5em">
    <?= csrf_field() ?>
    <input type="hidden" name="code" value="<?= h($code) ?>">
    <input type="hidden" name="email" value="<?= h($email) ?>">
    <input type="hidden" name="action" value="withdraw">
    <input type="submit" value="<?= h(t('manage.withdraw_queued')) ?>">
  </form>
</div>
<?php endif; ?>

<table>
  <tr><th><?= h(t('manage.name_label')) ?></th><td><code><?= h((string)$domain['name']) ?></code></td></tr>
  <tr><th><?= h(t('status.zone')) ?></th><td><?= h((string)$domain['zone']) ?></td></tr>
  <tr><th><?= h(t('status.status')) ?></th><td><?= h(status_text((string)$domain['status'])) ?></td></tr>
  <tr><th><?= h(t('manage.code_label')) ?></th><td><?= h((string)$domain['request_code']) ?></td></tr>
  <tr>
    <th><?= h((string)$domain['status'] === 'active' ? t('manage.records_live') : t('manage.records_on_file')) ?></th>
    <td><?= count($liveRecords) ?></td>
  </tr>
</table>

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

<form method="post" action="<?= h(url('manage.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="code" value="<?= h($code) ?>">
<input type="hidden" name="email" value="<?= h($email) ?>">
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
    <td><input type="text" name="row_ttl[<?= $index ?>]" value="<?= (int)($row['ttl'] ?? 0) ?>" maxlength="5" size="5"
               title="<?= h(t('rec.ttl_hint')) ?>"></td>
    <td><input type="checkbox" name="row_proxied[<?= $index ?>]" value="1"<?= empty($row['proxied']) ? '' : ' checked' ?>></td>
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

<p><a href="<?= h(url('status.php')) ?>"><?= h(t('manage.check_status')) ?></a></p>

<?php else: ?>

<form method="post" action="<?= h(url('manage.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="lookup">
<label for="code"><?= h(t('manage.code')) ?></label>
<input type="text" id="code" name="code" value="<?= h($code) ?>" maxlength="20" autocomplete="off" required>
<p class="hint"><?= t('manage.code_hint') ?></p>

<label for="email"><?= h(t('manage.email')) ?></label>
<input type="text" id="email" name="email" value="<?= h($email) ?>" maxlength="190" required>

<p><input type="submit" value="<?= h(t('manage.open')) ?>"></p>
</form>

<h3><?= h(t('manage.can_do')) ?></h3>
<ul>
  <li><?= t('manage.can_1') ?></li>
  <li><?= t('manage.can_2') ?></li>
  <li><?= t('manage.can_3') ?></li>
  <li><?= t('manage.can_4') ?></li>
  <li><?= t('manage.can_5') ?></li>
  <li><?= t('manage.can_6') ?></li>
</ul>
<p class="hint"><?= t('manage.hint_review')
    . (setting_bool('self_service', true) ? '' : t('manage.hint_disabled')) ?></p>
<?php endif; ?>
<?php
page_footer();

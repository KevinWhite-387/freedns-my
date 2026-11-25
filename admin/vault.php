<?php
declare(strict_types=1);

/**
 * freedns.my - back office: the mail vault.
 *
 * Everything that was archived by the mail pipeline (mail-cron.php) is looked at here. The list itself is
 * only metadata: opening a message needs the master password, and a message that carries its own password
 * needs both. How long a message stays readable, downloadable or present at all follows the settings at the
 * bottom (90 / 180 days by default).
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('The mail vault');
$errors = [];
$notice = '';
$opened = null;

$ready = mailvault_table_ready();

/* ------------------------------------------------------------------ actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);
    $entry  = $id > 0 ? mailvault_entry($id) : null;
    $master = (string)($_POST['master'] ?? '');

    if ($action === 'settings') {
        $changed = 0;
        $values = [
            'mail_imap_host'   => trim((string)($_POST['mail_imap_host'] ?? '')),
            'mail_imap_port'   => (string)max(1, min(65535, (int)($_POST['mail_imap_port'] ?? 993))),
            'mail_imap_user'   => trim((string)($_POST['mail_imap_user'] ?? '')),
            'mail_imap_folder' => trim((string)($_POST['mail_imap_folder'] ?? 'INBOX')),
            'mail_scan_mode'   => in_array($_POST['mail_scan_mode'] ?? 'rules', ['rules', 'off'], true)
                ? (string)$_POST['mail_scan_mode'] : 'rules',
            'mail_vault_view_days'     => (string)max(1, (int)($_POST['mail_vault_view_days'] ?? 90)),
            'mail_vault_download_days' => (string)max(1, (int)($_POST['mail_vault_download_days'] ?? 180)),
            'mail_vault_postmaster'    => trim((string)($_POST['mail_vault_postmaster'] ?? '')),
            'mail_vault_notify'        => trim((string)($_POST['mail_vault_notify'] ?? '')),
        ];
        foreach ($values as $key => $value) {
            if ((string)setting($key, '') !== $value && $value !== '') {
                set_setting($key, $value);
                $changed++;
            }
        }
        // Passwords are only touched when something was typed, so an empty box never wipes a working setup.
        if (trim((string)($_POST['mail_imap_pass'] ?? '')) !== '') {
            set_setting('mail_imap_pass', (string)$_POST['mail_imap_pass']);
            $changed++;
        }
        foreach (['mail_imap_insecure', 'mail_imap_local_fallback', 'mail_pipeline'] as $toggle) {
            $want = isset($_POST[$toggle]) ? '1' : '0';
            if ((string)setting($toggle, '') !== $want) {
                set_setting($toggle, $want);
                $changed++;
            }
        }
        log_action('vault_settings', 'settings', null, $changed . ' value(s) changed');
        flash($changed === 0 ? 'Nothing changed.' : 'Saved (' . $changed . ' value(s)).', 'ok');
        redirect('vault.php');
    }

    if ($action === 'test') {
        $test = mailimap_self_test();
        if ($test['ok']) {
            $notice = 'The mailbox answered at ' . $test['host'] . ', ' . $test['count'] . ' unseen message(s).';
        } else {
            $errors[] = 'The mailbox could not be reached: ' . $test['error'];
        }
    }

    if ($action === 'open' && $entry !== null) {
        $result = mailvault_open($entry, $master, (string)($_POST['entry_pass'] ?? ''),
            (string)$admin['username']);
        if ($result['ok']) {
            $opened  = ['id' => $id, 'body' => $result['body']];
        } else {
            $errors[] = $result['error'];
        }
    }

    if ($action === 'download' && $entry !== null) {
        // Allowed in the middle stage: no longer readable on screen, still recoverable as a file.
        if (!mailvault_can_download($entry)) {
            $errors[] = 'That message passed the last stage and is only waiting to be deleted.';
        } else {
            $result = mailvault_open($entry, $master, (string)($_POST['entry_pass'] ?? ''),
                (string)$admin['username']);
            if (!$result['ok']) {
                $errors[] = $result['error'];
            } else {
                header('Content-Type: text/plain; charset=utf-8');
                header('Content-Disposition: attachment; filename="mail-' . $id . '.txt"');
                echo mailvault_export($entry, $result['body']);
                exit;
            }
        }
    }

    if ($action === 'entry_pass' && $entry !== null) {
        if (!mailvault_master_ok($master)) {
            $errors[] = 'The master password is not right.';
        } else {
            $result = mailvault_set_entry_password($entry, (string)($_POST['new_pass'] ?? ''),
                trim((string)($_POST['hint'] ?? '')), (string)$admin['username']);
            if ($result['ok']) {
                flash((string)($_POST['new_pass'] ?? '') === ''
                    ? 'That message no longer has its own password.'
                    : 'That message now has its own password.', 'ok');
                redirect('vault.php');
            }
            $errors[] = $result['error'];
        }
    }

    if ($action === 'review' && $entry !== null) {
        $state = (string)($_POST['state'] ?? '');
        if (mailvault_review($entry, $state, trim((string)($_POST['note'] ?? '')), (string)$admin['username'])) {
            flash('The message is marked "' . $state . '".', 'ok');
            redirect('vault.php');
        }
        $errors[] = 'That state is not known.';
    }

    if ($action === 'reset_request') {
        $result = mailvault_reset_request($id > 0 ? 'entry' : 'master', $id, (string)$admin['username']);
        if ($result['ok']) {
            $notice = 'A reset link was mailed to ' . mailvault_postmaster() . '; '
                . mailvault_notify_address() . ' was told that it happened, without the link.';
        } else {
            $errors[] = $result['error'];
        }
    }

    if ($action === 'prune') {
        $pruned = mailvault_prune(500);
        flash((int)$pruned['deleted'] . ' message(s) older than ' . (int)$pruned['days'] . ' day(s) deleted.', 'ok');
        redirect('vault.php');
    }
}

/* ------------------------------------------------------------------ the list */
$filter = strtolower((string)($_GET['review'] ?? ''));
$rows   = $ready ? mailvault_list(150, $filter) : [];
$counts = ['none' => 0, 'queued' => 0, 'released' => 0, 'blocked' => 0, 'dangerous' => 0];
if ($ready) {
    foreach (db_all('SELECT review_state, COUNT(*) AS n FROM mail_archive GROUP BY review_state') as $row) {
        $counts[(string)$row['review_state']] = (int)$row['n'];
    }
    $counts['dangerous'] = (int)db_value("SELECT COUNT(*) FROM mail_archive WHERE scan_state = 'dangerous'", [], 0);
}

page_header('Mail vault');
?>
<p>
Messages collected from the vault mailbox. The list shows the envelope only; the body is opened with the
<strong>master password</strong>, and a message with its own password needs both. Kept for
<?= (int)mailvault_view_days() ?> day(s) readable, <?= (int)mailvault_download_days() ?> day(s) in total.
</p>

<?php if (!$ready): ?>
<div class="msg error">The vault tables are missing; import <code>upgrade-mailvault.sql</code>
(the <a href="<?= h(url('selftest.php')) ?>">self test</a> can apply it for you).</div>
<?php else: ?>

<?= error_list($errors) ?>
<?php if ($notice !== ''): ?><div class="msg ok"><?= h($notice) ?></div><?php endif; ?>
<?php if (!mailvault_master_ready()): ?>
<div class="msg error">No master password is set yet, so nothing can be opened.
<a href="vault-reset.php">Set it on the password page.</a></div>
<?php endif; ?>

<?php if ($opened !== null): ?>
<h2>Message #<?= (int)$opened['id'] ?></h2>
<pre style="white-space:pre-wrap;max-height:32em;overflow:auto;border:1px solid #ccc;padding:.6em"><?= h($opened['body']) ?></pre>
<?php endif; ?>

<h2>Messages (<?= count($rows) ?>)</h2>
<p class="small">
<a href="vault.php">all</a> &middot;
<a href="vault.php?review=queued">held for a human (<?= (int)$counts['queued'] ?>)</a> &middot;
<a href="vault.php?review=released">released (<?= (int)$counts['released'] ?>)</a> &middot;
<a href="vault.php?review=blocked">blocked (<?= (int)$counts['blocked'] ?>)</a> &middot;
held as dangerous now: <strong><?= (int)$counts['dangerous'] ?></strong>
</p>
<?php if ($rows === []): ?>
<p>Nothing archived yet. The pipeline is <a href="#settings">below</a>; run
<code>mail-cron.php?key=…</code> to collect what is in the mailbox.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>When</th><th>From</th><th>To</th><th>Subject</th><th>Check</th><th>State</th><th>Pass</th></tr>
  <?php foreach ($rows as $row): ?>
  <?php $stage = mailvault_stage($row); ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td class="small"><?= h((string)$row['created_at']) ?><br>
        <span class="hint"><?= (int)mailvault_age_days($row) ?>d / <?= h($stage) ?></span></td>
    <td class="small"><?= h(shorten((string)($row['mail_from'] ?? ''), 28)) ?></td>
    <td class="small"><?= h(shorten((string)($row['mail_to'] ?? ''), 28)) ?></td>
    <td class="small"><?= h(shorten((string)($row['subject'] ?? ''), 40)) ?></td>
    <td><?= (string)$row['scan_state'] === 'dangerous'
        ? '<span class="err">dangerous</span>' : h((string)$row['scan_state']) ?></td>
    <td><?= h((string)$row['review_state']) ?></td>
    <td><?= trim((string)($row['pass_hash'] ?? '')) === '' ? '<span class="hint">-</span>' : 'own' ?></td>
  </tr>
  <tr>
    <td colspan="8">
      <?php if ($stage === 'viewable' || $stage === 'download-only'): ?>
      <form class="inline" method="post" action="vault.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="password" name="master" placeholder="master password" autocomplete="off">
        <input type="password" name="entry_pass" placeholder="its own password (if any)" autocomplete="off">
        <?php if ($stage === 'viewable'): ?>
        <input type="submit" name="action" value="open">
        <?php endif; ?>
        <input type="submit" name="action" value="download">
        <span class="hint"><?= $stage === 'download-only'
            ? 'no longer readable on screen; download it if you still need it' : '' ?></span>
      </form>
      <?php else: ?>
      <span class="hint">past the last stage - waiting to be deleted</span>
      <?php endif; ?>
    </td>
  </tr>
  <tr>
    <td colspan="8">
      <form class="inline" method="post" action="vault.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="action" value="review">
        <select name="state">
          <option value="none">no mark</option>
          <option value="released">released (may be forwarded)</option>
          <option value="blocked">blocked (never forward)</option>
        </select>
        <input type="text" name="note" maxlength="190" placeholder="note">
        <input type="submit" value="Mark">
      </form>
      <form class="inline" method="post" action="vault.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="action" value="entry_pass">
        <input type="password" name="master" placeholder="master password" autocomplete="off">
        <input type="password" name="new_pass" placeholder="its own password (empty = remove)" autocomplete="off">
        <input type="text" name="hint" maxlength="64" placeholder="hint">
        <input type="submit" value="Set password">
      </form>
      <?php if (trim((string)($row['pass_hash'] ?? '')) !== ''): ?>
      <form class="inline" method="post" action="vault.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="action" value="reset_request">
        <input type="submit" value="I lost its password">
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2 id="settings">Settings</h2>
<form method="post" action="vault.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="settings">
<p>
  <input type="checkbox" id="mail_pipeline" name="mail_pipeline" value="1"<?= setting_bool('mail_pipeline', true) ? ' checked' : '' ?>>
  <label class="inline" for="mail_pipeline">Collect and forward mail (the pipeline)</label>
</p>
<label for="mail_imap_host">Mailbox host (the public name is tried first)</label>
<input type="text" id="mail_imap_host" name="mail_imap_host" maxlength="190"
       value="<?= h((string)setting('mail_imap_host', 'mail.mx.freedns.my')) ?>">
<label for="mail_imap_port">Port</label>
<input type="text" id="mail_imap_port" name="mail_imap_port" maxlength="5"
       value="<?= h((string)setting('mail_imap_port', '993')) ?>">
<label for="mail_imap_user">Account</label>
<input type="text" id="mail_imap_user" name="mail_imap_user" maxlength="190"
       value="<?= h((string)setting('mail_imap_user', 'vault@mx.freedns.my')) ?>">
<label for="mail_imap_pass">Password (leave empty to keep the one in place)</label>
<input type="password" id="mail_imap_pass" name="mail_imap_pass" autocomplete="new-password">
<label for="mail_imap_folder">Folder</label>
<input type="text" id="mail_imap_folder" name="mail_imap_folder" maxlength="64"
       value="<?= h((string)setting('mail_imap_folder', 'INBOX')) ?>">
<p>
  <input type="checkbox" id="mail_imap_local_fallback" name="mail_imap_local_fallback" value="1"<?= setting_bool('mail_imap_local_fallback', true) ? ' checked' : '' ?>>
  <label class="inline" for="mail_imap_local_fallback">Fall back to the machine itself (127.0.0.1) when the public name fails</label>
</p>
<p>
  <input type="checkbox" id="mail_imap_insecure" name="mail_imap_insecure" value="1"<?= setting_bool('mail_imap_insecure', true) ? ' checked' : '' ?>>
  <label class="inline" for="mail_imap_insecure">Accept the certificate without checking it (needed while the mail certificate is self-signed)</label>
</p>
<label for="mail_scan_mode">Content check</label>
<select id="mail_scan_mode" name="mail_scan_mode">
  <option value="rules"<?= (string)setting('mail_scan_mode', 'rules') === 'rules' ? ' selected' : '' ?>>rules (words and patterns, nothing leaves the server)</option>
  <option value="off"<?= (string)setting('mail_scan_mode', 'rules') === 'off' ? ' selected' : '' ?>>off (forward everything)</option>
</select>
<label for="mail_vault_view_days">Readable for (days)</label>
<input type="text" id="mail_vault_view_days" name="mail_vault_view_days" maxlength="5"
       value="<?= h((string)setting('mail_vault_view_days', '90')) ?>">
<label for="mail_vault_download_days">Kept for (days, then deleted)</label>
<input type="text" id="mail_vault_download_days" name="mail_vault_download_days" maxlength="5"
       value="<?= h((string)setting('mail_vault_download_days', '180')) ?>">
<label for="mail_vault_postmaster">Reset links go to</label>
<input type="text" id="mail_vault_postmaster" name="mail_vault_postmaster" maxlength="190"
       value="<?= h(mailvault_postmaster()) ?>">
<label for="mail_vault_notify">The second address is only told that a reset happened</label>
<input type="text" id="mail_vault_notify" name="mail_vault_notify" maxlength="190"
       value="<?= h(mailvault_notify_address()) ?>">
<p><input type="submit" value="Save"></p>
</form>

<form method="post" action="vault.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="test">
  <p><input type="submit" value="Test the mailbox"></p>
</form>
<form method="post" action="vault.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="reset_request">
  <p><input type="submit" value="I lost the master password (mails a reset link)"></p>
</form>
<form method="post" action="vault.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="prune">
  <p><input type="submit" value="Delete what passed the last stage"></p>
</form>
<?php endif; ?>
<?php
page_footer();

<?php
declare(strict_types=1);

/**
 * freedns.my - public request status lookup.
 */

require __DIR__ . '/inc/bootstrap.php';

// The form carries a CSRF token, so the session must exist before the first byte of output.
fd_start_session();

$errors        = [];
$found         = null;
$records       = [];
$pendingChange = null;
$code          = '';
$email         = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $code  = strtoupper(trim((string)($_POST['code'] ?? '')));
    $email = trim((string)($_POST['email'] ?? ''));

    if ($code === '' || $email === '') {
        $errors[] = t('val.lookup_missing');
    } elseif (!valid_email_addr($email)) {
        $errors[] = t('val.email_invalid');
    } elseif (portal_throttled(portal_key($code, $email))) {
        $errors[] = t('val.throttled');
    } else {
        $key   = portal_key($code, $email);
        $found = db_one(
            'SELECT * FROM domains WHERE request_code = ? AND registrant_email = ?',
            [$code, $email]
        );
        if ($found === null) {
            record_portal_attempt($key, false);
            $errors[] = t('val.lookup_none');
        } else {
            record_portal_attempt($key, true);
            $records       = domain_records((int)$found['id']);
            $pendingChange = pending_change_for_domain((int)$found['id']);
        }
    }
}

page_header(t('status.heading'));
?>
<p><?= h(t('status.intro')) ?></p>

<?= error_list($errors) ?>

<form method="post" action="<?= h(url('status.php')) ?>">
<?= csrf_field() ?>
<label for="code"><?= h(t('status.code')) ?></label>
<input type="text" id="code" name="code" value="<?= h($code) ?>" maxlength="20" autocomplete="off" required>
<p class="hint"><?= t('status.code_hint') ?></p>

<label for="email"><?= h(t('status.email')) ?></label>
<input type="text" id="email" name="email" value="<?= h($email) ?>" maxlength="190" required>

<p><input type="submit" value="<?= h(t('status.show')) ?>"></p>
</form>

<?php if ($found !== null): ?>
<hr>
<h3><?= h((string)$found['name']) ?></h3>
<table>
  <tr><th><?= h(t('status.status')) ?></th><td><strong><?= h(status_text((string)$found['status'])) ?></strong></td></tr>
  <tr><th><?= h(t('status.code')) ?></th><td><?= h((string)$found['request_code']) ?></td></tr>
  <tr><th><?= h(t('request.done_domain')) ?></th><td><?= h((string)$found['name']) ?></td></tr>
  <tr><th><?= h(t('status.zone')) ?></th><td><?= h((string)$found['zone']) ?></td></tr>
  <tr><th><?= h(t('status.applicant')) ?></th><td><?= h((string)$found['registrant_name']) ?></td></tr>
  <?php if (trim((string)($found['organization'] ?? '')) !== ''): ?>
  <tr><th><?= h(t('status.org')) ?></th><td><?= h((string)$found['organization']) ?></td></tr>
  <?php endif; ?>
  <tr><th><?= h(t('status.submitted')) ?></th><td><?= h(format_date((string)$found['created_at'])) ?></td></tr>
  <tr><th><?= h(t('status.updated')) ?></th><td><?= h(format_date((string)$found['updated_at'])) ?></td></tr>
  <?php if (trim((string)($found['decided_at'] ?? '')) !== ''): ?>
  <tr><th><?= h(t('status.decided')) ?></th><td><?= h(format_date((string)$found['decided_at'])) ?></td></tr>
  <?php endif; ?>
  <tr><th><?= h(t('status.records')) ?></th><td><?= count($records) ?></td></tr>
</table>

<p><?= h((string)$found['status'] === 'active' ? t('status.published') : t('status.kept')) ?></p>
<pre class="wrap code-block"><?= h($records === []
    ? t('status.no_record')
    : implode("\n", record_zone_lines($records, (string)$found['name']))) ?></pre>

<?php if ($pendingChange !== null): ?>
<h3><?= h(t('status.change_title')) ?></h3>
<p><?= t('status.change_text', h((string)$pendingChange['submitted_at'])) ?></p>
<p><?= h(t('status.proposed')) ?></p>
<pre class="wrap code-block"><?= h(implode("\n", record_zone_lines(
    change_snapshot_decode((string)$pendingChange['snapshot']),
    (string)$found['name']
))) ?></pre>
<?php endif; ?>

<?php $note = trim((string)($found['public_note'] ?? '')); ?>
<?php if ($note !== ''): ?>
<h3><?= h(t('status.note')) ?></h3>
<?= text_block($note) ?>
<?php endif; ?>

<?php if ((string)$found['status'] === 'active'): ?>
<p><?= t(
    'status.active_text',
    h((string)$found['zone']),
    '<a href="' . h(url('manage.php')) . '">' . h(t('nav.manage')) . '</a>'
) ?></p>
<?php elseif ((string)$found['status'] === 'pending'): ?>
<p><?= h(t('status.pending_text')) ?></p>
<?php elseif ((string)$found['status'] === 'rejected'): ?>
<p><?= t('status.rejected_text', '<a href="' . h(url('doc.php')) . '">' . h(t('index.docs_link')) . '</a>') ?></p>
<?php endif; ?>
<?php endif; ?>
<?php
page_footer();

<?php
declare(strict_types=1);

/**
 * freedns.my - console: share a name with another account (READ-ONLY), step 1 of domain sharing.
 *
 * The holder invites an address; the account behind that address sees the name and its records in its own
 * console, and can look at them, but nothing in this feature may change a record: no write path consults
 * the table, so a mistake here cannot hand out rights over somebody else's name. Writing stays with the
 * holder (and with the operator), as before.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user   = require_user();
$errors = [];

$ready = false;
try {
    $ready = db_one("SHOW TABLES LIKE 'domain\\_access'") !== null;
} catch (Throwable $e) {
    $ready = false;
}

/** Names somebody shared with this account (read-only). */
$sharedWithMe = static function (int $userId) use ($ready): array {
    if (!$ready) {
        return [];
    }

    return db_all(
        'SELECT d.*, a.invited_by, a.created_at AS shared_at
         FROM domain_access a JOIN domains d ON d.id = a.domain_id
         WHERE a.user_id = ? ORDER BY d.name ASC LIMIT 100',
        [$userId]
    );
};

/** Names of this account that were shared with somebody. */
$sharedByMe = static function (string $email) use ($ready): array {
    if (!$ready) {
        return [];
    }

    return db_all(
        'SELECT a.*, d.name, d.zone, u.username, u.email AS account_email
         FROM domain_access a JOIN domains d ON d.id = a.domain_id
         LEFT JOIN users u ON u.id = a.user_id
         WHERE LOWER(d.registrant_email) = LOWER(?) ORDER BY d.name ASC LIMIT 200',
        [$email]
    );
};

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');
    $domain = null;
    foreach (user_domains($user) as $candidate) {
        if ((int)$candidate['id'] === (int)($_POST['domain_id'] ?? 0)) {
            $domain = $candidate;
        }
    }

    if ($domain === null) {
        $errors[] = t('share.not_mine');
    } elseif ($action === 'invite') {
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        if (!valid_email_addr($email)) {
            $errors[] = t('val.email_invalid');
        } elseif ($email === strtolower((string)$user['email'])) {
            $errors[] = t('share.self');
        } else {
            $guest = db_one('SELECT * FROM users WHERE LOWER(email) = ? LIMIT 1', [$email]);
            if ($guest === null) {
                $errors[] = t('share.no_account', $email);
            } elseif ((int)$guest['id'] !== 0
                && db_one('SELECT id FROM domain_access WHERE domain_id = ? AND user_id = ?',
                    [(int)$domain['id'], (int)$guest['id']]) !== null) {
                $errors[] = t('share.already', (string)$guest['username']);
            } else {
                db_query(
                    'INSERT INTO domain_access (domain_id, user_id, invited_by, created_at) VALUES (?, ?, ?, NOW())',
                    [(int)$domain['id'], (int)$guest['id'], (string)$user['email']]
                );
                log_action('share_added', 'domain', (int)$domain['id'],
                    (string)$domain['name'] . ' shared read-only with ' . $email);

                $previous = lang_switch('en');
                send_mail($email, t('mail.share_subject', (string)$domain['name']), implode("\n", [
                    t('mail.dear', $email), '',
                    t('mail.share_body', (string)$domain['name'], (string)$user['email']),
                    '', url('account/shared.php'), '',
                    t('mail.share_footer'), '', (string)setting('site_title', 'freedns.my'),
                ]));
                lang_switch($previous);

                flash(t('share.added'), 'ok');
                redirect(url('account/shared.php'));
            }
        }
    } elseif ($action === 'revoke') {
        $id = (int)($_POST['access_id'] ?? 0);
        $row = db_one(
            'SELECT a.id FROM domain_access a WHERE a.id = ? AND a.domain_id = ?',
            [$id, (int)$domain['id']]
        );
        if ($row === null) {
            $errors[] = t('share.not_mine');
        } else {
            db_query('DELETE FROM domain_access WHERE id = ?', [$id]);
            log_action('share_removed', 'domain', (int)$domain['id'],
                'read-only access to ' . (string)$domain['name'] . ' revoked');
            flash(t('share.revoked'), 'ok');
            redirect(url('account/shared.php'));
        }
    }
}

$mine   = $sharedWithMe((int)$user['id']);
$granted = $sharedByMe((string)$user['email']);
$lookId = (int)($_GET['look'] ?? 0);
$look   = null;
foreach ($mine as $candidate) {
    if ((int)$candidate['id'] === $lookId) {
        $look = $candidate;
    }
}

page_header(t('share.heading'));
?>
<p><?= h(t('share.intro')) ?></p>
<?= error_list($errors) ?>

<?php if (!$ready): ?>
<div class="msg error"><?= h(t('share.no_table')) ?></div>
<?php else: ?>

<h2><?= h(t('share.with_me')) ?></h2>
<?php if ($mine === []): ?>
<p><?= h(t('share.with_me_none')) ?></p>
<?php else: ?>
<table>
  <tr><th><?= h(t('share.name')) ?></th><th><?= h(t('share.zone')) ?></th>
      <th><?= h(t('share.state')) ?></th><th><?= h(t('share.by')) ?></th><th></th></tr>
  <?php foreach ($mine as $row): ?>
  <tr>
    <td><code><?= h((string)$row['name']) ?></code></td>
    <td><?= h((string)$row['zone']) ?></td>
    <td><?= h(status_text((string)$row['status'])) ?></td>
    <td class="small"><?= h((string)($row['invited_by'] ?? '')) ?><br>
        <span class="small"><?= h((string)$row['shared_at']) ?></span></td>
    <td class="actions"><a href="<?= h(url('account/shared.php?look=' . (int)$row['id'])) ?>">
        <?= h(t('share.look')) ?></a></td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint"><?= h(t('share.readonly_note')) ?></p>
<?php endif; ?>

<?php if ($look !== null): ?>
<h3><?= h((string)$look['name']) ?></h3>
<table>
  <tr><th><?= h(t('share.rec_name')) ?></th><th><?= h(t('share.rec_type')) ?></th>
      <th><?= h(t('share.rec_value')) ?></th><th>TTL</th></tr>
  <?php foreach (domain_records((int)$look['id']) as $record): ?>
  <tr>
    <td><code><?= h(record_sub_display(normalize_record_sub((string)($record['name'] ?? ''),
        (string)$look['name']))) ?></code></td>
    <td><?= h(strtoupper((string)($record['type'] ?? ''))) ?></td>
    <td class="small"><?= h((string)($record['value'] ?? '')) ?></td>
    <td><?= (int)($record['ttl'] ?? 0) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint"><?= h(t('share.readonly_note')) ?></p>
<?php endif; ?>

<h2><?= h(t('share.by_me')) ?></h2>
<p class="hint"><?= h(t('share.by_me_hint')) ?></p>
<?php if ($granted === []): ?>
<p><?= h(t('share.by_me_none')) ?></p>
<?php else: ?>
<table>
  <tr><th><?= h(t('share.name')) ?></th><th><?= h(t('share.account')) ?></th>
      <th><?= h(t('share.when')) ?></th><th></th></tr>
  <?php foreach ($granted as $row): ?>
  <tr>
    <td><code><?= h((string)$row['name']) ?></code></td>
    <td><?= h((string)($row['username'] ?? $row['account_email'] ?? '')) ?><br>
        <span class="small"><?= h((string)($row['account_email'] ?? '')) ?></span></td>
    <td class="small"><?= h((string)$row['created_at']) ?></td>
    <td class="actions">
      <form class="inline" method="post" action="<?= h(url('account/shared.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="revoke">
        <input type="hidden" name="access_id" value="<?= (int)$row['id'] ?>">
        <input type="hidden" name="domain_id" value="<?= (int)$row['domain_id'] ?>">
        <input type="submit" value="<?= h(t('share.revoke')) ?>">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php $canShare = user_domains($user); ?>
<?php if ($canShare !== []): ?>
<h3><?= h(t('share.invite')) ?></h3>
<form method="post" action="<?= h(url('account/shared.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="invite">
<label for="domain_id"><?= h(t('share.name')) ?></label>
<select id="domain_id" name="domain_id">
  <?php foreach ($canShare as $candidate): ?>
  <option value="<?= (int)$candidate['id'] ?>"><?= h((string)$candidate['name']) ?></option>
  <?php endforeach; ?>
</select>
<label for="email"><?= h(t('share.invite_email')) ?></label>
<input type="text" id="email" name="email" maxlength="190" value="" placeholder="friend@example.com">
<p class="hint"><?= h(t('share.invite_hint')) ?></p>
<p><input type="submit" value="<?= h(t('share.invite_submit')) ?>"></p>
</form>
<?php endif; ?>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

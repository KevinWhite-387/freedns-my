<?php
declare(strict_types=1);

/**
 * freedns.my - back office: moving holders off the old forwarding and onto the mailbox service.
 *
 * The old Cloudflare forwarding (mail_routes) only forwards; the new BillionMail service sends as well. This
 * page is the migration: it lists what the old addresses are, can hand that list over as a file, and can turn
 * one of them into a mailbox of the same address for the same holder - after which the holder has both (the
 * forwarding still works) until the old page is switched to read-only and then closed.
 *
 * Nothing here deletes anything: the old rows are kept as a record.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('Mailboxes');
$errors = [];
$notice = '';

$boxesReady = false;
try {
    $boxesReady = db_one("SHOW TABLES LIKE 'mail\\_boxes'") !== null;
} catch (Throwable $e) {
    $boxesReady = false;
}

/** The legacy rows, with the holder and the name they belong to. */
function legacy_rows(): array
{
    if (!mailroute_table_ready()) {
        return [];
    }

    return db_all(
        'SELECT r.*, d.name AS domain_name, d.zone, d.registrant_email, u.username, u.id AS owner_id
         FROM mail_routes r
         JOIN domains d ON d.id = r.domain_id
         LEFT JOIN users u ON LOWER(u.email) = LOWER(d.registrant_email)
         ORDER BY d.name ASC, r.local_part ASC LIMIT 1000'
    );
}

/* ------------------------------------------------------------------ actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'move' && $boxesReady) {
        $route = db_one('SELECT r.*, d.name AS domain_name, d.status AS domain_status,
                                d.registrant_email FROM mail_routes r JOIN domains d ON d.id = r.domain_id
                         WHERE r.id = ?', [(int)($_POST['id'] ?? 0)]);
        if ($route === null) {
            $errors[] = 'That legacy address is gone.';
        } else {
            $owner = db_one('SELECT id, username, email FROM users WHERE LOWER(email) = LOWER(?)',
                [(string)$route['registrant_email']]);
            $local = strtolower((string)$route['local_part']);
            $name  = strtolower(trim((string)($owner['username'] ?? '')));

            /*
             * The new address cannot be the old one: mailboxes always end in <username>.freedns.my, and the old
             * rules point at registered names. So the prefix is carried over (info stays info) and the suffix
             * becomes the holder's own name - which is what the console offers him afterwards.
             */
            $address = $name !== '' ? $local . '@' . $name . '.freedns.my' : '';

            if ($owner === null || $name === '') {
                $errors[] = 'No account (with a user name) matches ' . (string)$route['registrant_email']
                    . ', so there is nobody to give the mailbox to.';
            } elseif (in_array($local, reserved_labels(), true)) {
                $errors[] = $local . ' is a reserved name; the holder has to pick another prefix.';
            } elseif (db_one('SELECT id FROM mail_boxes WHERE address = ?', [$address]) !== null) {
                $notice = $address . ' is already a mailbox; nothing to do.';
            } else {
                $sizeMb = max(64, (int)setting('email_default_size_mb', '1024'));

                // The registry's own record first, so the password can be stored against a known id.
                $password = '';
                $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                for ($i = 0; $i < 14; $i++) {
                    $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $password .= '!7';

                $answer = bm_mailbox_create($address, $password, $sizeMb);
                if (!$answer['ok']) {
                    $errors[] = 'BillionMail refused ' . $address . ': ' . $answer['error'] . "\n" . $answer['raw'];
                } else {
                    db_query(
                        "INSERT INTO mail_boxes (user_id, domain_id, local_part, address, size_mb, status,
                            note, created_by, created_at, updated_at)
                         VALUES (?, NULL, ?, ?, ?, 'active', ?, ?, NOW(), NOW())",
                        [(int)$owner['id'], $local, $address, $sizeMb,
                         'migrated from the old forwarding (was ' . strtolower((string)$route['domain_name']) . ')',
                         (string)$admin['username']]
                    );
                    $id     = (int)db()->lastInsertId();
                    $cipher = rainyun_crypto_ready() ? rainyun_encrypt($password, 'mailbox:' . $id) : null;
                    db_query('UPDATE mail_boxes SET pass_cipher = ?, pass_set_at = NOW(), remote_state = ?
                              WHERE id = ?', [$cipher, 'created (migration)', $id]);
                    db_query('UPDATE mail_routes SET note = ? WHERE id = ?',
                        ['migrated to a mailbox on ' . date('Y-m-d'), (int)$route['id']]);
                    log_action('mailbox_migrated', 'mail', $id, $address . ' created from the old forwarding');

                    $to = (string)$route['registrant_email'];
                    if ($to !== '' && mail_pref_allows($to, 'account')) {
                        $previous = lang_switch('en');
                        send_mail($to, 'Your mailbox is ready (migrated)', implode("\n", [
                            t('mail.dear', (string)($owner['username'] ?? $to)), '',
                            'The old forwarding address below is now also a real mailbox, so you can send as '
                            . 'well as receive:',
                            '  ' . $address, '  size: ' . $sizeMb . ' MB',
                            '',
                            'The password is not mailed. Open your console, press "show the password", and a '
                            . 'code is sent to this address first.',
                            '',
                            rtrim(app_base_url(), '/') . '/account/email.php',
                        ]));
                        lang_switch($previous);
                    }
                    flash('Created ' . $address . ' for ' . (string)($owner['username'] ?? $owner['id']) . '.', 'ok');
                    redirect('legacy-mail.php');
                }
            }
        }
    }

    if ($action === 'export') {
        $rows = legacy_rows();
        header('Content-Type: text/plain; charset=utf-8');
        header('Content-Disposition: attachment; filename="legacy-forwarding.txt"');
        echo "Old forwarding addresses (Cloudflare routing, forwarding only)\n";
        echo 'taken ' . date('Y-m-d H:i') . "\n\n";
        echo "address\ttarget\tholder\tname\n";
        foreach ($rows as $row) {
            echo strtolower((string)$row['local_part']) . '@' . strtolower((string)$row['domain_name']) . "\t"
                . (string)$row['forward_to'] . "\t"
                . (string)($row['registrant_email'] ?? '') . "\t"
                . (string)$row['domain_name'] . "\n";
        }
        exit;
    }
}

/* ------------------------------------------------------------------ the page */
$rows  = legacy_rows();
$moved = 0;
foreach ($rows as $row) {
    if (trim((string)($row['note'] ?? '')) !== '' && str_contains(strtolower((string)$row['note']), 'migrat')) {
        $moved++;
    }
}

page_header('Legacy mail migration');
?>
<p>
The old page (Cloudflare routing) only forwards. This page is how holders are moved across: each legacy
address can be turned into a <strong>mailbox of the same address</strong>, owned by the same account, so the
holder keeps receiving and can start sending. The old rule stays in place and keeps working, and the old rows
are never deleted - they remain as a record. Nothing is created unless you press the button.
</p>
<?= error_list($errors) ?>
<?php if ($notice !== ''): ?><div class="msg ok"><?= h($notice) ?></div><?php endif; ?>
<?php if (!$boxesReady): ?>
<div class="msg error">The mailbox tables are missing; import <code>upgrade-email.sql</code> first.</div>
<?php endif; ?>

<h2>Legacy addresses (<?= count($rows) ?><?= $moved > 0 ? ', ' . $moved . ' already moved' : '' ?>)</h2>
<?php if ($rows === []): ?>
<p>No legacy forwarding addresses.</p>
<?php else: ?>
<table>
  <tr><th>Address</th><th>Forwards to</th><th>Holder</th><th>State</th><th></th></tr>
  <?php foreach ($rows as $row): ?>
  <?php
  $address = strtolower((string)$row['local_part']) . '@' . strtolower((string)$row['domain_name']);
  $target  = trim((string)($row['username'] ?? '')) === ''
      ? ''
      : strtolower((string)$row['local_part']) . '@' . strtolower((string)$row['username']) . '.freedns.my';
  $done    = trim((string)($row['note'] ?? '')) !== '' && str_contains(strtolower((string)$row['note']), 'migrat');
  ?>
  <tr>
    <td><code><?= h($address) ?></code>
        <?php if ($target !== '' && $target !== $address): ?>
        <br><span class="hint">becomes <code><?= h($target) ?></code></span>
        <?php endif; ?></td>
    <td class="small"><?= h((string)$row['forward_to']) ?></td>
    <td class="small"><?= (int)($row['owner_id'] ?? 0) > 0
        ? h((string)$row['username']) . ' <span class="hint">&lt;' . h((string)($row['registrant_email'] ?? '')) . '&gt;</span>'
        : '<span class="err">no matching account</span>' ?></td>
    <td><?= $done ? 'moved' : ((string)($row['status'] ?? '') !== '' ? h((string)$row['status']) : '-') ?></td>
    <td class="actions">
      <?php if (!$done && (int)($row['owner_id'] ?? 0) > 0): ?>
      <form class="inline" method="post" action="legacy-mail.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" name="action" value="move">
      </form>
      <?php else: ?>
      <span class="hint">-</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint">
"Move" creates the mailbox on BillionMail with the same address and gives it to the account that owns the
name, sized by the default in the mailbox settings. The holder is mailed that it is ready - the password is
never mailed; he unlocks it in his console with a code. A reserved name (support, nic, postmaster and the
rest) is refused: the holder has to choose another one.
</p>
<?php endif; ?>

<h2>Keep a copy</h2>
<form method="post" action="legacy-mail.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="export">
  <p><input type="submit" value="Download the list as a file"></p>
</form>

<h2>Then close the old page</h2>
<p class="hint">
When the migration is done, switch the old page to <strong>read only</strong> and later to
<strong>closed</strong> in <a href="email.php">Mailboxes</a> - closed means it redirects to the new Email page,
which is what the notice on that page promises the holders.
</p>
<?php
page_footer();

<?php
declare(strict_types=1);

/**
 * freedns.my - back office: the mailbox service (BillionMail) and the requests for it.
 *
 * A holder asks for mailboxes from his console; approving here decides HOW MANY and HOW BIG that account may
 * have (users.mailbox_quota). Creating a mailbox writes it to BillionMail over its own API on 127.0.0.1 and
 * keeps the registry's own record, including the password - encrypted - because the holder may ask to see it
 * again after proving he owns the account.
 *
 * Also here: the switches for the OLD forwarding page (read only, then closed), which is being retired in
 * favour of this service.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('Mailboxes');
$errors = [];
$notice = '';
$apiRaw = '';

$boxesReady    = false;
$requestsReady = false;
$quotaReady    = false;
try {
    $boxesReady    = db_one("SHOW TABLES LIKE 'mail\\_boxes'") !== null;
    $requestsReady = db_one("SHOW TABLES LIKE 'mail\\_box\\_requests'") !== null;
    $quotaReady    = db_one("SHOW COLUMNS FROM users LIKE 'mailbox_quota'") !== null;
} catch (Throwable $e) {
    $boxesReady = $requestsReady = $quotaReady = false;
}

/** A password that survives being typed into a mail client. */
function fd_box_password(): string
{
    $alphabet = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $out = '';
    for ($i = 0; $i < 14; $i++) {
        $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }

    return $out . '!7';
}

/** Creates the mailbox at BillionMail and records it. Returns [ok, message]. */
function fd_box_create(array $domain, int $userId, string $local, int $sizeMb, string $staff,
    ?int $requestId = null): array
{
    $local   = strtolower(trim($local));
    $address = $local . '@' . strtolower((string)$domain['name']);

    if (preg_match('/^[a-z0-9]([a-z0-9._-]{0,62}[a-z0-9])?$/', $local) !== 1) {
        return [false, 'That part before the @ is not usable.'];
    }
    if (in_array($local, reserved_labels(), true) || reserved_name_is_listed((string)$domain['zone'], $local)) {
        return [false, 'That name is reserved; pick another one.'];
    }
    if (db_one('SELECT id FROM mail_boxes WHERE address = ?', [$address]) !== null) {
        return [false, 'That mailbox is already recorded.'];
    }
    if (strlen($address) > 190) {
        return [false, 'That address is too long.'];
    }

    $password = fd_box_password();
    db_query(
        "INSERT INTO mail_boxes (user_id, domain_id, local_part, address, size_mb, status, note, request_id,
            created_by, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, 'pending', NULL, ?, ?, NOW(), NOW())",
        [$userId, (int)$domain['id'], $local, $address, $sizeMb, $requestId, $staff]
    );
    $id = (int)db()->lastInsertId();

    $answer = bm_mailbox_create($address, $password, $sizeMb);
    if (!$answer['ok']) {
        db_query("UPDATE mail_boxes SET status = 'pending', remote_state = ?, updated_at = NOW() WHERE id = ?",
            [substr($answer['error'] . ' | ' . $answer['raw'], 0, 32), $id]);

        return [false, 'BillionMail refused it: ' . $answer['error'] . "\n" . $answer['raw']];
    }

    $cipher = rainyun_crypto_ready() ? rainyun_encrypt($password, 'mailbox:' . $id) : null;
    db_query(
        "UPDATE mail_boxes SET status = 'active', pass_cipher = ?, pass_set_at = NOW(),
            remote_state = 'created', updated_at = NOW() WHERE id = ?",
        [$cipher, $id]
    );
    log_action('mailbox_create', 'mail', $id, $address . ' created by ' . $staff
        . ($requestId !== null ? ' (request ' . $requestId . ')' : ''));

    // The holder is told, but not the password: he asks for it in his console after a verification code.
    $email = (string)db_value('SELECT email FROM users WHERE id = ?', [$userId], '');
    if ($email !== '' && mail_pref_allows($email, 'account')) {
        $previous = lang_switch('en');
        send_mail($email, 'Your mailbox is ready', implode("\n", [
            t('mail.dear', $email), '',
            'The mailbox below was created for you:',
            '  ' . $address, '  size: ' . $sizeMb . ' MB',
            '',
            'The password is not sent by mail. Open your console and press "show the password": a code is '
            . 'mailed to this address first, and only then is it shown.',
            '',
            rtrim(app_base_url(), '/') . '/account/email.php',
        ]));
        lang_switch($previous);
    }

    return [true, 'Created ' . $address . ' (' . $sizeMb . ' MB).'];
}

/* ------------------------------------------------------------------ actions */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'settings') {
        $changed = 0;
        $keys = [
            'bm_base' => '/^https?:\/\//', 'bm_api_key' => null,
            'bm_path_create' => null, 'bm_path_update' => null, 'bm_path_delete' => null,
            'bm_path_password' => null, 'bm_path_list' => null,
            'email_verify_minutes' => null, 'email_default_size_mb' => null,
            'email_client_host' => null, 'email_webmail_url' => null,
            // The panel's own database: used to register a mail domain when its API cannot do it.
            'bm_pg_host' => null, 'bm_pg_port' => null, 'bm_pg_db' => null, 'bm_pg_user' => null,
            'bm_pg_pass' => null, 'bm_domain_route' => null,
        ];
        foreach ($keys as $key => $pattern) {
            $value = trim((string)($_POST[$key] ?? ''));
            if ($value === '' && $key === 'bm_api_key') {
                continue;   // an empty box never wipes the key
            }
            if ($value === '' && $key === 'bm_pg_pass') {
                continue;   // the same courtesy for the database password
            }
            if ((string)setting($key, '') !== $value) {
                set_setting($key, $value);
                $changed++;
            }
        }
        foreach (['email_enabled', 'forward_legacy_readonly', 'forward_legacy_closed'] as $toggle) {
            $want = isset($_POST[$toggle]) ? '1' : '0';
            if ((string)setting($toggle, '') !== $want) {
                set_setting($toggle, $want);
                $changed++;
            }
        }
        log_action('email_settings', 'settings', null, $changed . ' value(s) changed by '
            . (string)$admin['username']);
        flash($changed === 0 ? 'Nothing changed.' : 'Saved (' . $changed . ' value(s)).', 'ok');
        redirect('email.php');
    }

    if ($action === 'pg_test') {
        // Proof that the database route works, without changing anything.
        if (!bm_pg_ready()) {
            $errors[] = 'The panel database is not configured, or pdo_pgsql is missing. Drivers now: '
                . implode(', ', PDO::getAvailableDrivers());
        } else {
            $config = bm_pg_settings();
            try {
                $pdo = new PDO(
                    'pgsql:host=' . $config['host'] . ';port=' . $config['port'] . ';dbname=' . $config['db'],
                    $config['user'], $config['pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 8]
                );
                $count = (int)$pdo->query('SELECT COUNT(*) FROM domain')->fetchColumn();
                $last  = $pdo->query('SELECT domain FROM domain ORDER BY create_time DESC LIMIT 5')
                    ->fetchAll(PDO::FETCH_COLUMN);
                $notice = 'The panel database answered: ' . $count . ' domain(s) known. Newest: '
                    . ($last === [] ? '(none)' : implode(', ', $last));
                log_action('mailbox_pg_test', 'settings', null, 'ok, ' . $count . ' domain(s)');
            } catch (Throwable $e) {
                $errors[] = 'The panel database could not be used: ' . $e->getMessage();
                log_action('mailbox_pg_test', 'settings', null, 'failed: ' . substr($e->getMessage(), 0, 180));
            }
        }
    }

    if ($action === 'test') {
        $test = bm_selftest();
        $notice = $test['ok']
            ? 'The panel answered (HTTP ' . $test['status'] . ').'
            : 'The panel could not be reached: ' . $test['error'];
        $apiRaw = implode("\n", $test['tried']) . "\n\n" . $test['raw'];
    }

    if ($action === 'grant' && $quotaReady) {
        $userId  = (int)($_POST['user_id'] ?? 0);
        $quota   = max(0, min(50, (int)($_POST['quota'] ?? 0)));
        $sizeMb  = max(64, min(102400, (int)($_POST['size_mb'] ?? 1024)));
        if ($userId <= 0 || db_one('SELECT id FROM users WHERE id = ?', [$userId]) === null) {
            $errors[] = 'That account does not exist.';
        } else {
            db_query('UPDATE users SET mailbox_quota = ? WHERE id = ?', [$quota, $userId]);
            if ($sizeMb > 0) {
                set_setting('email_default_size_mb', (string)$sizeMb);
            }
            log_action('mailbox_grant', 'settings', $userId, 'account #' . $userId . ' may keep ' . $quota
                . ' mailbox(es) of ' . $sizeMb . ' MB (by ' . (string)$admin['username'] . ')');
            flash('Account #' . $userId . ' may keep ' . $quota . ' mailbox(es) of ' . $sizeMb . ' MB.', 'ok');
            redirect('email.php');
        }
    }

    if (($action === 'approve' || $action === 'reject') && $requestsReady) {
        $id      = (int)($_POST['id'] ?? 0);
        $request = db_one('SELECT * FROM mail_box_requests WHERE id = ?', [$id]);
        if ($request === null) {
            $errors[] = 'That request does not exist.';
        } elseif ((string)$request['status'] !== 'pending') {
            $errors[] = 'That request was already answered.';
        } else {
            $reason = trim((string)($_POST['reason'] ?? ''));
            $quota  = max(0, min(50, (int)($_POST['quota'] ?? 0)));
            $sizeMb = max(64, min(102400, (int)($_POST['size_mb'] ?? 1024)));

            if ($action === 'approve' && $quota <= 0) {
                $errors[] = 'Give a number of mailboxes above zero when approving.';
            } elseif ($action === 'reject' && $reason === '') {
                $errors[] = 'Give a reason when rejecting.';
            } else {
                if ($action === 'approve') {
                    db_query('UPDATE users SET mailbox_quota = ? WHERE id = ?', [$quota, (int)$request['user_id']]);
                }                db_query(
                    'UPDATE mail_box_requests SET status = ?, quota = ?, size_mb = ?, reason = ?, decided_by = ?,
                        decided_at = NOW() WHERE id = ?',
                    [$action === 'approve' ? 'approved' : 'rejected', $quota, $sizeMb,
                     $action === 'approve' ? null : substr($reason, 0, 255), (string)$admin['username'], $id]
                );
                log_action($action === 'approve' ? 'mailbox_approved' : 'mailbox_rejected', 'settings',
                    (int)$request['user_id'], 'request ' . $id . ($action === 'approve'
                        ? ' approved: ' . $quota . ' x ' . $sizeMb . ' MB' : ': ' . $reason));

                $to = (string)db_value('SELECT email FROM users WHERE id = ?', [(int)$request['user_id']], '');
                if ($to !== '' && mail_pref_allows($to, 'account')) {
                    $previous = lang_switch('en');
                    send_mail($to, $action === 'approve' ? 'Mailboxes were granted' : 'Your mailbox request was answered',
                        implode("\n", array_merge(
                            [t('mail.dear', $to), ''],
                            [$action === 'approve'
                                ? 'You may now keep ' . $quota . ' mailbox(es) of ' . $sizeMb . ' MB each.'
                                : 'Your request was not approved.'],
                            $action === 'approve' ? [] : ['', 'Reason: ' . $reason],
                            ['', rtrim(app_base_url(), '/') . '/account/email.php']
                        )));
                    lang_switch($previous);
                }
                flash($action === 'approve' ? 'Approved.' : 'Rejected.', 'ok');

                // Approving also creates the first mailbox straight away - one step instead of two. The suffix
                // is always <username>.freedns.my (the holder's own name under freedns.my), and the part before
                // the @ defaults to the user name itself; the operator may type another prefix instead.
                if ($action === 'approve' && $boxesReady && !empty($_POST['make_now'])) {
                    $makeLocal = strtolower(trim((string)($_POST['local'] ?? '')));
                    $makeName  = strtolower(trim((string)db_value('SELECT username FROM users WHERE id = ?',
                        [(int)$request['user_id']], '')));
                    if ($makeName === '') {
                        $errors[] = 'Approved, but that account has no user name, so no mailbox was created.';
                    } else {
                        if ($makeLocal === '') {
                            $makeLocal = $makeName;
                        }
                        $synthetic = ['id' => null, 'name' => $makeName . '.freedns.my', 'zone' => 'freedns.my'];
                        [$made, $madeMessage] = fd_box_create($synthetic, (int)$request['user_id'], $makeLocal,
                            $sizeMb, (string)$admin['username'], $id);
                        if ($made) {
                            flash('Approved, and ' . $madeMessage, 'ok');
                            redirect('email.php');
                        }
                        $errors[] = 'Approved, but the mailbox was not created: ' . $madeMessage;
                    }
                }

                redirect('email.php');
            }
        }
    }

    /*
     * Registering the mail domain with the panel. A mailbox without a registered domain cannot receive mail at
     * all (the panel answers "454 Relay access denied"), so this can be done for one row or for every mailbox
     * at once. Calling it for a domain that is already known is harmless.
     */
    if ($action === 'domain_sync' && $boxesReady) {
        $wanted = db_all("SELECT DISTINCT address FROM mail_boxes WHERE status <> 'deleted' LIMIT 2000");
        $done = 0;
        $failed = [];
        $seen = [];
        foreach ($wanted as $row) {
            $at = strrpos((string)$row['address'], '@');
            if ($at === false) {
                continue;
            }
            $domain = strtolower(substr((string)$row['address'], $at + 1));
            if (isset($seen[$domain])) {
                continue;
            }
            $seen[$domain] = true;
            $answer = bm_domain_create($domain);
            if ($answer['ok']) {
                $done++;
            } else {
                $failed[$domain] = $answer['error'];
            }
        }
        log_action('mailbox_domain_sync', 'settings', null,
            count($seen) . ' domain(s) checked, ' . $done . ' registered now');
        $notice = count($seen) . ' mail domain(s) checked; ' . $done . ' were registered by this run.';
        if ($failed !== []) {
            $apiRaw = "Domains the panel did not accept (usually because they are already there):\n"
                . implode("\n", array_map(static fn($k, $v) => $k . ' -> ' . $v, array_keys($failed), $failed));
        }
    }

    if ($action === 'domain_one' && $boxesReady) {
        $box = db_one('SELECT * FROM mail_boxes WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        if ($box === null) {
            $errors[] = 'That mailbox is not recorded.';
        } else {
            $at     = strrpos((string)$box['address'], '@');
            $domain = $at === false ? '' : strtolower(substr((string)$box['address'], $at + 1));
            $answer = $domain === '' ? ['ok' => false, 'error' => 'no domain in that address', 'raw' => '']
                : bm_domain_create($domain);
            if ($answer['ok']) {
                flash($domain . ' is registered with the panel now.', 'ok');
            } else {
                $notice = $domain . ': ' . $answer['error'] . ' (if it says it already exists, all is well).';
            }
            log_action('mailbox_domain', 'mail', (int)$box['id'], 'domain ' . $domain . ' -> '
                . ($answer['ok'] ? 'registered' : $answer['error']));
        }
    }

    if ($action === 'create' && $boxesReady && $quotaReady) {
        // The suffix is always <username>.freedns.my - the holder's own name under freedns.my, never one of
        // the names he registered. Only the part before the @ is chosen here.
        $userId = (int)($_POST['user_id'] ?? 0);
        $local  = (string)($_POST['local'] ?? '');
        $sizeMb = max(64, min(102400, (int)($_POST['size_mb'] ?? (int)setting('email_default_size_mb', '1024'))));
        $owner  = $userId > 0 ? db_one('SELECT id, username FROM users WHERE id = ?', [$userId]) : null;
        if ($owner === null) {
            $errors[] = 'Give the number of an account that exists.';
        } elseif (trim((string)$owner['username']) === '') {
            $errors[] = 'That account has no user name, so it cannot have a mail domain.';
        } else {
            $synthetic = ['id' => null, 'name' => strtolower((string)$owner['username']) . '.freedns.my',
                          'zone' => 'freedns.my'];
            if (trim($local) === '') {
                $local = strtolower((string)$owner['username']);
            }
            [$ok, $message] = fd_box_create($synthetic, $userId, $local, $sizeMb, (string)$admin['username']);
            if ($ok) {
                flash($message, 'ok');
                redirect('email.php');
            }
            $errors[] = $message;
        }
    }

    if ($boxesReady && in_array($action, ['wake', 'sleep', 'resize', 'reset', 'remove'], true)) {
        $box = db_one('SELECT * FROM mail_boxes WHERE id = ?', [(int)($_POST['id'] ?? 0)]);
        if ($box === null) {
            $errors[] = 'That mailbox is not recorded.';
        } else {
            $address = (string)$box['address'];
            $sizeMb  = max(64, min(102400, (int)($_POST['size_mb'] ?? (int)$box['size_mb'])));

            if ($action === 'wake' || $action === 'sleep' || $action === 'resize') {
                $active = $action === 'sleep' ? false : true;
                $answer = bm_mailbox_state($address, $active, $sizeMb);
                if ($answer['ok']) {
                    db_query('UPDATE mail_boxes SET status = ?, size_mb = ?, remote_state = ?, updated_at = NOW()
                              WHERE id = ?',
                        [$active ? 'active' : 'suspended', $sizeMb, $active ? 'active' : 'suspended', (int)$box['id']]);
                    log_action('mailbox_' . $action, 'mail', (int)$box['id'], $address . ' -> '
                        . ($active ? 'active' : 'suspended') . ' / ' . $sizeMb . ' MB');
                    flash('Done: ' . $address . ' is now ' . ($active ? 'active' : 'suspended') . ' (' . $sizeMb . ' MB).', 'ok');
                    redirect('email.php');
                }
                $errors[] = 'BillionMail refused it: ' . $answer['error'] . "\n" . $answer['raw'];
            }

            if ($action === 'reset') {
                $password = fd_box_password();
                $answer   = bm_mailbox_password($address, $password);
                if ($answer['ok']) {
                    $cipher = rainyun_crypto_ready() ? rainyun_encrypt($password, 'mailbox:' . (int)$box['id']) : null;
                    db_query('UPDATE mail_boxes SET pass_cipher = ?, pass_set_at = NOW(), updated_at = NOW()
                              WHERE id = ?', [$cipher, (int)$box['id']]);
                    log_action('mailbox_reset', 'mail', (int)$box['id'], 'password replaced for ' . $address);
                    flash('A new password was set. The holder can see it in his console after verifying.', 'ok');
                    redirect('email.php');
                }
                $errors[] = 'BillionMail refused it: ' . $answer['error'] . "\n" . $answer['raw'];
            }

            if ($action === 'remove') {
                $answer = bm_mailbox_delete($address);
                db_query("UPDATE mail_boxes SET status = 'deleted', remote_state = ?, updated_at = NOW()
                          WHERE id = ?", [substr($answer['error'], 0, 32), (int)$box['id']]);
                log_action('mailbox_delete', 'mail', (int)$box['id'], $address . ' deleted'
                    . ($answer['ok'] ? '' : ' (the panel complained: ' . $answer['error'] . ')'));
                if ($answer['ok']) {
                    flash($address . ' was deleted.', 'ok');
                } else {
                    $errors[] = 'Removed here, but BillionMail answered: ' . $answer['error'] . "\n" . $answer['raw'];
                }
                redirect('email.php');
            }
        }
    }
}

/* ------------------------------------------------------------------ the page */
$pending   = $requestsReady ? db_all(
    "SELECT r.*, u.username, u.email, u.mailbox_quota FROM mail_box_requests r JOIN users u ON u.id = r.user_id
     WHERE r.status = 'pending' ORDER BY r.id ASC LIMIT 200") : [];
$boxes     = $boxesReady ? db_all(
    'SELECT b.*, u.username FROM mail_boxes b LEFT JOIN users u ON u.id = b.user_id
     WHERE b.status <> \'deleted\' ORDER BY b.id DESC LIMIT 200') : [];
$granted   = $quotaReady ? db_all(
    'SELECT id, username, email, mailbox_quota FROM users WHERE mailbox_quota > 0 ORDER BY id ASC LIMIT 200') : [];
$domains   = db_all("SELECT id, name FROM domains WHERE status = 'active' ORDER BY name ASC LIMIT 500");

page_header('Mailboxes');
?>
<p>
The new mailbox service: a holder asks, you approve, and the mailbox is created on the self-hosted
BillionMail through its own API on <code>127.0.0.1</code>. The password is kept encrypted here so the holder
can ask to see it again after a verification code is mailed to his registered address.
</p>
<?= error_list($errors) ?>
<?php if ($notice !== ''): ?><div class="msg ok"><?= h($notice) ?></div><?php endif; ?>
<?php if ($apiRaw !== ''): ?>
<details><summary>What the panel answered</summary><pre style="white-space:pre-wrap"><?= h($apiRaw) ?></pre></details>
<?php endif; ?>
<?php if (!$boxesReady || !$requestsReady || !$quotaReady): ?>
<div class="msg error">The mailbox tables are missing; import <code>upgrade-email.sql</code>
(the <a href="<?= h(url('selftest.php')) ?>">self test</a> can apply it for you).</div>
<?php endif; ?>

<h2>Requests (<?= count($pending) ?>)</h2>
<?php if ($pending === []): ?>
<p>Nothing waiting.</p>
<?php else: ?>
<?php foreach ($pending as $row): ?>
<div class="msg info">
  <strong><?= h((string)$row['username']) ?></strong> &lt;<?= h((string)$row['email']) ?>&gt;
  &middot; <?= h((string)$row['created_at']) ?>
  &middot; granted now: <?= (int)$row['mailbox_quota'] ?>
  <div class="small">wants <?= (int)$row['wanted'] ?> mailbox(es), <?= (int)$row['size_mb'] ?> MB each</div>
  <div><?= nl2br(h(shorten((string)$row['purpose'], 500))) ?></div>
</div>
<form method="post" action="email.php">
  <?= csrf_field() ?>
  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
  <label for="q<?= (int)$row['id'] ?>">Mailboxes to grant</label>
  <input type="text" id="q<?= (int)$row['id'] ?>" name="quota" maxlength="2" size="3"
         value="<?= max(1, (int)$row['wanted']) ?>">
  <label for="s<?= (int)$row['id'] ?>">Size each (MB)</label>
  <input type="text" id="s<?= (int)$row['id'] ?>" name="size_mb" maxlength="6" size="7"
         value="<?= max(64, (int)($row['size_mb'] > 0 ? $row['size_mb'] : setting('email_default_size_mb', '1024'))) ?>">
  <input type="submit" name="action" value="approve">
  <input type="text" name="reason" maxlength="190" placeholder="reason, if rejected">
  <input type="submit" name="action" value="reject">
  <div class="small">
    <input type="checkbox" id="mn<?= (int)$row['id'] ?>" name="make_now" value="1" checked>
    <label class="inline" for="mn<?= (int)$row['id'] ?>">also create the first mailbox now —</label>
    <input type="text" name="local" maxlength="64" size="12"
           placeholder="<?= h((string)$row['username']) ?>">
    <span class="hint">@<?= h(strtolower((string)$row['username'])) ?>.freedns.my
      (empty = the user name itself)</span>
  </div>
</form>
<?php endforeach; ?>
<?php endif; ?>

<h2>Grant, without a request</h2>
<form method="post" action="email.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="grant">
<label for="user_id">Account number</label>
<input type="text" id="user_id" name="user_id" maxlength="8" size="8">
<label for="gquota">Mailboxes it may keep (0 = take the right away)</label>
<input type="text" id="gquota" name="quota" maxlength="2" size="3" value="1">
<label for="gsize">Size each (MB)</label>
<input type="text" id="gsize" name="size_mb" maxlength="6" size="7"
       value="<?= h((string)setting('email_default_size_mb', '1024')) ?>">
<p><input type="submit" value="Set it"></p>
</form>

<h2>Create a mailbox by hand</h2>
<form method="post" action="email.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="create">
<label for="cuser">Account number</label>
<input type="text" id="cuser" name="user_id" maxlength="8" size="8">
<label for="clocal">Part before the @ (empty = the user name itself)</label>
<input type="text" id="clocal" name="local" maxlength="64" placeholder="info">
<span class="hint">@&lt;username&gt;.freedns.my — the suffix comes from the account, it cannot be chosen.</span>
<label for="csize">Size (MB)</label>
<input type="text" id="csize" name="size_mb" maxlength="6" size="7"
       value="<?= h((string)setting('email_default_size_mb', '1024')) ?>">
<p class="hint">Reserved names (support, nic, postmaster and about 190 more) are refused, and so is an
address that already exists.</p>
<p><input type="submit" value="Create it"></p>
</form>

<h2>Mailboxes (<?= count($boxes) ?>)</h2>
<?php if ($boxes === []): ?>
<p>None yet.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Address</th><th>Mail domain</th><th>Account</th><th>Size</th><th>State</th><th>Password</th><th>Made</th><th></th></tr>
  <?php foreach ($boxes as $box): ?>
  <?php
  $at      = strrpos((string)$box['address'], '@');
  $boxZone = $at === false ? '' : substr((string)$box['address'], $at + 1);
  ?>
  <tr>
    <td><?= (int)$box['id'] ?></td>
    <td><code><?= h((string)$box['address']) ?></code></td>
    <td class="small"><code><?= h($boxZone) ?></code>
      <form class="inline" method="post" action="email.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$box['id'] ?>">
        <input type="submit" name="action" value="domain_one">
      </form></td>
    <td class="small"><?= h((string)($box['username'] ?? ('#' . (int)$box['user_id']))) ?></td>
    <td><?= (int)$box['size_mb'] ?> MB</td>
    <td><?= h((string)$box['status']) ?></td>
    <td class="small"><?= trim((string)($box['pass_cipher'] ?? '')) === ''
        ? '<span class="err">none kept</span>' : h((string)($box['pass_set_at'] ?? '')) ?></td>
    <td class="small"><?= h((string)$box['created_at']) ?><br><?= h((string)($box['created_by'] ?? '')) ?></td>
    <td class="actions">
      <?php foreach ([['wake', 'Wake'], ['sleep', 'Suspend'], ['resize', 'Resize'], ['reset', 'New password'],
                      ['remove', 'Delete']] as $pair): ?>
      <form class="inline" method="post" action="email.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$box['id'] ?>">
        <?php if ($pair[0] === 'resize'): ?>
        <input type="text" name="size_mb" maxlength="6" size="7" value="<?= (int)$box['size_mb'] ?>">
        <?php endif; ?>
        <input type="submit" name="action" value="<?= h($pair[0]) ?>">
      </form>
      <?php endforeach; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint">"Wake", "Suspend", "Resize" and "New password" go to BillionMail through
<code>/api/mailbox/update</code>; "Delete" uses <code>/api/mailbox/delete</code>. If the panel complains, its
own words are shown - and the path can be corrected in the settings below.</p>
<?php endif; ?>

<h2>Accounts that may have mailboxes (<?= count($granted) ?>)</h2>
<?php if ($granted === []): ?>
<p>Nobody yet.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Account</th><th>E-mail</th><th>May keep</th><th>In use</th></tr>
  <?php foreach ($granted as $row): ?>
  <?php $used = (int)db_value('SELECT COUNT(*) FROM mail_boxes WHERE user_id = ? AND status <> \'deleted\'',
      [(int)$row['id']], 0); ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><?= h((string)$row['username']) ?></td>
    <td class="small"><?= h((string)$row['email']) ?></td>
    <td><?= (int)$row['mailbox_quota'] ?></td>
    <td><?= $used ?><?= $used >= (int)$row['mailbox_quota'] ? ' <span class="err">(full)</span>' : '' ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>BillionMail and the old page</h2>
<form method="post" action="email.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="settings">
<p>
  <input type="checkbox" id="email_enabled" name="email_enabled" value="1"<?= setting_bool('email_enabled', true) ? ' checked' : '' ?>>
  <label class="inline" for="email_enabled">The mailbox service is open (console page, requests, mailbox creation)</label>
</p>
<p>
  <input type="checkbox" id="forward_legacy_readonly" name="forward_legacy_readonly" value="1"<?= setting_bool('forward_legacy_readonly', false) ? ' checked' : '' ?>>
  <label class="inline" for="forward_legacy_readonly">Old Mail Forwarding page: read only (existing addresses keep working, no new ones)</label>
</p>
<p>
  <input type="checkbox" id="forward_legacy_closed" name="forward_legacy_closed" value="1"<?= setting_bool('forward_legacy_closed', false) ? ' checked' : '' ?>>
  <label class="inline" for="forward_legacy_closed">Old Mail Forwarding page: closed (it redirects to the new Email page)</label>
</p>
<label for="bm_base">BillionMail on this machine</label>
<input type="text" id="bm_base" name="bm_base" maxlength="190"
       value="<?= h((string)setting('bm_base', 'https://127.0.0.1:41443')) ?>">
<label for="bm_api_key">API key (leave empty to keep the one in place)</label>
<input type="password" id="bm_api_key" name="bm_api_key" autocomplete="new-password">
<label for="bm_domain_route">How a mail domain is registered</label>
<select id="bm_domain_route" name="bm_domain_route">
  <option value="auto"<?= bm_domain_route() === 'auto' ? ' selected' : '' ?>>auto — try the API, fall back to the database</option>
  <option value="db"<?= bm_domain_route() === 'db' ? ' selected' : '' ?>>database only (the API of this version cannot do it)</option>
  <option value="api"<?= bm_domain_route() === 'api' ? ' selected' : '' ?>>API only</option>
</select>
<label for="bm_pg_host">Panel database — host / port</label>
<input type="text" id="bm_pg_host" name="bm_pg_host" maxlength="190" size="16"
       value="<?= h((string)setting('bm_pg_host', '127.0.0.1')) ?>">
<input type="text" id="bm_pg_port" name="bm_pg_port" maxlength="5" size="6"
       value="<?= h((string)setting('bm_pg_port', '25432')) ?>">
<label for="bm_pg_db">Database / user</label>
<input type="text" id="bm_pg_db" name="bm_pg_db" maxlength="64" size="16"
       value="<?= h((string)setting('bm_pg_db', 'billionmail')) ?>">
<input type="text" id="bm_pg_user" name="bm_pg_user" maxlength="64" size="16"
       value="<?= h((string)setting('bm_pg_user', 'billionmail')) ?>">
<label for="bm_pg_pass">Database password (leave empty to keep the one in place)</label>
<input type="password" id="bm_pg_pass" name="bm_pg_pass" autocomplete="new-password">
<label for="bm_path_create">Path: create</label>
<input type="text" id="bm_path_create" name="bm_path_create" maxlength="190"
       value="<?= h((string)setting('bm_path_create', '/api/mailbox/create')) ?>">
<label for="bm_path_update">Path: update (also used for the password)</label>
<input type="text" id="bm_path_update" name="bm_path_update" maxlength="190"
       value="<?= h((string)setting('bm_path_update', '/api/mailbox/update')) ?>">
<label for="bm_path_delete">Path: delete</label>
<input type="text" id="bm_path_delete" name="bm_path_delete" maxlength="190"
       value="<?= h((string)setting('bm_path_delete', '/api/mailbox/delete')) ?>">
<label for="bm_path_list">Path: list</label>
<input type="text" id="bm_path_list" name="bm_path_list" maxlength="190"
       value="<?= h((string)setting('bm_path_list', '/api/mailbox/list')) ?>">
<label for="email_client_host">Host shown to mail clients (empty = the same as the collector)</label>
<input type="text" id="email_client_host" name="email_client_host" maxlength="190"
       value="<?= h((string)setting('email_client_host', '')) ?>" placeholder="mail.mx.freedns.my">
<label for="email_webmail_url">Webmail link shown to holders (empty = hidden)</label>
<input type="text" id="email_webmail_url" name="email_webmail_url" maxlength="190"
       value="<?= h((string)setting('email_webmail_url', '')) ?>" placeholder="https://mail.mx.freedns.my">
<label for="email_verify_minutes">A "show me my password" code stays valid for (minutes)</label>
<input type="text" id="email_verify_minutes" name="email_verify_minutes" maxlength="4" size="5"
       value="<?= h((string)setting('email_verify_minutes', '15')) ?>">
<label for="email_default_size_mb">Size given to a new mailbox (MB)</label>
<input type="text" id="email_default_size_mb" name="email_default_size_mb" maxlength="6" size="7"
       value="<?= h((string)setting('email_default_size_mb', '1024')) ?>">
<p><input type="submit" value="Save"></p>
</form>

<form method="post" action="email.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="pg_test">
  <p><input type="submit" value="Test the panel database"></p>
</form>
<form method="post" action="email.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="test">
  <p><input type="submit" value="Test BillionMail"></p>
</form>
<form method="post" action="email.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="domain_sync">
  <p><input type="submit" value="Register every mail domain with the panel">
  <span class="hint">A mailbox whose domain is not known to the panel cannot receive anything (the sender is
  told "454 Relay access denied"). This walks every mailbox once and registers its domain; a domain that is
  already there is simply reported as such.</span></p>
</form>
<?php
page_footer();

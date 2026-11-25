<?php
declare(strict_types=1);

/**
 * freedns.my - administration: the registrant accounts of the user console.
 *
 * One page for everything: filter and page through the accounts, open one for editing (user name,
 * e-mail, status, language, password), resend the confirmation or reset mail, suspend, reactivate
 * or delete, single or in bulk.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('User accounts');
$errors = [];

$statuses = ['pending', 'active', 'suspended'];

/* ------------------------------------------------------------------ *
 * Actions                                                             *
 * ------------------------------------------------------------------ */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = strtolower((string)($_POST['action'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);
    $user   = $id > 0 ? db_one('SELECT * FROM users WHERE id = ?', [$id]) : null;

    $ids = [];
    if (isset($_POST['ids']) && is_array($_POST['ids'])) {
        foreach ($_POST['ids'] as $value) {
            $ids[] = (int)$value;
        }
    }
    $ids = array_values(array_unique(array_filter($ids, static fn($v) => $v > 0)));

    if ($action === 'open') {
        // The link in the list is a GET; keep this for the forms that carry the id.
        redirect('users.php?edit=' . $id);
    }

    if ($action === 'save') {
        if ($user === null) {
            $errors[] = 'That account does not exist.';
        } else {
            $username = trim((string)($_POST['username'] ?? ''));
            $email    = trim((string)($_POST['email'] ?? ''));
            $status   = (string)($_POST['status'] ?? 'active');
            $language = (string)($_POST['language'] ?? 'en');

            $problem = username_problem($username, $id);
            if ($problem !== '') {
                $errors[] = 'User name: ' . $problem;
            }
            if (!valid_email_addr($email)) {
                $errors[] = 'The e-mail address is not valid.';
            } elseif (db_one('SELECT id FROM users WHERE email_lower = ? AND id <> ?', [strtolower($email), $id]) !== null) {
                $errors[] = 'Another account already uses that e-mail address.';
            }
            if (!in_array($status, $statuses, true)) {
                $errors[] = 'Unknown status.';
            }
            if (!in_array($language, ['en', 'zh'], true)) {
                $language = 'en';
            }

            if ($errors === []) {
                $emailChanged = strcasecmp($email, (string)$user['email']) !== 0;

                db_query(
                    'UPDATE users SET username = ?, username_lower = ?, email = ?, email_lower = ?,
                        status = ?, language = ? WHERE id = ?',
                    [$username, strtolower($username), $email, strtolower($email), $status, $language, $id]
                );

                $fresh = db_one('SELECT * FROM users WHERE id = ?', [$id]);
                $note  = '';

                if ($emailChanged && $fresh !== null) {
                    // The new address has to be confirmed; the account itself keeps working.
                    user_verify_send($fresh);
                    $note = ' The new address is not confirmed yet - a confirmation mail was sent to '
                        . $email . '.';
                }
                if ($status === 'active' && $fresh !== null && !$emailChanged) {
                    // The operator vouches for the address: drop any pending confirmation.
                    db_query('UPDATE users SET verify_token = NULL, verify_expires = NULL WHERE id = ?', [$id]);
                }

                log_action(
                    'user_updated',
                    'user',
                    $id,
                    (string)$user['username'] . ' -> ' . $username . ' <' . $email . '>, status ' . $status
                        . ($emailChanged ? ' (e-mail changed)' : '')
                );
                flash('The account has been saved.' . $note, 'ok');
                redirect('users.php?edit=' . $id);
            }
        }
    }

    if ($action === 'password') {
        if ($user === null) {
            $errors[] = 'That account does not exist.';
        } else {
            $password = (string)($_POST['password'] ?? '');

            if (isset($_POST['generate'])) {
                // A readable random password; it is shown once in the message below.
                $alphabet = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789';
                $password = '';
                for ($i = 0; $i < 14; $i++) {
                    $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
            }

            $problem = password_problem($password, (string)$user['username'], (string)$user['email']);
            if ($problem !== '') {
                $errors[] = 'Password: ' . $problem;
            } else {
                db_query('UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?', [
                    password_hash($password, PASSWORD_DEFAULT),
                    $id,
                ]);
                log_action('user_password_set', 'user', $id, (string)$user['username'] . ' (by operator)');
                flash(
                    isset($_POST['generate'])
                        ? 'New password for ' . (string)$user['username'] . ': ' . $password
                            . ' — write it down now, it is not shown again.'
                        : 'The password has been changed.',
                    'ok'
                );
                redirect('users.php?edit=' . $id);
            }
        }
    }

    if ($action === 'verify_mail' && $user !== null) {
        if (user_verify_send($user)) {
            flash('A new confirmation mail was sent to ' . (string)$user['email'] . '.', 'ok');
        } else {
            flash('The confirmation mail could not be sent.', 'error');
        }
        redirect('users.php?edit=' . $id);
    }

    if ($action === 'reset_mail' && $user !== null) {
        if (user_reset_send_now($user)) {
            flash('A password reset link was sent to ' . (string)$user['email'] . '.', 'ok');
        } else {
            flash('The reset mail could not be sent.', 'error');
        }
        redirect('users.php?edit=' . $id);
    }

    if ($action === 'mark_verified' && $user !== null) {
        db_query('UPDATE users SET status = \'active\', verify_token = NULL, verify_expires = NULL WHERE id = ?', [$id]);
        log_action('user_verified', 'user', $id, (string)$user['username'] . ' (confirmed by operator)');
        flash('The account is active and counts as confirmed.', 'ok');
        redirect('users.php?edit=' . $id);
    }

    if ($action === 'suspend' || $action === 'activate' || $action === 'delete') {
        $targets = $ids !== [] ? $ids : ($id > 0 ? [$id] : []);
        if ($targets === []) {
            $errors[] = 'No account was selected.';
        } elseif ($action === 'delete' && !isset($_POST['confirm'])) {
            $errors[] = 'Tick the confirmation box to delete accounts - their names stay registered.';
        } else {
            $done = 0;
            foreach ($targets as $target) {
                $row = db_one('SELECT * FROM users WHERE id = ?', [$target]);
                if ($row === null) {
                    continue;
                }
                $name = (string)$row['username'];

                if ($action === 'delete') {
                    db_query('DELETE FROM users WHERE id = ?', [$target]);
                    log_action('user_deleted', 'user', $target, $name . ' <' . (string)$row['email'] . '>');
                } else {
                    $newStatus = $action === 'suspend' ? 'suspended' : 'active';
                    db_query('UPDATE users SET status = ? WHERE id = ?', [$newStatus, $target]);
                    log_action('user_' . $action, 'user', $target, $name . ' -> ' . $newStatus);
                }
                $done++;
            }
            flash(
                $action === 'delete'
                    ? $done . ' account(s) deleted; their names stay registered.'
                    : $done . ' account(s) set to ' . ($action === 'suspend' ? 'SUSPENDED' : 'ACTIVE') . '.',
                'ok'
            );
            redirect('users.php');
        }
    }

    if ($action === 'quota' && $user !== null) {
        if (!quota_columns_ready()) {
            $errors[] = 'The users table has no quota columns yet; import upgrade-quota.sql '
                . '(the self test prints the SQL).';
        } else {
            $mode   = (string)($_POST['quota_mode'] ?? 'default');
            $number = (int)($_POST['quota_number'] ?? 0);

            if ($mode === 'unlimited') {
                db_query('UPDATE users SET quota_unlimited = 1, quota_limit = NULL WHERE id = ?', [$id]);
                log_action('quota_changed', 'user', $id, (string)$user['username'] . ': unlimited');
                flash('That account may now register as many names as it likes.', 'ok');
                redirect('users.php?edit=' . $id);
            } elseif ($mode === 'number') {
                if ($number < 1 || $number > 1000) {
                    $errors[] = 'The number must be between 1 and 1000.';
                } else {
                    db_query('UPDATE users SET quota_unlimited = 0, quota_limit = ? WHERE id = ?',
                        [$number, $id]);
                    log_action('quota_changed', 'user', $id, (string)$user['username'] . ': own quota ' . $number);
                    flash('That account may now hold ' . $number . ' name(s) in total.', 'ok');
                    redirect('users.php?edit=' . $id);
                }
            } else {
                db_query('UPDATE users SET quota_unlimited = 0, quota_limit = NULL WHERE id = ?', [$id]);
                log_action('quota_changed', 'user', $id,
                    (string)$user['username'] . ': back to the default of ' . quota_default_limit());
                flash('That account uses the default allowance again ('
                    . quota_default_limit() . ' name(s)).', 'ok');
                redirect('users.php?edit=' . $id);
            }
        }
    }

    if ($action === 'unlink_github' && $user !== null) {
        $login = (string)($user['github_login'] ?? '');
        github_unlink_user((int)$user['id'], $login);
        flash('The GitHub link was removed.', 'ok');
        redirect('users.php?edit=' . $id);
    }
}

/* ------------------------------------------------------------------ *
 * Editing one account                                                 *
 * ------------------------------------------------------------------ */

$editId = (int)($_GET['edit'] ?? 0);
$edit   = $editId > 0 ? db_one('SELECT * FROM users WHERE id = ?', [$editId]) : null;
if ($editId > 0 && $edit === null) {
    flash('That account does not exist.', 'error');
    redirect('users.php');
}

$editDomains = [];
if ($edit !== null) {
    $editDomains = db_all(
        'SELECT d.*, (SELECT COUNT(*) FROM records r WHERE r.domain_id = d.id) AS record_count
         FROM domains d WHERE LOWER(d.registrant_email) = ? ORDER BY d.name ASC',
        [strtolower((string)$edit['email'])]
    );
}

/* ------------------------------------------------------------------ *
 * Listing                                                             *
 * ------------------------------------------------------------------ */

$q       = trim((string)($_GET['q'] ?? ''));
$status  = (string)($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;

$where  = [];
$params = [];
if ($q !== '') {
    $where[]  = '(u.username LIKE ? OR u.email LIKE ?)';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
    $params[] = '%' . addcslashes($q, '%_\\') . '%';
}
if (in_array($status, $statuses, true)) {
    $where[]  = 'u.status = ?';
    $params[] = $status;
} else {
    $status = '';
}

// The list query needs the alias: the sub-select counts the names through u.email_lower.
$sql   = 'FROM users u' . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where));
$total = (int)db_value('SELECT COUNT(*) ' . $sql, $params, 0);
$pager = paginate($total, $perPage, $page);

$users = db_all(
    'SELECT u.*, (SELECT COUNT(*) FROM domains d WHERE LOWER(d.registrant_email) = u.email_lower) AS domain_count '
    . $sql . ' ORDER BY u.id DESC LIMIT ' . (int)$pager['per_page'] . ' OFFSET ' . (int)$pager['offset'],
    $params
);

$counts = ['all' => 0];
foreach ($statuses as $option) {
    $counts[$option] = 0;
}
foreach (db_all('SELECT status, COUNT(*) AS n FROM users GROUP BY status') as $row) {
    $key = (string)$row['status'];
    $counts['all'] += (int)$row['n'];
    if (isset($counts[$key])) {
        $counts[$key] = (int)$row['n'];
    }
}

page_header('User accounts');
?>
<p>
Everyone who registered in the user console. Names are matched by e-mail address, so a name requested
with the same address appears on the account even if it was requested long before the console existed.
</p>

<?= error_list($errors) ?>

<?php if (!users_table_ready()): ?>
<div class="msg error">
  The <code>users</code> table does not exist yet; import <code>upgrade-users.sql</code>
  (the <a href="selftest.php">self test</a> prints the SQL).
</div>
<?php endif; ?>

<?php if ($edit !== null): ?>
<h2>Account #<?= (int)$edit['id'] ?>: <?= h((string)$edit['username']) ?></h2>
<form method="post" action="users.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">

<label for="username">User name</label>
<input type="text" id="username" name="username" maxlength="32" value="<?= h((string)$edit['username']) ?>">
<p class="hint">3 to 32 characters, letters/digits/"_"/"-", starting with a letter, not a reserved word.</p>

<label for="email">E-mail address</label>
<input type="text" id="email" name="email" maxlength="190" value="<?= h((string)$edit['email']) ?>">
<p class="hint">
Changing it moves the console to the new address and sends a confirmation mail; names requested with the
old address stay registered and can still be managed with the request code.
</p>

<label for="status">Status</label>
<select id="status" name="status">
  <?php foreach ($statuses as $option): ?>
  <option value="<?= h($option) ?>"<?= (string)$edit['status'] === $option ? ' selected' : '' ?>>
    <?= h(strtoupper($option)) ?>
  </option>
  <?php endforeach; ?>
</select>
<p class="hint">
PENDING cannot sign in until the address is confirmed, ACTIVE can, SUSPENDED is refused at sign-in.
Saving an account as ACTIVE also clears any pending confirmation.
</p>

<label for="language">Language of the mails</label>
<select id="language" name="language">
  <option value="en"<?= (string)$edit['language'] === 'zh' ? '' : ' selected' ?>>English</option>
  <option value="zh"<?= (string)$edit['language'] === 'zh' ? ' selected' : '' ?>>中文</option>
</select>

<p><input type="submit" value="Save the account"></p>
</form>

<h3>Password</h3>
<form method="post" action="users.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="password">
<input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
<label for="password">New password</label>
<input type="password" id="password" name="password" maxlength="200" value="" autocomplete="new-password">
<p class="hint">
At least 8 characters. Leave the field empty and press <em>Generate</em> to get a random one, which is
shown once in the message at the top of the next page.
</p>
<p>
  <input type="submit" value="Set the password">
  <input type="submit" name="generate" value="Generate a random password">
</p>
</form>

<h3>Mails</h3>
<form method="post" action="users.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="verify_mail">
  <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <input type="submit" value="Send the confirmation mail again">
</form>
<form method="post" action="users.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="reset_mail">
  <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <input type="submit" value="Send a password reset link">
</form>
<?php if ((string)$edit['status'] !== 'active' || trim((string)($edit['verify_token'] ?? '')) !== ''): ?>
<form method="post" action="users.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="mark_verified">
  <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <input type="submit" value="Mark the address as confirmed">
</form>
<?php endif; ?>

<h3>Activity</h3>
<table>
  <tr><th>Registered</th><td><?= h((string)$edit['created_at']) ?>
      <?= trim((string)($edit['created_ip'] ?? '')) === '' ? '' : ' from ' . h((string)$edit['created_ip']) ?></td></tr>
  <tr><th>Last sign-in</th><td><?= h((string)($edit['last_login_at'] ?? '-')) ?>
      <?= trim((string)($edit['last_login_ip'] ?? '')) === '' ? '' : ' from ' . h((string)$edit['last_login_ip']) ?></td></tr>
  <tr><th>Address confirmed</th><td>
      <?php
      $confirmed = trim((string)($edit['verify_token'] ?? '')) === '';
      $sentAt    = trim((string)($edit['verify_sent_at'] ?? ''));
      ?>
      <?= $confirmed ? 'yes' : 'no' ?><?= (!$confirmed && $sentAt !== '') ? ' (mail sent ' . h($sentAt) . ')' : '' ?>
  </td></tr>
  <tr><th>Reset pending</th><td><?= trim((string)($edit['reset_token'] ?? '')) === ''
      ? 'no' : 'yes, expires ' . h((string)($edit['reset_expires'] ?? '')) ?></td></tr>
  <tr><th>Names</th><td><?= count($editDomains) ?></td></tr>
  <?php if (github_columns_ready()): ?>
  <tr><th>GitHub</th><td>
      <?= trim((string)($edit['github_id'] ?? '')) === ''
          ? 'not linked'
          : h((string)($edit['github_login'] ?? '')) . ' (id ' . h((string)$edit['github_id']) . ', linked '
            . h((string)($edit['github_linked_at'] ?? '')) . ')' ?>
  </td></tr>
  <tr><th>Own password</th><td><?= user_has_password($edit)
      ? 'yes' : 'no - the account signs in through GitHub only' ?></td></tr>
  <?php endif; ?>
  <?php if (quota_columns_ready()): ?>
  <tr><th>Registration quota</th><td>
      <?php
      $quotaRow = quota_for_email((string)$edit['email']);
      $sourceLabels = [
          'account-unlimited' => 'set for this account',
          'account'           => 'set for this account',
          'disabled'          => 'the quota is switched off globally',
      ];
      $sourceLabel = $sourceLabels[(string)$quotaRow['source']] ?? 'the global default';
      ?>
      <?= $quotaRow['unlimited']
          ? '<span class="ok">unlimited</span>'
          : (int)$quotaRow['limit'] . ' name(s) in total' ?>
      (<?= h($sourceLabel) ?>)
      &middot; holds <?= (int)$quotaRow['used'] ?> name(s) now, may add
      <?= $quotaRow['unlimited'] ? 'any number' : (int)max(0, $quotaRow['limit'] - $quotaRow['used']) ?>.
  </td></tr>
  <?php endif; ?>
</table>

<?php if (quota_columns_ready()): ?>
<h3>Registration quota of this account</h3>
<form method="post" action="users.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="quota">
  <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <label for="quota_mode">Allowance</label>
  <select id="quota_mode" name="quota_mode">
    <option value="default">Use the default of <?= (int)quota_default_limit() ?> name(s)</option>
    <option value="unlimited"<?= (int)($edit['quota_unlimited'] ?? 0) === 1 ? ' selected' : '' ?>>Unlimited</option>
    <option value="number">Own number</option>
  </select>
  <label for="quota_number">Own number (1 to 1000)</label>
  <input type="text" id="quota_number" name="quota_number" maxlength="4"
         value="<?= (int)($edit['quota_limit'] ?? 0) > 0 ? (int)$edit['quota_limit'] : '' ?>">
  <p><input type="submit" value="Save the allowance"></p>
</form>
<p class="hint">
Counted per e-mail address across all suffixes; pending, active and suspended names count, rejected and
deleted ones do not. An account created through GitHub is limited like any other new account.
</p>
<?php endif; ?>

<?php if (github_columns_ready() && trim((string)($edit['github_id'] ?? '')) !== ''): ?>
<form method="post" action="users.php" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="unlink_github">
  <input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
  <input type="submit" value="Remove the GitHub link">
</form>
<p class="hint">
Removing the link is meant for support cases (a lost GitHub account). The owner can also do it in the
console, but only after setting a password - otherwise the account could no longer be opened at all.
</p>
<?php endif; ?>

<?php if ($editDomains !== []): ?>
<h3>Names of this account</h3>
<table>
  <tr><th>Name</th><th>Zone</th><th>Status</th><th>Records</th><th></th></tr>
  <?php foreach ($editDomains as $domain): ?>
  <tr>
    <td><a href="edit.php?id=<?= (int)$domain['id'] ?>"><?= h((string)$domain['name']) ?></a></td>
    <td><?= h((string)$domain['zone']) ?></td>
    <td><?= h(status_text((string)$domain['status'])) ?></td>
    <td><?= (int)$domain['record_count'] ?></td>
    <td class="actions"><a href="edit.php?id=<?= (int)$domain['id'] ?>">open</a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h3>Danger zone</h3>
<form method="post" action="users.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="suspend">
<input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
<input type="submit" value="Suspend the account">
</form>
<form method="post" action="users.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="activate">
<input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
<input type="submit" value="Reactivate the account">
</form>
<form method="post" action="users.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="delete">
<input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
<p>
  <input type="checkbox" id="confirm" name="confirm" value="1">
  <label class="inline" for="confirm">Yes, delete this account (its names stay registered)</label>
</p>
<input type="submit" value="Delete the account">
</form>

<p><a href="users.php">Back to the list</a></p>
<?php else: ?>

<h2>Accounts</h2>
<p class="small">
<a href="users.php">all (<?= (int)$counts['all'] ?>)</a>
&middot; <a href="users.php?status=pending">pending (<?= (int)$counts['pending'] ?>)</a>
&middot; <a href="users.php?status=active">active (<?= (int)$counts['active'] ?>)</a>
&middot; <a href="users.php?status=suspended">suspended (<?= (int)$counts['suspended'] ?>)</a>
</p>

<form method="get" action="users.php">
  <label for="q">Search by user name or e-mail address</label>
  <input type="text" id="q" name="q" maxlength="190" value="<?= h($q) ?>">
  <input type="hidden" name="status" value="<?= h($status) ?>">
  <p><input type="submit" value="Search">
  <?php if ($q !== ''): ?> <a href="users.php">reset</a><?php endif; ?></p>
</form>

<?= page_links($pager, ['q' => $q, 'status' => $status]) ?>

<?php if ($users === []): ?>
<p>No account matches.</p>
<?php else: ?>
<form method="post" action="users.php">
<?= csrf_field() ?>
<table>
  <tr>
    <th></th><th>#</th><th>User name</th><th>E-mail</th><th>Status</th><th>Names</th>
    <th>Registered</th><th>Last sign-in</th><th></th>
  </tr>
  <?php foreach ($users as $user): ?>
  <tr>
    <td><input type="checkbox" name="ids[]" value="<?= (int)$user['id'] ?>"></td>
    <td><?= (int)$user['id'] ?></td>
    <td><?= h((string)$user['username']) ?>
        <?= trim((string)($user['verify_token'] ?? '')) === '' ? ''
            : '<span class="err">unconfirmed</span>' ?></td>
    <td><?= h((string)$user['email']) ?></td>
    <td><?= h(strtoupper((string)$user['status'])) ?></td>
    <td><?= (int)$user['domain_count'] ?></td>
    <td><?= h(format_date((string)$user['created_at'])) ?></td>
    <td><?= h(format_date((string)($user['last_login_at'] ?? ''))) ?></td>
    <td class="actions"><a href="users.php?edit=<?= (int)$user['id'] ?>">open</a></td>
  </tr>
  <?php endforeach; ?>
</table>

<p>
  <input type="submit" name="action" value="suspend">
  <input type="submit" name="action" value="activate">
</p>
<p>
  <input type="checkbox" id="confirm_bulk" name="confirm" value="1">
  <label class="inline" for="confirm_bulk">Confirm deleting the selected accounts (their names stay
  registered)</label>
</p>
<p><input type="submit" name="action" value="delete"></p>
</form>
<?php endif; ?>

<?= page_links($pager, ['q' => $q, 'status' => $status]) ?>
<?php endif; ?>
<?php
page_footer();

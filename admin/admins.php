<?php
declare(strict_types=1);

/**
 * freedns.my - administration: administrator accounts.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('Administrators and their roles');
$errors = [];

/**
 * The suffixes ticked for a reviewer, as a comma separated list (empty when none is ticked).
 * Only suffixes that really exist are accepted.
 */
function admin_posted_zones(): string
{
    $posted = $_POST['zones'] ?? [];
    if (!is_array($posted)) {
        return '';
    }

    $known = [];
    foreach (zone_list() as $zone) {
        $known[] = strtolower((string)$zone['name']);
    }

    $out = [];
    foreach ($posted as $value) {
        $name = strtolower(trim((string)$value));
        if ($name !== '' && in_array($name, $known, true) && !in_array($name, $out, true)) {
            $out[] = $name;
        }
    }

    return implode(',', $out);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = (string)($_POST['action'] ?? '');

    /* ---------------- add an administrator ---------------- */
    if ($action === 'add') {
        if (!is_owner()) {
            $errors[] = 'Only an owner account can add administrators.';
        } else {
            $username = trim((string)($_POST['username'] ?? ''));
            $email    = trim((string)($_POST['email'] ?? ''));
            $pass     = (string)($_POST['password'] ?? '');
            $pass2    = (string)($_POST['password2'] ?? '');

            if (!preg_match('/^[A-Za-z0-9._-]{3,32}$/', $username)) {
                $errors[] = 'The user name must be 3 to 32 characters: letters, digits, dot, dash or underscore.';
            } elseif (db_one('SELECT id FROM admins WHERE username = ?', [$username]) !== null) {
                $errors[] = 'That user name is already taken.';
            }
            if ($email !== '' && !valid_email_addr($email)) {
                $errors[] = 'The e-mail address is not valid.';
            }
            if (strlen($pass) < 10) {
                $errors[] = 'The password must be at least 10 characters long.';
            }
            if ($pass !== $pass2) {
                $errors[] = 'The two passwords do not match.';
            }

            if ($errors === []) {
                $wantOwner   = isset($_POST['is_owner']);
                $zonesColumn = admin_zones_ready();
                $zones       = $wantOwner ? '' : admin_posted_zones();

                // Without the column every account can only be an owner; say so instead of failing.
                if (!$zonesColumn && !$wantOwner) {
                    $errors[] = 'The database has no "zones" column yet, so reviewer accounts cannot be '
                        . 'created. Either tick "Owner rights", or import upgrade-reviewer.sql first: '
                        . 'ALTER TABLE admins ADD COLUMN zones VARCHAR(255) DEFAULT NULL AFTER is_owner;';
                } elseif (!$wantOwner && $zones === '') {
                    $errors[] = 'A reviewer needs at least one suffix - tick one, or make the account an '
                        . 'owner.';
                }
            }

            if ($errors === []) {
                if (admin_zones_ready()) {
                    db_query(
                        'INSERT INTO admins (username, password_hash, email, is_owner, zones, created_at)
                         VALUES (?, ?, ?, ?, ?, NOW())',
                        [
                            $username,
                            password_hash($pass, PASSWORD_DEFAULT),
                            $email === '' ? null : $email,
                            isset($_POST['is_owner']) ? 1 : 0,
                            isset($_POST['is_owner']) ? null : admin_posted_zones(),
                        ]
                    );
                } else {
                    // The column is missing: create the account without it (owner rights only).
                    db_query(
                        'INSERT INTO admins (username, password_hash, email, is_owner, created_at)
                         VALUES (?, ?, ?, 1, NOW())',
                        [
                            $username,
                            password_hash($pass, PASSWORD_DEFAULT),
                            $email === '' ? null : $email,
                        ]
                    );
                }
                log_action('admin_added', 'admin', (int)db()->lastInsertId(), 'Created administrator ' . $username
                    . (isset($_POST['is_owner']) ? ' (owner)' : ' (reviewer for ' . admin_posted_zones() . ')'));
                flash('Administrator ' . $username . ' created.', 'ok');
                redirect('admins.php');
            }
        }
    }

    /* ---------------- remove an administrator ---------------- */
    if ($action === 'delete') {
        $id    = (int)($_POST['id'] ?? 0);
        $other = $id > 0 ? db_one('SELECT * FROM admins WHERE id = ?', [$id]) : null;

        if (!is_owner()) {
            $errors[] = 'Only an owner account can remove administrators.';
        } elseif ($other === null) {
            $errors[] = 'That administrator does not exist.';
        } elseif ((int)$other['id'] === (int)$admin['id']) {
            $errors[] = 'You cannot remove your own account.';
        } elseif ((int)$other['is_owner'] === 1
            && (int)db_value('SELECT COUNT(*) FROM admins WHERE is_owner = 1', [], 0) <= 1) {
            $errors[] = 'The last owner account cannot be removed.';
        } else {
            db_query('DELETE FROM admins WHERE id = ?', [$id]);
            log_action('admin_removed', 'admin', $id, 'Removed administrator ' . (string)$other['username']);
            flash('Administrator ' . (string)$other['username'] . ' removed.', 'ok');
            redirect('admins.php');
        }
    }

    /* ---------------- grant or revoke owner rights ---------------- */
    if ($action === 'toggle_owner') {
        $id    = (int)($_POST['id'] ?? 0);
        $other = $id > 0 ? db_one('SELECT * FROM admins WHERE id = ?', [$id]) : null;

        if (!is_owner()) {
            $errors[] = 'Only an owner account can change owner rights.';
        } elseif ($other === null) {
            $errors[] = 'That administrator does not exist.';
        } elseif ((int)$other['is_owner'] === 1
            && (int)db_value('SELECT COUNT(*) FROM admins WHERE is_owner = 1', [], 0) <= 1) {
            $errors[] = 'The last owner account must keep its rights.';
        } else {
            $newValue = (int)$other['is_owner'] === 1 ? 0 : 1;
            db_query('UPDATE admins SET is_owner = ? WHERE id = ?', [$newValue, $id]);
            log_action(
                'admin_owner_changed',
                'admin',
                $id,
                (string)$other['username'] . ' owner = ' . ($newValue === 1 ? 'yes' : 'no')
            );
            flash('Owner rights updated for ' . (string)$other['username'] . '.', 'ok');
            redirect('admins.php');
        }
    }

    /* ---------------- the suffixes of a reviewer ---------------- */
    if ($action === 'save_zones') {
        $id    = (int)($_POST['id'] ?? 0);
        $other = $id > 0 ? db_one('SELECT * FROM admins WHERE id = ?', [$id]) : null;

        if ($other === null) {
            $errors[] = 'That administrator does not exist.';
        } elseif (!admin_zones_ready()) {
            $errors[] = 'The database has no "zones" column yet. Import upgrade-reviewer.sql first: '
                . 'ALTER TABLE admins ADD COLUMN zones VARCHAR(255) DEFAULT NULL AFTER is_owner;';
        } elseif ((int)$other['is_owner'] === 1) {
            $errors[] = 'Owner accounts may use every suffix, so there is nothing to assign.';
        } else {
            $zones = admin_posted_zones();
            if ($zones === '') {
                $errors[] = 'A reviewer needs at least one suffix; use "Make owner" for full access.';
            } else {
                if (admin_zones_ready()) {
                    db_query('UPDATE admins SET zones = ? WHERE id = ?', [$zones, $id]);
                }
                log_action('admin_zones_changed', 'admin', $id, (string)$other['username'] . ' -> ' . $zones);
                flash('Suffixes of ' . (string)$other['username'] . ' saved.', 'ok');
                redirect('admins.php');
            }
        }
    }

    /* ---------------- the entry key of one account ---------------- */

    if (in_array($action, ['entry_generate', 'entry_remove'], true)) {
        $id    = (int)($_POST['id'] ?? 0);
        $other = $id > 0 ? db_one('SELECT * FROM admins WHERE id = ?', [$id]) : null;

        if (!admin_gate_columns_ready()) {
            $errors[] = 'The admins table has no entry-key columns yet. Import upgrade-entries.sql first: '
                . 'ALTER TABLE admins ADD COLUMN gate_slug VARCHAR(64) DEFAULT NULL AFTER zones, '
                . 'ADD COLUMN gate_pass VARCHAR(255) DEFAULT NULL AFTER gate_slug;';
        } elseif ($other === null) {
            $errors[] = 'That administrator does not exist.';
        } elseif ($action === 'entry_generate') {
            do {
                $slug = admin_entry_new_slug();
            } while (db_one('SELECT id FROM admins WHERE gate_slug = ?', [$slug]) !== null
                || $slug === admin_gate_slug());
            db_query('UPDATE admins SET gate_slug = ? WHERE id = ?', [$slug, $id]);
            log_action('admin_entry_created', 'admin', $id,
                'Own entry key created for ' . (string)$other['username']);
            flash('A new entry address was created for ' . (string)$other['username']
                . '. Copy the address below and hand it over - set a pass phrase as well.', 'ok');
            redirect('admins.php');
        } else {
            db_query('UPDATE admins SET gate_slug = NULL, gate_pass = NULL WHERE id = ?', [$id]);
            log_action('admin_entry_removed', 'admin', $id,
                'Own entry key removed for ' . (string)$other['username']);
            flash('The own entry key of ' . (string)$other['username'] . ' was removed; the account uses '
                . 'the master entry again.', 'ok');
            redirect('admins.php');
        }
    }

    if ($action === 'entry_pass') {
        $id    = (int)($_POST['id'] ?? 0);
        $other = $id > 0 ? db_one('SELECT * FROM admins WHERE id = ?', [$id]) : null;
        $pass  = (string)($_POST['entry_pass'] ?? '');

        if (!admin_gate_columns_ready()) {
            $errors[] = 'The admins table has no entry-key columns yet; import upgrade-entries.sql.';
        } elseif ($other === null) {
            $errors[] = 'That administrator does not exist.';
        } elseif ($pass !== '' && strlen($pass) < 10) {
            $errors[] = 'The entry pass phrase must be at least 10 characters long.';
        } else {
            if ($pass !== '') {
                db_query('UPDATE admins SET gate_pass = ? WHERE id = ?', [
                    password_hash($pass, PASSWORD_DEFAULT),
                    $id,
                ]);
                log_action('admin_entry_pass_set', 'admin', $id,
                    'Entry pass phrase set for ' . (string)$other['username']);
                flash('The entry pass phrase of ' . (string)$other['username'] . ' was saved.', 'ok');
            } else {
                flash('Nothing changed: the pass phrase field was empty.', 'info');
            }
            redirect('admins.php');
        }
    }

    /* ---------------- change your own password ---------------- */
    if ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $pass    = (string)($_POST['password'] ?? '');
        $pass2   = (string)($_POST['password2'] ?? '');

        if (!password_verify($current, (string)$admin['password_hash'])) {
            $errors[] = 'The current password is not correct.';
        }
        if (strlen($pass) < 10) {
            $errors[] = 'The new password must be at least 10 characters long.';
        }
        if ($pass !== $pass2) {
            $errors[] = 'The two new passwords do not match.';
        }

        if ($errors === []) {
            db_query('UPDATE admins SET password_hash = ? WHERE id = ?', [
                password_hash($pass, PASSWORD_DEFAULT),
                (int)$admin['id'],
            ]);
            log_action('admin_password_changed', 'admin', (int)$admin['id'], 'Password changed');
            flash('Your password has been changed.', 'ok');
            redirect('admins.php');
        }
    }
}

$admins = db_all('SELECT * FROM admins ORDER BY id ASC');

page_header('Administrators');
?>
<p>
An <strong>owner</strong> account may do everything: settings, announcement, suffixes, reserved names,
mail, Cloudflare, rate limits, user accounts, administrators, audit log and export.
A <strong>reviewer</strong> account may only decide new requests and record changes for the
<strong>suffixes assigned to it</strong>, read those names, and suspend or delete them - everything else
answers 403 and is hidden from its navigation. Every account can change its own password. Actions are
recorded in the audit log.
</p>
<p>
Each account can also get its <strong>own entry key</strong>: its own secret address parameter and its own
pass phrase, independent of the master entry in <em>Settings &rarr; Admin entry</em>. Hand the address over
together with the pass phrase; that entry signs in <strong>that account only</strong>, so a reviewer never
has to know the master entry, and a leaked reviewer key does not open the owner's door. Only an owner can
create, replace or remove these keys here; the account itself never sees its slug in the console.
</p>

<?= error_list($errors) ?>

<?php if (!admin_zones_ready() || !admin_gate_columns_ready()): ?>
<div class="msg error">
  <strong>The database is missing part of the reviewer columns.</strong>
  Everything below still works, but reviewer accounts and their own entry keys need these statements
  (or import <code>upgrade-reviewer.sql</code>):
  <?php if (!admin_zones_ready()): ?>
  <pre class="wrap code-block">ALTER TABLE admins ADD COLUMN zones VARCHAR(255) DEFAULT NULL AFTER is_owner;</pre>
  <?php endif; ?>
  <?php if (!admin_gate_columns_ready()): ?>
  <pre class="wrap code-block">ALTER TABLE admins ADD COLUMN gate_slug VARCHAR(64) DEFAULT NULL AFTER zones,
  ADD COLUMN gate_pass VARCHAR(255) DEFAULT NULL AFTER gate_slug;</pre>
  <?php endif; ?>
  The <a href="<?= h(url('selftest.php')) ?>">self test</a> lists them as well.
</div>
<?php endif; ?>

<?php if (!admin_gate_enabled()): ?>
<div class="msg info">
  The hidden entry (<em>Settings &rarr; Admin entry</em>) is switched off, so the per-account entry keys
  below have no effect yet: without the hidden entry every <code>/admin/</code> address is reachable
  directly and only the password protects it.
</div>
<?php endif; ?>

<table>
  <tr><th>ID</th><th>User name</th><th>E-mail</th><th>Owner</th><th>Suffixes (reviewer)</th><th>Created</th>
      <th>Last sign-in</th><th>Own entry key (address + pass phrase)</th><th></th></tr>
  <?php foreach ($admins as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><?= h((string)$row['username']) ?><?= (int)$row['id'] === (int)$admin['id'] ? ' <span class="pill">you</span>' : '' ?></td>
    <td><?= h((string)($row['email'] ?? '')) ?></td>
    <td><?= (int)$row['is_owner'] === 1 ? 'yes' : 'no' ?></td>
    <td>
      <?php if ((int)$row['is_owner'] === 1): ?>
      <span class="small">everything</span>
      <?php else: ?>
      <form method="post" action="admins.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_zones">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <?php
        $current = array_map('trim', explode(',', (string)($row['zones'] ?? '')));
        foreach (zone_list() as $zoneOption):
            $zoneName = strtolower((string)$zoneOption['name']); ?>
        <label class="inline">
          <input type="checkbox" name="zones[]" value="<?= h($zoneName) ?>"
                 <?= in_array($zoneName, $current, true) ? 'checked' : '' ?>>
          <?= h($zoneName) ?>
        </label>
        <?php endforeach; ?>
        <?php if (zone_list() === []): ?><span class="err">no suffix exists yet</span><?php endif; ?>
        <input type="submit" value="Save">
      </form>
      <?php endif; ?>
    </td>
    <td><?= h(format_date((string)$row['created_at'])) ?></td>
    <td><?= h(format_date((string)($row['last_login_at'] ?? ''))) ?><br>
        <span class="small"><?= h((string)($row['last_login_ip'] ?? '')) ?></span></td>
    <td>
      <?php if ((int)$row['is_owner'] === 1): ?>
      <span class="small">uses the master entry</span>
      <?php elseif (!admin_gate_columns_ready()): ?>
      <span class="err">entry keys unavailable</span>
      <?php else: ?>
      <?php $entry = admin_entry_url($row); ?>
      <?php if ($entry === ''): ?>
      <span class="small">no own entry: uses the master entry</span>
      <form class="inline" method="post" action="admins.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="entry_generate">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Create an own entry address">
      </form>
      <?php else: ?>
      <span class="small">own entry address:</span>
      <pre class="wrap code-block"><?= h($entry) ?></pre>
      <span class="small">
        pass phrase: <?= admin_entry_has_pass($row)
            ? '<span class="ok">set</span>'
            : '<span class="err">none yet - the address alone opens the sign-in form</span>' ?>
      </span>
      <form method="post" action="admins.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="entry_pass">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <label for="entry_pass_<?= (int)$row['id'] ?>">Set the pass phrase (at least 10 characters)</label>
        <input type="password" id="entry_pass_<?= (int)$row['id'] ?>" name="entry_pass" value=""
               autocomplete="new-password">
        <input type="submit" value="Save the pass phrase">
      </form>
      <form class="inline" method="post" action="admins.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="entry_generate">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Replace the address">
      </form>
      <form class="inline" method="post" action="admins.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="entry_remove">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Remove the own entry">
      </form>
      <?php endif; ?>
      <?php endif; ?>
    </td>
    <td class="actions">
      <?php if (is_owner() && (int)$row['id'] !== (int)$admin['id']): ?>
      <form class="inline" method="post" action="admins.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_owner">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="<?= (int)$row['is_owner'] === 1 ? 'Revoke owner' : 'Make owner' ?>">
      </form>
      <form class="inline" method="post" action="admins.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Remove">
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<?php if (is_owner()): ?>
<h2>Add an administrator</h2>
<form method="post" action="admins.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add">
  <label for="username">User name</label>
  <input type="text" id="username" name="username" maxlength="32" autocomplete="off">
  <label for="email">E-mail (optional)</label>
  <input type="text" id="email" name="email" maxlength="190">
  <label for="password">Password (at least 10 characters)</label>
  <input type="password" id="password" name="password" autocomplete="new-password">
  <label for="password2">Repeat password</label>
  <input type="password" id="password2" name="password2" autocomplete="new-password">
  <p>
    <input type="checkbox" id="is_owner" name="is_owner" value="1">
    <label class="inline" for="is_owner">Owner rights (full access to everything)</label>
  </p>
  <p>
    Without owner rights the account is a <strong>reviewer</strong>: tick the suffixes it may decide
    requests and record changes for, read, suspend and delete.
  </p>
  <?php foreach (zone_list() as $zoneOption): ?>
  <label class="inline">
    <input type="checkbox" name="zones[]" value="<?= h(strtolower((string)$zoneOption['name'])) ?>">
    <?= h((string)$zoneOption['name']) ?>
  </label>
  <?php endforeach; ?>
  <?php if (zone_list() === []): ?>
  <p class="hint">No suffix exists yet - create one under <a href="zones.php">Zones</a> first; a reviewer
  needs at least one.</p>
  <?php endif; ?>
  <p><input type="submit" value="Create administrator"></p>
</form>
<?php endif; ?>

<h2>Change your own password</h2>
<form method="post" action="admins.php">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="password">
  <label for="current_password">Current password</label>
  <input type="password" id="current_password" name="current_password" autocomplete="current-password">
  <label for="new_password">New password</label>
  <input type="password" id="new_password" name="password" autocomplete="new-password">
  <label for="new_password2">Repeat new password</label>
  <input type="password" id="new_password2" name="password2" autocomplete="new-password">
  <p><input type="submit" value="Change password"></p>
</form>
<?php
page_footer();

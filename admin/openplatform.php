<?php
declare(strict_types=1);

/**
 * freedns.my - back office: the open platform (OAuth 2.0 applications).
 *
 * Applications are reviewed here by hand, which is the whole gate: a pending application gets no token, a revoked
 * one stops answering immediately, and the client_secret is shown exactly once - the moment it is generated - and
 * only its sha256 is stored afterwards. Re-generating a secret (and revoking an application) kills every token
 * that application already holds, because a leaked key must not stay usable.
 *
 * Scopes are chosen here and again on the consent page: the user sees, in words, what is asked for, and may
 * refuse. Nothing here decides what an application can do - op_token_verify() checks the scope on every call.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('Open platform');
$errors = [];
$fresh  = null;   // a secret that is shown once, right after it is minted

if (op_unavailable()) {
    page_header('Open platform');
    echo '<div class="msg error">The tables are missing: import <code>upgrade-openplatform.sql</code> first '
        . '(phpMyAdmin, or the repair button on the self-check page). Nothing here works until then.</div>';
    page_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);
    $app    = $id > 0 ? op_app($id) : null;

    if ($action === 'create') {
        $name    = trim((string)($_POST['name'] ?? ''));
        $contact = trim((string)($_POST['contact_email'] ?? ''));
        $home    = trim((string)($_POST['homepage'] ?? ''));
        $about   = trim((string)($_POST['description'] ?? ''));
        $uris    = trim((string)($_POST['redirect_uris'] ?? ''));
        $scopes  = op_scopes_clean(implode(' ', (array)($_POST['scopes'] ?? [])));
        $public  = isset($_POST['is_public']) ? 1 : 0;

        if ($name === '' || mb_strlen($name) > 80) {
            $errors[] = 'A name is needed (up to 80 characters) - it is what the user sees on the consent page.';
        }
        if (!valid_email_addr($contact)) {
            $errors[] = 'A valid contact address is needed.';
        }
        if ($home !== '' && preg_match('#^https?://#i', $home) !== 1) {
            $errors[] = 'The home page has to be a full http:// or https:// address.';
        }
        $clean = [];
        foreach (preg_split('/\r\n|\r|\n/', $uris) ?: [] as $one) {
            $one = trim($one);
            if ($one === '') {
                continue;
            }
            if (preg_match('#^https://#i', $one) !== 1) {
                $errors[] = 'Every redirect address must use https:// (got: ' . $one . ').';
                continue;
            }
            $clean[] = $one;
        }
        if ($clean === []) {
            $errors[] = 'At least one https:// redirect address is required.';
        }
        if ($scopes === '') {
            $errors[] = 'Pick at least one scope.';
        }

        if ($errors === []) {
            $client = op_new_client();
            db_query(
                'INSERT INTO oauth_apps (name, owner_user_id, contact_email, homepage, description, redirect_uris,
                    scopes, status, is_public, client_id, client_secret_hash, secret_hint, decided_by, created_at, decided_at)
                 VALUES (?, NULL, ?, ?, ?, ?, ?, \'approved\', ?, ?, ?, ?, ?, NOW(), NOW())',
                [$name, $contact, $home !== '' ? $home : null, $about !== '' ? $about : null,
                 implode("\n", $clean), $scopes, $public,
                 $client['client_id'], $public === 1 ? null : $client['secret_hash'], $public === 1 ? null : $client['secret_hint'],
                 (string)$admin['username']]
            );
            $newId = (int)db()->lastInsertId();
            log_action('open_app_create', 'settings', null, $name . ' (' . $client['client_id'] . ') created by hand');
            if ($public === 0) {
                $fresh = ['app' => $name, 'id' => $newId, 'client_id' => $client['client_id'], 'secret' => $client['secret']];
            } else {
                flash('Application created (public client: no secret, PKCE only).', 'ok');
                redirect('openplatform.php');
            }
        }
    }

    if ($action === 'approve' && $app !== null) {
        db_query('UPDATE oauth_apps SET status = \'approved\', review_note = NULL, decided_by = ?, decided_at = NOW()
                  WHERE id = ?', [(string)$admin['username'], $id]);
        log_action('open_app_approve', 'settings', null, (string)$app['name'] . ' approved');
        flash('Approved: the application can now be used.', 'ok');
        redirect('openplatform.php');
    }

    if ($action === 'reject' && $app !== null) {
        $note = substr(trim((string)($_POST['review_note'] ?? '')), 0, 255);
        db_query('UPDATE oauth_apps SET status = \'rejected\', review_note = ?, decided_by = ?, decided_at = NOW()
                  WHERE id = ?', [$note !== '' ? $note : null, (string)$admin['username'], $id]);
        op_app_revoke_tokens($id);
        log_action('open_app_reject', 'settings', null, (string)$app['name'] . ' rejected');
        flash('Rejected, and every token it held has been revoked.', 'ok');
        redirect('openplatform.php');
    }

    if ($action === 'revoke' && $app !== null) {
        db_query('UPDATE oauth_apps SET status = \'revoked\', decided_by = ?, decided_at = NOW() WHERE id = ?',
            [(string)$admin['username'], $id]);
        op_app_revoke_tokens($id);
        log_action('open_app_revoke', 'settings', null, (string)$app['name'] . ' revoked');
        flash('Revoked: it answers nothing from now on, and its tokens are dead.', 'ok');
        redirect('openplatform.php');
    }

    if ($action === 'save' && $app !== null) {
        $uris   = trim((string)($_POST['redirect_uris'] ?? ''));
        $scopes = op_scopes_clean(implode(' ', (array)($_POST['scopes'] ?? [])));
        $clean  = [];
        foreach (preg_split('/\r\n|\r|\n/', $uris) ?: [] as $one) {
            $one = trim($one);
            if ($one === '') {
                continue;
            }
            if (preg_match('#^https://#i', $one) !== 1) {
                $errors[] = 'Every redirect address must use https:// (got: ' . $one . ').';
                continue;
            }
            $clean[] = $one;
        }
        if ($clean === []) {
            $errors[] = 'At least one https:// redirect address is required.';
        }
        if ($scopes === '') {
            $errors[] = 'Pick at least one scope.';
        }
        if ($errors === []) {
            db_query('UPDATE oauth_apps SET redirect_uris = ?, scopes = ? WHERE id = ?',
                [implode("\n", $clean), $scopes, $id]);
            log_action('open_app_save', 'settings', null, (string)$app['name'] . ' scopes/redirects changed');
            flash('Saved. A scope removed here stops working at once; tokens already given keep their own list.', 'ok');
            redirect('openplatform.php');
        }
    }

    if ($action === 'newsecret' && $app !== null) {
        $client = op_new_client();
        db_query('UPDATE oauth_apps SET client_secret_hash = ?, secret_hint = ?, is_public = 0 WHERE id = ?',
            [$client['secret_hash'], $client['secret_hint'], $id]);
        op_app_revoke_tokens($id);
        log_action('open_app_secret', 'settings', null, (string)$app['name'] . ' secret regenerated, tokens revoked');
        $fresh = ['app' => (string)$app['name'], 'id' => $id,
                  'client_id' => (string)$app['client_id'], 'secret' => $client['secret']];
    }
}

$rows = db_all('SELECT * FROM oauth_apps ORDER BY FIELD(status, \'pending\', \'approved\', \'rejected\', \'revoked\'), id DESC');
$by   = ['pending' => [], 'approved' => [], 'rejected' => [], 'revoked' => []];
foreach ($rows as $row) {
    $by[(string)$row['status']][] = $row;
}

$scopesAll = op_scopes();
$editId    = (int)($_GET['edit'] ?? 0);
$edit      = $editId > 0 ? op_app($editId) : null;

page_header('Open platform');
?>
<p>
  Third parties sign a user in with <strong>Sign in with freedns.my</strong> (OAuth 2.0, authorisation code with
  PKCE). Endpoints: <code>/oauth/authorize.php</code>, <code>/oauth/token.php</code>, <code>/oauth/userinfo.php</code>,
  and the resources under <code>/open/v1/</code>. Nothing is handed over until the user presses "Allow" on the
  consent page, and an application only exists once it is approved below.
</p>

<?php if ($fresh !== null): ?>
<div class="msg ok">
  <strong>Copy the client secret now - it is shown once.</strong><br>
  Application: <?= h($fresh['app']) ?> (id <?= (int)$fresh['id'] ?>)<br>
  <code>client_id</code>: <code><?= h($fresh['client_id']) ?></code><br>
  <code>client_secret</code>: <code><?= h($fresh['secret']) ?></code>
</div>
<?php endif; ?>

<div class="msg <?= $by['pending'] === [] ? 'ok' : 'error' ?>">
  <?= count($by['pending']) ?> waiting for review &middot; <?= count($by['approved']) ?> approved &middot;
  <?= count($by['rejected']) ?> rejected &middot; <?= count($by['revoked']) ?> revoked
</div>

<?= error_list($errors) ?>

<h2>Add an application by hand</h2>
<p class="hint">For your own integrations, or to test before the console page exists. Users will be able to submit
their own from <code>/account/apps.php</code>.</p>
<form method="post" action="openplatform.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="create">
<label for="name">Name (shown to the user when asked to allow)</label>
<input type="text" id="name" name="name" maxlength="80" required>
<label for="contact_email">Contact address</label>
<input type="text" id="contact_email" name="contact_email" maxlength="190" required>
<label for="homepage">Home page (optional)</label>
<input type="text" id="homepage" name="homepage" maxlength="190" placeholder="https://">
<label for="description">What it does (for the review)</label>
<textarea id="description" name="description" rows="3"></textarea>
<label for="redirect_uris">Redirect addresses - one per line, must be exact at authorisation time</label>
<textarea id="redirect_uris" name="redirect_uris" rows="3" placeholder="https://app.example.org/oauth/freedns/callback" required></textarea>
<fieldset>
  <legend>Scopes this application may ask for</legend>
  <?php foreach ($scopesAll as $key => $label): ?>
  <label class="small"><input type="checkbox" name="scopes[]" value="<?= h($key) ?>"> <code><?= h($key) ?></code>
    - <?= h($label[0]) ?></label><br>
  <?php endforeach; ?>
</fieldset>
<p class="hint"><label class="small"><input type="checkbox" name="is_public" value="1"> Public client (no secret:
  PKCE only, for a browser or mobile app).</label></p>
<p><input type="submit" value="Create and approve"></p>
</form>

<?php if ($edit !== null): ?>
<h2>Edit: <?= h((string)$edit['name']) ?></h2>
<form method="post" action="openplatform.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">
<input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
<label for="e_uris">Redirect addresses - one per line</label>
<textarea id="e_uris" name="redirect_uris" rows="3"><?= h((string)$edit['redirect_uris']) ?></textarea>
<fieldset>
  <legend>Scopes</legend>
  <?php foreach ($scopesAll as $key => $label): ?>
  <label class="small"><input type="checkbox" name="scopes[]" value="<?= h($key) ?>"
    <?= op_has((string)$edit['scopes'], $key) ? 'checked' : '' ?>> <code><?= h($key) ?></code>
    - <?= h($label[0]) ?></label><br>
  <?php endforeach; ?>
</fieldset>
<?php if ((int)$edit['is_public'] === 0): ?>
<p><input type="submit" value="Save"></p>
</form>
<form method="post" action="openplatform.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="newsecret">
<input type="hidden" name="id" value="<?= (int)$edit['id'] ?>">
<p><input type="submit" value="Regenerate the secret (revokes every token it holds)"></p>
</form>
<?php else: ?>
<p><input type="submit" value="Save"></p>
</form>
<?php endif; ?>
<?php endif; ?>

<h2>Applications</h2>
<?php if ($rows === []): ?>
<p class="hint">None yet.</p>
<?php else: ?>
<table>
  <tr>
    <th>Name</th><th>Status</th><th>Scopes</th><th>Redirect addresses</th><th>Users / tokens</th><th>Last used</th><th></th>
  </tr>
  <?php foreach ($rows as $row): ?>
  <?php $counts = op_app_counts((int)$row['id']); ?>
  <tr>
    <td>
      <strong><?= h((string)$row['name']) ?></strong>
      <div class="small"><code><?= h((string)$row['client_id']) ?></code><?= (int)$row['is_public'] === 1
          ? ' (public client)' : (trim((string)$row['secret_hint']) !== '' ? ' …' . h((string)$row['secret_hint']) : '') ?>
      </div>
      <?php if (trim((string)$row['homepage']) !== ''): ?>
      <div class="small"><a href="<?= h((string)$row['homepage']) ?>" rel="nofollow noopener" target="_blank"><?= h((string)$row['homepage']) ?></a></div>
      <?php endif; ?>
      <div class="small"><?= h((string)$row['contact_email']) ?></div>
    </td>
    <td><code><?= h((string)$row['status']) ?></code></td>
    <td class="small"><?= $row['scopes'] === '' ? '<span class="small">-</span>' : h(op_scopes_text((string)$row['scopes'])) ?></td>
    <td class="small"><?= nl2br(h((string)$row['redirect_uris'])) ?></td>
    <td class="small"><?= (int)$counts['users'] ?> / <?= (int)$counts['tokens'] ?><div class="small"><?= (int)$row['requests'] ?> calls</div></td>
    <td class="small"><?= h((string)($row['last_used_at'] ?? '-')) ?></td>
    <td class="small">
      <a href="openplatform.php?edit=<?= (int)$row['id'] ?>">edit</a>
      <?php if ((string)$row['status'] !== 'approved'): ?>
      <form method="post" action="openplatform.php" style="display:inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="approve">
      </form>
      <?php endif; ?>
      <?php if ((string)$row['status'] !== 'revoked'): ?>
      <form method="post" action="openplatform.php" style="display:inline">
        <?= csrf_field() ?><input type="hidden" name="action" value="revoke"><input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="revoke">
      </form>
      <?php endif; ?>
      <?php if (trim((string)$row['review_note']) !== ''): ?>
      <div class="small">note: <?= h((string)$row['review_note']) ?></div>
      <?php endif; ?>
      <?php if (trim((string)$row['description']) !== ''): ?>
      <div class="small"><?= h((string)$row['description']) ?></div>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>What is still to come</h2>
<ul class="small">
  <li><code>/oauth/authorize.php</code> - the consent screen the user sees.</li>
  <li><code>/oauth/token.php</code> - code for tokens, and refresh.</li>
  <li><code>/oauth/userinfo.php</code> and <code>/open/v1/domains|records|mailboxes|applications</code> - the data,
      each behind its own scope.</li>
  <li><code>/account/apps.php</code> - where a holder submits an application and sees who is connected, with a
      "disconnect" button.</li>
</ul>
<?php
page_footer();

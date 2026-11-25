<?php
declare(strict_types=1);

/**
 * freedns.my - back office: putting the HTTP API down for maintenance.
 *
 * The switch here is the one thing /api.php checks before it answers anything: while it is on, every call gets
 * HTTP 503 with {"ok":false,"error":"maintenance"} plus the note written below, and a Retry-After header so a
 * script backs off instead of hammering. Addresses in the allow list keep working normally, which is how the
 * operator tests during the downtime.
 *
 * The note is stored per language (api_maint_note / api_maint_note_zh) like every other translated setting, so a
 * Chinese caller reads Chinese. "Expected back" is free text on purpose: a date one has to guess is worse than
 * "unknown".
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('API maintenance');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'save') {
        $on     = isset($_POST['api_maint']) ? '1' : '0';
        $until  = trim((string)($_POST['api_maint_until'] ?? ''));
        $note   = trim((string)($_POST['api_maint_note'] ?? ''));
        $noteZh = trim((string)($_POST['api_maint_note_zh'] ?? ''));
        $allow  = trim((string)($_POST['api_maint_allow'] ?? ''));

        // The allow list is addresses only: anything that is not one is dropped rather than stored and never used.
        $clean = [];
        foreach (preg_split('/[\s,]+/', $allow) ?: [] as $one) {
            $one = trim($one);
            if ($one !== '' && filter_var($one, FILTER_VALIDATE_IP) !== false) {
                $clean[] = $one;
            } elseif ($one !== '') {
                $errors[] = '"' . $one . '" is not an IP address, so it was dropped from the allow list.';
            }
        }
        $allow = implode(' ', array_unique($clean));

        if (mb_strlen($until) > 80) {
            $errors[] = 'The "expected back" line is longer than 80 characters.';
        }
        if (mb_strlen($note) > 500 || mb_strlen($noteZh) > 500) {
            $errors[] = 'The note is longer than 500 characters.';
        }

        if ($errors === []) {
            $changed = 0;
            foreach ([
                'api_maint'         => $on,
                'api_maint_until'   => $until,
                'api_maint_note'    => $note,
                'api_maint_note_zh' => $noteZh,
                'api_maint_allow'   => $allow,
            ] as $key => $value) {
                if ((string)setting($key, '') !== $value) {
                    set_setting($key, $value);
                    $changed++;
                }
            }
            log_action('api_maint_save', 'settings', null,
                'API maintenance ' . ($on === '1' ? 'ON' : 'off') . ', ' . $changed . ' value(s) changed');
            flash($changed === 0 ? 'Nothing changed.' : 'Saved (' . $changed . ' value(s)).', 'ok');
            redirect('api-maintenance.php');
        }
    }
}

$on     = function_exists('api_maint_on') ? api_maint_on() : false;
$rawOn  = (string)setting('api_maint', '0') === '1';
$until  = (string)setting('api_maint_until', '');
$note   = (string)setting('api_maint_note', '');
$noteZh = (string)setting('api_maint_note_zh', '');
$allow  = (string)setting('api_maint_allow', '');

// What a caller would get right now, built the same way api.php builds it.
$example = '{"ok":false,"error":"maintenance","message":"'
    . ($note !== '' ? $note : 'The API is down for maintenance and is temporarily unavailable.')
    . ($until !== '' ? ' (Expected back: ' . $until . ')' : '')
    . '"}';

$tokens = null;
try {
    if (function_exists('api_table_ready') && api_table_ready()) {
        $row    = db_one('SELECT COUNT(*) AS n FROM api_tokens WHERE revoked_at IS NULL');
        $tokens = $row === null ? 0 : (int)$row['n'];
    }
} catch (Throwable $e) {
    $tokens = null;
}

page_header('API maintenance');
?>
<p>
  The HTTP API lives at <a href="<?= h(url('api.php')) ?>">/api.php</a> and is documented on the key page
  (<a href="<?= h(url('account/api.php')) ?>">/account/api.php</a>). While maintenance is on, every call is answered
  with <code>HTTP 503</code> and the notice below, plus a <code>Retry-After</code> header; a caller that is wrong
  (bad key, bad name) keeps getting its own error instead, so people can tell the two apart.
</p>

<div class="msg <?= $on ? 'error' : 'ok' ?>">
  <?php if ($on): ?>
    <strong>The API is DOWN right now.</strong> Callers get 503<?= $allow !== ''
        ? ' - except ' . h($allow) . ', which is allowed through.' : '.' ?>
  <?php else: ?>
    The API is up and answering normally.
  <?php endif; ?>
  <?php if ($tokens !== null): ?>
    &middot; <?= (int)$tokens ?> live key(s) in <code>api_tokens</code>.
  <?php else: ?>
    &middot; <span class="small">the <code>api_tokens</code> table could not be read.</span>
  <?php endif; ?>
</div>

<?= error_list($errors) ?>

<form method="post" action="api-maintenance.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">

<h2>The switch</h2>
<label class="small">
  <input type="checkbox" name="api_maint" value="1"<?= $rawOn ? ' checked' : '' ?>>
  Take the API down for maintenance (下架维护)
</label>
<p class="hint">Saved state: <strong><?= $rawOn ? 'ON' : 'off' ?></strong><?= $rawOn !== $on
    ? ' - you are reading this from an allow-listed address, so the API still answers you.' : '' ?></p>

<h2>What callers are told</h2>
<label for="api_maint_note">Note (English)</label>
<textarea id="api_maint_note" name="api_maint_note" rows="3" maxlength="500"><?= h($note) ?></textarea>
<label for="api_maint_note_zh">Note (Chinese)</label>
<textarea id="api_maint_note_zh" name="api_maint_note_zh" rows="3" maxlength="500"><?= h($noteZh) ?></textarea>
<p class="hint">Left empty, a sensible sentence in the caller's language is used instead.</p>

<label for="api_maint_until">Expected back (free text)</label>
<input type="text" id="api_maint_until" name="api_maint_until" maxlength="80" value="<?= h($until) ?>"
       placeholder="e.g. 2026-10-06 12:00 +08, or leave empty">
<p class="hint">Shown in brackets after the note. An honest "unknown" beats a guessed date.</p>

<label for="api_maint_allow">Addresses still allowed through</label>
<input type="text" id="api_maint_allow" name="api_maint_allow" maxlength="190" value="<?= h($allow) ?>"
       placeholder="203.0.113.7 2001:db8::1">
<p class="hint">Space or comma separated IPv4/IPv6. Use it for your own scripts and for this check:
  <code>curl -H "Authorization: Bearer &lt;key&gt;" <?= h(url('api.php?action=me')) ?></code>.
  Anything that is not an IP is dropped and reported.</p>

<p><input type="submit" value="Save"></p>
</form>

<h2>What a caller gets while it is on</h2>
<pre style="white-space:pre-wrap">HTTP/1.1 503 Service Unavailable
Retry-After: 3600
Content-Type: application/json; charset=utf-8

<?= h($example) ?></pre>
<p class="hint">The key page (<a href="<?= h(url('account/api.php')) ?>">/account/api.php</a>) shows the same note to
holders while the switch is on, so nobody mistakes downtime for a broken key.</p>

<h2>Please remember</h2>
<ul class="small">
  <li>Taking the API down does <strong>not</strong> stop published DNS records: names keep resolving, only the way
      to change them over HTTP stops.</li>
  <li>Keys are not touched, and they keep working the moment the switch goes off.</li>
  <li>Dynamic DNS updates (<code>/ddns.php?token=…</code>) are a different door and are <strong>not</strong> closed
      by this switch.</li>
</ul>
<?php
page_footer();

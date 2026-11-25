<?php
declare(strict_types=1);

/**
 * freedns.my - back office: maintenance mode.
 *
 * One switch, an editable window, an editable reason, and the text a visitor reads (English and Chinese
 * separately). While it is on, registering, adding a name and adding a mailbox are closed; signing in,
 * changing the records of a name you already hold and using existing mailboxes keep working.
 *
 * The back office is English-only, like the rest of it.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('Maintenance mode');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'save') {
        $changed = 0;
        $values = [
            'maintenance_from'   => trim((string)($_POST['maintenance_from'] ?? '')),
            'maintenance_to'     => trim((string)($_POST['maintenance_to'] ?? '')),
            'maintenance_reason' => trim((string)($_POST['maintenance_reason'] ?? '')),
            'maintenance_text'   => trim((string)($_POST['maintenance_text'] ?? '')),
            'maintenance_text_zh' => trim((string)($_POST['maintenance_text_zh'] ?? '')),
            // A planned window, if the operator wants one. Both ends are optional; the format is checked so a
            // typo cannot silently schedule something at the wrong time.
            'maintenance_start_at' => trim((string)($_POST['maintenance_start_at'] ?? '')),
            'maintenance_end_at'   => trim((string)($_POST['maintenance_end_at'] ?? '')),
            // Two switches that used to need SQL. A checkbox that is not sent means "off".
            'srv_readonly'      => isset($_POST['srv_readonly']) ? '1' : '0',
            'mailroute_enabled' => isset($_POST['mailroute_enabled']) ? '1' : '0',
        ];

        foreach (['maintenance_start_at', 'maintenance_end_at'] as $when) {
            if ($values[$when] !== '' && strtotime($values[$when]) === false) {
                $errors[] = 'The date and time have to look like 2026-10-05 02:00 (got: ' . $values[$when] . ').';
            }
        }
        foreach ($values as $key => $value) {
            if ((string)setting($key, '') !== $value) {
                set_setting($key, $value);
                $changed++;
            }
        }

        $want = isset($_POST['maintenance_on']) ? '1' : '0';
        if ((string)setting('maintenance_on', '0') !== $want) {
            set_setting('maintenance_on', $want);
            $changed++;
            log_action('maintenance_mode', 'settings', null,
                $want === '1' ? 'maintenance mode switched ON' : 'maintenance mode switched off');
        }

        flash($changed === 0 ? 'Nothing changed.' : 'Saved (' . $changed . ' value(s)).', 'ok');
        redirect('maintenance.php');
    }
}

$on = maintenance_active();

page_header('Maintenance mode');
?>
<p>
While this is on, the things that <strong>create</strong> something are closed and everything else keeps working:
</p>
<table>
  <tr><th>Closed</th><td>registering an account &middot; applying for a name &middot; creating or applying for a
      mailbox &middot; adding a forwarding address</td></tr>
  <tr><th>Still working</th><td>signing in &middot; changing the records of a name you already hold &middot;
      using existing mailboxes and changing their password &middot; the whole back office</td></tr>
</table>
<p class="hint">
A refused request is answered with the notice below and a <code>503</code>, and it is written to the audit log.
Administrators are never blocked, so you can always switch this off again.
</p>
<?= error_list($errors) ?>

<?php if ($on): ?>
<div class="msg error"><strong>Maintenance mode is ON</strong> - visitors cannot register, add a name or add a
mailbox right now.</div>
<?php else: ?>
<div class="msg ok">Maintenance mode is off; the site works as usual.</div>
<?php endif; ?>

<h2>What a visitor reads</h2>
<pre style="white-space:pre-wrap;border:1px solid #ccc;padding:.6em"><?= h(setting_lang('maintenance_text')) ?></pre>
<p class="hint">That is the text in force for the language you are reading the back office in (English). The
Chinese text is below.</p>

<form method="post" action="maintenance.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">
<p>
  <input type="checkbox" id="maintenance_on" name="maintenance_on" value="1"<?= $on ? ' checked' : '' ?>>
  <label class="inline" for="maintenance_on"><strong>Turn maintenance mode on</strong></label>
</p>
<label for="maintenance_from">Window: from (free text, for example "2026-10-05 02:00")</label>
<input type="text" id="maintenance_from" name="maintenance_from" maxlength="120"
       value="<?= h((string)setting('maintenance_from', '')) ?>">
<label for="maintenance_to">Window: to</label>
<input type="text" id="maintenance_to" name="maintenance_to" maxlength="120"
       value="<?= h((string)setting('maintenance_to', '')) ?>">
<p class="hint">The two fields above are only <em>text shown to visitors</em>. To have maintenance switch itself
on and off, fill the two below as well - the window above is then usually written the same way.</p>
<label for="maintenance_start_at">Planned: starts at (YYYY-MM-DD HH:MM, empty = no plan)</label>
<input type="text" id="maintenance_start_at" name="maintenance_start_at" maxlength="19"
       value="<?= h((string)setting('maintenance_start_at', '')) ?>" placeholder="2026-10-05 02:00">
<label for="maintenance_end_at">Planned: ends at (empty = until you stop it)</label>
<input type="text" id="maintenance_end_at" name="maintenance_end_at" maxlength="19"
       value="<?= h((string)setting('maintenance_end_at', '')) ?>" placeholder="2026-10-05 04:00">
<p class="hint">Right now: <strong><?= h(maintenance_active() ? 'maintenance is ON' : 'maintenance is off') ?></strong>
 (<?= h(maintenance_source() === 'manual' ? 'by the switch above'
      : (maintenance_source() === 'planned' ? 'by the planned window' : 'nothing is scheduled')) ?>).</p>
<label for="maintenance_reason">Reason (shown as "Reason: ..." when the text below is empty)</label>
<input type="text" id="maintenance_reason" name="maintenance_reason" maxlength="190"
       value="<?= h((string)setting('maintenance_reason', '')) ?>">
<label for="maintenance_text">Text shown to visitors (English) - leave empty to build it from the window and the reason</label>
<textarea id="maintenance_text" name="maintenance_text" rows="6"><?= h((string)setting('maintenance_text', '')) ?></textarea>
<label for="maintenance_text_zh">Text shown to visitors (Chinese)</label>
<textarea id="maintenance_text_zh" name="maintenance_text_zh" rows="6"><?= h((string)setting('maintenance_text_zh', '')) ?></textarea>
<p class="hint">Use plain text; line breaks are kept. Suggested wording:</p>
<pre style="white-space:pre-wrap">当前系统正在进行例行维护，维护时间：_____，维护原因：_____。
维护期间：新增域名、新增邮箱、注册账户暂不可用；已有账户可继续登录、修改已有域名的解析、使用已有邮箱。</pre>
<h2>Other switches</h2>
<p class="hint">These two have nothing to do with maintenance mode; they live here because this page is the
place for site-wide switches. Both are saved by the same button.</p>
<p>
  <input type="checkbox" id="srv_readonly" name="srv_readonly" value="1"<?= rainyun_readonly() ? ' checked' : '' ?>>
  <label class="inline" for="srv_readonly"><strong>Cloud servers are read-only</strong> &mdash; holders can see
  their servers and their state, but cannot bind, start, stop, reboot, reinstall, back up or change the firewall;
  the page says the feature is under maintenance. Uncheck it to hand the feature back.</label>
</p>
<p>
  <input type="checkbox" id="mailroute_enabled" name="mailroute_enabled" value="1"<?= mailroute_enabled() ? ' checked' : '' ?>>
  <label class="inline" for="mailroute_enabled"><strong>Old Cloudflare mail forwarding is open</strong> &mdash;
  untick it to retire it: the old page then tells visitors the mail service has moved and links them to the new
  Email page. Unticking also stops the "Authentication error" from Cloudflare, because nothing is asked of it
  any more.</label>
</p>
<?php if (rainyun_readonly()): ?>
<p class="hint">Cloud servers: read-only is ON.</p>
<?php endif; ?>
<?php if (!mailroute_enabled()): ?>
<p class="hint">Old forwarding: closed (visitors are sent to the new Email page).</p>
<?php endif; ?>

<p><input type="submit" value="Save"></p>
</form>
<?php
page_footer();

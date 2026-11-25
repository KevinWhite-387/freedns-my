<?php
declare(strict_types=1);

/**
 * freedns.my - back office: which interface goes to which browser.
 *
 * Two jobs on one page:
 *
 *   1. the switch that decides whether the browser picks the interface at all (`ui_auto_pick`), with the table
 *      of what every family actually gets - so the answer to "why did that visitor see the old one?" is on a
 *      page instead of in somebody's memory;
 *   2. a live reading of the browser asking right now: family, engine, version, shell, which interface that
 *      earns, and whether the old-browser notice would be shown. Useful when a holder writes in saying "the
 *      site looks broken", because they can be asked to open this page and read one line.
 *
 * The detection itself lives in inc/browser.php and is pure PHP: no JavaScript, no library, no cookie.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin  = require_admin();
require_owner('Interface and browsers');
$errors = [];
$done   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'save') {
        $auto = isset($_POST['ui_auto_pick']) ? '1' : '0';
        set_setting('ui_auto_pick', $auto);
        log_action('ui_auto_save', 'settings', null, 'ui_auto_pick = ' . $auto);
        flash($auto === '1'
            ? 'Saved: the interface now follows the browser.'
            : 'Saved: visitors get the classic interface again, whatever their browser.', 'ok');
        redirect(url('admin/ui-browser.php'));
    }
}

$auto   = setting_bool('ui_auto_pick', true);
$now    = function_exists('fd_browser') ? fd_browser() : ['key' => '?', 'name' => 'unknown', 'version' => 0.0,
                                                          'engine' => '?', 'shell' => ''];
$label  = function_exists('fd_browser_label') ? fd_browser_label() : 'unknown';
$uiNow  = function_exists('fd_ui_default') ? fd_ui_default() : 'classic';
$warn   = function_exists('fd_browser_outdated') && fd_browser_outdated();
$noRail = function_exists('fd_ie_no_media') && fd_ie_no_media();

$uiNames = ['classic' => 'Classic (the old interface)', 'modern' => 'UI 1.0', 'modern2' => 'UI 2.0'];

/** The table of families, written out rather than derived, so it can be read and argued with. */
$rows = [
    ['Internet Explorer 6, 7, 8', 'trident', 'classic', 'Yes - and no advertising rails (@media is unsupported)'],
    ['Internet Explorer 9', 'trident', 'classic', 'Yes'],
    ['Internet Explorer 10, 11', 'trident', 'classic', 'No'],
    ['Edge Legacy (EdgeHTML, up to 18)', 'edgehtml', 'classic', 'No - it is a dead engine, UI 2.0 would be half-dressed'],
    ['Edge (Chromium)', 'blink', 'modern2', 'No'],
    ['Chrome, Chromium, Opera, Brave, Vivaldi', 'blink', 'modern2', 'No'],
    ['360, QQ, Sogou, UC in speed mode', 'blink', 'modern2', 'No'],
    ['360, QQ, Sogou in compatibility mode', 'trident (reports as IE 7)', 'classic', 'Yes'],
    ['Firefox', 'gecko', 'modern2', 'No'],
    ['Safari, all iOS browsers', 'webkit', 'modern2', 'No'],
    ['Android WebView (inside an app)', 'blink, years behind', 'classic', 'No'],
    ['Anything unknown, a script, or no User-Agent at all', 'unknown', 'classic', 'No - the safe answer'],
];

page_header('Interface and browsers');
?>
<p>
  The interface a visitor gets is decided in three steps, and this page controls the second one:
  an explicit choice made in the console always wins; otherwise the browser decides; and the switch below can
  turn that second step off so that visitors always get the classic interface.
</p>

<?= error_list($errors) ?>

<h2>The switch</h2>
<form method="post" action="ui-browser.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">
<label class="small">
  <input type="checkbox" name="ui_auto_pick" value="1" <?= $auto ? 'checked' : '' ?>>
  Let the browser choose the interface (Chrome and friends get UI 2.0, Internet Explorer gets the classic one)
</label>
<p class="hint">
  Off: every visitor gets the classic interface, exactly as before this feature existed - and only a signed-in
  holder who switched in the console ever sees a modern one. A holder's own choice is never overridden either way.
</p>
<p><input type="submit" value="Save"></p>
</form>

<p>The switch is currently <strong><?= $auto ? 'ON' : 'OFF' ?></strong>.</p>

<h2>The browser reading this page right now</h2>
<table>
  <tr><th>Family</th><td><code><?= h((string)$now['key']) ?></code></td></tr>
  <tr><th>Name and version</th><td><?= h($label) ?><?= (float)$now['version'] > 0
      ? ' <span class="small">(' . h((string)$now['version']) . ')</span>' : '' ?></td></tr>
  <tr><th>Engine</th><td><code><?= h((string)$now['engine']) ?></code></td></tr>
  <tr><th>Chinese shell detected</th><td><?= trim((string)$now['shell']) !== ''
      ? '<code>' . h((string)$now['shell']) . '</code>' : '<span class="hint">none</span>' ?></td></tr>
  <tr><th>Interface it would be given</th><td><strong><?= h($uiNames[$uiNow] ?? $uiNow) ?></strong>
      <?= $auto ? '' : ' <span class="small">(the switch is off: a visitor would get the classic one)</span>' ?></td></tr>
  <tr><th>Old-browser notice</th><td><?= $warn ? '<strong>Yes</strong> - IE 9 or older' : 'No' ?></td></tr>
  <tr><th>Advertising rails</th><td><?= $noRail ? '<strong>Suppressed</strong> - IE 8 or older' : 'Shown on wide screens' ?></td></tr>
  <tr><th>User-Agent as received</th><td class="small"><code><?= h((string)($_SERVER['HTTP_USER_AGENT'] ?? '(none)')) ?></code></td></tr>
</table>
<p class="hint">Ask a holder who reports a broken layout to open this page and read the first six rows aloud.</p>

<h2>What each family gets</h2>
<table>
  <tr><th>Browser</th><th>Engine</th><th>Default interface</th><th>Old-browser notice</th></tr>
  <?php foreach ($rows as $row): ?>
  <tr>
    <td><?= h($row[0]) ?></td>
    <td class="small"><?= h($row[1]) ?></td>
    <td><strong><?= h($uiNames[$row[2]] ?? $row[2]) ?></strong></td>
    <td class="small"><?= h($row[3]) ?></td>
  </tr>
  <?php endforeach; ?>
</table>

<h2>Where this is implemented</h2>
<ul class="small">
  <li><code>inc/browser.php</code> - the detection, and the default per browser. Pure PHP, no JavaScript.</li>
  <li><code>inc/theme.php</code> - the order of precedence: explicit choice, then browser, then the switch.</li>
  <li><code>inc/views.php</code> - <code>X-UA-Compatible</code>, the notice at the top of the page, and the
      conditional comment that loads <code>assets/legacy-ie.css</code> for IE 8 and older only.</li>
  <li><code>assets/legacy-ie.css</code> - the patches those engines need, and the decision that they get no
      advertising rails, because a media query is how the rails are hidden everywhere else.</li>
</ul>
<?php
page_footer();

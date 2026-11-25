<?php
declare(strict_types=1);

/** freedns.my - back office: which suffixes may use their own name servers (owner only). */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Own name servers per suffix');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach (zone_list() as $zone) {
        $zoneName = (string)$zone['name'];
        $wanted   = isset($_POST['allow_ns'][$zoneName]) ? 1 : 0;
        if ((int)($zone['allow_ns'] ?? 0) === $wanted) {
            continue;
        }
        db_query('UPDATE zones SET allow_ns = ?, updated_at = NOW() WHERE id = ?', [$wanted, (int)$zone['id']]);
        log_action('settings_updated', 'zone', (int)$zone['id'],
            $zoneName . ': own name servers ' . ($wanted === 1 ? 'allowed' : 'refused'));
    }
    flash('Saved.', 'ok');
    redirect('nameservers.php');
}

page_header('Own name servers');
?>
<p>
Tick a suffix when its upstream platform allows the holder to point the name at his own name servers
(for example Cloudflare's). Only suffixes whose zone is in the Public Suffix List can do that - the
suffixes this registry hands out itself cannot, because the registry is the authority for them.
</p>
<p class="hint">
While a name is delegated the registry no longer answers for it: the records it pushed are removed and
only the NS records plus glue stay upstream. The holder can switch back at any time, but the records have
to be entered again afterwards.
</p>

<?= error_list($errors) ?>

<form method="post" action="nameservers.php">
<?= csrf_field() ?>
<table>
  <tr><th>Suffix</th><th>Publisher</th><th>Allow own name servers</th><th>Delegated names</th></tr>
  <?php foreach (zone_list() as $zone): ?>
  <?php $zoneName = (string)$zone['name']; ?>
  <tr>
    <td><code><?= h($zoneName) ?></code></td>
    <td><?= h(publish_provider_label(publish_provider($zoneName))) ?></td>
    <td>
      <input type="checkbox" name="allow_ns[<?= h($zoneName) ?>]" value="1"
             <?= (int)($zone['allow_ns'] ?? 0) === 1 ? 'checked' : '' ?>>
    </td>
    <td><?= (int)db_value('SELECT COUNT(*) FROM domains WHERE zone = ? AND delegated = 1', [$zoneName], 0) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<p><input type="submit" value="Save"></p>
</form>

<?php $delegated = db_all('SELECT name, zone, nameservers FROM domains WHERE delegated = 1 ORDER BY name ASC'); ?>
<h2>Delegated names</h2>
<?php if ($delegated === []): ?>
<p>Nobody uses his own name servers yet.</p>
<?php else: ?>
<table>
  <tr><th>Name</th><th>Suffix</th><th>Name servers</th></tr>
  <?php foreach ($delegated as $row): ?>
  <tr><td><code><?= h((string)$row['name']) ?></code></td><td><?= h((string)$row['zone']) ?></td>
      <td class="small"><?= h(str_replace("\n", ', ', (string)$row['nameservers'])) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();

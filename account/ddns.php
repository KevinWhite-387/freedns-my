<?php
declare(strict_types=1);

/** freedns.my - console: dynamic updates (DDNS) for the names of this account. */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user   = require_user();
$errors = [];
$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ddns_columns_ready()) {
    csrf_verify();

    $domain = null;
    foreach (user_domains($user) as $candidate) {
        if ((int)$candidate['id'] === (int)($_POST['domain_id'] ?? 0)) {
            $domain = $candidate;
        }
    }
    if ($domain === null) {
        $errors[] = t('val.ddns_own');
    } elseif ($action === 'create') {
        ddns_token($domain, true);
        flash(t('ddns.created'), 'ok');
        redirect(url('account/ddns.php?id=' . (int)$domain['id']));
    } elseif ($action === 'reset') {
        ddns_reset_token($domain);
        flash(t('ddns.reset_done'), 'ok');
        redirect(url('account/ddns.php?id=' . (int)$domain['id']));
    } elseif ($action === 'revoke') {
        ddns_revoke_token($domain);
        flash(t('ddns.revoked_done'), 'ok');
        redirect(url('account/ddns.php'));
    }
}

$id      = (int)($_GET['id'] ?? 0);
$current = null;
foreach (user_domains($user) as $candidate) {
    if ($id === 0 || (int)$candidate['id'] === $id) {
        $current = $candidate;
        if ($id !== 0) {
            break;
        }
    }
}

$names = [];
foreach (user_domains($user) as $candidate) {
    if ((string)$candidate['status'] === 'active') {
        $names[] = $candidate;
    }
}

page_header(t('ddns.heading'));
?>
<p><?= h(t('ddns.intro')) ?></p>
<?= error_list($errors) ?>

<?php if (!ddns_columns_ready()): ?>
<div class="msg error"><?= h(t('ddns.no_table')) ?></div>
<?php elseif (!ddns_enabled()): ?>
<div class="msg info"><?= h(t('ddns.off')) ?></div>
<?php elseif ($names === []): ?>
<p><?= h(t('ddns.none')) ?></p>
<?php elseif ($current !== null): ?>
<?php $token = ddns_token($current, false); ?>
<h2><?= h((string)$current['name']) ?></h2>
<?php if ($token === ''): ?>
<p><?= h(t('ddns.no_token')) ?></p>
<form method="post" action="<?= h(url('account/ddns.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="create">
<input type="hidden" name="domain_id" value="<?= (int)$current['id'] ?>">
<p><input type="submit" value="<?= h(t('ddns.create')) ?>"></p>
</form>
<?php else: ?>
<table>
  <tr><th><?= h(t('ddns.token')) ?></th><td><code><?= h($token) ?></code></td></tr>
  <tr><th><?= h(t('ddns.url')) ?></th><td><code><?= h(ddns_url($token, (string)$current['name'])) ?></code></td></tr>
  <tr><th><?= h(t('ddns.last')) ?></th><td><?= h((string)($current['ddns_updated_at'] ?? '-')) ?></td></tr>
</table>

<h3><?= h(t('ddns.examples')) ?></h3>
<pre class="wrap code-block"><?= h(t('ddns.example_curl', ddns_url($token, (string)$current['name']) . '&ip=1.2.3.4')) ?>

<?= h(t('ddns.example_router')) ?></pre>

<p class="hint"><?= h(t('ddns.rules')) ?></p>

<form class="inline" method="post" action="<?= h(url('account/ddns.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="reset">
  <input type="hidden" name="domain_id" value="<?= (int)$current['id'] ?>">
  <input type="submit" value="<?= h(t('ddns.reset')) ?>">
</form>
<form class="inline" method="post" action="<?= h(url('account/ddns.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="revoke">
  <input type="hidden" name="domain_id" value="<?= (int)$current['id'] ?>">
  <input type="submit" value="<?= h(t('ddns.revoke')) ?>">
</form>
<?php endif; ?>
<p><a href="<?= h(url('account/ddns.php')) ?>"><?= h(t('ddns.all')) ?></a></p>

<?php else: ?>
<h2><?= h(t('ddns.mine')) ?></h2>
<table>
  <tr><th><?= h(t('ddns.name')) ?></th><th><?= h(t('ddns.state')) ?></th>
      <th><?= h(t('ddns.last')) ?></th><th></th></tr>
  <?php foreach ($names as $candidate): ?>
  <?php $token = ddns_token($candidate, false); ?>
  <tr>
    <td><code><?= h((string)$candidate['name']) ?></code></td>
    <td><?= $token === '' ? h(t('ddns.state_off')) : '<span class="ok">' . h(t('ddns.state_on')) . '</span>' ?></td>
    <td class="small"><?= h((string)($candidate['ddns_updated_at'] ?? '-')) ?></td>
    <td class="actions"><a href="<?= h(url('account/ddns.php?id=' . (int)$candidate['id'])) ?>">
        <?= h(t('ddns.open')) ?></a></td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint"><?= h(t('ddns.list_hint')) ?></p>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

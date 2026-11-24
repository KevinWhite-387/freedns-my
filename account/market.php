<?php
declare(strict_types=1);

/** freedns.my - console: my market offers (create, edit, withdraw, mark sold) and the enquiries. */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user   = require_user();
$errors = [];
$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if ($action === 'create') {
        $result = market_create($user, $_POST);
        if ($result['ok']) {
            flash(t('market.created'), 'ok');
            redirect(url('account/market.php?id=' . (int)$result['id']));
        }
        $errors[] = $result['error'];
    }

    if ($action === 'update') {
        $result = market_update((int)($_POST['id'] ?? 0), $user, $_POST);
        if ($result['ok']) {
            flash(t('market.updated'), 'ok');
            redirect(url('account/market.php?id=' . (int)($_POST['id'] ?? 0)));
        }
        $errors[] = $result['error'];
    }

    if ($action === 'sold' || $action === 'withdraw') {
        $result = market_seller_status((int)($_POST['id'] ?? 0), $user,
            $action === 'sold' ? 'sold' : 'withdrawn');
        if ($result['ok']) {
            flash($action === 'sold' ? t('market.marked_sold') : t('market.withdrawn_ok'), 'ok');
            redirect(url('account/market.php'));
        }
        $errors[] = $result['error'];
    }
}

$id      = (int)($_GET['id'] ?? 0);
$current = $id > 0 ? market_by_id($id) : null;
if ($current !== null && (int)$current['user_id'] !== (int)$user['id']) {
    $current = null;
}

$mine      = market_mine((int)$user['id']);
$enquiries = market_enquiries_for_user((int)$user['id']);

page_header(t('market.mine_heading'));
?>
<div class="msg info"><strong><?= h(t('market.disclaimer_heading')) ?></strong>
  <div><?= h(market_disclaimer()) ?></div>
</div>

<p><?= h(t('market.mine_intro')) ?></p>
<?= error_list($errors) ?>

<?php if (!market_enabled()): ?>
<div class="msg info"><?= h(t('market.off')) ?></div>
<?php else: ?>

<?php if ($current !== null): ?>
<h2><?= h((string)$current['domain']) ?> &middot; <?= h(market_status_text((string)$current['status'])) ?></h2>
<?php if ((string)$current['status'] === 'rejected' && trim((string)($current['reject_reason'] ?? '')) !== ''): ?>
<div class="msg error"><?= h(t('market.reject_reason')) ?>: <?= h((string)$current['reject_reason']) ?></div>
<?php endif; ?>
<form method="post" action="<?= h(url('account/market.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="update">
<input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
<label for="domain"><?= h(t('market.domain')) ?></label>
<input type="text" id="domain" name="domain" maxlength="190" value="<?= h((string)$current['domain']) ?>">
<label for="registered_on"><?= h(t('market.registered_on')) ?></label>
<input type="text" id="registered_on" name="registered_on" maxlength="10"
       value="<?= h((string)($current['registered_on'] ?? '')) ?>" placeholder="2023-05-01">
<label for="expires_on"><?= h(t('market.expires_on')) ?></label>
<input type="text" id="expires_on" name="expires_on" maxlength="10"
       value="<?= h((string)($current['expires_on'] ?? '')) ?>" placeholder="2026-05-01">
<label for="registrar"><?= h(t('market.registrar')) ?></label>
<input type="text" id="registrar" name="registrar" maxlength="190"
       value="<?= h((string)($current['registrar'] ?? '')) ?>">
<label for="transfer_price"><?= h(t('market.transfer_price')) ?></label>
<input type="text" id="transfer_price" name="transfer_price" maxlength="12"
       value="<?= h((string)($current['transfer_price'] ?? '')) ?>">
<label for="price"><?= h(t('market.price')) ?></label>
<input type="text" id="price" name="price" maxlength="12" value="<?= h((string)($current['price'] ?? '')) ?>">
<label for="currency"><?= h(t('market.currency')) ?></label>
<select id="currency" name="currency">
  <?php foreach (market_currencies() as $code => $label): ?>
  <option value="<?= h($code) ?>"<?= (string)$current['currency'] === $code ? ' selected' : '' ?>>
    <?= h($label) ?></option>
  <?php endforeach; ?>
</select>
<p>
  <input type="checkbox" id="negotiable" name="negotiable" value="1"<?= (int)$current['negotiable'] === 1 ? ' checked' : '' ?>>
  <label class="inline" for="negotiable"><?= h(t('market.negotiable')) ?></label>
</p>
<label for="contact_email"><?= h(t('market.contact_email')) ?></label>
<input type="text" id="contact_email" name="contact_email" maxlength="190"
       value="<?= h((string)$current['contact_email']) ?>">
<p class="hint"><?= h(t('market.contact_hint')) ?></p>
<label for="description"><?= h(t('market.description')) ?></label>
<textarea id="description" name="description" rows="5"><?= h((string)($current['description'] ?? '')) ?></textarea>
<p><input type="submit" value="<?= h(t('market.save')) ?>"></p>
</form>

<?php if (in_array((string)$current['status'], ['active', 'pending'], true)): ?>
<form method="post" action="<?= h(url('account/market.php')) ?>" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="sold">
  <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
  <input type="submit" value="<?= h(t('market.mark_sold')) ?>">
</form>
<form method="post" action="<?= h(url('account/market.php')) ?>" class="inline">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="withdraw">
  <input type="hidden" name="id" value="<?= (int)$current['id'] ?>">
  <input type="submit" value="<?= h(t('market.withdraw')) ?>">
</form>
<?php endif; ?>

<?php $rows = market_enquiries((int)$current['id']); ?>
<h3><?= h(t('market.enquiries')) ?></h3>
<?php if ($rows === []): ?>
<p><?= h(t('market.no_enquiries')) ?></p>
<?php else: ?>
<table>
  <tr><th><?= h(t('market.when')) ?></th><th><?= h(t('market.buyer')) ?></th><th><?= h(t('market.message')) ?></th></tr>
  <?php foreach ($rows as $row): ?>
  <tr><td class="small"><?= h((string)$row['created_at']) ?></td>
      <td><?= h((string)($row['buyer_name'] ?? '')) ?><br>
          <span class="small"><?= h((string)$row['buyer_email']) ?></span></td>
      <td class="small"><?= nl2br(h((string)($row['message'] ?? ''))) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<p><a href="<?= h(url('account/market.php')) ?>"><?= h(t('market.back_mine')) ?></a>
&middot; <a href="<?= h(url('market.php?id=' . (int)$current['id'])) ?>"><?= h(t('market.view_public')) ?></a></p>

<?php else: ?>

<h2><?= h(t('market.new_heading')) ?></h2>
<p><?= h(t('market.new_intro')) ?></p>
<form method="post" action="<?= h(url('account/market.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="create">
<label for="n_domain"><?= h(t('market.domain')) ?></label>
<input type="text" id="n_domain" name="domain" maxlength="190" value="" placeholder="example.com">
<p class="hint"><?= h(t('market.domain_hint')) ?></p>
<label for="n_registered_on"><?= h(t('market.registered_on')) ?></label>
<input type="text" id="n_registered_on" name="registered_on" maxlength="10" value="" placeholder="2023-05-01">
<label for="n_expires_on"><?= h(t('market.expires_on')) ?></label>
<input type="text" id="n_expires_on" name="expires_on" maxlength="10" value="" placeholder="2026-05-01">
<label for="n_registrar"><?= h(t('market.registrar')) ?></label>
<input type="text" id="n_registrar" name="registrar" maxlength="190" value="" placeholder="Namecheap">
<label for="n_transfer_price"><?= h(t('market.transfer_price')) ?></label>
<input type="text" id="n_transfer_price" name="transfer_price" maxlength="12" value="">
<label for="n_price"><?= h(t('market.price')) ?></label>
<input type="text" id="n_price" name="price" maxlength="12" value="">
<label for="n_currency"><?= h(t('market.currency')) ?></label>
<select id="n_currency" name="currency">
  <?php foreach (market_currencies() as $code => $label): ?>
  <option value="<?= h($code) ?>"><?= h($label) ?></option>
  <?php endforeach; ?>
</select>
<p>
  <input type="checkbox" id="n_negotiable" name="negotiable" value="1">
  <label class="inline" for="n_negotiable"><?= h(t('market.negotiable')) ?></label>
</p>
<label for="n_contact_email"><?= h(t('market.contact_email')) ?></label>
<input type="text" id="n_contact_email" name="contact_email" maxlength="190" value="<?= h((string)$user['email']) ?>">
<label for="n_description"><?= h(t('market.description')) ?></label>
<textarea id="n_description" name="description" rows="5"></textarea>
<p><input type="submit" value="<?= h(t('market.submit')) ?>"></p>
</form>
<p class="hint"><?= h(t('market.review_hint')) ?></p>

<h2><?= h(t('market.mine_heading')) ?></h2>
<?php if ($mine === []): ?>
<p><?= h(t('market.none')) ?></p>
<?php else: ?>
<table>
  <tr><th>#</th><th><?= h(t('market.domain')) ?></th><th><?= h(t('market.price')) ?></th>
      <th><?= h(t('market.status_heading')) ?></th><th><?= h(t('market.enquiries_count')) ?></th><th></th></tr>
  <?php foreach ($mine as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><code><?= h((string)$row['domain']) ?></code></td>
    <td><?= h(market_money((string)$row['price'])) ?> <?= h(market_currency_text((string)$row['currency'])) ?></td>
    <td><?= h(market_status_text((string)$row['status'])) ?></td>
    <td><?= count(market_enquiries((int)$row['id'])) ?></td>
    <td class="actions"><a href="<?= h(url('account/market.php?id=' . (int)$row['id'])) ?>">
        <?= h(t('market.open')) ?></a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($enquiries !== []): ?>
<h2><?= h(t('market.all_enquiries')) ?></h2>
<table>
  <tr><th><?= h(t('market.when')) ?></th><th><?= h(t('market.domain')) ?></th>
      <th><?= h(t('market.buyer')) ?></th><th><?= h(t('market.message')) ?></th></tr>
  <?php foreach ($enquiries as $row): ?>
  <tr><td class="small"><?= h((string)$row['created_at']) ?></td>
      <td><code><?= h((string)$row['domain']) ?></code></td>
      <td><?= h((string)($row['buyer_name'] ?? '')) ?><br>
          <span class="small"><?= h((string)$row['buyer_email']) ?></span></td>
      <td class="small"><?= nl2br(h((string)($row['message'] ?? ''))) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

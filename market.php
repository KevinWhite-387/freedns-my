<?php
declare(strict_types=1);

/** freedns.my - public market place: domains offered for sale by console accounts. */

require __DIR__ . '/inc/bootstrap.php';

$user   = user_session_available() ? current_user() : null;
$errors = [];

$id      = (int)($_GET['id'] ?? 0);
$listing = $id > 0 ? market_by_id($id) : null;
if ($listing !== null && !in_array((string)$listing['status'], ['active', 'sold'], true)) {
    $listing = null;
}
if ($listing !== null && $user !== null && (int)$listing['user_id'] === (int)$user['id']) {
    // The seller looking at his own offer: fine, just no "apply to buy".
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'enquire') {
    csrf_verify();
    $target = market_by_id((int)($_POST['id'] ?? 0));
    if ($user === null) {
        flash(t('market.login_first'), 'error');
        redirect(url('account/login.php'));
    }
    if ($target === null) {
        $errors[] = t('val.market_closed');
    } else {
        $result = market_enquire(
            $target,
            $user,
            trim((string)($_POST['contact_email'] ?? '')),
            trim((string)($_POST['contact_name'] ?? '')),
            trim((string)($_POST['message'] ?? ''))
        );
        if ($result['ok']) {
            flash(t('market.sent'), 'ok');
            redirect(url('market.php?id=' . (int)$target['id']));
        }
        $errors[] = $result['error'];
    }
}

$filters = [
    'q'      => trim((string)($_GET['q'] ?? '')),
    'suffix' => trim((string)($_GET['suffix'] ?? '')),
    'max'    => trim((string)($_GET['max'] ?? '')),
];
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$total   = market_public_count($filters);
$pager   = paginate($total, $perPage, $page);
$offers  = market_enabled() ? market_public($filters, $perPage, (int)$pager['offset']) : [];

page_header($listing === null ? t('market.heading') : (string)$listing['domain']);
?>
<div class="msg info"><strong><?= h(t('market.disclaimer_heading')) ?></strong>
  <div><?= h(market_disclaimer()) ?></div>
</div>

<?php if (!market_enabled()): ?>
<div class="msg info"><?= h(t('market.off')) ?></div>
<?php elseif ($listing !== null): ?>

<h2><?= h((string)$listing['domain']) ?></h2>
<table>
  <tr><th><?= h(t('market.price')) ?></th><td><strong><?= h(market_money((string)$listing['price'])) ?>
      <?= h(market_currency_text((string)$listing['currency'])) ?></strong>
      <?= (int)$listing['negotiable'] === 1 ? ' &middot; ' . h(t('market.negotiable')) : '' ?></td></tr>
  <tr><th><?= h(t('market.transfer_price')) ?></th>
      <td><?= h(market_money((string)$listing['transfer_price'])) ?> <?= h((string)$listing['currency']) ?></td></tr>
  <tr><th><?= h(t('market.registered_on')) ?></th><td><?= h((string)($listing['registered_on'] ?? '-')) ?></td></tr>
  <tr><th><?= h(t('market.expires_on')) ?></th><td><?= h((string)($listing['expires_on'] ?? '-')) ?></td></tr>
  <tr><th><?= h(t('market.registrar')) ?></th><td><?= h((string)($listing['registrar'] ?? '-')) ?></td></tr>
  <tr><th><?= h(t('market.status_heading')) ?></th><td><?= h(market_status_text((string)$listing['status'])) ?></td></tr>
  <tr><th><?= h(t('market.listed')) ?></th><td><?= h(format_date((string)$listing['created_at'])) ?></td></tr>
</table>
<p class="small">
<a href="<?= h(url('abuse.php?domain=' . rawurlencode((string)$listing['domain']))) ?>">
  <?= h(t('market.report_link')) ?></a>
</p>

<?php if (trim((string)($listing['description'] ?? '')) !== ''): ?>
<h3><?= h(t('market.description')) ?></h3>
<p><?= nl2br(h((string)$listing['description'])) ?></p>
<?php endif; ?>

<?php if ((string)$listing['status'] === 'sold'): ?>
<div class="msg info"><?= h(t('market.sold_note')) ?></div>
<?php elseif ($user !== null && (int)$listing['user_id'] === (int)$user['id']): ?>
<div class="msg info"><?= h(t('market.own_offer')) ?></div>
<?php elseif ($user === null): ?>
<h3><?= h(t('market.buy_heading')) ?></h3>
<p><?= t('market.login_first_help', '<a href="' . h(url('account/login.php')) . '">'
    . h(t('account.login_heading')) . '</a>', '<a href="' . h(url('account/register.php')) . '">'
    . h(t('account.register_heading')) . '</a>') ?></p>
<?php else: ?>
<h3><?= h(t('market.buy_heading')) ?></h3>
<p><?= h(t('market.buy_intro', (string)$listing['contact_email'] === (string)$listing['seller_email']
    ? t('market.buy_intro_account')
    : t('market.buy_intro_custom'))) ?></p>
<?= error_list($errors) ?>
<form method="post" action="<?= h(url('market.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="enquire">
<input type="hidden" name="id" value="<?= (int)$listing['id'] ?>">
<label for="contact_name"><?= h(t('market.your_name')) ?></label>
<input type="text" id="contact_name" name="contact_name" maxlength="190"
       value="<?= h((string)$user['username']) ?>">
<label for="contact_email"><?= h(t('market.your_email')) ?></label>
<input type="text" id="contact_email" name="contact_email" maxlength="190" value="<?= h((string)$user['email']) ?>">
<p class="hint"><?= h(t('market.email_hint')) ?></p>
<label for="message"><?= h(t('market.message')) ?></label>
<textarea id="message" name="message" rows="4"></textarea>
<p><input type="submit" value="<?= h(t('market.send')) ?>"></p>
</form>
<?php endif; ?>

<p><a href="<?= h(url('market.php')) ?>"><?= h(t('market.back')) ?></a></p>

<?php else: ?>

<p><?= h(t('market.intro')) ?></p>

<form method="get" action="<?= h(url('market.php')) ?>">
<label for="q"><?= h(t('market.search')) ?></label>
<input type="text" id="q" name="q" maxlength="190" value="<?= h($filters['q']) ?>">
<label for="suffix"><?= h(t('market.suffix')) ?></label>
<input type="text" id="suffix" name="suffix" maxlength="63" value="<?= h($filters['suffix']) ?>"
       placeholder="com">
<label for="max"><?= h(t('market.max_price')) ?></label>
<input type="text" id="max" name="max" maxlength="12" value="<?= h($filters['max']) ?>">
<p><input type="submit" value="<?= h(t('market.filter')) ?>">
<?php if ($filters['q'] !== '' || $filters['suffix'] !== '' || $filters['max'] !== ''): ?>
<a href="<?= h(url('market.php')) ?>"><?= h(t('market.reset')) ?></a>
<?php endif; ?></p>
</form>

<?= error_list($errors) ?>

<?php if ($offers === []): ?>
<p><?= h(t('market.empty')) ?></p>
<?php else: ?>
<table>
  <tr><th><?= h(t('market.domain')) ?></th><th><?= h(t('market.price')) ?></th>
      <th><?= h(t('market.transfer_price')) ?></th><th><?= h(t('market.expires_on')) ?></th>
      <th><?= h(t('market.registrar')) ?></th><th></th></tr>
  <?php foreach ($offers as $offer): ?>
  <tr>
    <td><?php if ((int)$offer['pinned'] === 1): ?><span class="pill"><?= h(t('market.pinned')) ?></span> <?php endif; ?>
        <a href="<?= h(url('market.php?id=' . (int)$offer['id'])) ?>"><code><?= h((string)$offer['domain']) ?></code></a>
        <?php if ((int)$offer['negotiable'] === 1): ?> <span class="small"><?= h(t('market.negotiable')) ?></span><?php endif; ?>
        <br><span class="small"><?= h(shorten((string)($offer['description'] ?? ''), 90)) ?></span></td>
    <td><?= h(market_money((string)$offer['price'])) ?> <?= h(market_currency_text((string)$offer['currency'])) ?></td>
    <td><?= h(market_money((string)$offer['transfer_price'])) ?></td>
    <td><?= h((string)($offer['expires_on'] ?? '-')) ?></td>
    <td><?= h((string)($offer['registrar'] ?? '-')) ?></td>
    <td class="actions"><a href="<?= h(url('market.php?id=' . (int)$offer['id'])) ?>"><?= h(t('market.open')) ?></a></td>
  </tr>
  <?php endforeach; ?>
</table>
<?= page_links($pager, ['q' => $filters['q'], 'suffix' => $filters['suffix'], 'max' => $filters['max']]) ?>
<?php endif; ?>

<p class="hint">
<?= t('market.sell_hint', '<a href="' . h(url('account/market.php')) . '">' . h(t('market.sell_link')) . '</a>') ?>
</p>
<?php endif; ?>
<?php
page_footer();

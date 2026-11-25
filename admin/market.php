<?php
declare(strict_types=1);

/** freedns.my - back office: the market place (review, pin, take down). Owner only. */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('The market place');
$errors = [];

if (!market_table_ready()) {
    page_header('Market place');
    echo '<div class="msg error">The <code>market_listings</code> table is missing; import '
        . '<code>upgrade-market.sql</code> (the <a href="' . h(url('selftest.php')) . '">self test</a> '
        . 'prints the SQL).</div>';
    page_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = strtolower((string)($_POST['action'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);
    $reason = trim((string)($_POST['reason'] ?? ''));
    $staff  = (string)$admin['username'];

    if ($action === 'approve' || $action === 'reject') {
        $result = market_decide($id, $action === 'approve', $reason, $staff);
        if ($result['ok']) {
            flash($action === 'approve'
                ? 'The offer is visible in the market now.'
                : 'The offer was rejected and the seller was informed.', 'ok');
            redirect('market.php');
        }
        $errors[] = $result['error'];
    }

    if ($action === 'pin' || $action === 'unpin') {
        market_pin($id, $action === 'pin');
        flash($action === 'pin' ? 'The offer is pinned to the top.' : 'The pin was removed.', 'ok');
        redirect('market.php');
    }

    if ($action === 'remove') {
        market_remove($id, $staff);
        flash('The offer was taken down.', 'ok');
        redirect('market.php');
    }
}

$status = (string)($_GET['status'] ?? 'pending');
if (!in_array($status, ['pending', 'active', 'sold', 'withdrawn', 'rejected', 'all'], true)) {
    $status = 'pending';
}

$where  = $status === 'all' ? '' : ' WHERE l.status = ?';
$params = $status === 'all' ? [] : [$status];
$rows   = db_all(
    'SELECT l.*, (SELECT COUNT(*) FROM market_enquiries e WHERE e.listing_id = l.id) AS enquiries
     FROM market_listings l' . $where . ' ORDER BY l.pinned DESC, l.id DESC LIMIT 200',
    $params
);
$counts = [];
foreach (db_all('SELECT status, COUNT(*) AS n FROM market_listings GROUP BY status') as $row) {
    $counts[(string)$row['status']] = (int)$row['n'];
}

page_header('Market place');
?>
<p>
Offers from console accounts. Check that the domain is plausible (the registry cannot verify ownership),
that the prices make sense and that nothing abusive is in the description; approving mails the seller, and
rejecting sends your reason. <strong>Pin</strong> keeps an offer at the top of the public list.
</p>
<p class="small">
<?= h(t('market.disclaimer')) ?>
</p>

<?= error_list($errors) ?>

<p class="small">
<?php foreach (['pending', 'active', 'sold', 'withdrawn', 'rejected', 'all'] as $option): ?>
<a href="market.php?status=<?= h($option) ?>"><?= h($option) ?>
  (<?= $option === 'all' ? array_sum($counts) : (int)($counts[$option] ?? 0) ?>)</a>
<?= $option === 'all' ? '' : '&middot;' ?>
<?php endforeach; ?>
</p>

<?php if ($rows === []): ?>
<p>Nothing here.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Domain</th><th>Price</th><th>Transfer</th><th>Registrar</th><th>Expires</th>
      <th>Seller</th><th>Status</th><th>Requests</th><th></th></tr>
  <?php foreach ($rows as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?><?= (int)$row['pinned'] === 1 ? ' <span class="pill">pinned</span>' : '' ?></td>
    <td><code><?= h((string)$row['domain']) ?></code></td>
    <td><?= h(market_money((string)$row['price'])) ?> <?= h((string)$row['currency']) ?>
        <?= (int)$row['negotiable'] === 1 ? '<span class="small">nego.</span>' : '' ?></td>
    <td><?= h(market_money((string)$row['transfer_price'])) ?></td>
    <td><?= h((string)($row['registrar'] ?? '-')) ?></td>
    <td><?= h((string)($row['expires_on'] ?? '-')) ?></td>
    <td><?= h((string)$row['seller_email']) ?><br><span class="small"><?= h((string)$row['contact_email']) ?></span></td>
    <td><?= h(market_status_text((string)$row['status'])) ?></td>
    <td><?= (int)$row['enquiries'] ?></td>
    <td class="actions">
      <?php if ((string)$row['status'] === 'pending'): ?>
      <form class="inline" method="post" action="market.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Approve">
      </form>
      <form class="inline" method="post" action="market.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="text" name="reason" maxlength="190" placeholder="reason">
        <input type="submit" value="Reject">
      </form>
      <?php endif; ?>
      <?php if ((string)$row['status'] === 'active'): ?>
      <form class="inline" method="post" action="market.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= (int)$row['pinned'] === 1 ? 'unpin' : 'pin' ?>">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="<?= (int)$row['pinned'] === 1 ? 'Unpin' : 'Pin to top' ?>">
      </form>
      <form class="inline" method="post" action="market.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Take down">
      </form>
      <?php endif; ?>
      <a href="<?= h(url('market.php?id=' . (int)$row['id'])) ?>">view</a>
    </td>
  </tr>
  <?php if (trim((string)($row['description'] ?? '')) !== ''): ?>
  <tr><td></td><td colspan="9" class="small"><?= nl2br(h(shorten((string)$row['description'], 400))) ?>
      <?php if (trim((string)($row['reject_reason'] ?? '')) !== ''): ?>
      <br><span class="err">rejected: <?= h((string)$row['reject_reason']) ?></span>
      <?php endif; ?></td></tr>
  <?php endif; ?>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Recent requests</h2>
<?php $last = db_all(
    'SELECT e.*, l.domain FROM market_enquiries e JOIN market_listings l ON l.id = e.listing_id
     ORDER BY e.id DESC LIMIT 20'
); ?>
<?php if ($last === []): ?>
<p>Nobody applied yet.</p>
<?php else: ?>
<table>
  <tr><th>When</th><th>Domain</th><th>Buyer</th><th>Message</th></tr>
  <?php foreach ($last as $row): ?>
  <tr><td class="small"><?= h((string)$row['created_at']) ?></td>
      <td><code><?= h((string)$row['domain']) ?></code></td>
      <td><?= h((string)($row['buyer_name'] ?? '')) ?><br><span class="small"><?= h((string)$row['buyer_email']) ?></span></td>
      <td class="small"><?= nl2br(h(shorten((string)($row['message'] ?? ''), 200))) ?></td></tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();

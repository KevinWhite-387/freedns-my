<?php
declare(strict_types=1);

/** freedns.my - back office: the Shop (settings, promotion review, invite ranking). Owner only. */

require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
配额商店
');

$admin = require_admin();
require_owner('The shop');
$errors = [];

if (!shop_table_ready()) {
    page_header('Shop');
    echo '<div class="msg error">The <code>shop_promos</code> table or the invite columns are missing; '
        . 'import <code>upgrade-shop.sql</code> (the <a href="' . h(url('selftest.php')) . '">self test</a> '
        . 'prints the SQL).</div>';
    page_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = strtolower((string)($_POST['action'] ?? ''));
    $staff  = (string)$admin['username'];

    if ($action === 'save') {
        $changed = 0;
        foreach (['star_bonus_amount' => [1, 100], 'promo_bonus_amount' => [1, 100],
            'promo_max_total' => [1, 1000]] as $key => $range) {
            $value = (int)($_POST[$key] ?? 0);
            if ($value < $range[0] || $value > $range[1]) {
                $errors[] = $key . ' must be between ' . $range[0] . ' and ' . $range[1] . '.';
                continue;
            }
            if ((string)setting($key, '') !== (string)$value) {
                set_setting($key, (string)$value);
                $changed++;
            }
        }
        $enabled = isset($_POST['shop_enabled']) ? '1' : '0';
        if ((string)setting('shop_enabled', '1') !== $enabled) {
            set_setting('shop_enabled', $enabled);
            $changed++;
        }
        if ($errors === []) {
            log_action('settings_updated', 'settings', null, 'shop: ' . $changed . ' value(s)');
            flash($changed === 0 ? 'Nothing changed.' : 'Saved.', 'ok');
            redirect('shop.php');
        }
    }

    if ($action === 'approve' || $action === 'reject') {
        $result = shop_promo_decide((int)($_POST['id'] ?? 0), $action === 'approve',
            trim((string)($_POST['reason'] ?? '')), $staff);
        if ($result['ok']) {
            flash($action === 'approve'
                ? 'Approved: the account received +' . (int)($result['bonus'] ?? 0) . ' quota.'
                : 'Rejected; the account was informed.', 'ok');
            redirect('shop.php');
        }
        $errors[] = $result['error'];
    }
}

$pending = shop_promos_pending();
$recent  = shop_promos_recent(40);
$ranking = shop_referral_ranking();

page_header('Shop');
?>
<p>
The Shop hands out extra registration allowance for things that help the registry: starring the GitHub
repository (once per account), promoting it somewhere (reviewed here) and bringing in new users with a
personal invite code. Every reward is added to the account's <code>quota_bonus</code>, on top of the normal
allowance.
</p>

<?= error_list($errors) ?>

<h2>Settings</h2>
<form method="post" action="shop.php">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">
<p>
  <input type="checkbox" id="shop_enabled" name="shop_enabled" value="1"<?= setting_bool('shop_enabled', true) ? ' checked' : '' ?>>
  <label class="inline" for="shop_enabled">Offer the Shop in the console</label>
</p>
<label for="star_bonus_amount">Extra names for starring the repository (once per account)</label>
<input type="text" id="star_bonus_amount" name="star_bonus_amount" maxlength="3"
       value="<?= (int)star_bonus_amount() ?>">
<label for="promo_bonus_amount">Extra names per approved promotion</label>
<input type="text" id="promo_bonus_amount" name="promo_bonus_amount" maxlength="3"
       value="<?= (int)shop_promo_amount() ?>">
<label for="promo_max_total">Upper limit of promotion rewards per account</label>
<input type="text" id="promo_max_total" name="promo_max_total" maxlength="4"
       value="<?= (int)shop_promo_cap() ?>">
<p><input type="submit" value="Save the shop settings"></p>
</form>

<h2>Promotions waiting for review (<?= count($pending) ?>)</h2>
<?php if ($pending === []): ?>
<p>Nothing waiting.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>When</th><th>Account</th><th>Link</th><th>Description</th><th>Invited</th><th></th></tr>
  <?php foreach ($pending as $row): ?>
  <?php $stats = shop_referral_stats((string)db_value('SELECT invite_code FROM users WHERE id = ?',
      [(int)$row['user_id']], '')); ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td class="small"><?= h((string)$row['created_at']) ?></td>
    <td><?= h((string)$row['user_email']) ?></td>
    <td class="small"><a href="<?= h((string)$row['url']) ?>" rel="noopener"><?= h(shorten((string)$row['url'], 60)) ?></a></td>
    <td class="small"><?= nl2br(h(shorten((string)($row['description'] ?? ''), 300))) ?></td>
    <td class="small"><?= (int)$stats['people'] ?> <?= h(t('shop.people_short')) ?>,
        <?= (int)$stats['active'] ?> <?= h(t('shop.active_short')) ?></td>
    <td class="actions">
      <form class="inline" method="post" action="shop.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="approve">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="Approve (+<?= (int)shop_promo_amount() ?>)">
      </form>
      <form class="inline" method="post" action="shop.php">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="reject">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="text" name="reason" maxlength="190" placeholder="reason">
        <input type="submit" value="Reject">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Invite ranking</h2>
<?php if ($ranking === []): ?>
<p>Nobody has an invite code yet; they are created on the first visit of a Shop page.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>Account</th><th>Code</th><th>Invited people</th><th>Their active names</th>
      <th>Promotion bonus</th><th>Total bonus</th></tr>
  <?php foreach ($ranking as $index => $row): ?>
  <tr>
    <td><?= (int)$index + 1 ?></td>
    <td><?= h((string)$row['username']) ?><br><span class="small"><?= h((string)$row['email']) ?></span></td>
    <td><code><?= h((string)$row['invite_code']) ?></code></td>
    <td><?= (int)$row['people'] ?></td>
    <td><?= (int)$row['active_names'] ?></td>
    <td><?= (int)$row['promo_bonus'] ?></td>
    <td><?= (int)$row['quota_bonus'] ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2>Recent submissions</h2>
<?php if ($recent === []): ?>
<p>Nothing yet.</p>
<?php else: ?>
<table>
  <tr><th>#</th><th>When</th><th>Account</th><th>Link</th><th>Status</th><th>Bonus</th><th>Decided by</th></tr>
  <?php foreach ($recent as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td class="small"><?= h((string)$row['created_at']) ?></td>
    <td><?= h((string)$row['user_email']) ?></td>
    <td class="small"><?= h(shorten((string)$row['url'], 50)) ?></td>
    <td><?= h((string)$row['status']) ?>
        <?= trim((string)($row['reason'] ?? '')) === '' ? '' : ' <span class="small err">' . h((string)$row['reason']) . '</span>' ?></td>
    <td><?= (int)$row['bonus'] ?></td>
    <td class="small"><?= h((string)($row['decided_by'] ?? '')) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>
<?php
page_footer();

<?php
declare(strict_types=1);

/** freedns.my - console: the Shop (GitHub star, promotion, invite code). */

require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
配额商店
');

$user   = require_user();
$errors = [];

if (shop_table_ready()) {
    shop_attribute_user($user);
    $user = current_user() ?? $user;
}

$action = (string)($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && shop_table_ready()) {
    csrf_verify();

    if ($action === 'star') {
        $claim = star_bonus_claim($user);
        if ($claim['ok']) {
            flash(t('shop.star_ok', (string)$claim['amount']), 'ok');
            redirect(url('account/shop.php'));
        }
        if ($claim['error'] === 'val.star_lookup') {
            $list     = github_stargazers();
            $errors[] = t('val.star_lookup', $list['error']);
        } else {
            $errors[] = t($claim['error']);
        }
    }

    if ($action === 'promo') {
        $result = shop_promo_submit(
            $user,
            (string)($_POST['url'] ?? ''),
            trim((string)($_POST['description'] ?? ''))
        );
        if ($result['ok']) {
            flash(t('shop.promo_sent'), 'ok');
            redirect(url('account/shop.php'));
        }
        $errors[] = $result['error'];
    }
}

$view  = shop_table_ready() ? shop_overview($user) : null;
$mine  = shop_table_ready() ? shop_promos_of((int)$user['id']) : [];

page_header(t('shop.heading'));
?>
<p><?= h(t('shop.intro')) ?></p>
<?= error_list($errors) ?>

<?php if (!shop_table_ready()): ?>
<div class="msg error"><?= h(t('shop.no_table')) ?></div>
<?php else: ?>

<table>
  <tr><th><?= h(t('shop.current')) ?></th><td>
      <?= $view['quota']['unlimited']
          ? '<span class="ok">' . h(t('shop.unlimited')) . '</span>'
          : h(t('shop.names', (string)(int)$view['quota']['limit'])) ?>
      &middot; <?= h(t('shop.used', (string)(int)$view['quota']['used'])) ?>
      <?= $view['bonus'] > 0 ? ' &middot; ' . h(t('shop.earned', (string)$view['bonus'])) : '' ?></td></tr>
</table>

<h2><?= h(t('shop.item_star')) ?></h2>
<p><?= h($view['star_ready'] ? t('shop.star_intro', (string)$view['star_amount']) : t('shop.star_off')) ?></p>
<?php if ($view['star_ready']): ?>
<?php if ($view['star_taken']): ?>
<div class="msg ok"><?= h(t('shop.star_done', (string)($user['star_bonus_at'] ?? ''))) ?></div>
<?php elseif (trim((string)($user['github_id'] ?? '')) === ''): ?>
<div class="msg info"><?= t('shop.star_need_link',
    '<a href="' . h(url('account/profile.php')) . '">' . h(t('account.github_link')) . '</a>') ?></div>
<?php else: ?>
<p><a href="<?= h(star_bonus_url()) ?>" rel="noopener"><?= h(star_bonus_repo()) ?></a></p>
<form method="post" action="<?= h(url('account/shop.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="star">
<p><input type="submit" value="<?= h(t('shop.star_submit')) ?>"></p>
</form>
<?php endif; ?>
<?php endif; ?>

<h2><?= h(t('shop.item_promo')) ?></h2>
<p><?= h(t('shop.promo_intro', (string)shop_promo_amount(), (string)$view['promo_cap'])) ?></p>
<p class="hint"><?= h(t('shop.promo_left', (string)$view['promo_done'], (string)$view['promo_left'])) ?></p>
<?php if ($view['promo_left'] <= 0): ?>
<div class="msg info"><?= h(t('shop.promo_full')) ?></div>
<?php else: ?>
<form method="post" action="<?= h(url('account/shop.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="promo">
<label for="url"><?= h(t('shop.promo_url')) ?></label>
<input type="text" id="url" name="url" maxlength="255" value="" placeholder="https://...">
<p class="hint"><?= h(t('shop.promo_url_hint')) ?></p>
<label for="description"><?= h(t('shop.promo_description')) ?></label>
<textarea id="description" name="description" rows="4"></textarea>
<p><input type="submit" value="<?= h(t('shop.promo_submit')) ?>"></p>
</form>
<?php endif; ?>

<?php if ($mine !== []): ?>
<table>
  <tr><th><?= h(t('shop.when')) ?></th><th><?= h(t('shop.promo_url')) ?></th>
      <th><?= h(t('shop.status')) ?></th><th><?= h(t('shop.bonus')) ?></th></tr>
  <?php foreach ($mine as $row): ?>
  <tr>
    <td class="small"><?= h((string)$row['created_at']) ?></td>
    <td class="small"><?= h(shorten((string)$row['url'], 60)) ?></td>
    <td><?= h(t('shop.status_' . (string)$row['status'])) ?>
        <?= trim((string)($row['reason'] ?? '')) === '' ? ''
            : '<br><span class="small err">' . h((string)$row['reason']) . '</span>' ?></td>
    <td><?= (int)$row['bonus'] > 0 ? '+' . (int)$row['bonus'] : '-' ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= h(t('shop.item_invite')) ?></h2>
<p><?= h(t('shop.invite_intro', (string)$view['stats']['people'], (string)$view['stats']['active'])) ?></p>
<table>
  <tr><th><?= h(t('shop.invite_code')) ?></th><td><code><?= h($view['code']) ?></code></td></tr>
  <tr><th><?= h(t('shop.invite_link')) ?></th><td><code><?= h($view['link']) ?></code></td></tr>
  <tr><th><?= h(t('shop.invite_people')) ?></th><td><?= (int)$view['stats']['people'] ?></td></tr>
  <tr><th><?= h(t('shop.invite_active')) ?></th><td><?= (int)$view['stats']['active'] ?>
      <?php if ((int)$view['stats']['pending'] > 0): ?>
      &middot; <?= h(t('shop.invite_pending', (string)(int)$view['stats']['pending'])) ?>
      <?php endif; ?></td></tr>
</table>
<p class="hint"><?= h(t('shop.invite_hint')) ?></p>
<p><a href="<?= h(url('market.php')) ?>"><?= h(t('market.heading')) ?></a></p>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

<?php
declare(strict_types=1);

/**
 * freedns.my - console: the extra registration quota for starring the repository on GitHub.
 *
 * The account has to be linked to GitHub first; the check compares the account's GitHub id with the
 * stargazers of the configured repository, so nobody can claim somebody else's star.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('我的奖励');

$user   = require_user();
$errors = [];
$fresh  = $user;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    if ((string)($_POST['action'] ?? '') === 'claim') {
        $claim = star_bonus_claim($user);
        if ($claim['ok']) {
            flash(t('account.bonus_ok', (string)$claim['amount']), 'ok');
            redirect(url('account/bonus.php'));
        }
        if ($claim['error'] === 'val.star_lookup') {
            $list    = github_stargazers();
            $errors[] = t('val.star_lookup', $list['error']);
        } else {
            $errors[] = t($claim['error']);
        }
    }

    $fresh = current_user() ?? $user;
}

$claimable = star_bonus_enabled() && !star_bonus_taken($fresh);
$linked    = trim((string)($fresh['github_id'] ?? '')) !== '';
$list      = null;
if (star_bonus_enabled()) {
    $list = github_stargazers();
}

page_header(t('account.bonus_heading'));
?>
<p><?= t('account.bonus_intro', h((string)setting('site_title', 'freedns.my'))) ?></p>

<?= error_list($errors) ?>

<?php if (!star_bonus_enabled()): ?>
<div class="msg info"><?= h(t('account.bonus_off')) ?></div>
<?php else: ?>

<table>
  <tr><th><?= h(t('account.bonus_repo')) ?></th>
      <td><a href="<?= h(star_bonus_url()) ?>" rel="noopener"><?= h(star_bonus_repo()) ?></a></td></tr>
  <tr><th><?= h(t('account.bonus_amount')) ?></th>
      <td><?= (int)star_bonus_amount() ?> <?= h(t('account.bonus_names')) ?>
      <?= (int)($fresh['quota_bonus'] ?? 0) > 0
          ? '&middot; ' . h(t('account.bonus_yours', (string)(int)$fresh['quota_bonus']))
          : '' ?></td></tr>
  <tr><th><?= h(t('account.bonus_status')) ?></th>
      <td>
        <?php if (star_bonus_taken($fresh)): ?>
        <span class="ok"><?= h(t('account.bonus_taken', (string)($fresh['star_bonus_at'] ?? ''))) ?></span>
        <?php elseif (!$linked): ?>
        <span class="err"><?= t('account.bonus_need_link',
            '<a href="' . h(url('account/profile.php')) . '">' . h(t('account.github_link')) . '</a>') ?></span>
        <?php else: ?>
        <?= h(t('account.bonus_ready')) ?>
        <?php endif; ?>
      </td></tr>
</table>

<ol>
  <li><?= t('account.bonus_step1', '<a href="' . h(star_bonus_url()) . '" rel="noopener">'
      . h(star_bonus_repo()) . '</a>') ?></li>
  <li><?= h(t('account.bonus_step2')) ?></li>
  <li><?= h(t('account.bonus_step3')) ?></li>
</ol>

<?php if ($claimable && $linked): ?>
<form method="post" action="<?= h(url('account/bonus.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="claim">
<p><input type="submit" value="<?= h(t('account.bonus_submit')) ?>"></p>
</form>
<?php endif; ?>

<?php if ($list !== null && $list['ok']): ?>
<p class="hint">
  <?= h(t('account.bonus_count', (string)$list['count'], (string)$list['fetched'])) ?>
  <?= $list['truncated'] ? ' ' . h(t('account.bonus_truncated')) : '' ?>
</p>
<?php endif; ?>

<p class="hint"><?= h(t('account.bonus_note')) ?></p>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

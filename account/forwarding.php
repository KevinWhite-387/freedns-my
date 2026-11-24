<?php
declare(strict_types=1);

/** freedns.my - console: mail forwarding for names on a Cloudflare suffix. */

require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
邮件转发（旧版）
');

$user   = require_user();
$errors = [];
$action = (string)($_POST['action'] ?? '');

$mine = [];
foreach (user_domains($user) as $candidate) {
    $mine[] = $candidate;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && mailroute_table_ready()) {
    csrf_verify();

    $domain = null;
    foreach ($mine as $candidate) {
        if ((int)$candidate['id'] === (int)($_POST['domain_id'] ?? 0)) {
            $domain = $candidate;
        }
    }

    if ($domain === null) {
        $errors[] = t('val.mail_own');
    } elseif ($action === 'add') {
        $result = mailroute_add($domain, $user, (string)($_POST['local'] ?? ''),
            trim((string)($_POST['forward_to'] ?? '')));
        if ($result['ok']) {
            flash(t('mail.added'), 'ok');
            redirect(url('account/forwarding.php'));
        }
        $errors[] = $result['error'];
    } elseif ($action === 'remove') {
        $route = mailroute_table_ready()
            ? db_one('SELECT * FROM mail_routes WHERE id = ? AND domain_id = ?',
                [(int)($_POST['id'] ?? 0), (int)$domain['id']])
            : null;
        if ($route === null) {
            $errors[] = t('val.mail_own');
        } else {
            $result = mailroute_remove($route, $domain);
            if ($result['ok']) {
                flash(t('mail.removed'), 'ok');
                redirect(url('account/forwarding.php'));
            }
            $errors[] = $result['error'];
        }
    }
}

// Names that could receive mail at all.
$eligible = [];
foreach ($mine as $candidate) {
    if ((string)$candidate['status'] === 'active' && mailroute_zone_ok((string)$candidate['zone'])) {
        $eligible[] = $candidate;
    }
}

$routes = mailroute_table_ready() ? mailroute_of_user((int)$user['id']) : [];

page_header(t('mail.heading'));
?>
<p><?= h(t('mail.intro')) ?></p>
<?= error_list($errors) ?>

<?php if (!mailroute_table_ready()): ?>
<div class="msg error"><?= h(t('mail.no_table')) ?></div>
<?php elseif (!mailroute_enabled()): ?>
<div class="msg info"><?= h(t('mail.off')) ?></div>
<?php elseif ($eligible === []): ?>
<p><?= h(t('mail.none')) ?></p>
<?php else: ?>

<h2><?= h(t('mail.add_heading')) ?></h2>
<p class="hint"><?= h(t('mail.add_hint')) ?></p>
<form method="post" action="<?= h(url('account/forwarding.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="add">
<label for="domain_id"><?= h(t('mail.name')) ?></label>
<select id="domain_id" name="domain_id">
  <?php foreach ($eligible as $candidate): ?>
  <option value="<?= (int)$candidate['id'] ?>"><?= h((string)$candidate['name']) ?></option>
  <?php endforeach; ?>
</select>
<label for="local"><?= h(t('mail.local')) ?></label>
<input type="text" id="local" name="local" maxlength="64" value="info" placeholder="info">
<label for="forward_to"><?= h(t('mail.forward_to')) ?></label>
<input type="text" id="forward_to" name="forward_to" maxlength="190" value="<?= h((string)$user['email']) ?>">
<p class="hint"><?= h(t('mail.confirm_hint')) ?></p>
<p><input type="submit" value="<?= h(t('mail.add')) ?>"></p>
</form>

<h2><?= h(t('mail.mine')) ?></h2>
<?php if ($routes === []): ?>
<p><?= h(t('mail.no_routes')) ?></p>
<?php else: ?>
<table>
  <tr><th><?= h(t('mail.address')) ?></th><th><?= h(t('mail.forward_to')) ?></th>
      <th><?= h(t('mail.status')) ?></th><th><?= h(t('mail.when')) ?></th><th></th></tr>
  <?php foreach ($routes as $route): ?>
  <tr>
    <td><code><?= h((string)$route['local_part'] . '@' . (string)$route['name']) ?></code></td>
    <td><?= h((string)$route['forward_to']) ?></td>
    <td><?= h(mailroute_status_text((string)$route['status'])) ?>
        <?= trim((string)($route['note'] ?? '')) === '' ? ''
            : '<br><span class="small err">' . h((string)$route['note']) . '</span>' ?></td>
    <td class="small"><?= h((string)$route['created_at']) ?></td>
    <td class="actions">
      <form class="inline" method="post" action="<?= h(url('account/forwarding.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="id" value="<?= (int)$route['id'] ?>">
        <input type="hidden" name="domain_id" value="<?= (int)$route['domain_id'] ?>">
        <input type="submit" value="<?= h(t('mail.remove')) ?>">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<p class="hint"><?= h(t('mail.rules')) ?></p>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

<?php
declare(strict_types=1);

/** freedns.my - console: which notifications this account wants. */

require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('通知偏好');

$user   = require_user();
$errors = [];
$ready  = mailpref_column_ready();
$off    = mail_off_of($user);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ready) {
    csrf_verify();

    $wanted = [];
    foreach ((array)($_POST['off'] ?? []) as $kind) {
        $wanted[] = (string)$kind;
    }
    mail_off_set((int)$user['id'], $wanted);
    flash(t('notify.saved'), 'ok');
    redirect(url('account/notify.php'));
}

page_header(t('notify.heading'));
?>
<p><?= h(t('notify.intro')) ?></p>
<?= error_list($errors) ?>

<?php if (!$ready): ?>
<div class="msg error"><?= h(t('notify.no_table')) ?></div>
<?php else: ?>
<form method="post" action="<?= h(url('account/notify.php')) ?>">
<?= csrf_field() ?>
<table>
  <tr><th><?= h(t('notify.kind')) ?></th><th><?= h(t('notify.examples')) ?></th>
      <th><?= h(t('notify.switch_off')) ?></th></tr>
  <?php foreach (mail_kinds() as $kind): ?>
  <?php if ($kind === 'account') { continue; } ?>
  <tr>
    <td><?= h(mail_kind_label($kind)) ?></td>
    <td class="small"><?= h(t('notify.example_' . $kind)) ?></td>
    <td><input type="checkbox" name="off[]" value="<?= h($kind) ?>"
               <?= in_array($kind, $off, true) ? 'checked' : '' ?>></td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint"><?= h(t('notify.always')) ?></p>
<p><input type="submit" value="<?= h(t('notify.save')) ?>"></p>
</form>
<p class="hint"><?= h(t('notify.scope')) ?></p>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

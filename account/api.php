<?php
declare(strict_types=1);

/** freedns.my - console: API tokens. */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user   = require_user();
$errors = [];
$fresh  = '';
$action = (string)($_POST['action'] ?? '');

// The API is an experimental feature: say so on the page itself, and point at the full statement.
$fdApiZh = function_exists('lang') && is_zh();
$fdApiNote = $fdApiZh
    ? '这是<strong>实验性功能</strong>：接口仍在调整，字段与返回内容可能变化，不承诺长期兼容，也不保证一直保留。遇到问题请及时上报。'
    : 'This is an <strong>experimental feature</strong>: the interface is still moving, fields and answers may change, long-term compatibility is not promised and it may not stay forever. Please report problems.';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && api_table_ready()) {
    csrf_verify();

    if ($action === 'create') {
        $created = api_token_create((int)$user['id'], trim((string)($_POST['label'] ?? '')));
        $_SESSION['fd_api_fresh'] = $created['token'];
        flash(t('api.created'), 'ok');
        redirect(url('account/api.php'));
    }

    if ($action === 'revoke') {
        if (api_token_revoke((int)$user['id'], (int)($_POST['id'] ?? 0))) {
            flash(t('api.revoked'), 'ok');
        } else {
            $errors[] = t('api.not_yours');
        }
        redirect(url('account/api.php'));
    }
}

if (($_SESSION['fd_api_fresh'] ?? '') !== '') {
    $fresh = (string)$_SESSION['fd_api_fresh'];
    unset($_SESSION['fd_api_fresh']);
}

$tokens = api_tokens_of((int)$user['id']);

$apiBase = rtrim(app_base_url(), '/') . '/api.php';

page_header(t('api.heading'));
?>
<div class="msg error">
  <?= $fdApiNote ?>
      ? '实验性功能的完整声明' : 'the full statement about experimental features' ?></a>
</div>
<?php
?>
<p><?= h(t('api.intro')) ?></p>
<?= error_list($errors) ?>

<?php if (!api_table_ready()): ?>
<div class="msg error"><?= h(t('api.no_table')) ?></div>
<?php elseif (!api_enabled()): ?>
<div class="msg info"><?= h(t('api.off')) ?></div>
<?php else: ?>

<?php if ($fresh !== ''): ?>
<div class="msg ok">
  <strong><?= h(t('api.token_once')) ?></strong>
  <p><code><?= h($fresh) ?></code></p>
  <pre class="wrap code-block"><?= h(api_example_curl($fresh, 'myname.us.ci')) ?></pre>
</div>
<?php endif; ?>

<h2><?= h(t('api.new')) ?></h2>
<form method="post" action="<?= h(url('account/api.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="create">
<label for="label"><?= h(t('api.label')) ?></label>
<input type="text" id="label" name="label" maxlength="64" value="" placeholder="<?= h(t('api.label_hint')) ?>">
<p><input type="submit" value="<?= h(t('api.create')) ?>"></p>
</form>

<h2><?= h(t('api.mine')) ?></h2>
<?php if ($tokens === []): ?>
<p><?= h(t('api.none')) ?></p>
<?php else: ?>
<table>
  <tr><th>#</th><th><?= h(t('api.label')) ?></th><th><?= h(t('api.hint')) ?></th>
      <th><?= h(t('api.calls')) ?></th><th><?= h(t('api.last')) ?></th><th><?= h(t('api.state')) ?></th><th></th></tr>
  <?php foreach ($tokens as $row): ?>
  <tr>
    <td><?= (int)$row['id'] ?></td>
    <td><?= h((string)($row['label'] ?? '-')) ?></td>
    <td><code>…<?= h((string)$row['token_hint']) ?></code></td>
    <td><?= (int)$row['calls'] ?></td>
    <td class="small"><?= h((string)($row['last_used_at'] ?? '-')) ?></td>
    <td><?= $row['revoked_at'] === null
        ? '<span class="ok">' . h(t('api.active')) . '</span>'
        : h(t('api.revoked_state', (string)$row['revoked_at'])) ?></td>
    <td class="actions">
      <?php if ($row['revoked_at'] === null): ?>
      <form class="inline" method="post" action="<?= h(url('account/api.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="revoke">
        <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
        <input type="submit" value="<?= h(t('api.revoke')) ?>">
      </form>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= h(t('api.docs')) ?></h2>
<pre class="wrap code-block"><?= h(t('api.docs_body', $apiBase, $apiBase, $apiBase, $apiBase, $apiBase)) ?></pre>
<p class="hint"><?= h(t('api.docs_auth')) ?></p>
<p class="hint"><?= h(t('api.rules')) ?></p>
<?php endif; ?>

<p><a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.back_to_console')) ?></a></p>
<?php
page_footer();

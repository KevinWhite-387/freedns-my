<?php
declare(strict_types=1);

/**
 * freedns.my - console: the open platform, seen from the holder's side.
 *
 * Two things live here, and they are different questions:
 *   * the applications this holder wants to build ("my applications"): submitting one puts it in the queue for a
 *     human to approve, exactly like a listing on the partners page;
 *   * the applications this holder has signed in to ("connected"): who may read what, and a button that cuts it
 *     off - which revokes both the consent and every token that application holds for this account.
 *
 * A secret is shown once, at the moment the application is submitted, and never again.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$zh   = function_exists('lang') && is_zh();
$user = function_exists('current_user') ? current_user() : null;
if ($user === null) {
    // url() is required here: a bare relative path would be resolved against the current directory, and inside a
    // language wrapper (/zh/account/apps.php) that turns into /zh/account/account/login.php.
    redirect(url('account/login.php'));
}

$errors = [];
$fresh  = null;
$done   = '';
$userId = (int)$user['id'];
$ready  = function_exists('op_table_ready') && op_table_ready();

if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = strtolower((string)($_POST['action'] ?? ''));

    if ($action === 'submit') {
        $name    = trim((string)($_POST['name'] ?? ''));
        $contact = trim((string)($_POST['contact_email'] ?? ''));
        $home    = trim((string)($_POST['homepage'] ?? ''));
        $about   = trim((string)($_POST['description'] ?? ''));
        $scopes  = op_scopes_clean(implode(' ', (array)($_POST['scopes'] ?? [])));
        $public  = isset($_POST['is_public']) ? 1 : 0;

        if ($name === '' || mb_strlen($name) > 80) {
            $errors[] = $zh ? '请填写应用名称（80 字以内）。' : 'A name is needed (up to 80 characters).';
        }
        if (!valid_email_addr($contact)) {
            $errors[] = $zh ? '请填写有效的联系邮箱。' : 'A valid contact address is needed.';
        }
        if ($home !== '' && preg_match('#^https?://#i', $home) !== 1) {
            $errors[] = $zh ? '主页必须是以 http:// 或 https:// 开头的完整地址。' : 'The home page has to be a full URL.';
        }
        $clean = [];
        foreach (preg_split('/\r\n|\r|\n/', trim((string)($_POST['redirect_uris'] ?? ''))) ?: [] as $one) {
            $one = trim($one);
            if ($one === '') {
                continue;
            }
            if (preg_match('#^https://#i', $one) !== 1) {
                $errors[] = ($zh ? '回调地址必须使用 https：' : 'Every redirect address must use https://: ') . $one;
                continue;
            }
            $clean[] = $one;
        }
        if ($clean === []) {
            $errors[] = $zh ? '至少要填一个 https 回调地址。' : 'At least one https:// redirect address is required.';
        }
        if ($scopes === '') {
            $errors[] = $zh ? '至少勾选一个权限范围。' : 'Pick at least one scope.';
        }

        if ($errors === []) {
            $client = op_new_client();
            db_query(
                'INSERT INTO oauth_apps (name, owner_user_id, contact_email, homepage, description, redirect_uris,
                    scopes, status, is_public, client_id, client_secret_hash, secret_hint, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, \'pending\', ?, ?, ?, ?, NOW())',
                [$name, $userId, $contact, $home !== '' ? $home : null, $about !== '' ? $about : null,
                 implode("\n", $clean), $scopes, $public, $client['client_id'],
                 $public === 1 ? null : $client['secret_hash'], $public === 1 ? null : $client['secret_hint']]
            );
            log_action('open_app_request', 'user', $userId, $name . ' submitted for review');
            if ($public === 0) {
                $fresh = ['name' => $name, 'client_id' => $client['client_id'], 'secret' => $client['secret']];
            } else {
                $done = $zh ? '已提交，等待站长审核。' : 'Submitted; the operator reviews it by hand.';
            }
        }
    }

    if ($action === 'disconnect') {
        $appId = (int)($_POST['id'] ?? 0);
        if ($appId > 0) {
            db_query('UPDATE oauth_consents SET revoked_at = NOW(), updated_at = NOW()
                      WHERE app_id = ? AND user_id = ? AND revoked_at IS NULL', [$appId, $userId]);
            db_query('UPDATE oauth_tokens SET revoked_at = NOW()
                      WHERE app_id = ? AND user_id = ? AND revoked_at IS NULL', [$appId, $userId]);
            log_action('open_disconnect', 'user', $userId, 'disconnected application #' . $appId);
            flash($zh ? '已断开：它拿到的令牌立刻失效。' : 'Disconnected: every token it held for you is dead now.', 'ok');
            redirect(url('account/apps.php'));
        }
    }
}

$mine      = $ready ? op_apps_of_user($userId) : [];
$connected = [];
if ($ready) {
    try {
        $connected = db_all(
            'SELECT c.*, a.name AS app_name, a.client_id, a.homepage, a.status AS app_status
               FROM oauth_consents c
               JOIN oauth_apps a ON a.id = c.app_id
              WHERE c.user_id = ? AND c.revoked_at IS NULL
              ORDER BY c.updated_at DESC',
            [$userId]
        );
    } catch (Throwable $e) {
        $connected = [];
    }
}

page_header($zh ? '开放平台' : 'Open platform');
?>
<p><?= $zh
    ? '让别的网站用「freedns.my 账号登录」，并读取你允许的域名、解析记录或邮箱。你的密码永远不会给对方；你可以随时在这里断开。'
    : 'Let other sites sign users in with a freedns.my account, and read the names, records or mailboxes you allow. '
      . 'Your password is never handed over, and you can cut any of them off here.' ?></p>

<?php if (!$ready): ?>
<div class="msg error"><?= $zh
    ? '开放平台的数据表还没建好，站长需要先导入 upgrade-openplatform.sql。'
    : 'The open platform tables are missing: the operator has to import upgrade-openplatform.sql first.' ?></div>
<?php endif; ?>

<?php if ($fresh !== null): ?>
<div class="msg ok">
  <strong><?= $zh ? '请立刻复制 client_secret —— 它只显示这一次。' : 'Copy the client_secret now - it is shown once.' ?></strong><br>
  <?= $zh ? '应用' : 'Application' ?>: <?= h($fresh['name']) ?><br>
  <code>client_id</code>: <code><?= h($fresh['client_id']) ?></code><br>
  <code>client_secret</code>: <code><?= h($fresh['secret']) ?></code>
</div>
<?php endif; ?>
<?php if ($done !== ''): ?><div class="msg ok"><?= h($done) ?></div><?php endif; ?>
<?= error_list($errors) ?>

<h2><?= $zh ? '已连接到你的账号' : 'Connected to your account' ?></h2>
<?php if ($connected === []): ?>
<p class="hint"><?= $zh ? '目前没有应用连着你的账号。' : 'No application is connected to your account.' ?></p>
<?php else: ?>
<table>
  <tr>
    <th><?= $zh ? '应用' : 'Application' ?></th>
    <th><?= $zh ? '它能做什么' : 'What it may do' ?></th>
    <th><?= $zh ? '授权时间' : 'Allowed' ?></th>
    <th></th>
  </tr>
  <?php foreach ($connected as $row): ?>
  <tr>
    <td>
      <strong><?= h((string)$row['app_name']) ?></strong>
      <?php if (trim((string)$row['homepage']) !== ''): ?>
      <div class="small"><a href="<?= h((string)$row['homepage']) ?>" rel="nofollow noopener" target="_blank"><?= h((string)$row['homepage']) ?></a></div>
      <?php endif; ?>
      <div class="small"><code><?= h((string)$row['client_id']) ?></code></div>
    </td>
    <td class="small"><?= h(op_scopes_text((string)$row['scopes'])) ?></td>
    <td class="small"><?= h((string)$row['updated_at']) ?></td>
    <td>
      <form method="post" action="<?= h(url('account/apps.php')) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="disconnect">
        <input type="hidden" name="id" value="<?= (int)$row['app_id'] ?>">
        <input type="submit" value="<?= h($zh ? '断开' : 'Disconnect') ?>">
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<p class="hint"><?= $zh
    ? '断开后，对方手里的令牌立刻失效；它下次想访问必须重新征求你的同意。'
    : 'Disconnecting kills its tokens immediately; it has to ask you again before it can read anything.' ?></p>
<?php endif; ?>

<h2><?= $zh ? '我的应用' : 'My applications' ?></h2>
<?php if ($mine === []): ?>
<p class="hint"><?= $zh ? '你还没有提交过应用。' : 'You have not submitted an application yet.' ?></p>
<?php else: ?>
<table>
  <tr><th><?= $zh ? '名称' : 'Name' ?></th><th><?= $zh ? '状态' : 'State' ?></th><th><?= $zh ? '权限' : 'Scopes' ?></th><th><?= $zh ? '备注' : 'Note' ?></th></tr>
  <?php foreach ($mine as $row): ?>
  <tr>
    <td><strong><?= h((string)$row['name']) ?></strong>
      <div class="small"><code><?= h((string)$row['client_id']) ?></code></div></td>
    <td><code><?= h((string)$row['status']) ?></code></td>
    <td class="small"><?= h(op_scopes_text((string)$row['scopes'])) ?></td>
    <td class="small"><?= h((string)($row['review_note'] ?? '')) ?></td>
  </tr>
  <?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= $zh ? '提交一个应用' : 'Submit an application' ?></h2>
<p><?= $zh
    ? '提交后由站长人工审核（通常几天）。条件很简单：说明清楚用途、回调地址用 https、不索取不需要的权限。'
    : 'A human reviews it (usually within days). The conditions are short: say what it does, use https for the '
      . 'redirect address, and do not ask for scopes you do not need.' ?></p>
<?php if ($ready): ?>
<form method="post" action="<?= h(url('account/apps.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="submit">
<label for="name"><?= $zh ? '应用名称（用户在授权页上看到的名字）' : 'Application name (shown on the consent screen)' ?></label>
<input type="text" id="name" name="name" maxlength="80" required>
<label for="contact_email"><?= $zh ? '联系邮箱' : 'Contact address' ?></label>
<input type="text" id="contact_email" name="contact_email" maxlength="190" required>
<label for="homepage"><?= $zh ? '应用主页（可选）' : 'Home page (optional)' ?></label>
<input type="text" id="homepage" name="homepage" maxlength="190" placeholder="https://">
<label for="description"><?= $zh ? '用途说明（审核用）' : 'What it does (for the review)' ?></label>
<textarea id="description" name="description" rows="3"></textarea>
<label for="redirect_uris"><?= $zh ? '回调地址（一行一个，必须 https 且完全一致）' : 'Redirect addresses (one per line, https, matched exactly)' ?></label>
<textarea id="redirect_uris" name="redirect_uris" rows="3" placeholder="https://myapp.example.org/oauth/freedns/callback" required></textarea>
<fieldset>
  <legend><?= $zh ? '希望申请哪些权限' : 'Scopes you want to ask for' ?></legend>
  <?php foreach (op_scopes() as $key => $label): ?>
  <label class="small"><input type="checkbox" name="scopes[]" value="<?= h($key) ?>"> <code><?= h($key) ?></code>
    - <?= h($zh ? $label[1] : $label[0]) ?></label><br>
  <?php endforeach; ?>
</fieldset>
<p class="hint"><label class="small"><input type="checkbox" name="is_public" value="1">
  <?= $zh ? '公共客户端（无密钥，仅 PKCE，适合手机 App 或纯前端）' : 'Public client (no secret: PKCE only, for a mobile or browser app)' ?></label></p>
<p><input type="submit" value="<?= $zh ? '提交审核' : 'Submit for review' ?>"></p>
</form>
<?php endif; ?>

<h2><?= $zh ? '给开发者的说明' : 'For developers' ?></h2>
<p><a href="<?= h(url('developers.php')) ?>"><?= $zh ? '开放平台文档（英文）' : 'Open platform documentation (English)' ?></a>
  &middot; <?= $zh ? '接口：' : 'Endpoints: ' ?><code>/oauth/authorize.php</code> ·
  <code>/oauth/token.php</code> · <code>/oauth/userinfo.php</code> ·
  <code>/open/v1/domains.php</code> · <code>/open/v1/records.php</code> ·
  <code>/open/v1/mailboxes.php</code> · <code>/open/v1/applications.php</code></p>
<?php
page_footer();

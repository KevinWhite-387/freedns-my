<?php
declare(strict_types=1);

/**
 * freedns.my - back office: vc.ci sign-in configuration.
 *
 * A page of its own rather than another section inside admin/settings.php: that file already
 * carries every policy text and its own whitelist, and this needs a working "test" button.
 *
 * Everything here is optional except client_id / client_secret. The migration block only needs
 * the database details, and it is read-only against vc.ci.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

require_owner('vc.ci sign-in');

$zh     = function_exists('lang') && is_zh();
$notice = '';
$error  = '';

$textKeys = [
    'vcci_issuer', 'vcci_client_id', 'vcci_redirect_uri',
    'vcci_db_host', 'vcci_db_name', 'vcci_db_user',
];
$secretKeys = ['vcci_client_secret', 'vcci_db_pass'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? 'save');

    if ($action === 'save') {
        foreach ($textKeys as $key) {
            if (array_key_exists($key, $_POST)) {
                $value = trim((string)$_POST[$key]);
                if ((string)setting($key, '') !== $value) {
                    set_setting($key, $value);
                    log_action('settings_updated', 'settings', null, 'vcci: ' . $key);
                }
            }
        }
        /* Secrets are only replaced when something is typed; an empty field keeps the stored one. */
        foreach ($secretKeys as $key) {
            $value = trim((string)($_POST[$key] ?? ''));
            if ($value !== '') {
                set_setting($key, $value);
                log_action('settings_updated', 'settings', null, 'vcci: ' . $key . ' replaced');
            }
            if (isset($_POST[$key . '_clear'])) {
                set_setting($key, '');
                log_action('settings_updated', 'settings', null, 'vcci: ' . $key . ' cleared');
            }
        }
        foreach (['vcci_auto_migrate', 'vcci_migrate_active_only'] as $key) {
            $value = (string)($_POST[$key] ?? '0') === '1' ? '1' : '0';
            if ((string)setting($key, '0') !== $value) {
                set_setting($key, $value);
                log_action('settings_updated', 'settings', null, 'vcci: ' . $key . ' = ' . $value);
            }
        }
        $notice = $zh ? '已保存。' : 'Saved.';
    } elseif ($action === 'test') {
        $c = vcci_migrate_pdo();
        if (!$c['ok'] || $c['pdo'] === null) {
            $error = ($zh ? 'vc.ci 数据库：' : 'vc.ci database: ') . $c['error'];
        } else {
            try {
                $n = (int)$c['pdo']->query('SELECT COUNT(*) FROM domains')->fetchColumn();
                $u = (int)$c['pdo']->query('SELECT COUNT(*) FROM users')->fetchColumn();
                $notice = ($zh ? 'vc.ci 数据库可以读：' : 'The vc.ci database is readable: ')
                    . 'domains=' . $n . ' users=' . $u;
            } catch (Throwable $e) {
                $error = ($zh ? '连上了但读不到表：' : 'Connected, but the tables are not readable: ')
                    . $e->getMessage();
            }
        }
        if (vcci_configured()) {
            $notice .= ' · ' . ($zh ? 'OAuth 凭据已填 ✓' : 'OAuth credentials present ✓');
        } else {
            $error .= ($error === '' ? '' : ' · ')
                . ($zh ? 'OAuth 凭据还没填完 ✗' : 'OAuth credentials are incomplete ✗');
        }
    }
}

$linked = 0;
try {
    $linked = (int)db_value("SELECT COUNT(*) FROM users WHERE vcci_id IS NOT NULL AND vcci_id <> ''", [], 0);
} catch (Throwable $e) {
    $linked = 0;
}

page_header($zh ? 'vc.ci 登录设置' : 'vc.ci sign-in');
?>
<h1><?= h($zh ? 'vc.ci 登录设置' : 'vc.ci sign-in') ?></h1>
<hr>

<?php if ($notice !== ''): ?>
<p class="msg ok"><?= h($notice) ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
<p class="msg error"><?= h($error) ?></p>
<?php endif; ?>

<?php if (!vcci_columns_ready()): ?>
<p class="msg error"><?= h($zh
    ? '数据库还缺列：请先导入 upgrade-vcci.sql，本页才能保存。'
    : 'The database is missing the vc.ci columns: import upgrade-vcci.sql first.') ?></p>
<?php endif; ?>

<h2><?= h($zh ? '当前状态' : 'Status') ?></h2>
<table>
<tr><th>OAuth</th><td>
<?= vcci_configured() ? '<span class="ok">' . h($zh ? '已配置' : 'configured') . '</span>'
    : '<span class="err">' . h($zh ? '缺 client_id / secret' : 'missing client_id / secret') . '</span>' ?>
</td></tr>
<tr><th><?= h($zh ? '回调地址' : 'Redirect URI') ?></th><td><code><?= h(vcci_redirect_uri()) ?></code></td></tr>
<tr><th><?= h($zh ? '已绑定用户' : 'Linked users') ?></th><td><?= $linked ?></td></tr>
<tr><th><?= h($zh ? 'vc.ci 数据库' : 'vc.ci database') ?></th><td>
<code><?= h(trim((string)setting('vcci_db_host', '127.0.0.1')) . ' / '
    . trim((string)setting('vcci_db_name', 'vc_ci')) . ' as '
    . (trim((string)setting('vcci_db_user', '')) !== '' ? trim((string)setting('vcci_db_user', '')) : '—')) ?></code>
</td></tr>
</table>

<form method="post" action="<?= h(url('admin/vcci.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="save">

<h2>OAuth</h2>
<p><label for="vcci_issuer">Issuer</label><br>
<input type="text" id="vcci_issuer" name="vcci_issuer" maxlength="190" style="width:100%;max-width:420px"
       value="<?= h((string)setting('vcci_issuer', 'https://register.vc.ci')) ?>">
<span class="small"><?= h($zh ? '必须是 register.vc.ci（开放平台在那边）' : 'must be register.vc.ci') ?></span></p>

<p><label for="vcci_client_id">client_id</label><br>
<input type="text" id="vcci_client_id" name="vcci_client_id" maxlength="190" style="width:100%;max-width:420px"
       value="<?= h((string)setting('vcci_client_id', '')) ?>" autocomplete="off"></p>

<p><label for="vcci_client_secret">client_secret</label><br>
<input type="password" id="vcci_client_secret" name="vcci_client_secret" maxlength="190" style="width:100%;max-width:420px"
       value="" autocomplete="new-password"
       placeholder="<?= trim((string)setting('vcci_client_secret', '')) !== '' ? h('已保存（留空表示不改）') : 'vcs_…' ?>">
<label class="inline"><input type="checkbox" name="vcci_client_secret_clear" value="1"> <?= h($zh ? '清空' : 'clear') ?></label></p>

<p><label for="vcci_redirect_uri">redirect_uri</label><br>
<input type="text" id="vcci_redirect_uri" name="vcci_redirect_uri" maxlength="190" style="width:100%;max-width:420px"
       value="<?= h((string)setting('vcci_redirect_uri', 'https://freedns.my/account/vcci-callback.php')) ?>">
<span class="small"><?= h($zh ? '要和 vc.ci 后台注册的地址完全一致' : 'must match the URI registered at vc.ci') ?></span></p>

<h2><?= h($zh ? '域名自动迁移（读 vc.ci 的库）' : 'Domain migration (reads the vc.ci database)') ?></h2>
<p class="small"><?= h($zh
    ? '只做 SELECT，不需要写权限。建议用只读账号。'
    : 'SELECT only - a read-only MySQL user is enough.') ?></p>

<p><label for="vcci_db_host">host</label><br>
<input type="text" id="vcci_db_host" name="vcci_db_host" maxlength="190" style="width:100%;max-width:280px"
       value="<?= h((string)setting('vcci_db_host', '127.0.0.1')) ?>">
<span class="small"><?= h($zh ? '连不上就换成 localhost' : 'try localhost if 127.0.0.1 fails') ?></span></p>

<p><label for="vcci_db_name">database</label><br>
<input type="text" id="vcci_db_name" name="vcci_db_name" maxlength="190" style="width:100%;max-width:280px"
       value="<?= h((string)setting('vcci_db_name', 'vc_ci')) ?>"></p>

<p><label for="vcci_db_user">user</label><br>
<input type="text" id="vcci_db_user" name="vcci_db_user" maxlength="190" style="width:100%;max-width:280px"
       value="<?= h((string)setting('vcci_db_user', '')) ?>" autocomplete="off"></p>

<p><label for="vcci_db_pass">password</label><br>
<input type="password" id="vcci_db_pass" name="vcci_db_pass" maxlength="190" style="width:100%;max-width:280px"
       value="" autocomplete="new-password"
       placeholder="<?= trim((string)setting('vcci_db_pass', '')) !== '' ? h('已保存（留空表示不改）') : '' ?>">
<label class="inline"><input type="checkbox" name="vcci_db_pass_clear" value="1"> <?= h($zh ? '清空' : 'clear') ?></label></p>

<h2><?= h($zh ? '迁移策略' : 'Migration policy') ?></h2>
<p><label for="vcci_auto_migrate"><?= h($zh ? '登录 / 绑定时自动迁移' : 'Migrate on sign-in / link') ?></label><br>
<select id="vcci_auto_migrate" name="vcci_auto_migrate">
  <option value="1"<?= setting_bool('vcci_auto_migrate', true) ? ' selected' : '' ?>><?= h($zh ? '开' : 'on') ?></option>
  <option value="0"<?= setting_bool('vcci_auto_migrate', true) ? '' : ' selected' ?>><?= h($zh ? '关' : 'off') ?></option>
</select></p>

<p><label for="vcci_migrate_active_only"><?= h($zh ? '只迁移已生效（active）的域名' : 'Only migrate active domains') ?></label><br>
<select id="vcci_migrate_active_only" name="vcci_migrate_active_only">
  <option value="1"<?= setting_bool('vcci_migrate_active_only', true) ? ' selected' : '' ?>><?= h($zh ? '是' : 'yes') ?></option>
  <option value="0"<?= setting_bool('vcci_migrate_active_only', true) ? '' : ' selected' ?>><?= h($zh ? '否（连 pending 也迁）' : 'no (also pending)') ?></option>
</select></p>

<p><button class="btn" type="submit"><?= h($zh ? '保存' : 'Save') ?></button></p>
</form>

<form method="post" action="<?= h(url('admin/vcci.php')) ?>" style="margin-top:1em">
<?= csrf_field() ?>
<input type="hidden" name="action" value="test">
<p><button class="btn" type="submit"><?= h($zh ? '测试 vc.ci 数据库连接' : 'Test the vc.ci database') ?></button></p>
</form>

<h2><?= h($zh ? '在 vc.ci 那边要做的' : 'On the vc.ci side') ?></h2>
<ol class="small">
<li><?= h($zh ? '在 register.vc.ci/developers 建应用，Redirect URI 填上面那个地址，scopes 勾 openid profile email domains。'
    : 'Create the app at register.vc.ci/developers with the redirect URI above and the scopes openid profile email domains.') ?></li>
<li><?= h($zh ? '在 register.vc.ci/admin/openplatform 批准应用，并把四个 scope 都勾上。'
    : 'Approve it at register.vc.ci/admin/openplatform and tick all four scopes.') ?></li>
<li><?= h($zh ? '把 client_id / client_secret 填到本页。' : 'Paste the client_id and client_secret here.') ?></li>
</ol>

<p class="small"><?= h($zh
    ? '登录入口：/account/vcci-callback.php · 绑定与解绑也在那里 · 未绑定的用户登录后会自动建号。'
    : 'Entry point: /account/vcci-callback.php (link and unlink live there too). A first sign-in creates the account.') ?></p>

<?php page_footer(); ?>

<?php
declare(strict_types=1);

/**
 * freedns.my - sign in with vc.ci, or link the vc.ci account to the signed-in one.
 *
 * One file does all four jobs, so there is only one URL to register at vc.ci:
 *
 *   GET  /account/vcci.php                 status: linked or not, with the buttons
 *   GET  /account/vcci.php?do=login        start the flow, then come back here
 *   GET  /account/vcci.php?do=link         the same, but bound to the signed-in account
 *   POST /account/vcci.php?do=unlink       drop the binding
 *   GET  /account/vcci.php?code=…&state=…  the callback vc.ci redirects to
 *
 * Redirect URI to register at vc.ci: https://freedns.my/account/vcci-callback.php
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

fd_start_session();

$zh   = function_exists('lang') && is_zh();
$user = current_user();
$do   = strtolower(trim((string)($_GET['do'] ?? '')));
$notice = '';
$error  = '';

/* ------------------------------------------------------------------ actions */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    if ($do === 'migrate' && $user !== null && function_exists('vcci_migrate_user')) {
        $sum = vcci_migrate_user((int)$user['id']);
        if (!empty($sum['ok'])) {
            flash(sprintf($zh ? '迁移完成：新域名 %d 个 ✓ 记录 %d 条 ✓ 跳过 %d 个'
                             : 'Migration done: %d new domain(s), %d record(s), %d skipped.',
                (int)$sum['created'], (int)$sum['records'], (int)$sum['skipped']), 'ok');
        } else {
            flash(($zh ? '迁移失败：' : 'Migration failed: ') . (string)($sum['error'] ?? ''), 'error');
        }
        redirect(url('account/vcci-callback.php'));
    }
    if ($do === 'unlink' && $user !== null) {
        vcci_unbind((int)$user['id']);
        flash($zh ? '已解绑 vc.ci 账户。' : 'The vc.ci account was unlinked.', 'ok');
        redirect(url('account/vcci-callback.php'));
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    /* Start */
    if ($do === 'login' || $do === 'link') {
        if (!vcci_columns_ready() || !vcci_configured()) {
            $error = $zh
                ? '还没配置好：请先在后台填写 vc.ci 的 client_id / client_secret，并导入 upgrade-vcci.sql。'
                : 'Not configured yet: fill in the vc.ci client_id / client_secret and import upgrade-vcci.sql first.';
        } elseif ($do === 'link' && $user === null) {
            $error = $zh ? '请先登录，再绑定 vc.ci 账户。' : 'Please sign in first, then link the vc.ci account.';
        } else {
            vcci_begin($do, $do === 'link' && $user !== null ? (int)$user['id'] : null);
            exit;
        }
    }

    /* Callback */
    if (isset($_GET['code']) || isset($_GET['error'])) {
        $result = vcci_callback();
        if (empty($result['ok'])) {
            $error = (string)($result['error'] ?? 'Unknown error.');
        } else {
            $identity = vcci_identity((array)$result['profile']);
            if (($result['mode'] ?? 'login') === 'link' && (int)($result['link'] ?? 0) > 0) {
                $bind = vcci_bind((int)$result['link'], $identity);
                if ($bind['ok']) {
                    if (!empty($identity['email'])) {
                        db_query('UPDATE users SET vcci_email = ? WHERE id = ?', [$identity['email'], (int)$result['link']]);
                    }
                    flash($zh ? 'vc.ci 账户已绑定 ✓' : 'The vc.ci account is now linked.', 'ok');
                    redirect(url('account/vcci-callback.php'));
                }
                $error = $bind['error'];
            } else {
                $login = vcci_login($identity);
                if (!empty($login['ok']) && $login['user'] !== null) {
                    vcci_sign_in($login['user']);
                    if (!empty($login['created'])) {
                        flash($zh ? '已用 vc.ci 创建账号并登录 ✓' : 'Account created from vc.ci and signed in.', 'ok');
                    } else {
                        flash($zh ? '已用 vc.ci 登录 ✓' : 'Signed in with vc.ci.', 'ok');
                    }
                    /* The migration hook lives in inc/vcci-migrate.php; if it is not deployed yet the
                     * sign-in still works. */
                    if (function_exists('vcci_migrate_user')) {
                        try {
                            $summary = vcci_migrate_user((int)$login['user']['id']);
                            if ($summary !== null && empty($summary['ok'])) {
                                flash(($zh ? '自动迁移没有完成：' : 'Automatic migration did not run: ')
                                    . (string)($summary['error'] ?? ''), 'error');
                            }
                            if ($summary !== null && (int)($summary['created'] ?? 0) > 0) {
                                flash(sprintf(
                                    $zh ? '已自动迁移 %d 个域名 ✓（记录 %d 条）'
                                        : 'Migrated %1$d domain(s) and %2$d record(s) automatically.',
                                    (int)$summary['created'],
                                    (int)($summary['records'] ?? 0)
                                ), 'ok');
                            }
                        } catch (Throwable $e) {
                            /* never block the sign-in on a migration problem */
                            log_action('vcci_migrate_failed', 'user', (int)$login['user']['id'], $e->getMessage());
                        }
                    }
                    redirect(url('account/index.php'));
                }
                $error = (string)($login['error'] ?? 'Unknown error.');
            }
        }
    }
}

/* ------------------------------------------------------------------- status */

$fresh   = $user === null ? null : db_one('SELECT * FROM users WHERE id = ?', [(int)$user['id']]);
$boundId = $fresh === null ? '' : trim((string)($fresh['vcci_id'] ?? ''));
$boundAt = $fresh === null ? '' : trim((string)($fresh['vcci_linked_at'] ?? ''));
$boundTo = $fresh === null ? '' : trim((string)($fresh['vcci_email'] ?? ''));

page_header($zh ? '通过 vc.ci 登录' : 'Sign in with vc.ci');
?>
<h1><?= h($zh ? '通过 vc.ci 登录' : 'Sign in with vc.ci') ?></h1>
<hr>

<?php if ($error !== ''): ?>
<p class="msg error"><?= h($error) ?></p>
<?php endif; ?>

<?php if (!vcci_columns_ready()): ?>
<p class="msg error"><?= h($zh
    ? '数据库还缺列：请先导入 upgrade-vcci.sql。'
    : 'The database is missing the vc.ci columns: import upgrade-vcci.sql first.') ?></p>
<?php endif; ?>

<?php if (!vcci_configured()): ?>
<p class="msg error"><?= h($zh
    ? '还没填凭据：后台「设置」里填 vc.ci 的 client_id 与 client_secret，或者写进 inc/config.php。'
    : 'No credentials yet: set the vc.ci client_id and client_secret in the settings or in inc/config.php.') ?></p>
<p class="small"><?= h($zh ? '回调地址必须是：' : 'The redirect URI must be:') ?>
<code><?= h(vcci_redirect_uri()) ?></code></p>
<?php endif; ?>

<?php if ($user === null): ?>
<h2><?= h($zh ? '登录' : 'Sign in') ?></h2>
<p><?= h($zh
    ? '用你的 vc.ci 账户登录本站，第一次登录会自动建一个本地账号，你可以继续绑定 / 解绑。'
    : 'Use your vc.ci account to sign in here. The first sign-in creates a local account.') ?></p>
<p><a class="btn" href="<?= h(url('account/vcci-callback.php?do=login')) ?>"><?= h($zh ? '用 vc.ci 登录' : 'Sign in with vc.ci') ?></a></p>
<p class="small">
<a href="<?= h(url('account/login.php')) ?>"><?= h(t('account.login_heading')) ?></a> &middot;
<a href="<?= h(url('account/register.php')) ?>"><?= h(t('account.register_heading')) ?></a>
</p>
<?php else: ?>
<h2><?= h($zh ? '绑定状态' : 'Link status') ?></h2>
<table>
<tr><th><?= h($zh ? '本账号' : 'This account') ?></th><td><code><?= h((string)$fresh['username']) ?></code></td></tr>
<tr><th>vc.ci</th><td>
<?php if ($boundId !== ''): ?>
  <span class="ok"><?= h($zh ? '已绑定' : 'linked') ?></span>
  <?= $boundTo !== '' ? '<code>' . h($boundTo) . '</code>' : '' ?>
  <?= $boundAt !== '' ? '<span class="small">(' . h($boundAt) . ')</span>' : '' ?>
<?php else: ?>
  <span class="hint"><?= h($zh ? '未绑定' : 'not linked') ?></span>
<?php endif; ?>
</td></tr>
<?php if ($boundId !== ''): ?>
<tr><th><?= h($zh ? '上次自动迁移' : 'Last migration') ?></th><td><?= h((string)($fresh['vcci_migrated_at'] ?? '') !== '' ? (string)$fresh['vcci_migrated_at'] : '—') ?></td></tr>
<?php endif; ?>
</table>

<p>
<?php if ($boundId === ''): ?>
  <a class="btn" href="<?= h(url('account/vcci-callback.php?do=link')) ?>"><?= h($zh ? '绑定 vc.ci 账户' : 'Link a vc.ci account') ?></a>
<?php else: ?>
  <form method="post" action="<?= h(url('account/vcci-callback.php?do=unlink')) ?>" style="display:inline">
    <?= csrf_field() ?>
    <input type="submit" value="<?= h($zh ? '解绑' : 'Unlink') ?>">
  </form>
<?php endif; ?>
</p>

<?php if ($boundId !== '' && function_exists('vcci_migrate_user')): ?>
<form method="post" action="<?= h(url('account/vcci-callback.php?do=migrate')) ?>" style="margin-top:1em">
  <?= csrf_field() ?>
  <input type="submit" value="<?= h($zh ? '立即迁移我在 vc.ci 的域名' : 'Migrate my vc.ci domains now') ?>">
</form>
<?php endif; ?>
<?php endif; ?>

<hr>
<p class="small"><?= h($zh
    ? '本站只会拿到 vc.ci 授权的资料：账号 id、用户名、邮箱、域名列表；不会拿到密码。'
    : 'Only what vc.ci grants is read here: account id, user name, e-mail and the domain list. Never a password.') ?></p>

<?php page_footer(); ?>

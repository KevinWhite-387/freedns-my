<?php
declare(strict_types=1);

/**
 * freedns.my - administration: accounts whose e-mail address was never confirmed.
 *
 * A registration stays in "pending" until the visitor clicks the link in the confirmation mail.
 * When that mail never arrives the account cannot be used at all, and the only fix used to be one
 * account at a time on users.php. This page collects them, confirms one, the selected ones or all
 * of them, and records who did it in the audit log.
 *
 * "Confirmed" means exactly what user_verify() does: status becomes active and the pending token is
 * removed. Suspended accounts are skipped - that status is a decision, not an unfinished sign-up.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$admin = require_admin();
require_owner('Unverified users');

$zh = function_exists('admin_lang') ? admin_lang() === 'zh' : false;
$L  = static function (string $en, string $zhText) use ($zh): string {
    return $zh ? $zhText : $en;
};

$errors = [];
$done   = 0;

/* ------------------------------------------------------------------ POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $action = strtolower((string)($_POST['action'] ?? ''));
    $id     = (int)($_POST['id'] ?? 0);

    $ids = [];
    if (isset($_POST['ids']) && is_array($_POST['ids'])) {
        foreach ($_POST['ids'] as $value) {
            $ids[] = (int)$value;
        }
    }
    $ids = array_values(array_unique(array_filter($ids, static fn($v) => $v > 0)));

    if ($action === 'one' && $id > 0) {
        $ids    = [$id];
        $action = 'bulk';
    }

    if ($action === 'all') {
        $ids = array_map(
            static fn(array $row): int => (int)$row['id'],
            db_all("SELECT id FROM users WHERE status = 'pending'
                       OR (verify_token IS NOT NULL AND verify_token <> '')
                    ORDER BY id ASC")
        );
        $action = 'bulk';
    }

    if ($action === 'bulk') {
        foreach ($ids as $one) {
            $user = db_one('SELECT id, username, email, status, verify_token FROM users WHERE id = ?', [$one]);
            if ($user === null || (string)$user['status'] === 'suspended') {
                continue;
            }
            $needs = (string)$user['status'] === 'pending'
                || trim((string)($user['verify_token'] ?? '')) !== '';
            if (!$needs) {
                continue;
            }

            db_query(
                "UPDATE users SET status = 'active', verify_token = NULL, verify_expires = NULL WHERE id = ?",
                [$one]
            );
            log_action('user_verified_by_admin', 'user', $one,
                (string)$user['username'] . ' <' . (string)$user['email'] . '> by ' . (string)$admin['username']);
            $done++;
        }

        flash($L($done . ' account(s) marked as confirmed.', $done . ' 个账号已标记为邮箱已验证。'), 'ok');
        redirect('verify-users.php');
    }
}

/* ------------------------------------------------------------------ list */
$ready = function_exists('users_table_ready') && users_table_ready();

$rows = [];
if ($ready) {
    $rows = db_all(
        "SELECT id, username, email, status, language, created_at, created_ip, verify_sent_at
           FROM users
          WHERE status = 'pending' OR (verify_token IS NOT NULL AND verify_token <> '')
          ORDER BY id ASC"
    );
}
$total = count($rows);
$countPending = 0;
foreach ($rows as $row) {
    if ((string)$row['status'] === 'pending') {
        $countPending++;
    }
}

page_header($L('Unverified users', '未验证邮箱的用户'));
?>
<h1><?= h($L('Unverified users', '未验证邮箱的用户')) ?></h1>

<p class="small">
  <?= h($L(
      'These accounts exist but their e-mail address was never confirmed, so they cannot sign in. '
      . 'Marking one confirms the address for it: the account becomes active and the pending token is dropped.',
      '这些账号已注册但邮箱未验证，因此无法登录。标记后即视为邮箱已验证：账号变为 active，待用验证令牌被清除。'
  )) ?>
</p>

<?= error_list($errors) ?>

<?php if (!$ready): ?>

<div class="msg error"><?= h($L('The users table is missing. Import upgrade-users.sql first.', '用户表不存在，请先导入 upgrade-users.sql。')) ?></div>

<?php else: ?>

<div class="msg info">
  <p>
    <?= h($L(
        $total . ' unverified account(s), ' . $countPending . ' of them still pending.',
        '共 ' . $total . ' 个未验证账号，其中 ' . $countPending . ' 个处于 pending。'
    )) ?>
  </p>
  <?php if ($total > 0): ?>
  <form class="inline" method="post" action="<?= h(url('admin/verify-users.php')) ?>"
        onsubmit="return confirm(<?= h(json_encode($L('Mark ALL unverified accounts as confirmed?', '确定要把全部未验证账号标记为已验证吗？'), JSON_UNESCAPED_UNICODE)) ?>);">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="all">
    <input type="submit" value="<?= h($L('Mark all as confirmed', '全部标记为已验证')) ?>">
  </form>
  <?php endif; ?>
</div>

<?php if ($rows === []): ?>

<p><?= h($L('Nothing to do: every account has a confirmed address.', '没有待处理的账号：所有账号的邮箱都已验证。')) ?></p>

<?php else: ?>

<form method="post" action="<?= h(url('admin/verify-users.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="action" value="bulk">

<table>
  <tr>
    <th style="width:2.5em">
      <input type="checkbox" title="<?= h($L('select all', '全选')) ?>"
             onclick="var box=this;document.querySelectorAll('input[name=&quot;ids[]&quot;]').forEach(function(c){c.checked=box.checked;});">
    </th>
    <th>#</th>
    <th><?= h($L('User name', '用户名')) ?></th>
    <th><?= h($L('E-mail', '邮箱')) ?></th>
    <th><?= h($L('Status', '状态')) ?></th>
    <th><?= h($L('Registered', '注册时间')) ?></th>
    <th><?= h($L('Mail sent', '发信时间')) ?></th>
    <th class="actions"></th>
  </tr>
  <?php foreach ($rows as $row): ?>
  <tr>
    <td><input type="checkbox" name="ids[]" value="<?= (int)$row['id'] ?>"></td>
    <td><?= (int)$row['id'] ?></td>
    <td><code><?= h((string)$row['username']) ?></code></td>
    <td class="small"><?= h((string)$row['email']) ?></td>
    <td><?= (string)$row['status'] === 'pending'
        ? '<span class="err">' . h($L('pending', '待验证')) . '</span>'
        : h((string)$row['status']) ?></td>
    <td class="small"><?= h((string)($row['created_at'] ?? '')) ?></td>
    <td class="small"><?= h((string)($row['verify_sent_at'] ?? '-')) ?></td>
    <td class="actions">
      <a href="<?= h(url('admin/verify-users.php?one=' . (int)$row['id'])) ?>"><?= h($L('Mark confirmed', '标记为已验证')) ?></a>
    </td>
  </tr>
  <?php endforeach; ?>
</table>

<p><input type="submit" value="<?= h($L('Mark the selected accounts as confirmed', '把选中的账号标记为已验证')) ?>"></p>
</form>

<p class="small"><?= h($L(
    'Suspended accounts are skipped on purpose: their status is a decision of the operator, not an unfinished registration.',
    '已封禁的账号会被跳过：那是管理员做出的处理决定，不属于“注册未完成”。'
)) ?></p>

<?php endif; ?>
<?php endif; ?>

<p><a href="<?= h(url('admin/users.php')) ?>"><?= h($L('Back to user accounts', '返回用户管理')) ?></a></p>
<?php
page_footer();
<?php
declare(strict_types=1);

/**
 * freedns.my - blog admin: who may post, and who carries the badge.
 *
 * Two flags per account: status (pending / approved / revoked) decides whether it can post at
 * all, verified decides whether the check mark is drawn next to its name.
 */

require dirname(__DIR__, 2) . '/inc/bootstrap.php';

require_owner('Blog authors');

$zh    = function_exists('lang') && is_zh();
$admin = current_admin();
$who   = is_array($admin) ? (string)($admin['username'] ?? 'admin') : 'admin';
$notice = '';
$error  = '';

if (!blog_enabled()) {
    page_header('Blog authors');
    echo '<h1>Blog authors</h1><hr><p class="msg error">'
        . h($zh ? '博客还没准备好：请先导入 upgrade-blog.sql。' : 'The blog is not ready: import upgrade-blog.sql first.')
        . '</p>';
    page_footer();
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');
    $userId = (int)($_POST['user_id'] ?? 0);
    $note   = trim((string)($_POST['note'] ?? ''));
    $verified = isset($_POST['verified']);

    if ($userId > 0 && $action === 'approve') {
        $res = blog_author_decide($userId, true, $who, $verified, $note);
        if (!empty($res['ok'])) {
            blog_message_send($userId,
                $zh ? '你的作者申请已通过' : 'Your author request was approved',
                $zh
                    ? '现在可以在博客里写文章了' . ($verified ? '，并且你的名字旁会显示认证标记。' : '。')
                    : ('You can write posts now' . ($verified ? ' and your name carries the verified badge.' : '.')),
                'blog_author', url('blog/console.php'), $who);
            $notice = ($zh ? '已批准作者 #' : 'Approved author #') . $userId;
        } else {
            $error = (string)($res['error'] ?? '');
        }
    } elseif ($userId > 0 && $action === 'revoke') {
        $res = blog_author_decide($userId, false, $who, false, $note);
        if (!empty($res['ok'])) {
            blog_message_send($userId,
                $zh ? '你的作者权限已被收回' : 'Your author rights were revoked',
                $zh ? '如果这不是你预期的，请在主站后台联系我们。' : 'If this is unexpected, contact us on the main site.',
                'blog_author', null, $who);
            $notice = ($zh ? '已收回作者权限 #' : 'Revoked author #') . $userId;
        } else {
            $error = (string)($res['error'] ?? '');
        }
    } elseif ($userId > 0 && $action === 'badge') {
        /* Only the badge, without touching the posting right. */
        $row = blog_author_row($userId);
        if ($row === null) {
            $error = $zh ? '这个账号还不是作者。' : 'That account is not an author.';
        } else {
            db_query('UPDATE blog_authors SET verified = ?, updated_at = NOW() WHERE user_id = ?',
                [$verified ? 1 : 0, $userId]);
            log_action('blog_badge_changed', 'user', $userId, $verified ? 'verified' : 'unverified');
            $notice = ($zh ? '认证标记已' : 'Badge ') . ($verified ? ($zh ? '开启' : 'on') : ($zh ? '关闭' : 'off'));
        }
    }
}

$pending  = blog_authors_queue('pending', 200);
$approved = blog_authors_queue('approved', 200);
$revoked  = blog_authors_queue('revoked', 30);

page_header($zh ? '作者与认证' : 'Blog authors');
?>
<p class="small">
<a href="<?= h(url('blog/admin/index.php')) ?>">← <?= h($zh ? '博客管理' : 'Blog admin') ?></a> &middot;
<a href="<?= h(url('blog/')) ?>"><?= h($zh ? '博客首页' : 'Blog') ?></a>
</p>
<h1><?= h($zh ? '作者与认证' : 'Blog authors') ?></h1>
<hr>

<?php if ($notice !== ''): ?><p class="msg ok"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="msg error"><?= h($error) ?></p><?php endif; ?>

<p class="small"><?= h($zh
    ? '普通用户默认不能发帖。批准后即可发帖；勾「认证」则在其名字旁显示徽章。'
    : 'Readers cannot post. Approving lets them post; ticking verified adds the badge.') ?></p>

<h2><?= h($zh ? '待批申请' : 'Waiting') ?> <span class="hint">(<?= count($pending) ?>)</span></h2>
<?php if ($pending === []): ?>
<p class="hint"><?= h($zh ? '队列是空的。' : 'Nothing waiting.') ?></p>
<?php else: ?>
<table>
<tr><th>#</th><th><?= h($zh ? '账号' : 'User') ?></th><th><?= h($zh ? '理由' : 'Reason') ?></th>
    <th><?= h($zh ? '已有文章' : 'Posts') ?></th><th><?= h($zh ? '时间' : 'Sent') ?></th><th><?= h($zh ? '操作' : 'Decide') ?></th></tr>
<?php foreach ($pending as $a): ?>
<tr>
  <td><?= (int)$a['id'] ?></td>
  <td><code><?= h((string)$a['username']) ?></code><br><span class="small"><?= h((string)$a['email']) ?></span><br>
      <a class="small" href="<?= h(url('blog/admin/author.php?user_id=' . (int)$a['user_id'])) ?>"><?= h($zh ? '编辑' : 'edit') ?></a></td>
  <td class="small" style="max-width:380px"><?= nl2br(h((string)($a['reason'] ?? ''))) ?></td>
  <td><?= (int)$a['post_count'] ?></td>
  <td class="small"><?= h((string)$a['created_at']) ?></td>
  <td>
    <form method="post" action="<?= h(url('blog/admin/authors.php')) ?>" style="margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="user_id" value="<?= (int)$a['user_id'] ?>">
      <input type="text" name="note" maxlength="190" placeholder="<?= h($zh ? '备注' : 'note') ?>" style="width:130px">
      <p style="margin:4px 0 0 0">
        <label class="inline"><input type="checkbox" name="verified" value="1"> <?= h($zh ? '认证' : 'verified') ?></label>
        <button class="btn" type="submit" name="action" value="approve"><?= h($zh ? '批准' : 'Approve') ?></button>
        <button class="btn" type="submit" name="action" value="revoke"><?= h($zh ? '驳回' : 'Reject') ?></button>
      </p>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= h($zh ? '现有作者' : 'Authors') ?> <span class="hint">(<?= count($approved) ?>)</span></h2>
<?php if ($approved === []): ?>
<p class="hint"><?= h($zh ? '还没有作者。' : 'No authors yet.') ?></p>
<?php else: ?>
<table>
<tr><th>#</th><th><?= h($zh ? '账号' : 'User') ?></th><th><?= h($zh ? '认证' : 'Badge') ?></th>
    <th><?= h($zh ? '文章' : 'Posts') ?></th><th><?= h($zh ? '批准时间' : 'Approved') ?></th><th><?= h($zh ? '操作' : 'Actions') ?></th></tr>
<?php foreach ($approved as $a): ?>
<tr>
  <td><?= (int)$a['id'] ?></td>
  <td><code><?= h((string)$a['username']) ?></code><br>
      <a class="small" href="<?= h(url('blog/admin/author.php?user_id=' . (int)$a['user_id'])) ?>"><?= h($zh ? '编辑' : 'edit') ?></a></td>
  <td><?= (int)$a['verified'] === 1 ? '<span class="ok">✓</span>' : '<span class="hint">—</span>' ?></td>
  <td><?= (int)$a['post_count'] ?></td>
  <td class="small"><?= h((string)($a['approved_at'] ?? '')) ?></td>
  <td class="small">
    <form method="post" action="<?= h(url('blog/admin/authors.php')) ?>" style="display:inline;margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="user_id" value="<?= (int)$a['user_id'] ?>">
      <input type="hidden" name="action" value="badge">
      <label class="inline"><input type="checkbox" name="verified" value="1"<?= (int)$a['verified'] === 1 ? ' checked' : '' ?>>
        <?= h($zh ? '认证' : 'verified') ?></label>
      <input type="submit" value="<?= h($zh ? '更新' : 'update') ?>">
    </form>
    <form method="post" action="<?= h(url('blog/admin/authors.php')) ?>" style="display:inline;margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="user_id" value="<?= (int)$a['user_id'] ?>">
      <input type="hidden" name="action" value="revoke">
      <input type="submit" value="<?= h($zh ? '收回' : 'revoke') ?>">
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($revoked !== []): ?>
<h2><?= h($zh ? '已收回' : 'Revoked') ?></h2>
<table>
<tr><th>#</th><th><?= h($zh ? '账号' : 'User') ?></th><th><?= h($zh ? '时间' : 'When') ?></th><th><?= h($zh ? '备注' : 'Note') ?></th></tr>
<?php foreach ($revoked as $a): ?>
<tr>
  <td><?= (int)$a['id'] ?></td>
  <td><code><?= h((string)$a['username']) ?></code><br>
      <a class="small" href="<?= h(url('blog/admin/author.php?user_id=' . (int)$a['user_id'])) ?>"><?= h($zh ? '编辑' : 'edit') ?></a></td>
  <td class="small"><?= h((string)($a['decided_at'] ?? '')) ?></td>
  <td class="small"><?= h((string)($a['admin_note'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<hr>
<p class="small"><?= h($zh
    ? '批准或收回时会自动给对方发一条站内信。官方账号（freedns）在第一次发文章时自动创建并已认证。'
    : 'Approving or revoking sends the account a message. The official account (freedns) is created and verified on the first post.') ?></p>

<?php page_footer(); ?>

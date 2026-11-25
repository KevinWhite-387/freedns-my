<?php
declare(strict_types=1);

/**
 * freedns.my - blog admin: the comment queue.
 *
 * A comment is written by a signed-in user and waits as 'pending'. Approving it makes it visible
 * on the post immediately; rejecting keeps it for the record. Owner only.
 */

require dirname(__DIR__, 2) . '/inc/bootstrap.php';

require_owner('Blog comments');

$zh    = function_exists('lang') && is_zh();
$admin = current_admin();
$who   = is_array($admin) ? (string)($admin['username'] ?? 'admin') : 'admin';
$notice = '';
$error  = '';

if (!blog_enabled()) {
    page_header('Blog comments');
    echo '<h1>Blog comments</h1><hr><p class="msg error">'
        . h($zh ? '博客还没准备好：请先导入 upgrade-blog.sql。' : 'The blog is not ready: import upgrade-blog.sql first.')
        . '</p>';
    page_footer();
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');
    $id     = (int)($_POST['id'] ?? 0);
    $note   = trim((string)($_POST['note'] ?? ''));

    if ($id > 0 && ($action === 'approve' || $action === 'reject')) {
        $res = blog_comment_decide($id, $action === 'approve', $who, $note);
        if (!empty($res['ok'])) {
            $notice = ($zh ? ($action === 'approve' ? '已批准 #' : '已驳回 #')
                : ($action === 'approve' ? 'Approved #' : 'Rejected #')) . $id;
        } else {
            $error = (string)($res['error'] ?? '');
        }
    }
}

$pending = blog_comments_pending(200);

$recent = [];
try {
    $recent = db_all(
        "SELECT c.*, u.username, p.title AS post_title, p.slug AS post_slug
         FROM blog_comments c JOIN users u ON u.id = c.user_id JOIN blog_posts p ON p.id = c.post_id
         WHERE c.status <> 'pending' ORDER BY c.decided_at DESC, c.id DESC LIMIT 30"
    );
} catch (Throwable $e) {
    $recent = [];
}

page_header($zh ? '评论审核' : 'Blog comments');
?>
<p class="small">
<a href="<?= h(url('blog/admin/index.php')) ?>">← <?= h($zh ? '博客管理' : 'Blog admin') ?></a> &middot;
<a href="<?= h(url('blog/')) ?>"><?= h($zh ? '博客首页' : 'Blog') ?></a>
</p>
<h1><?= h($zh ? '评论审核' : 'Blog comments') ?></h1>
<hr>

<?php if ($notice !== ''): ?><p class="msg ok"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="msg error"><?= h($error) ?></p><?php endif; ?>

<p class="small"><?= h($zh
    ? '审核开关：当前' : 'Review switch: ') ?>
<code><?= setting_bool('blog_comments_need_review', true) ? ($zh ? '先审后发' : 'review first') : ($zh ? '直接显示' : 'publish directly') ?></code>
<?= h($zh ? '（在设置的 blog_comments_need_review 里改）' : '(setting blog_comments_need_review)') ?></p>

<h2><?= h($zh ? '待审' : 'Waiting') ?> <span class="hint">(<?= count($pending) ?>)</span></h2>
<?php if ($pending === []): ?>
<p class="hint"><?= h($zh ? '队列是空的。' : 'Nothing waiting.') ?></p>
<?php else: ?>
<table>
<tr><th>#</th><th><?= h($zh ? '评论者' : 'User') ?></th><th><?= h($zh ? '文章' : 'Post') ?></th>
    <th><?= h($zh ? '内容' : 'Comment') ?></th><th><?= h($zh ? '时间' : 'When') ?></th>
    <th><?= h($zh ? '操作' : 'Decide') ?></th></tr>
<?php foreach ($pending as $c): ?>
<tr>
  <td><?= (int)$c['id'] ?></td>
  <td><code><?= h((string)$c['username']) ?></code><br><span class="small"><?= h((string)($c['created_ip'] ?? '')) ?></span></td>
  <td><a href="<?= h(url('blog/post.php?slug=' . rawurlencode((string)$c['post_slug']))) ?>"><?= h((string)$c['post_title']) ?></a></td>
  <td style="max-width:420px"><?= nl2br(h((string)$c['body'])) ?></td>
  <td class="small"><?= h((string)$c['created_at']) ?></td>
  <td>
    <form method="post" action="<?= h(url('blog/admin/comments.php')) ?>" style="margin:0">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
      <input type="text" name="note" maxlength="190" placeholder="<?= h($zh ? '备注（可选）' : 'note (optional)') ?>" style="width:140px">
      <p style="margin:4px 0 0 0">
        <button class="btn" type="submit" name="action" value="approve"><?= h($zh ? '批准' : 'Approve') ?></button>
        <button class="btn" type="submit" name="action" value="reject"><?= h($zh ? '驳回' : 'Reject') ?></button>
      </p>
    </form>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<h2><?= h($zh ? '最近处理' : 'Recently decided') ?></h2>
<?php if ($recent === []): ?>
<p class="hint">—</p>
<?php else: ?>
<table>
<tr><th>#</th><th><?= h($zh ? '评论者' : 'User') ?></th><th><?= h($zh ? '文章' : 'Post') ?></th>
    <th><?= h($zh ? '状态' : 'Status') ?></th><th><?= h($zh ? '处理' : 'Decided') ?></th><th><?= h($zh ? '备注' : 'Note') ?></th></tr>
<?php foreach ($recent as $c): ?>
<tr>
  <td><?= (int)$c['id'] ?></td>
  <td><code><?= h((string)$c['username']) ?></code></td>
  <td class="small"><?= h((string)$c['post_title']) ?></td>
  <td><?php $st = (string)$c['status'];
      echo '<span class="' . ($st === 'approved' ? 'ok' : 'err') . '">' . h($st) . '</span>'; ?></td>
  <td class="small"><?= h((string)($c['decided_at'] ?? '')) ?> · <?= h((string)($c['decided_by'] ?? '')) ?></td>
  <td class="small"><?= h((string)($c['admin_note'] ?? '')) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<hr>
<p class="small"><?= h($zh
    ? '批准后评论立刻出现在文章页；驳回后只有你能看到（保留在记录里）。'
    : 'Approving shows the comment at once; rejecting keeps it in the record only.') ?></p>

<?php page_footer(); ?>

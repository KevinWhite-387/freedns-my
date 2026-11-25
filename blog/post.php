<?php
declare(strict_types=1);

/**
 * freedns.my - one blog post, with its comments.
 *
 * Comments are submitted by signed-in users only and start as 'pending' unless the operator
 * turned the review off. Nothing here needs JavaScript.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$zh   = function_exists('lang') && is_zh();
$user = current_user();
$slug = trim((string)($_GET['slug'] ?? ''));
$post = blog_enabled() ? blog_post_by_slug($slug) : null;

if ($post === null || (string)$post['status'] !== 'published') {
    page_header($zh ? '文章不存在' : 'Post not found');
    echo '<h1>' . h($zh ? '文章不存在' : 'Post not found') . '</h1><hr>';
    echo '<p class="msg error">' . h($zh ? '这个地址没有对应的文章。' : 'There is no post at that address.') . '</p>';
    echo '<p><a href="' . h(url('blog/')) . '">' . h($zh ? '返回博客首页' : 'Back to the blog') . '</a></p>';
    page_footer();
    exit;
}

$postId = (int)$post['id'];
$notice = '';
$error  = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    if ($user === null) {
        $error = $zh ? '请先登录（用主站账号）。' : 'Please sign in first (the registry account).';
    } else {
        $res = blog_comment_add($postId, $user, (string)($_POST['body'] ?? ''));
        if (!empty($res['ok'])) {
            $notice = !empty($res['pending'])
                ? ($zh ? '评论已提交，等管理员审核后显示。' : 'Comment submitted and waiting for review.')
                : ($zh ? '评论已发布。' : 'Comment posted.');
        } else {
            $error = (string)($res['error'] ?? '');
        }
    }
}

blog_post_view($postId);
$comments = blog_comments_approved($postId);

page_header(blog_text($post, 'title') . ' · Blog');
?>
<p class="small"><a href="<?= h(url('blog/')) ?>">← <?= h($zh ? '博客首页' : 'Blog home') ?></a></p>
<h1><?= h(blog_text($post, 'title')) ?></h1>
<p class="sub">
<?= h(blog_date((string)($post['published_at'] ?? $post['created_at'] ?? ''))) ?>
&middot; <code><?= h((string)$post['author_name']) ?></code>
<?php if ((int)($post['author_verified'] ?? 0) === 1): ?><span class="ok">✓</span>
<?php
      $badgeText = trim((string)($post['author_badge'] ?? ''));
      echo $badgeText !== '' ? ' <span class="pill">' . h($badgeText) . '</span>' : ' <span class="ok">' . h($zh ? '认证作者' : 'verified') . '</span>';
      ?><?php endif; ?>
<?php if ((string)($post['category_name'] ?? '') !== ''): ?>
&middot; <a href="<?= h(url('blog/?view=index&category=' . (int)($post['category_id'] ?? 0))) ?>"><?= h(blog_text($post, 'category_name')) ?></a>
<?php endif; ?>
&middot; <?= (int)$post['views'] ?> <?= h($zh ? '次浏览' : 'views') ?>
</p>
<hr>

<div class="post-body">
<?php
/* 正文按纯文本存 ✓ 段落按空行切 ✓ 不做 HTML 透传（防 XSS ✓ 也不需要富文本 ✗） */
$body = blog_text($post, 'body');
$paras = preg_split('/\n{2,}/', str_replace("\r\n", "\n", $body)) ?: [];
foreach ($paras as $para) {
    $para = trim($para);
    if ($para === '') {
        continue;
    }
    echo '<p>' . nl2br(h($para)) . '</p>' . "\n";
}
?>
</div>

<?php
/* About the author: badge text, biography and link, all optional ✓ */
$authorBadge = trim((string)($post['author_badge'] ?? ''));
$authorBio   = trim((string)($post['author_bio'] ?? ''));
$authorSite  = trim((string)($post['author_website'] ?? ''));
if ($authorBio !== '' || $authorSite !== '' || $authorBadge !== ''):
?>
<hr>
<h2><?= h($zh ? '关于作者' : 'About the author') ?></h2>
<p class="small">
  <code><?= h((string)$post['author_name']) ?></code>
  <?php if ($authorBadge !== ''): ?><span class="pill"><?= h($authorBadge) ?></span><?php endif; ?>
  <?php if ((int)($post['author_verified'] ?? 0) === 1): ?><span class="ok">✓</span><?php endif; ?>
  <?php if ($authorSite !== ''): ?>&middot; <a href="<?= h($authorSite) ?>" rel="noopener nofollow"><?= h($authorSite) ?></a><?php endif; ?>
</p>
<?php if ($authorBio !== ''): ?><p><?= nl2br(h($authorBio)) ?></p><?php endif; ?>
<?php endif; ?>

<hr>
<h2><?= h($zh ? '评论' : 'Comments') ?> <span class="hint">(<?= count($comments) ?>)</span></h2>

<?php if ($notice !== ''): ?><p class="msg ok"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="msg error"><?= h($error) ?></p><?php endif; ?>

<?php if ($comments === []): ?>
<p class="hint"><?= h($zh ? '还没有评论。' : 'No comments yet.') ?></p>
<?php else: ?>
<?php foreach ($comments as $c): ?>
<div style="border-left:1px solid var(--border);padding-left:.8em;margin:.9em 0">
<p class="small"><code><?= h((string)$c['username']) ?></code>
&middot; <?= h(blog_date((string)$c['created_at'])) ?></p>
<p><?= nl2br(h((string)$c['body'])) ?></p>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php if ((int)($post['allow_comments'] ?? 1) !== 1): ?>
<p class="hint"><?= h($zh ? '这篇文章关闭了评论。' : 'Comments are closed for this post.') ?></p>
<?php elseif ($user === null): ?>
<p><a href="<?= h(url('account/login.php')) ?>"><?= h($zh ? '登录' : 'Sign in') ?></a>
<?= h($zh ? '后可以评论（用主站账号）。' : 'to comment (registry account).') ?></p>
<?php else: ?>
<h3><?= h($zh ? '写评论' : 'Write a comment') ?></h3>
<form method="post" action="<?= h(url('blog/post.php?slug=' . rawurlencode($slug))) ?>">
  <?= csrf_field() ?>
  <p><textarea name="body" rows="4" maxlength="4000" style="width:100%;max-width:700px" required></textarea></p>
  <p class="hint"><?= h($zh
      ? '评论会先经过审核再显示。请勿灌水或发表人身攻击。'
      : 'Comments are reviewed before they appear.') ?></p>
  <p><input type="submit" value="<?= h($zh ? '提交评论' : 'Post comment') ?>"></p>
</form>
<?php endif; ?>

<?php page_footer(); ?>

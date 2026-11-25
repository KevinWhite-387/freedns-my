<?php
declare(strict_types=1);

/**
 * freedns.my - blog console.
 *
 * Signed in with the registry account - there is no separate blog login. What it shows:
 *   * the profile the registry already knows (name, e-mail, since when, how many names)
 *   * the system messages (the only kind the blog sends)
 *   * the posts of this account, when it is an author
 *   * the author request, and whether a verified badge was granted
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$user = require_user();
$zh   = function_exists('lang') && is_zh();
$uid  = (int)$user['id'];
$notice = '';
$error  = '';

if (!blog_enabled()) {
    page_header($zh ? '博客控制台' : 'Blog console');
    echo '<h1>' . h($zh ? '博客控制台' : 'Blog console') . '</h1><hr>';
    echo '<p class="msg error">' . h($zh
        ? '博客还没准备好：请先导入 upgrade-blog.sql，并在后台打开 blog_enabled。'
        : 'The blog is not ready: import upgrade-blog.sql and switch blog_enabled on.') . '</p>';
    page_footer();
    exit;
}

/* ------------------------------------------------------------------ actions */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'author_request') {
        if (!setting_bool('blog_author_requests_open', true)) {
            $error = $zh ? '暂时不接受作者申请。' : 'Author requests are closed right now.';
        } else {
            $res = blog_author_request($uid, trim((string)($_POST['reason'] ?? '')));
            if (!empty($res['ok'])) {
                blog_message_send($uid,
                    $zh ? '收到你的作者申请' : 'Your author request arrived',
                    $zh ? '我们已收到申请，审核结果会通过站内信通知你。' : 'Your request was received; you will get a message here.',
                    'blog_author');
                flash($zh ? '申请已提交，等管理员审核。' : 'Request submitted.', 'ok');
                redirect(url('blog/console.php'));
            }
            $error = (string)($res['error'] ?? '');
        }
    }
}

/* --------------------------------------------------------------- read data */

$fresh = db_one('SELECT * FROM users WHERE id = ?', [$uid]);
$author = blog_author_row($uid);
$isAuthor = blog_is_author($fresh);
$isVerified = blog_is_verified($fresh);
$unread = blog_unread_count($uid);

$messages = blog_messages_for($uid, 50);

/* Opening a message marks it read. */
$openId = (int)($_GET['msg'] ?? 0);
$openMsg = null;
if ($openId > 0) {
    foreach ($messages as $m) {
        if ((int)$m['id'] === $openId) {
            $openMsg = $m;
            break;
        }
    }
    if ($openMsg !== null) {
        blog_message_read($openId, $uid);
    }
}

$myPosts = [];
if ($isAuthor) {
    try {
        $myPosts = db_all(
            'SELECT id, slug, title, title_zh, status, featured, views, published_at, created_at
             FROM blog_posts WHERE author_id = ? ORDER BY id DESC LIMIT 100',
            [$uid]
        );
    } catch (Throwable $e) {
        $myPosts = [];
    }
}

$domainCount = 0;
try {
    $domainCount = (int)db_value('SELECT COUNT(*) FROM domains WHERE registrant_email = ?', [(string)$fresh['email']], 0);
} catch (Throwable $e) {
    $domainCount = 0;
}

page_header($zh ? '博客控制台' : 'Blog console');
?>
<p class="small">
<a href="<?= h(url('blog/')) ?>">← <?= h($zh ? '博客首页' : 'Blog home') ?></a> &middot;
<a href="<?= h(url('account/index.php')) ?>"><?= h(t('account.console_heading')) ?></a>
</p>
<h1><?= h($zh ? '博客控制台' : 'Blog console') ?></h1>
<hr>

<?php if ($notice !== ''): ?><p class="msg ok"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="msg error"><?= h($error) ?></p><?php endif; ?>

<h2><?= h($zh ? '个人信息' : 'Profile') ?></h2>
<table>
<tr><th><?= h($zh ? '用户名' : 'Username') ?></th><td><code><?= h((string)$fresh['username']) ?></code></td></tr>
<tr><th><?= h($zh ? '邮箱' : 'E-mail') ?></th><td><?= h((string)$fresh['email']) ?></td></tr>
<tr><th><?= h($zh ? '注册时间' : 'Member since') ?></th><td><?= h((string)$fresh['created_at']) ?></td></tr>
<tr><th><?= h($zh ? '我的域名' : 'My names') ?></th><td><?= $domainCount ?></td></tr>
<tr><th><?= h($zh ? '博客身份' : 'Blog role') ?></th><td>
<?php if ($isAuthor): ?>
  <span class="ok"><?= h($zh ? '作者' : 'author') ?></span>
  <?php if ($isVerified): ?><span class="pill">✓ <?= h($zh ? '认证' : 'verified') ?></span><?php endif; ?>
<?php elseif ($author !== null && (string)$author['status'] === 'pending'): ?>
  <span class="hint"><?= h($zh ? '作者申请待审核' : 'author request pending') ?></span>
<?php elseif ($author !== null && (string)$author['status'] === 'revoked'): ?>
  <span class="err"><?= h($zh ? '作者权限已收回' : 'author rights revoked') ?></span>
<?php else: ?>
  <span class="hint"><?= h($zh ? '普通用户（不能发帖）' : 'reader (cannot post)') ?></span>
<?php endif; ?>
</td></tr>
<tr><th><?= h($zh ? '站内信' : 'Messages') ?></th><td>
<?= (int)$unread ?> <?= h($zh ? '条未读' : 'unread') ?>
<?php if ($unread > 0): ?> <span class="pill"><?= (int)$unread ?></span><?php endif; ?>
</td></tr>
</table>

<?php if (!$isAuthor && ($author === null || (string)$author['status'] !== 'pending')): ?>
<h2><?= h($zh ? '申请作者权限' : 'Request author rights') ?></h2>
<p><?= h($zh
    ? '普通用户不能发帖。想写文章的话在这里申请，写清楚你打算写什么。'
    : 'Readers cannot post. Ask here and say what you plan to write about.') ?></p>
<form method="post" action="<?= h(url('blog/console.php')) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="author_request">
  <p><textarea name="reason" rows="4" maxlength="2000" style="width:100%;max-width:640px" required
      placeholder="<?= h($zh ? '例如：我想写域名解析教程与使用经验' : 'e.g. tutorials about DNS records') ?>"></textarea></p>
  <p><input type="submit" value="<?= h($zh ? '提交申请' : 'Submit request') ?>"></p>
</form>
<?php endif; ?>

<h2><?= h($zh ? '站内信' : 'Messages') ?></h2>
<?php if ($openMsg !== null): ?>
<div class="msg info">
  <strong><?= h((string)$openMsg['subject']) ?></strong><br>
  <span class="small"><?= h((string)$openMsg['created_at']) ?></span>
  <p><?= nl2br(h((string)$openMsg['body'])) ?></p>
  <?php if ((string)($openMsg['link'] ?? '') !== ''): ?>
    <p><a href="<?= h((string)$openMsg['link']) ?>"><?= h($zh ? '去看看' : 'Open') ?> →</a></p>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($messages === []): ?>
<p class="hint"><?= h($zh ? '还没有站内信。' : 'No messages yet.') ?></p>
<?php else: ?>
<table>
<tr><th></th><th><?= h($zh ? '标题' : 'Subject') ?></th><th><?= h($zh ? '时间' : 'When') ?></th></tr>
<?php foreach ($messages as $m): ?>
<tr>
  <td><?= (string)$m['read_at'] === '' || $m['read_at'] === null ? '<span class="pill">new</span>' : '' ?></td>
  <td><a href="<?= h(url('blog/console.php?msg=' . (int)$m['id'])) ?>"><?= h((string)$m['subject']) ?></a></td>
  <td class="small"><?= h((string)$m['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<?php if ($isAuthor): ?>
<h2><?= h($zh ? '我的文章' : 'My posts') ?></h2>
<?php if ($myPosts === []): ?>
<p class="hint"><?= h($zh ? '还没写过。' : 'Nothing written yet.') ?></p>
<?php else: ?>
<table>
<tr><th><?= h($zh ? '标题' : 'Title') ?></th><th><?= h($zh ? '状态' : 'Status') ?></th>
    <th><?= h($zh ? '浏览' : 'Views') ?></th><th><?= h($zh ? '时间' : 'Date') ?></th></tr>
<?php foreach ($myPosts as $p): ?>
<tr>
  <td>
    <?php if ((string)$p['status'] === 'published'): ?>
      <a href="<?= h(url('blog/post.php?slug=' . rawurlencode((string)$p['slug']))) ?>"><?= h(blog_text($p, 'title')) ?></a>
    <?php else: ?>
      <?= h(blog_text($p, 'title')) ?>
    <?php endif; ?>
    <?php if ((int)$p['featured'] === 1): ?> <span class="pill"><?= h($zh ? '精选' : 'featured') ?></span><?php endif; ?>
  </td>
  <td><?= h((string)$p['status']) ?></td>
  <td><?= (int)$p['views'] ?></td>
  <td class="small"><?= h(blog_date((string)($p['published_at'] ?? $p['created_at']))) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>
<p class="small"><?= h($zh
    ? '写文章与编辑在后台的博客管理里进行；这里只列出你自己的。'
    : 'Writing and editing happen in the blog admin; this is only a list.') ?></p>
<?php endif; ?>

<hr>
<p class="small"><?= h($zh
    ? '这个博客和注册局共用账号：注册、找回密码都在主站进行。'
    : 'The blog shares the registry accounts: registration and password recovery happen on the main site.') ?></p>

<?php page_footer(); ?>

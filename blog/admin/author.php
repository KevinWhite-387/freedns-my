<?php
declare(strict_types=1);

/**
 * freedns.my - blog admin: one author, fully editable.
 *
 * Reached from the author list, the same way a registry user is edited from the user list:
 * the account details are shown, and everything that belongs to the blog - posting rights, the
 * verified badge, the badge text, the biography and a link - can be changed on this page.
 *
 * The account itself (username, e-mail, password) is not touched here; that belongs to the
 * registry's own user page.
 */

require dirname(__DIR__, 2) . '/inc/bootstrap.php';

require_owner('Blog author');

$zh    = function_exists('lang') && is_zh();
$admin = current_admin();
$who   = is_array($admin) ? (string)($admin['username'] ?? 'admin') : 'admin';
$notice = '';
$error  = '';

if (!blog_enabled()) {
    page_header('Blog author');
    echo '<h1>Blog author</h1><hr><p class="msg error">'
        . h($zh ? '博客还没准备好：请先导入 upgrade-blog.sql。' : 'The blog is not ready: import upgrade-blog.sql first.')
        . '</p>';
    page_footer();
    exit;
}

$userId = (int)($_GET['user_id'] ?? $_POST['user_id'] ?? 0);
$userRow = $userId > 0 ? db_one('SELECT * FROM users WHERE id = ?', [$userId]) : null;

if ($userRow === null) {
    page_header($zh ? '账号不存在' : 'No such account');
    echo '<h1>' . h($zh ? '账号不存在' : 'No such account') . '</h1><hr>';
    echo '<p class="msg error">' . h($zh ? '找不到这个账号。' : 'That account does not exist.') . '</p>';
    echo '<p><a href="' . h(url('blog/admin/authors.php')) . '">← ' . h($zh ? '返回作者列表' : 'Back to authors') . '</a></p>';
    page_footer();
    exit;
}

/** Creating the row when it is missing keeps this page usable for any account. */
function blog_author_ensure(int $userId): void
{
    if (blog_author_row($userId) !== null) {
        return;
    }
    db_query('INSERT INTO blog_authors (user_id, status, verified, created_at, updated_at)
              VALUES (?, \'pending\', 0, NOW(), NOW())', [$userId]);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        blog_author_ensure($userId);

        $status   = (string)($_POST['status'] ?? 'pending');
        $status   = in_array($status, ['pending', 'approved', 'revoked'], true) ? $status : 'pending';
        $verified = isset($_POST['verified']) ? 1 : 0;
        $badge    = trim((string)($_POST['badge'] ?? ''));
        $bio      = trim((string)($_POST['bio'] ?? ''));
        $website  = trim((string)($_POST['website'] ?? ''));

        if (mb_strlen($badge) > 64) {
            $error = $zh ? '徽章文字太长了（最多 64 字）。' : 'The badge text is too long (64 max).';
        } elseif (mb_strlen($bio) > 4000) {
            $error = $zh ? '简介太长了（最多 4000 字）。' : 'The biography is too long (4000 max).';
        } elseif ($website !== '' && !preg_match('#^https?://#i', $website)) {
            $error = $zh ? '链接必须以 http:// 或 https:// 开头。' : 'The link must start with http:// or https://.';
        } else {
            try {
                db_query(
                    'UPDATE blog_authors SET status = ?, verified = ?, badge = ?, bio = ?, website = ?,
                        decided_by = ?, decided_at = COALESCE(decided_at, NOW()), updated_at = NOW()
                     WHERE user_id = ?',
                    [$status, $verified, $badge === '' ? null : $badge, $bio === '' ? null : $bio,
                        $website === '' ? null : $website, $who, $userId]
                );
                if ($status === 'approved') {
                    db_query('UPDATE blog_authors SET approved_at = COALESCE(approved_at, NOW()) WHERE user_id = ?', [$userId]);
                }
                log_action('blog_author_edited', 'user', $userId,
                    'status=' . $status . ' verified=' . $verified . ' by ' . $who);
                $notice = $zh ? '已保存。' : 'Saved.';
            } catch (Throwable $e) {
                $error = ($zh ? '保存失败：' : 'Save failed: ') . $e->getMessage();
            }
        }
    } elseif ($action === 'notify') {
        $subject = trim((string)($_POST['subject'] ?? ''));
        $body    = trim((string)($_POST['body'] ?? ''));
        if ($subject === '' || $body === '') {
            $error = $zh ? '标题和内容都要填。' : 'Subject and body are both required.';
        } else {
            blog_message_send($userId, $subject, $body, 'blog_author', url('blog/console.php'), $who);
            $notice = $zh ? '站内信已发送。' : 'Message sent.';
        }
    }
}

$author = blog_author_row($userId);

$postCount = 0;
try {
    $postCount = (int)db_value('SELECT COUNT(*) FROM blog_posts WHERE author_id = ?', [$userId], 0);
} catch (Throwable $e) {
    $postCount = 0;
}
$domainCount = 0;
try {
    $domainCount = (int)db_value('SELECT COUNT(*) FROM domains WHERE registrant_email = ?', [(string)$userRow['email']], 0);
} catch (Throwable $e) {
    $domainCount = 0;
}
$recentPosts = [];
try {
    $recentPosts = db_all('SELECT id, slug, title, title_zh, status, published_at FROM blog_posts
                           WHERE author_id = ? ORDER BY id DESC LIMIT 10', [$userId]);
} catch (Throwable $e) {
    $recentPosts = [];
}

page_header($zh ? '编辑作者' : 'Edit author');
?>
<p class="small">
<a href="<?= h(url('blog/admin/authors.php')) ?>">← <?= h($zh ? '作者与认证' : 'Authors') ?></a> &middot;
<a href="<?= h(url('admin/users.php')) ?>"><?= h($zh ? '注册局用户列表' : 'Registry users') ?></a>
</p>
<h1><?= h($zh ? '编辑作者' : 'Edit author') ?>: <code><?= h((string)$userRow['username']) ?></code></h1>
<hr>

<?php if ($notice !== ''): ?><p class="msg ok"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="msg error"><?= h($error) ?></p><?php endif; ?>

<h2><?= h($zh ? '账号信息（只读）' : 'Account (read only)') ?></h2>
<table>
<tr><th><?= h($zh ? '用户 id' : 'User id') ?></th><td><?= (int)$userRow['id'] ?></td></tr>
<tr><th><?= h($zh ? '用户名' : 'Username') ?></th><td><code><?= h((string)$userRow['username']) ?></code></td></tr>
<tr><th><?= h($zh ? '邮箱' : 'E-mail') ?></th><td><?= h((string)$userRow['email']) ?></td></tr>
<tr><th><?= h($zh ? '账号状态' : 'Status') ?></th><td><?= h((string)$userRow['status']) ?></td></tr>
<tr><th><?= h($zh ? '注册时间' : 'Member since') ?></th><td><?= h((string)$userRow['created_at']) ?></td></tr>
<tr><th><?= h($zh ? '域名数' : 'Names') ?></th><td><?= $domainCount ?></td></tr>
<tr><th><?= h($zh ? '文章数' : 'Posts') ?></th><td><?= $postCount ?></td></tr>
<tr><th><?= h($zh ? 'vc.ci' : 'vc.ci') ?></th><td>
<?= trim((string)($userRow['vcci_id'] ?? '')) !== ''
    ? '<span class="ok">' . h((string)($userRow['vcci_email'] ?? $userRow['vcci_id'])) . '</span>'
    : '<span class="hint">—</span>' ?>
</td></tr>
<tr><th><?= h($zh ? '开发者' : 'Developer') ?></th><td>
<?= (int)($userRow['developer'] ?? 0) === 1 ? '<span class="ok">✓</span>' : '<span class="hint">—</span>' ?>
</td></tr>
</table>

<h2><?= h($zh ? '博客权限与认证' : 'Blog rights and badge') ?></h2>
<form method="post" action="<?= h(url('blog/admin/author.php?user_id=' . $userId)) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="user_id" value="<?= $userId ?>">

  <p><label for="status"><?= h($zh ? '发帖权限' : 'Posting right') ?></label><br>
  <select id="status" name="status">
    <?php foreach (['pending', 'approved', 'revoked'] as $st): ?>
      <option value="<?= h($st) ?>"<?= (string)($author['status'] ?? 'pending') === $st ? ' selected' : '' ?>>
        <?= h($st === 'approved' ? ($zh ? 'approved（可发帖）' : 'approved (may post)')
            : ($st === 'revoked' ? ($zh ? 'revoked（已收回）' : 'revoked') : ($zh ? 'pending（待批）' : 'pending'))) ?>
      </option>
    <?php endforeach; ?>
  </select></p>

  <p><label class="inline"><input type="checkbox" name="verified" value="1"<?= (int)($author['verified'] ?? 0) === 1 ? ' checked' : '' ?>>
    <?= h($zh ? '认证徽章（名字旁显示 ✓）' : 'Verified badge (✓ next to the name)') ?></label></p>

  <p><label for="badge"><?= h($zh ? '徽章文字（显示在名字后，例如「官方」「认证作者」）' : 'Badge text (shown after the name)') ?></label><br>
  <input type="text" id="badge" name="badge" maxlength="64" style="width:100%;max-width:420px"
         value="<?= h((string)($author['badge'] ?? '')) ?>"
         placeholder="<?= h($zh ? '官方 / Official' : 'Official') ?>"></p>

  <p><label for="bio"><?= h($zh ? '作者简介（纯文本，显示在文章页）' : 'Biography (plain text, shown on posts)') ?></label><br>
  <textarea id="bio" name="bio" rows="5" maxlength="4000" style="width:100%;max-width:700px"><?= h((string)($author['bio'] ?? '')) ?></textarea></p>

  <p><label for="website"><?= h($zh ? '链接（可选，http(s):// 开头）' : 'Link (optional, http(s)://)') ?></label><br>
  <input type="text" id="website" name="website" maxlength="255" style="width:100%;max-width:480px"
         value="<?= h((string)($author['website'] ?? '')) ?>"></p>

  <p><input type="submit" value="<?= h($zh ? '保存' : 'Save') ?>"></p>
</form>

<h2><?= h($zh ? '给他/她发站内信' : 'Send a message') ?></h2>
<form method="post" action="<?= h(url('blog/admin/author.php?user_id=' . $userId)) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="notify">
  <input type="hidden" name="user_id" value="<?= $userId ?>">
  <p><input type="text" name="subject" maxlength="190" placeholder="<?= h($zh ? '标题' : 'subject') ?>"
            style="width:100%;max-width:520px" required></p>
  <p><textarea name="body" rows="4" style="width:100%;max-width:700px" required
      placeholder="<?= h($zh ? '内容' : 'body') ?>"></textarea></p>
  <p><input type="submit" value="<?= h($zh ? '发送' : 'Send') ?>"></p>
</form>

<h2><?= h($zh ? '最近的文章' : 'Recent posts') ?></h2>
<?php if ($recentPosts === []): ?>
<p class="hint">—</p>
<?php else: ?>
<table>
<tr><th>#</th><th><?= h($zh ? '标题' : 'Title') ?></th><th><?= h($zh ? '状态' : 'Status') ?></th><th><?= h($zh ? '时间' : 'Date') ?></th></tr>
<?php foreach ($recentPosts as $p): ?>
<tr>
  <td><?= (int)$p['id'] ?></td>
  <td><?= h(blog_text($p, 'title')) ?></td>
  <td><?= h((string)$p['status']) ?></td>
  <td class="small"><?= h(blog_date((string)($p['published_at'] ?? ''))) ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<hr>
<p class="small"><?= h($zh
    ? '账号本体的用户名 / 邮箱 / 密码在主站的用户页里改；这里只管博客这一侧。'
    : 'The username, e-mail and password belong to the registry user page; this page only manages the blog side.') ?></p>

<?php page_footer(); ?>

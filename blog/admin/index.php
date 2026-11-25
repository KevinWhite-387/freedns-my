<?php
declare(strict_types=1);

/**
 * freedns.my - blog admin: write and edit posts.
 *
 * Owner only. Posts are plain text (paragraphs split on blank lines) so nothing typed here can
 * inject markup into a page; the title, the Chinese title, the excerpt and the slug are separate
 * fields so a bilingual post does not need HTML.
 *
 * Posts need an author row in `users` (the blog links authors to registry accounts). The first
 * save creates a dedicated "official" account when none exists, and marks it verified, so the
 * operator can publish without inventing a user by hand.
 */

require dirname(__DIR__, 2) . '/inc/bootstrap.php';

require_owner('Blog');

$zh    = function_exists('lang') && is_zh();
$admin = current_admin();
$who   = is_array($admin) ? (string)($admin['username'] ?? 'admin') : 'admin';
$notice = '';
$error  = '';

if (!blog_enabled()) {
    page_header('Blog admin');
    echo '<h1>Blog admin</h1><hr>';
    echo '<p class="msg error">' . h($zh ? '博客还没准备好：请先导入 upgrade-blog.sql。'
        : 'The blog is not ready: import upgrade-blog.sql first.') . '</p>';
    page_footer();
    exit;
}

/* ------------------------------------------------- the official author account */

/**
 * The user row that owns posts written here. Created once, named after the site, verified so it
 * carries the badge. This is the "official account" the blog needs and nothing more.
 */
function blog_admin_author_id(string $adminName): int
{
    static $id = null;
    if ($id !== null) {
        return $id;
    }

    $username = 'freedns';
    $row = db_one('SELECT id FROM users WHERE username_lower = ? LIMIT 1', [$username]);
    if ($row === null) {
        $email = 'blog@freedns.my';
        db_query(
            'INSERT INTO users (username, username_lower, email, email_lower, password_hash, status,
                verify_token, verify_expires, verify_sent_at, language, created_at)
             VALUES (?, ?, ?, ?, ?, \'active\', NULL, NULL, NULL, ?, NOW())',
            [$username, $username, $email, $email,
                password_hash(bin2hex(random_bytes(24)), PASSWORD_DEFAULT), (function_exists('lang') ? (string)lang() : 'en')]
        );
        $row = db_one('SELECT id FROM users WHERE username_lower = ? LIMIT 1', [$username]);
    }
    if ($row === null) {
        return 0;
    }
    $id = (int)$row['id'];

    /* Make sure it can post and carries the badge. */
    $author = db_one('SELECT * FROM blog_authors WHERE user_id = ? LIMIT 1', [$id]);
    if ($author === null) {
        db_query("INSERT INTO blog_authors (user_id, status, verified, reason, decided_by, decided_at, approved_at, created_at, updated_at)
                  VALUES (?, 'approved', 1, 'official account', ?, NOW(), NOW(), NOW(), NOW())", [$id, $adminName]);
    } elseif ((string)$author['status'] !== 'approved' || (int)$author['verified'] !== 1) {
        db_query("UPDATE blog_authors SET status = 'approved', verified = 1, decided_by = ?, decided_at = NOW(),
                  approved_at = COALESCE(approved_at, NOW()), updated_at = NOW() WHERE user_id = ?", [$adminName, $id]);
    }

    return $id;
}

/** "你好，世界！" -> "ni-hao-shi-jie" style; falls back to the id when nothing survives. */
function blog_slugify(string $text): string
{
    $text = mb_strtolower(trim($text));
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if (is_string($ascii) && $ascii !== '') {
            $text = $ascii;
        }
    }
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');

    return $text === '' ? '' : mb_substr($text, 0, 120);
}

/* ----------------------------------------------------------------- actions */

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    csrf_verify();
    $action = (string)($_POST['action'] ?? '');

    if ($action === 'save') {
        $id     = (int)($_POST['id'] ?? 0);
        $title  = trim((string)($_POST['title'] ?? ''));
        $titleZh = trim((string)($_POST['title_zh'] ?? ''));
        $body   = (string)($_POST['body'] ?? '');
        $slug   = blog_slugify((string)($_POST['slug'] ?? ''));
        $status = (string)($_POST['status'] ?? 'draft');
        $status = in_array($status, ['draft', 'published', 'archived'], true) ? $status : 'draft';
        $featured = isset($_POST['featured']) ? 1 : 0;
        $allowComments = isset($_POST['allow_comments']) ? 1 : 0;
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $excerpt = trim((string)($_POST['excerpt'] ?? ''));
        $excerptZh = trim((string)($_POST['excerpt_zh'] ?? ''));

        if ($title === '' && $titleZh === '') {
            $error = $zh ? '标题不能为空。' : 'A title is required.';
        } elseif (trim($body) === '') {
            $error = $zh ? '正文不能为空。' : 'The body cannot be empty.';
        } else {
            if ($slug === '') {
                $slug = blog_slugify($title !== '' ? $title : $titleZh);
            }
            if ($slug === '') {
                $slug = 'post-' . time();
            }
            /* Unique slug. */
            $base = $slug;
            for ($i = 2; $i < 50; $i++) {
                $clash = db_one('SELECT id FROM blog_posts WHERE slug = ? AND id <> ? LIMIT 1', [$slug, $id]);
                if ($clash === null) {
                    break;
                }
                $slug = $base . '-' . $i;
            }

            $authorId = blog_admin_author_id($who);
            if ($authorId === 0) {
                $error = $zh ? '建不了官方作者账号。' : 'Could not create the official author account.';
            } else {
                $publishedAt = $status === 'published'
                    ? (trim((string)($_POST['published_at'] ?? '')) !== '' ? (string)$_POST['published_at'] : date('Y-m-d H:i:s'))
                    : null;

                try {
                    if ($id > 0) {
                        db_query(
                            'UPDATE blog_posts SET slug = ?, title = ?, title_zh = ?, excerpt = ?, excerpt_zh = ?,
                                body = ?, body_zh = ?, category_id = ?, status = ?, featured = ?, allow_comments = ?,
                                published_at = COALESCE(?, published_at), updated_at = NOW()
                             WHERE id = ?',
                            [$slug, $title, $titleZh === '' ? null : $titleZh,
                                $excerpt === '' ? null : $excerpt, $excerptZh === '' ? null : $excerptZh,
                                $body, trim((string)($_POST['body_zh'] ?? '')) === '' ? null : (string)$_POST['body_zh'],
                                $categoryId > 0 ? $categoryId : null, $status, $featured, $allowComments,
                                $publishedAt, $id]
                        );
                        $notice = $zh ? '已保存。' : 'Saved.';
                    } else {
                        db_query(
                            'INSERT INTO blog_posts (slug, title, title_zh, excerpt, excerpt_zh, body, body_zh,
                                category_id, author_id, status, featured, allow_comments, published_at, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                            [$slug, $title, $titleZh === '' ? null : $titleZh,
                                $excerpt === '' ? null : $excerpt, $excerptZh === '' ? null : $excerptZh,
                                $body, trim((string)($_POST['body_zh'] ?? '')) === '' ? null : (string)$_POST['body_zh'],
                                $categoryId > 0 ? $categoryId : null, $authorId, $status, $featured, $allowComments,
                                $publishedAt]
                        );
                        $id = (int)db()->lastInsertId();
                        $notice = $zh ? '文章已创建。' : 'Post created.';
                    }
                    log_action('blog_post_saved', 'settings', null, 'post ' . $id . ' ' . $status);
                } catch (Throwable $e) {
                    $error = ($zh ? '保存失败：' : 'Save failed: ') . $e->getMessage();
                }
            }
        }
    }

    if ($action === 'archive' && (int)($_POST['id'] ?? 0) > 0) {
        db_query("UPDATE blog_posts SET status = 'archived', updated_at = NOW() WHERE id = ?", [(int)$_POST['id']]);
        $notice = $zh ? '已归档。' : 'Archived.';
    }
}

/* -------------------------------------------------------------------- view */

$editId = (int)($_GET['edit'] ?? 0);
$editing = $editId > 0 ? blog_post_by_id($editId) : null;

$list = [];
try {
    $list = db_all(
        'SELECT p.*, u.username AS author_name FROM blog_posts p JOIN users u ON u.id = p.author_id
         ORDER BY p.id DESC LIMIT 200'
    );
} catch (Throwable $e) {
    $list = [];
}
$cats = blog_categories_all();

page_header($zh ? '博客管理' : 'Blog admin');
?>
<p class="small">
<a href="<?= h(url('blog/')) ?>">← <?= h($zh ? '博客首页' : 'Blog') ?></a> &middot;
<a href="<?= h(url('admin/developers.php')) ?>"><?= h($zh ? '其它后台' : 'Other admin') ?></a>
</p>
<h1><?= h($zh ? '博客管理' : 'Blog admin') ?></h1>
<hr>

<?php if ($notice !== ''): ?><p class="msg ok"><?= h($notice) ?></p><?php endif; ?>
<?php if ($error !== ''): ?><p class="msg error"><?= h($error) ?></p><?php endif; ?>

<h2><?= h($editing === null ? ($zh ? '新建文章' : 'New post') : ($zh ? '编辑文章' : 'Edit post')) ?></h2>
<form method="post" action="<?= h(url('blog/admin/index.php' . ($editing === null ? '' : '?edit=' . (int)$editing['id']))) ?>">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= $editing === null ? 0 : (int)$editing['id'] ?>">

  <p><label for="title"><?= h($zh ? '标题（英文）' : 'Title (English)') ?></label><br>
  <input type="text" id="title" name="title" maxlength="190" style="width:100%;max-width:700px"
         value="<?= h((string)($editing['title'] ?? '')) ?>"></p>

  <p><label for="title_zh"><?= h($zh ? '标题（中文，可空）' : 'Title (Chinese, optional)') ?></label><br>
  <input type="text" id="title_zh" name="title_zh" maxlength="190" style="width:100%;max-width:700px"
         value="<?= h((string)($editing['title_zh'] ?? '')) ?>"></p>

  <p><label for="slug"><?= h($zh ? '地址别名 slug（留空自动生成）' : 'Slug (empty = generated)') ?></label><br>
  <input type="text" id="slug" name="slug" maxlength="120" style="width:100%;max-width:420px"
         value="<?= h((string)($editing['slug'] ?? '')) ?>">
  <span class="small"><?= h($zh ? '文章地址：/blog/post.php?slug=…' : 'post address: /blog/post.php?slug=…') ?></span></p>

  <p><label for="excerpt"><?= h($zh ? '摘要（英文，可空 → 自动截正文）' : 'Excerpt (English, optional)') ?></label><br>
  <textarea id="excerpt" name="excerpt" rows="2" style="width:100%;max-width:700px"><?= h((string)($editing['excerpt'] ?? '')) ?></textarea></p>

  <p><label for="excerpt_zh"><?= h($zh ? '摘要（中文，可空）' : 'Excerpt (Chinese, optional)') ?></label><br>
  <textarea id="excerpt_zh" name="excerpt_zh" rows="2" style="width:100%;max-width:700px"><?= h((string)($editing['excerpt_zh'] ?? '')) ?></textarea></p>

  <p><label for="body"><?= h($zh ? '正文（英文 / 纯文本，空行分段）' : 'Body (English / plain text, blank line = new paragraph)') ?></label><br>
  <textarea id="body" name="body" rows="14" style="width:100%;max-width:760px" required><?= h((string)($editing['body'] ?? '')) ?></textarea></p>

  <p><label for="body_zh"><?= h($zh ? '正文（中文，可空）' : 'Body (Chinese, optional)') ?></label><br>
  <textarea id="body_zh" name="body_zh" rows="14" style="width:100%;max-width:760px"><?= h((string)($editing['body_zh'] ?? '')) ?></textarea></p>

  <p><label for="category_id"><?= h($zh ? '分类' : 'Category') ?></label><br>
  <select id="category_id" name="category_id">
    <option value="0"><?= h($zh ? '（无）' : '(none)') ?></option>
    <?php foreach ($cats as $c): ?>
      <option value="<?= (int)$c['id'] ?>"<?= (int)($editing['category_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>>
        <?= h(blog_text($c, 'name')) ?>
      </option>
    <?php endforeach; ?>
  </select></p>

  <p><label for="status"><?= h($zh ? '状态' : 'Status') ?></label><br>
  <select id="status" name="status">
    <?php foreach (['draft', 'published', 'archived'] as $st): ?>
      <option value="<?= h($st) ?>"<?= (string)($editing['status'] ?? 'draft') === $st ? ' selected' : '' ?>>
        <?= h($st) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <label class="inline"><input type="checkbox" name="featured" value="1"<?= (int)($editing['featured'] ?? 0) === 1 ? ' checked' : '' ?>>
    <?= h($zh ? '设为精选' : 'featured') ?></label>
  <label class="inline"><input type="checkbox" name="allow_comments" value="1"<?= (int)($editing['allow_comments'] ?? 1) === 1 ? ' checked' : '' ?>>
    <?= h($zh ? '允许评论' : 'allow comments') ?></label></p>

  <p><input type="submit" value="<?= h($editing === null ? ($zh ? '创建' : 'Create') : ($zh ? '保存' : 'Save')) ?>">
  <?php if ($editing !== null): ?>
    &middot; <a href="<?= h(url('blog/admin/index.php')) ?>"><?= h($zh ? '取消编辑' : 'cancel') ?></a>
    <?php if ((string)$editing['status'] === 'published'): ?>
      &middot; <a href="<?= h(url('blog/post.php?slug=' . rawurlencode((string)$editing['slug']))) ?>"><?= h($zh ? '查看' : 'view') ?></a>
    <?php endif; ?>
  <?php endif; ?>
  </p>
</form>

<h2><?= h($zh ? '全部文章' : 'All posts') ?> <span class="hint">(<?= count($list) ?>)</span></h2>
<?php if ($list === []): ?>
<p class="hint"><?= h($zh ? '还没有文章。上面写第一篇吧。' : 'No posts yet - write the first one above.') ?></p>
<?php else: ?>
<table>
<tr><th>#</th><th><?= h($zh ? '标题' : 'Title') ?></th><th><?= h($zh ? '状态' : 'Status') ?></th>
    <th><?= h($zh ? '作者' : 'Author') ?></th><th><?= h($zh ? '浏览' : 'Views') ?></th>
    <th><?= h($zh ? '时间' : 'Date') ?></th><th><?= h($zh ? '操作' : 'Actions') ?></th></tr>
<?php foreach ($list as $p): ?>
<tr>
  <td><?= (int)$p['id'] ?></td>
  <td>
    <?= h(blog_text($p, 'title')) ?>
    <?php if ((int)$p['featured'] === 1): ?> <span class="pill"><?= h($zh ? '精选' : 'featured') ?></span><?php endif; ?>
  </td>
  <td><?= h((string)$p['status']) ?></td>
  <td><code><?= h((string)$p['author_name']) ?></code></td>
  <td><?= (int)$p['views'] ?></td>
  <td class="small"><?= h(blog_date((string)($p['published_at'] ?? $p['created_at']))) ?></td>
  <td class="small">
    <a href="<?= h(url('blog/admin/index.php?edit=' . (int)$p['id'])) ?>"><?= h($zh ? '编辑' : 'edit') ?></a>
    <?php if ((string)$p['status'] === 'published'): ?>
      &middot; <a href="<?= h(url('blog/post.php?slug=' . rawurlencode((string)$p['slug']))) ?>"><?= h($zh ? '查看' : 'view') ?></a>
    <?php endif; ?>
    <?php if ((string)$p['status'] !== 'archived'): ?>
      &middot; <form method="post" action="<?= h(url('blog/admin/index.php')) ?>" style="display:inline;margin:0">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="archive">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <input type="submit" value="<?= h($zh ? '归档' : 'archive') ?>">
      </form>
    <?php endif; ?>
  </td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<hr>
<p class="small">
<?= h($zh ? '其它：' : 'More: ') ?>
<a href="<?= h(url('blog/admin/comments.php')) ?>"><?= h($zh ? '评论审核' : 'Comments') ?></a> &middot;
<a href="<?= h(url('blog/admin/authors.php')) ?>"><?= h($zh ? '作者与认证' : 'Authors') ?></a> &middot;
<a href="<?= h(url('blog/admin/message.php')) ?>"><?= h($zh ? '发站内信' : 'Send a message') ?></a>
</p>

<?php page_footer(); ?>

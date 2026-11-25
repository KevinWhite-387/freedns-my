<?php
declare(strict_types=1);

/**
 * freedns.my - the blog.
 *
 * One file serves the six addresses the navigation asks for, so the menu stays honest:
 *
 *   /blog/                index      latest posts
 *   /blog/?view=featured  featured   "精选"
 *   /blog/?view=events    events     "活动"    (posts tagged or categorised "events")
 *   /blog/?view=archive   archive    "归档"    (year / month, newest first)
 *   /blog/?view=search    search     "搜索"    (?q=…)
 *   /blog/rss.php         feed       "RSS"
 *
 * The look is the registry's own: page_header()/page_footer() bring the classic stylesheet, so
 * there is no second design to keep in sync and no JavaScript at all.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

$zh = function_exists('lang') && is_zh();

if (!blog_enabled()) {
    page_header('Blog');
    echo '<h1>Blog</h1><hr>';
    echo '<p class="msg error">' . h($zh
        ? '博客还没准备好：请先导入 upgrade-blog.sql，并在后台打开 blog_enabled。'
        : 'The blog is not ready: import upgrade-blog.sql and switch blog_enabled on.') . '</p>';
    page_footer();
    exit;
}

$view   = strtolower(trim((string)($_GET['view'] ?? 'index')));
$page   = max(1, (int)($_GET['page'] ?? 1));
$q      = trim((string)($_GET['q'] ?? ''));
$year   = trim((string)($_GET['y'] ?? ''));
$month  = trim((string)($_GET['m'] ?? ''));
$user   = current_user();
$perPage = blog_per_page();

$titles = [
    'index'    => $zh ? '首页' : 'Home',
    'featured' => $zh ? '精选' : 'Featured',
    'events'   => $zh ? '活动' : 'Events',
    'archive'  => $zh ? '归档' : 'Archive',
    'search'   => $zh ? '搜索' : 'Search',
];
if (!isset($titles[$view])) {
    $view = 'index';
}

/* ------------------------------------------------------------------ queries */

$filter = ['status' => 'published'];
$result = ['posts' => [], 'total' => 0, 'pages' => 1, 'page' => 1];

if ($view === 'featured') {
    $filter['featured'] = true;
    $result = blog_posts($page, $filter);
} elseif ($view === 'events') {
    /* 活动 = 分类 events，或者带了 events 标签的文章 ✓ 两种写法都认 ✓ */
    $result = blog_posts($page, ['status' => 'published', 'tag' => 'events']);
    if ((int)$result['total'] === 0) {
        $cat = null;
        foreach (blog_categories_all() as $c) {
            if ((string)$c['slug'] === 'events') {
                $cat = (int)$c['id'];
                break;
            }
        }
        if ($cat !== null) {
            $filter['category'] = $cat;
            $result = blog_posts($page, $filter);
        }
    }
} elseif ($view === 'search') {
    $filter['q'] = $q;
    $result = $q === '' ? $result : blog_posts($page, $filter);
} elseif ($view === 'archive' && $year !== '' && $month !== '') {
    $filter['q'] = '';
    $result = blog_posts($page, $filter);
    /* 归档页按年月过滤：blog_posts() 没有这个维度，这里直接筛一层 ✓ */
    if ($result['posts'] !== []) {
        $result['posts'] = array_values(array_filter($result['posts'], static function (array $p) use ($year, $month): bool {
            $ts = strtotime((string)($p['published_at'] ?? ''));

            return $ts !== false && date('Y', $ts) === $year && date('m', $ts) === $month;
        }));
    }
} else {
    $result = blog_posts($page, $filter);
}

$archive = $view === 'archive' ? blog_archive_index() : [];
$cats    = blog_categories_all();

$navFor = static function (string $v, string $label, string $current) use ($page): string {
    $cls = $v === $current ? ' class="here"' : '';
    $url = $v === 'index' ? url('blog/') : url('blog/?view=' . $v);

    return '<a href="' . h($url) . '"' . $cls . '>' . h($label) . '</a>';
};

page_header(($titles[$view] ?? 'Blog') . ' · Blog');
?>
<h1>FreeDns Blog</h1>
<p class="sub"><?= h($zh ? '公告、教程与更新日志' : 'Announcements, tutorials and changelog') ?></p>
<hr>

<p class="nav">
<?= $navFor('index', $zh ? '首页' : 'Home', $view) ?> &middot;
<?= $navFor('featured', $zh ? '精选' : 'Featured', $view) ?> &middot;
<?= $navFor('events', $zh ? '活动' : 'Events', $view) ?> &middot;
<?= $navFor('archive', $zh ? '归档' : 'Archive', $view) ?> &middot;
<?= $navFor('search', $zh ? '搜索' : 'Search', $view) ?> &middot;
<a href="<?= h(url('blog/rss.php')) ?>">RSS</a> &middot;
<?php if ($user === null): ?>
  <a href="<?= h(url('account/login.php')) ?>"><?= h($zh ? '登录' : 'Sign in') ?></a>
<?php else: ?>
  <a href="<?= h(url('blog/console.php')) ?>"><?= h((string)$user['username']) ?></a>
  <?php if (blog_unread_count((int)$user['id']) > 0): ?>
    <span class="pill"><?= (int)blog_unread_count((int)$user['id']) ?></span>
  <?php endif; ?>
<?php endif; ?>
</p>

<?php if ($view === 'search'): ?>
<h2><?= h($zh ? '搜索' : 'Search') ?></h2>
<form method="get" action="<?= h(url('blog/')) ?>">
  <input type="hidden" name="view" value="search">
  <p>
    <input type="text" name="q" value="<?= h($q) ?>" maxlength="80" style="width:100%;max-width:420px"
           placeholder="<?= h($zh ? '标题或正文关键字' : 'title or body') ?>">
    <input type="submit" value="<?= h($zh ? '搜索' : 'Search') ?>">
  </p>
</form>
<?php if ($q !== ''): ?>
<p class="hint"><?= h($zh ? '关键词：' : 'For: ') ?><code><?= h($q) ?></code> ·
<?= (int)$result['total'] ?> <?= h($zh ? '篇' : 'result(s)') ?></p>
<?php endif; ?>
<?php endif; ?>

<?php if ($view === 'archive' && ($year === '' || $month === '')): ?>
<h2><?= h($zh ? '归档' : 'Archive') ?></h2>
<?php if ($archive === []): ?>
<p class="hint"><?= h($zh ? '还没有文章。' : 'Nothing yet.') ?></p>
<?php else: ?>
<table>
<tr><th><?= h($zh ? '年月' : 'Month') ?></th><th><?= h($zh ? '篇数' : 'Posts') ?></th></tr>
<?php foreach ($archive as $a): ?>
<tr>
  <td><a href="<?= h(url('blog/?view=archive&y=' . (int)$a['y'] . '&m=' . (int)$a['m'])) ?>"><?= h((string)$a['y'] . '-' . (string)$a['m']) ?></a></td>
  <td><?= (int)$a['n'] ?></td>
</tr>
<?php endforeach; ?>
</table>
<?php endif; ?>

<?php elseif ($view !== 'search' || $q !== ''): ?>
<h2><?= h($view === 'archive' ? ((string)$year . '-' . (string)$month) : ($titles[$view] ?? 'Blog')) ?></h2>

<?php if ($result['posts'] === []): ?>
<p class="hint"><?= h($zh ? '这里还没有文章。' : 'No posts here yet.') ?></p>
<?php else: ?>
<?php foreach ($result['posts'] as $post): ?>
<h3><a href="<?= h(url('blog/post.php?slug=' . rawurlencode((string)$post['slug']))) ?>"><?= h(blog_text($post, 'title')) ?></a>
<?php if ((int)$post['featured'] === 1): ?> <span class="pill"><?= h($zh ? '精选' : 'featured') ?></span><?php endif; ?>
</h3>
<p class="small">
<?= h(blog_date((string)($post['published_at'] ?? $post['created_at'] ?? ''))) ?>
&middot; <code><?= h((string)$post['author_name']) ?></code>
<?php if ((int)($post['author_verified'] ?? 0) === 1): ?><span class="ok">✓</span><?php endif; ?>
<?php if ((string)($post['category_name'] ?? '') !== ''): ?>
&middot; <?= h(blog_text($post, 'category_name')) ?>
<?php endif; ?>
&middot; <?= (int)$post['comment_count'] ?> <?= h($zh ? '条评论' : 'comments') ?>
</p>
<p><?= h(blog_excerpt($post)) ?></p>
<?php endforeach; ?>

<?php if ((int)$result['pages'] > 1): ?>
<hr>
<p class="small">
<?php
$mk = static function (int $p, string $label) use ($view, $q, $year, $month): string {
    $args = ['view' => $view, 'page' => $p];
    if ($view === 'search' && $q !== '') {
        $args['q'] = $q;
    }
    if ($view === 'archive' && $year !== '') {
        $args['y'] = $year;
        $args['m'] = $month;
    }
    $url = url('blog/?' . http_build_query($args));

    return '<a href="' . h($url) . '">' . h($label) . '</a>';
};
if ((int)$result['page'] > 1) {
    echo $mk((int)$result['page'] - 1, '← ' . ($zh ? '上一页' : 'previous'));
    echo ' &middot; ';
}
echo (int)$result['page'] . ' / ' . (int)$result['pages'];
if ((int)$result['page'] < (int)$result['pages']) {
    echo ' &middot; ';
    echo $mk((int)$result['page'] + 1, ($zh ? '下一页' : 'next') . ' →');
}
?>
</p>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>

<?php if ($view === 'index' && $cats !== []): ?>
<hr>
<h2><?= h($zh ? '分类' : 'Categories') ?></h2>
<p class="small">
<?php foreach ($cats as $i => $c): ?>
  <?= $i > 0 ? ' &middot; ' : '' ?><a href="<?= h(url('blog/?view=index&category=' . (int)$c['id'])) ?>"><?= h(blog_text($c, 'name')) ?></a>
  <span class="hint">(<?= (int)$c['n'] ?>)</span>
<?php endforeach; ?>
</p>
<?php endif; ?>

<hr>
<p class="small">
<?= h($zh ? '博客与主站共用账号：在注册局注册后即可登录这里。发帖需要作者权限，可以在控制台申请。'
    : 'The blog shares the registry accounts. Posting needs author rights - request them in the console.') ?>
</p>

<?php page_footer(); ?>

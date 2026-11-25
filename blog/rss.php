<?php
declare(strict_types=1);

/**
 * freedns.my - the blog feed (RSS 2.0).
 *
 * Plain XML, no JavaScript, no session: feed readers fetch this without cookies.
 */

require dirname(__DIR__) . '/inc/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

if (!blog_enabled()) {
    http_response_code(404);
    echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<rss version=\"2.0\"><channel>"
        . '<title>FreeDns Blog</title><link>' . h(url('blog/')) . '</link>'
        . '<description>unavailable</description></channel></rss>';
    exit;
}

$limit = max(1, min(100, (int)setting('blog_rss_items', '20')));
$items = blog_rss_items($limit);

$siteTitle = (string)setting_lang('site_title');
if ($siteTitle === '') {
    $siteTitle = 'freedns.my';
}
$feedTitle = trim((string)setting('blog_title', 'FreeDns Blog'));
$blogUrl   = url('blog/');
$feedUrl   = url('blog/rss.php');

$esc = static function (string $v): string {
    return htmlspecialchars($v, ENT_QUOTES | ENT_XML1, 'UTF-8');
};

echo "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n";
?>
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
<channel>
  <title><?= $esc($feedTitle . ' · ' . $siteTitle) ?></title>
  <link><?= $esc($blogUrl) ?></link>
  <description><?= $esc($feedTitle) ?></description>
  <language><?= $esc(function_exists('lang') ? (string)lang() : 'en') ?></language>
  <lastBuildDate><?= $esc(gmdate('D, d M Y H:i:s') . ' GMT') ?></lastBuildDate>
  <atom:link href="<?= $esc($feedUrl) ?>" rel="self" type="application/rss+xml" />
<?php foreach ($items as $post): ?>
  <item>
    <title><?= $esc(blog_text($post, 'title')) ?></title>
    <link><?= $esc(url('blog/post.php?slug=' . rawurlencode((string)$post['slug']))) ?></link>
    <guid isPermaLink="true"><?= $esc(url('blog/post.php?slug=' . rawurlencode((string)$post['slug']))) ?></guid>
    <description><?= $esc(blog_excerpt($post, 500)) ?></description>
    <author><?= $esc((string)$post['author_name']) ?></author>
<?php if ((string)($post['published_at'] ?? '') !== ''): ?>
    <pubDate><?= $esc(gmdate('D, d M Y H:i:s', (int)strtotime((string)$post['published_at'])) . ' GMT') ?></pubDate>
<?php endif; ?>
  </item>
<?php endforeach; ?>
</channel>
</rss>

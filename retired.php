<?php
declare(strict_types=1);

/**
 * freedns.my - the page a withdrawn address lands on.
 *
 * The nine withdrawn features have had their files deleted, so those addresses now answer 404.
 * Point nginx's 404 at this file and a bookmark, a search result or an old e-mail link explains
 * itself instead of showing the server's default error page:
 *
 *     error_page 404 /retired.php;
 *
 * Written standalone (no page_header) so it also works when the framework itself is what is
 * missing - but it uses the framework when it is there, to look like the rest of the site.
 */

$zh = false;
$contact = '';
$title = 'Feature withdrawn';

if (is_file(__DIR__ . '/inc/bootstrap.php')) {
    require __DIR__ . '/inc/bootstrap.php';
    $zh      = function_exists('is_zh') && is_zh();
    $contact = trim((string)setting('contact_email', ''));
    $title   = $zh ? '功能已撤销' : 'This feature has been withdrawn';
}

header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');

if (function_exists('page_header')) {
    page_header($title);
} else {
    echo "<!DOCTYPE html>\n<html><head><meta charset=\"utf-8\"><title>" . htmlspecialchars($title) . "</title></head><body>\n";
}

$h = function_exists('h') ? 'h' : fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

echo '<h1>' . $h($title) . '</h1><hr>' . "\n";
echo '<div class="msg error">' . "\n";
echo '  <strong>' . $h($zh ? '因业务调整，本功能已被撤销。' : 'Because of a change in the service, this feature has been withdrawn.') . '</strong><br>' . "\n";
echo '  ' . $h($zh ? '感谢您一如既往的支持。' : 'Thank you for your continued support.') . "\n";
echo "</div>\n";
echo '<p>' . $h($zh ? '如有疑问请联系我们' : 'If you have any questions, please contact us') . '：'
    . ($contact !== '' ? '<a href="mailto:' . $h($contact) . '">' . $h($contact) . '</a>'
        : '<a href="' . (function_exists('url') ? $h(url('about.php')) : 'about.php') . '">'
          . $h($zh ? '联系我们' : 'contact us') . '</a>')
    . "</p>\n";
echo '<p><a href="' . (function_exists('url') ? $h(url('index.php')) : 'index.php') . '">'
    . $h($zh ? '返回首页' : 'Back to the home page') . '</a></p>' . "\n";

if (function_exists('page_footer')) {
    page_footer();
} else {
    echo "</body></html>\n";
}
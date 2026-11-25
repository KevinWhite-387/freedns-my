<?php
declare(strict_types=1);

/**
 * freedns.my - the Flash page.
 *
 * One screen, one .swf. Browsers that could still run the plugin get the interface; everything
 * else is told why it is not here and offered the plain-text page, which is what /index.php has
 * been all along.
 *
 * Deliberately standalone: the classic chrome (page_header) is not printed around the object,
 * because two interfaces on one page look like a mistake. The only HTML here is the object, one
 * heading, and a link back.
 */

require __DIR__ . '/inc/bootstrap.php';
require_once __DIR__ . '/inc/ui-flash.php';

$zh = is_zh();

if (!flash_ui_enabled()) {
    // The feature is off: say so instead of handing out an object that will never play.
    header('Location: ' . (function_exists('url') ? url('index.php') : 'index.php'), true, 302);
    exit;
}

if (!flash_capable()) {
    // A current browser: the classic interface plus the explanation, which is the stated policy.
    page_header($zh ? 'Flash 版界面' : 'The Flash interface');
    echo '<h1>' . h($zh ? 'Flash 版界面' : 'The Flash interface') . '</h1><hr>' . "\n";
    echo '<p class="msg info">' . h(flash_fallback_reason()) . '</p>' . "\n";
    echo '<p><a href="' . h(url('index.php')) . '">' . h($zh ? '返回纯文字界面' : 'Back to the plain-text interface') . '</a></p>' . "\n";
    echo '<p class="small">' . h($zh
        ? '用 IE 11 或更老的浏览器（并安装 Flash Player 32.0.0.371 之前的版本）可以打开它。'
        : 'It opens in Internet Explorer 11 or older with a Flash Player below 32.0.0.371 installed.') . '</p>' . "\n";
    page_footer();
    exit;
}

// A browser that may well have the plugin: hand it the interface.
$swf = flash_swf_url();
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
?>
<!DOCTYPE html>
<html lang="<?= h(lang()) ?>">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta charset="utf-8">
<title><?= h($zh ? 'FreeDns 免费域名 — Flash 版' : 'FreeDns free domain names - Flash') ?></title>
</head>
<body bgcolor="#ffffff" text="#111111" link="#0000EE" vlink="#551A8B"
      style="font-family:'Courier New',Courier,monospace;margin:8px 0">
<center>
<h1 style="font-family:'Courier New',Courier,monospace;font-size:16px;font-weight:normal;margin:6px 0 10px 0">
<?= h($zh ? 'freedns.my 免费域名' : 'freedns.my free domain names') ?></h1>
<?= flash_object_html($swf, 800, 600) ?>
<p class="small">
  <?= h($zh ? 'Flash 版界面' : 'Flash interface') ?> &middot;
  <a href="<?= h(url('index.php')) ?>"><?= h($zh ? '纯文字界面' : 'plain-text interface') ?></a> &middot;
  <a href="<?= h(url('doc.php')) ?>"><?= h($zh ? '文档' : 'documentation') ?></a>
</p>
</center>
</body>
</html>

<?php
declare(strict_types=1);

/**
 * freedns.my - 邮箱功能：暂时停用
 *
 * 原「我的邮件（BillionMail）」功能已下线 ✓
 * 这里保留同一个地址，只是为了给还收藏着旧链接的用户一句说明 ✗ 不显示任何数据 ✓
 */
require dirname(__DIR__) . '/inc/bootstrap.php';

// Withdrawn by the operator: business adjustment. The file stays so a bookmark explains itself.
retired_page('
我的邮箱（新版）
');

$user = require_user();
$zh   = function_exists('lang') && is_zh();

page_header($zh ? '邮箱功能' : 'Email');

echo '<h1>' . h($zh ? '邮箱功能' : 'Email') . "</h1>\n";
echo '<hr>' . "\n";
echo '<p class="msg info">'
   . h($zh
        ? '因功能调整，目前本功能暂时无法使用，谢谢您的支持。'
        : 'This feature is temporarily unavailable due to a service adjustment. Thank you for your understanding.')
   . "</p>\n";
echo '<p class="small">'
   . ($zh
        ? '已有的邮件数据不会被删除；恢复时间请留意 <a href="' . h(url('status.php')) . '">服务状态</a> 与首页公告。'
        : 'Existing mail data is kept. Please watch <a href="' . h(url('status.php')) . '">service status</a> and the home page for updates.')
   . "</p>\n";
echo '<p><a href="' . h(url('account/index.php')) . '">' . h($zh ? '返回控制台' : 'Back to the console') . '</a></p>' . "\n";

page_footer();

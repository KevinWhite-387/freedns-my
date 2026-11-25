<?php
declare(strict_types=1);

/**
 * freedns.my - the language of the back office.
 *
 * The back office used to be English whatever the site language was. It now follows a switch of
 * its own: ?lang=zh|en is remembered in the session, so an operator who works in Chinese keeps
 * Chinese across all 47 pages without touching the visitor-facing language.
 *
 * Applied inside page_header(), which runs before the page body is echoed: every t() call below
 * it then answers in the chosen language, and no page has to be edited.
 */

/** The two languages the back office offers. */
function admin_languages(): array
{
    return ['en' => 'English', 'zh' => '简体中文'];
}

/** Which one is in force. Falls back to the site language, then to English. */
function admin_lang(): string
{
    $asked = strtolower(trim((string)($_GET['lang'] ?? '')));
    if (array_key_exists($asked, admin_languages())) {
        $_SESSION['fd_admin_lang'] = $asked;
    }

    $kept = strtolower((string)($_SESSION['fd_admin_lang'] ?? ''));
    if (array_key_exists($kept, admin_languages())) {
        return $kept;
    }

    return (function_exists('lang') && lang() === 'zh') ? 'zh' : 'en';
}

/** Points lang() at the chosen language. Called once, before any output. */
function admin_lang_apply(): void
{
    $GLOBALS['FD_FORCE_LANG'] = admin_lang();
}

/** The switch itself: two links, no form, no JavaScript. */
function admin_lang_switcher(): string
{
    $now = admin_lang();
    $out = [];
    foreach (admin_languages() as $code => $label) {
        if ($code === $now) {
            $out[] = '<strong>' . h($label) . '</strong>';
            continue;
        }
        $target = (string)($_SERVER['REQUEST_URI'] ?? '/admin/index.php');
        $target = preg_replace('/([?&])lang=[^&]*&?/', '$1', $target);
        $target = rtrim((string)$target, '?&');
        $sep = str_contains((string)$target, '?') ? '&' : '?';
        $out[] = '<a href="' . h($target . $sep . 'lang=' . $code) . '">' . h($label) . '</a>';
    }

    return '<span class="small">' . h(is_zh() ? '语言' : 'Language') . ': ' . implode(' &middot; ', $out) . '</span>';
}

/**
 * Chinese names for the back-office entries.
 *
 * Only the menu is translated here. The body of an admin page is English where it was hard-coded,
 * which is honest: a half-translated table of numbers is worse than a consistent English one.
 */
function admin_nav_zh(): array
{
    return [
        'index.php'            => '仪表盘',
        'health.php'           => '健康检查',
        'maintenance.php'      => '维护开关',
        'api-maintenance.php'  => 'API 维护',
        'cron-key.php'         => '定时任务密钥',
        'domains.php'          => '域名',
        'zones.php'            => '后缀',
        'reserved.php'         => '保留名称',
        'nameservers.php'      => '名称服务器',
        'dns.php'              => 'DNS 设置',
        'bulk.php'             => '批量记录',
        'verify.php'           => '已发布记录',
        'limits.php'           => '频率限制',
        'changes.php'          => '待审变更',
        'users.php'            => '用户',
        'admins.php'           => '管理员',
        'tickets.php'          => '工单',
        'abuse.php'            => '滥用举报',
        'chat.php'             => '聊天',
        'mail.php'             => '邮件',
        'email.php'            => '邮箱',
        'vault.php'            => '邮件金库',
        'legacy-mail.php'      => '旧邮件迁移',
        'forward-requests.php' => '邮件转发申请',
        'servers.php'          => '云服务器',
        'holds.php'            => '等待页面',
        'finance.php'          => '关于我们与资金',
        'ads.php'              => '广告',
        'shop.php'             => '配额商店',
        'market.php'           => '域名市场',
        'partners.php'         => '其它平台',
        'reviews.php'          => '评价',
        'openplatform.php'     => '开放平台',
        'settings.php'         => '设置',
        'logs.php'             => '审计日志',
        'export.php'           => '导出',
        'ui-browser.php'       => '界面与浏览器',
        'dns-providers.php'    => 'DNS 服务商',
        'vcci.php'             => 'vc.ci 登录',
        'developers.php'       => '开发者申请',
        '/blog/admin/index.php'    => '博客文章',
        '/blog/admin/comments.php' => '博客评论',
        '/blog/admin/authors.php'  => '博客作者',
        '/blog/admin/message.php'  => '博客站内信',
    ];
}
/** Chinese names for the nine menu groups. */
function admin_group_zh(string $group): string
{
    $map = [
        'Overview' => '概览',
        'Domains'  => '域名',
        'People'   => '用户',
        'Mail'     => '邮件',
        'Servers'  => '服务器',
        'Money'    => '财务',
        'Platform' => '平台',
        'Extras'   => '附加',
        'Blog'     => '博客',
        'Other'    => '其它',
    ];

    return $map[$group] ?? $group;
}
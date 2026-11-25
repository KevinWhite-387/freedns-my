<?php
declare(strict_types=1);

/**
 * freedns.my - features that have been withdrawn.
 *
 * Nine features were retired in one go: the new mailbox, the old mail forwarding, notification
 * preferences, the quota shop, rewards, cloud servers, advertising, reviews, and the "1.0" modern
 * interface. The files still exist so that a bookmark or a search result does not land on a 404,
 * but every one of them stops at retired_page() and says the same thing.
 *
 * Nothing is deleted on purpose: a deleted file answers 404, and a 404 tells the visitor nothing
 * about why the feature is gone or where to ask.
 */

/** The paths that are withdrawn, as they appear in url() calls and in the sitemap. */
function retired_paths(): array
{
    return [
        'account/email.php',            // 我的邮箱（新版）
        'account/mailbox.php',
        'account/forward.php',          // 邮件转发（旧版）
        'account/forwarding.php',
        'account/notify.php',           // 通知偏好
        'account/notifications.php',    // 通知偏好（旧名）
        'account/preferences.php',
        'account/shop.php',             // 配额商店
        'account/bonus.php',            // 我的奖励
        'account/rewards.php',          // 我的奖励（旧名）
        'account/servers.php',          // 我的云服务器
        'account/theme.php',            // 新版界面（1.0）
        'advertise.php',                // 广告投放
        'reviews.php',                  // 评价本站
        'experimental.php',             // 新版界面（1.0）
        'admin/servers.php',
        'admin/forward-requests.php',
        'admin/reviews.php',
        'admin/shop.php',
    ];
}

/** True when an url() argument belongs to a withdrawn feature - used to filter menus. */
function retired_url(string $path): bool
{
    $path = ltrim($path, '/');
    // The console keeps ?query strings on some links; compare only the script part.
    $cut  = strcspn($path, '?#');
    $path = substr($path, 0, $cut);

    return in_array($path, retired_paths(), true);
}

/**
 * Stop here and explain. Called at the very top of every withdrawn page, after bootstrap.
 *
 * The wording is the operator's: business adjustment, withdrawn with thanks, contact us if in
 * doubt. It is deliberately the same page for all nine features, so nobody has to guess whether
 * "gone" means broken or gone.
 */
function retired_page(string $feature = ''): void
{
    $zh      = function_exists('is_zh') && is_zh();
    $contact = function_exists('setting') ? trim((string)setting('contact_email', '')) : '';
    $title   = $zh ? '功能已撤销' : 'This feature has been withdrawn';

    if (function_exists('page_header')) {
        page_header($title);
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html>\n<html><head><meta charset=\"utf-8\"><title>" . $title . "</title></head><body>\n";
    }

    echo '<h1>' . h($title) . '</h1><hr>' . "\n";
    echo '<div class="msg error">' . "\n";
    echo '  <strong>' . h($zh ? '因业务调整，本功能已被撤销。' : 'Because of a change in the service, this feature has been withdrawn.') . '</strong><br>' . "\n";
    echo '  ' . h($zh ? '感谢您一如既往的支持。' : 'Thank you for your continued support.') . "\n";
    echo "</div>\n";

    if ($feature !== '') {
        echo '<p class="small">' . h($zh ? '原功能：' : 'Withdrawn feature: ') . h($feature) . "</p>\n";
    }

    echo '<p>' . h($zh ? '如有疑问请联系我们' : 'If you have any questions, please contact us') . '：'
        . ($contact !== ''
            ? '<a href="mailto:' . h($contact) . '">' . h($contact) . '</a>'
            : '<a href="' . h(function_exists('url') ? url('about.php') : 'about.php') . '">'
              . h($zh ? '联系我们' : 'contact us') . '</a>')
        . "</p>\n";

    echo '<p class="small"><a href="' . h(function_exists('url') ? url('index.php') : 'index.php') . '">'
        . h($zh ? '返回首页' : 'Back to the home page') . '</a>';

    if (function_exists('current_user') && current_user() !== null) {
        echo ' &middot; <a href="' . h(function_exists('url') ? url('account/index.php') : 'account/index.php') . '">'
            . h($zh ? '控制台' : 'Console') . '</a>';
    }
    echo "</p>\n";

    if (function_exists('page_footer')) {
        page_footer();
    } else {
        echo "</body></html>\n";
    }

    exit;
}

/**
 * Drops withdrawn entries from a menu, keeping the order and the labels.
 *
 * The two public menus are literal arrays keyed by url(), so this is applied to the value they
 * return - one call beside each "return", and every menu in the registry loses the same entries.
 */
function fd_nav_filter(array $links): array
{
    $kept = [];
    foreach ($links as $href => $label) {
        if (retired_url((string)$href)) {
            continue;
        }
        $kept[$href] = $label;
    }

    return $kept;
}
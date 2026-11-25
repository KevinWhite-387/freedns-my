<?php
declare(strict_types=1);

/**
 * freedns.my - the page builders of interface 2.0.
 *
 * Moved out of inc/views.php so the classic page code there stays readable, and so the whole 2.0
 * look can be switched off by not requiring this file. Every function is called only when
 * theme_is_modern2() is true; the classic output never reaches any of them.
 *
 * Named ui2_* on purpose: theme() is the light/dark mode and theme_*() belong to inc/theme.php,
 * and three different meanings of "theme" in one codebase is two too many.
 */
/* ------------------------------------------------------------------ *
 * New interface 2.0 (modern2)                                         *
 *                                                                     *
 * Only called when theme_is_modern2() is true; the classic and the 1.0  *
 * output never reach these. Named ui2_* so they cannot collide with    *
 * theme() (light/dark) or the theme_*() functions of inc/theme.php.    *
 * ------------------------------------------------------------------ */

/** UI2 brand image; root-relative to the installation, not to the current language. */
function ui2_logo(): string
{
    $path = FD_ROOT . '/icon.jpg';
    $stamp = is_file($path) ? '?v=' . (int)filemtime($path) : '';
    return '<img class="ui2-logo" src="' . h(app_base_url() . '/icon.jpg' . $stamp)
        . '" width="32" height="32" alt="" aria-hidden="true" decoding="async">';
}

/**
 * The picture on the home page: a name, and the records it carries. Pure decoration (aria-hidden), no text that
 * needs translating - record types and example values only. The stylesheet moves it; without CSS it is still
 * a readable diagram.
 */
function ui2_hero(): string
{
    $rows = [[26, 'A', '192.0.2.1'], [68, 'AAAA', '2001:db8::1'], [110, 'CNAME', 'target.example'], [152, 'TXT', 'v=spf1 -all']];
    $svg  = '<svg viewBox="0 0 800 180" role="presentation" aria-hidden="true" focusable="false">';
    foreach ($rows as $i => [$y, $type, $value]) {
        $d    = 'M208 90H300V' . $y . 'H420';
        $svg .= '<path class="w" d="' . $d . '"/><path class="w" d="M540 ' . $y . 'H640"/>'
            . '<path class="p d' . ($i + 1) . '" pathLength="100" d="' . $d . '"/>'
            . '<rect class="n" x="420" y="' . ($y - 15) . '" width="120" height="30"/>'
            . '<text x="480" y="' . ($y + 5) . '" text-anchor="middle">' . $type . '</text>'
            . '<rect class="e e' . ($i + 1) . '" x="640" y="' . ($y - 6) . '" width="12" height="12"/>'
            . '<text x="664" y="' . ($y + 5) . '">' . h($value) . '</text>';
    }
    $svg .= '<rect class="root" x="24" y="64" width="184" height="52"/>'
        . '<text class="rt" x="116" y="95" text-anchor="middle">yourname.freedns.my</text></svg>';

    // The two things a newcomer does first. Same labels as the navigation, so every language already has them.
    $cta = '<p class="ui2-cta"><a class="ui2-filled" href="' . h(url('request.php')) . '">' . h(t('nav.request')) . '</a>'
        . '<a class="ui2-tonal" href="' . h(url('status.php')) . '">' . h(t('nav.status')) . "</a></p>\n";

    return '<div class="ui2-hero"><div aria-hidden="true">' . $svg . '</div>' . $cta . "</div>\n";
}

/** The top bar: brand, and the same navigation links as ever. A checkbox opens it on a phone - no JavaScript. */
function ui2_topbar(string $siteTitle, string $nav): string
{
    $menu = function_exists('lang') && is_zh() ? '菜单' : 'Menu';

    return '<header class="ui2-top"><div class="ui2-top-in">'
        . '<a class="ui2-brand" href="' . h(url('index.php')) . '">' . ui2_logo() . '<span>' . h($siteTitle) . '</span></a>'
        . '<input type="checkbox" id="ui2-nav-toggle" class="ui2-toggle" aria-label="' . h($menu) . '">'
        . '<label for="ui2-nav-toggle" class="ui2-burger" aria-hidden="true">' . h($menu) . '</label>'
        . '<label for="ui2-nav-toggle" class="ui2-scrim" aria-hidden="true"></label>'
        . '<nav class="ui2-nav" aria-label="' . h($menu) . '">' . $nav . "</nav>\n"
        . "</div></header>\n"
        // Reading progress for browsers that cannot do it in CSS (scroll-driven animations). Nothing depends on it.
        . "<script>(function(){if(window.CSS&&CSS.supports&&CSS.supports('animation-timeline','scroll()'))return;"
        . "var b=document.querySelector('.ui2-top');if(!b)return;function u(){var d=document.documentElement,"
        . "m=d.scrollHeight-d.clientHeight;b.style.setProperty('--ui2-p',m>0?Math.min(1,d.scrollTop/m):0)}"
        . "addEventListener('scroll',u,{passive:true});addEventListener('resize',u);u()})()</script>\n";
}

/** What the top of a 2.0 page says: back-to-classic for a signed-in holder, and the language-support note. */
function ui2_banner(): string
{
    $zh  = function_exists('lang') && is_zh();
    $out = '';

    // Visitors cannot switch (the registry default applies to them), so only a holder gets the way back.
    if (function_exists('current_user') && current_user() !== null) {
        $out .= '<p class="ui2-flag">'
            . h($zh ? '新版界面 2.0（实验）· 出现问题请及时反馈' : 'New interface 2.0 (experimental) · please report problems')
            . ' &middot; <a href="' . h(url('account/theme.php?back=1')) . '">'
            . h($zh ? '一键回到旧版' : 'back to the classic look') . '</a> &middot; <a href="'
            . h(url('experimental.php')) . '">' . h($zh ? '说明' : 'what this means') . "</a></p>\n";
    }

    // 2.0 only has English and Simplified Chinese; the other languages fall back to English on purpose.
    if (!in_array(lang(), ['en', 'zh'], true)) {
        $note = [
            'zh-hant' => '新版介面 2.0 目前只支援英文與簡體中文，本頁其餘內容將以英文顯示。',
            'ja'      => '新インターフェース 2.0 は現在、英語と簡体字中国語のみ対応しています。このページの残りは英語で表示されます。',
            'ko'      => '새 인터페이스 2.0은 현재 영어와 중국어 간체만 지원합니다. 이 페이지의 나머지는 영어로 표시됩니다.',
            'ms'      => 'Antara muka baharu 2.0 kini hanya menyokong bahasa Inggeris dan Cina Ringkas; '
                       . 'selebihnya halaman ini dipaparkan dalam bahasa Inggeris.',
        ][lang()] ?? 'New interface 2.0 currently supports English and Simplified Chinese only; the rest of this page is shown in English.';
        $out .= '<p class="ui2-support" lang="' . h(lang()) . '">' . h($note) . "</p>\n";
    }

    return $out;
}

/**
 * The console menu of 2.0 (a navigation drawer): shown beside every page of /account/ for a signed-in holder, so
 * the pages of the console are one click apart instead of one trip back to the overview. Only the links - every
 * page keeps its own content and its own logic.
 */
function ui2_console_rail(): string
{
    $zh = function_exists('lang') && is_zh();
    $groups = [
        [$zh ? '域名与解析' : 'Names & DNS', [
            ['index', $zh ? '概览' : 'Overview'],
            ['nameservers', $zh ? '自有域名服务器' : 'Name servers'],
            ['ddns', $zh ? '动态解析' : 'Dynamic DNS'],
            ['shared', $zh ? '共享域名' : 'Shared names'],
            ['transfers', $zh ? '域名转移' : 'Transfers'],
        ]],
        [$zh ? '邮件' : 'Mail', [
            ['email', $zh ? '邮箱' : 'Email'],
            ['forwarding', $zh ? '邮件转发' : 'Forwarding'],
            ['notify', $zh ? '邮件通知' : 'Notifications'],
        ]],
        [$zh ? '账号' : 'Account', [
            ['profile', $zh ? '账号资料' : 'Details'],
            ['security', $zh ? '两步验证' : 'Two-factor'],
            ['api', $zh ? 'API 密钥' : 'API tokens'],
            ['apps', $zh ? '开放平台' : 'Open platform'],
            ['theme', $zh ? '界面' : 'Interface'],
            ['close', $zh ? '数据与注销' : 'Data & closing'],
        ]],
        [$zh ? '更多' : 'More', [
            ['shop', $zh ? '商店' : 'Shop'],
            ['bonus', $zh ? '额外配额' : 'Extra quota'],
            ['servers', $zh ? '云服务器' : 'Servers'],
            ['market', $zh ? '市场' : 'Market'],
        ]],
    ];
    $here = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''), '.php');
    if ($here === 'manage') {
        $here = 'index';            // the record editor belongs to "my names"
    }

    $out = '<nav class="ui2-rail" aria-label="' . h($zh ? '控制台' : 'Console') . '">';
    foreach ($groups as [$title, $items]) {
        $out .= '<p class="ui2-rail-h">' . h($title) . '</p>';
        foreach ($items as [$page, $label]) {
            $out .= '<a href="' . h(url('account/' . $page . '.php')) . '"'
                . ($page === $here ? ' aria-current="page"' : '') . '>' . h($label) . '</a>';
        }
    }

    return $out . "</nav>\n";
}

/** Does this request get the console menu? Signed-in holder, a page of /account/, and not the sign-in forms. */
function ui2_console_shell(): bool
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!str_contains($script, '/account/') || !function_exists('current_user') || current_user() === null) {
        return false;
    }

    return !in_array(basename($script, '.php'), ['login', 'register', 'forgot', 'reset', 'verify', 'logout'], true);
}

/** A feedback line in the footer of every 2.0 page. */
function ui2_feedback(string $contact): string
{
    $zh  = function_exists('lang') && is_zh();
    $out = '<p class="small ui2-feedback"><a href="' . h(url('experimental.php')) . '">'
        . h($zh ? '反馈问题 / 实验性功能说明' : 'Report a problem / about experimental features') . '</a>';
    if ($contact !== '') {
        $out .= ' &middot; <a href="mailto:' . h($contact) . '">' . h($contact) . '</a>';
    }

    return $out . "</p>\n";
}


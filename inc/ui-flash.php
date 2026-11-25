<?php
declare(strict_types=1);

/**
 * freedns.my - the Flash interface, and the notice everybody else gets.
 *
 * assets/ui.swf is the whole interface for browsers that can still run Flash. Everyone else -
 * which today means every current browser, because Flash was removed from all of them in 2021 -
 * gets the plain-text classic interface plus one line saying why.
 *
 * The decision is made on the server, without JavaScript, because the registry has none on
 * visitor pages and a Flash UI that needed script to choose it would be absurd:
 *
 *   ?ui=flash      force the Flash page         (?ui=classic escapes back)
 *   ?ui=classic    force the classic page
 *   otherwise      the user agent decides
 */

/** Is the Flash interface switched on at all? Off by default, one setting flips it. */
function flash_ui_enabled(): bool
{
    return function_exists('setting_bool') && setting_bool('flash_ui', false);
}

/** The address of the compiled interface. */
function flash_swf_url(): string
{
    return function_exists('url') ? url('assets/ui.swf') : 'assets/ui.swf';
}

/**
 * Could this browser plausibly run a .swf?
 *
 * There is no way to ask a browser "do you have Flash" over HTTP. What is left is the engine:
 * Trident (IE), Gecko (Firefox) and Blink before Chrome 88 shipped a player, and WebKit before
 * Safari 14 removed it. Being generous is harmless - a browser that cannot play shows the Flash
 * player's own "install" box, and the classic link is right next to it.
 */
function flash_capable(): bool
{
    if (!flash_ui_enabled()) {
        return false;
    }

    $forced = strtolower(trim((string)($_GET['ui'] ?? '')));
    if ($forced === 'flash') {
        return true;
    }
    if ($forced === 'classic') {
        return false;
    }

    if (!function_exists('fd_browser')) {
        return false;
    }

    $info   = fd_browser();
    $engine = (string)($info['engine'] ?? '');
    $ver    = (float)($info['version'] ?? 0);

    // Trident never lost the plugin, and neither did EdgeHTML or Presto.
    if ($engine === 'trident' || $engine === 'edgehtml' || $engine === 'presto') {
        return true;
    }

    // Firefox removed Flash in 85, Safari in 14, Chrome and Edge in 88.
    if ($engine === 'gecko') {
        return $ver > 0 && $ver < 85;
    }
    if ($engine === 'blink') {
        return $ver > 0 && $ver < 88;
    }
    if ($engine === 'webkit') {
        return $ver > 0 && $ver < 14;
    }

    return false;
}

/** Why the classic interface is being shown, in one sentence. Empty for a capable browser. */
function flash_fallback_reason(): string
{
    $zh = function_exists('lang') && is_zh();

    if (!flash_ui_enabled()) {
        return '';
    }

    return $zh
        ? 'Flash 版界面需要 Adobe Flash Player，而它在 2020 年底已经停止支持、并从所有现代浏览器中移除，所以这里显示纯文字界面。'
        : 'The Flash interface needs Adobe Flash Player, which reached end of life in 2020 and has been removed from every current browser, so the plain-text interface is shown instead.';
}

/**
 * The notice printed at the top of the classic pages for browsers that cannot play Flash.
 *
 * Deliberately the same box shape as the old-browser notice, and deliberately not an error: the
 * registry still works, only the decoration is gone.
 */
function flash_notice_html(): string
{
    if (!flash_ui_enabled() || flash_capable()) {
        return '';
    }

    $zh   = function_exists('lang') && is_zh();
    $head = $zh ? 'Flash 版界面不可用' : 'The Flash interface is not available';
    $body = flash_fallback_reason();
    $link = $zh
        ? '仍想看看 Flash 版？'
        : 'Curious about the Flash version?';

    $out  = '<div class="msg info fd-flash-warn">' . "\n";
    $out .= '  <strong>' . h($head) . '</strong><br>' . "\n";
    $out .= '  ' . h($body) . "\n";
    $out .= '  <br><span class="small">' . h($link) . ' '
        . '<a href="' . h(function_exists('url') ? url('flash.php') : 'flash.php') . '">'
        . h($zh ? '打开 Flash 版' : 'open the Flash page') . '</a>' . "</span>\n";
    $out .= "</div>\n";

    return $out;
}

/** The <object> block: classid for IE, <embed> for everything else, one fallback line. */
function flash_object_html(string $swf, int $w = 800, int $h = 600): string
{
    $zh = function_exists('lang') && is_zh();

    $out  = '<object classid="clsid:D27CDB6E-AE6D-11cf-96B8-444553540000"' . "\n";
    $out .= '        codebase="https://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=9,0,0,0"' . "\n";
    $out .= '        width="' . $w . '" height="' . $h . '" id="fdFlashUI" align="middle">' . "\n";
    $out .= '  <param name="movie" value="' . h($swf) . '">' . "\n";
    $out .= '  <param name="quality" value="high">' . "\n";
    $out .= '  <param name="bgcolor" value="#ffffff">' . "\n";
    $out .= '  <param name="allowScriptAccess" value="never">' . "\n";
    $out .= '  <embed src="' . h($swf) . '" quality="high" bgcolor="#ffffff" width="' . $w . '" height="' . $h . '"' . "\n";
    $out .= '         name="fdFlashUI" align="middle" allowScriptAccess="never" type="application/x-shockwave-flash"' . "\n";
    $out .= '         pluginspage="https://get.adobe.com/flashplayer/">' . "\n";
    $out .= '</object>' . "\n";
    $out .= '<p class="small">' . h($zh
        ? '如果这里是一块空白，说明浏览器没有安装 Flash Player。'
        : 'If this area stays blank, this browser has no Flash Player installed.') . ' '
        . '<a href="' . h(function_exists('url') ? url('index.php') : 'index.php') . '">'
        . h($zh ? '改用纯文字界面' : 'use the plain-text interface') . "</a></p>\n";

    return $out;
}

/**
 * The decision, in one line, for ?debug=1: the fastest way to answer "why did it do that".
 */
function flash_debug_line(): string
{
    if (!isset($_GET['debug'])) {
        return '';
    }

    $info = function_exists('fd_browser') ? fd_browser() : [];

    return 'engine=' . (string)($info['engine'] ?? '?')
        . ' key=' . (string)($info['key'] ?? '?')
        . ' version=' . (string)($info['version'] ?? '?')
        . ' enabled=' . (flash_ui_enabled() ? '1' : '0')
        . ' capable=' . (flash_capable() ? '1' : '0');
}
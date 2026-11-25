<?php
declare(strict_types=1);

/**
 * freedns.my - who is asking, and which interface should they get.
 *
 * Everything here is server-side on purpose. The site's visitor-facing pages carry no JavaScript at all, and the
 * browsers this file has to look after - IE6 through IE11 - are exactly the ones a client-side detector could not
 * be trusted to run in. One look at the User-Agent header decides:
 *
 *   1. which browser this is (family, version, and which rendering engine);
 *   2. which interface it should be given by DEFAULT:
 *        Internet Explorer  -> the classic interface (the only one it can render properly)
 *        everything modern  -> UI 2.0
 *   3. whether to show the "your browser is too old" notice (IE9 and below).
 *
 * Two rules this file obeys, because breaking either one annoys real people:
 *   * AN EXPLICIT CHOICE WINS. If somebody picked an interface in the console, or with ?ui=, that choice is
 *     honoured and nothing here overrides it - a Chrome user who prefers the classic look keeps it.
 *   * CHINESE DUAL-CORE BROWSERS ARE DETECTED AS SUCH. 360, QQ, Sogou, UC and Maxthon ship two engines and
 *     report differently in each mode (Chrome in "speed" mode, MSIE/Trident in "compatibility" mode). The shell
 *     name is read first, then the engine, so the interface matches what is actually rendering the page.
 *
 * There is no database access, no session and no side effect: this file only reads $_SERVER and returns values,
 * so it is safe to load on the error page as well.
 */

/** The User-Agent, lower-cased and capped. A header can be huge; nothing here needs more than 512 bytes. */
function fd_ua(): string
{
    static $ua = null;
    if ($ua === null) {
        $ua = strtolower(substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512));
    }

    return $ua;
}

/**
 * The browser, as ['key', 'name', 'version', 'engine', 'shell'].
 *
 *   key     ie | edge | chrome | firefox | safari | opera | yandex | samsung | android | other
 *   engine  trident | edgehtml | blink | gecko | webkit | presto | unknown
 *   shell   '' or the Chinese shell's name (360, qq, sogou, uc, maxthon, liebao, theworld)
 */
function fd_browser(): array
{
    static $out = null;
    if ($out !== null) {
        return $out;
    }

    $ua    = fd_ua();
    $found = static function (string $pattern) use ($ua): ?string {
        return preg_match($pattern, $ua, $m) === 1 ? (string)$m[1] : null;
    };

    // --- the shells first: they matter more than the engine they borrow -------------------------
    $shell = '';
    foreach ([
        '360'      => '/360se|360ee|qihoobrowser|qhbrowser/',
        'qq'       => '/qqbrowser|mqqbrowser/',
        'sogou'    => '/metasr|sogoumobilebrowser|se 2\.x/',
        'uc'       => '/ucbrowser|ucweb/',
        'maxthon'  => '/maxthon/',
        'liebao'   => '/lbbrowser/',
        'theworld' => '/theworld/',
    ] as $name => $pattern) {
        if (preg_match($pattern, $ua) === 1) {
            $shell = $name;
            break;
        }
    }

    // --- a real browser pretending to be another one, in the usual order ------------------------
    if (preg_match('/edg(?:e|ios|a)?\/([0-9.]+)/', $ua, $m) === 1) {          // Edge, Chromium based
        $out = ['key' => 'edge', 'name' => 'Edge', 'version' => (float)$m[1], 'engine' => 'blink', 'shell' => $shell];

        return $out;
    }
    if (preg_match('/edge\/([0-9.]+)/', $ua, $m) === 1) {                    // Edge, the old EdgeHTML one
        $out = ['key' => 'edge', 'name' => 'Edge (legacy)', 'version' => (float)$m[1], 'engine' => 'edgehtml',
                'shell' => $shell];

        return $out;
    }
    if (preg_match('/msie ([0-9.]+)/', $ua, $m) === 1) {                     // IE 10 and below
        $out = ['key' => 'ie', 'name' => 'Internet Explorer', 'version' => (float)$m[1], 'engine' => 'trident',
                'shell' => $shell];

        return $out;
    }
    if (str_contains($ua, 'trident/') && preg_match('/rv:([0-9.]+)/', $ua, $m) === 1) {   // IE 11
        $out = ['key' => 'ie', 'name' => 'Internet Explorer', 'version' => (float)$m[1], 'engine' => 'trident',
                'shell' => $shell];

        return $out;
    }
    // IE 6/7 in compatibility mode often arrives with no MSIE and no Trident at all.
    if ($shell === '' && (str_contains($ua, 'windows ce') || str_contains($ua, 'iemobile'))) {
        $out = ['key' => 'ie', 'name' => 'Internet Explorer (mobile)', 'version' => 7.0, 'engine' => 'trident',
                'shell' => ''];

        return $out;
    }
    if (preg_match('/opr\/([0-9.]+)/', $ua, $m) === 1 || preg_match('/opera[\/ ]([0-9.]+)/', $ua, $m) === 1) {
        $out = ['key' => 'opera', 'name' => 'Opera', 'version' => (float)$m[1], 'engine' => 'blink', 'shell' => $shell];

        return $out;
    }
    if (preg_match('/yabrowser\/([0-9.]+)/', $ua, $m) === 1) {
        $out = ['key' => 'yandex', 'name' => 'Yandex', 'version' => (float)$m[1], 'engine' => 'blink', 'shell' => $shell];

        return $out;
    }
    if (preg_match('/samsungbrowser\/([0-9.]+)/', $ua, $m) === 1) {
        $out = ['key' => 'samsung', 'name' => 'Samsung Internet', 'version' => (float)$m[1], 'engine' => 'blink',
                'shell' => $shell];

        return $out;
    }
    if (preg_match('/(?:crios|chrome|chromium)\/([0-9.]+)/', $ua, $m) === 1) {
        $out = ['key' => 'chrome', 'name' => 'Chrome', 'version' => (float)$m[1], 'engine' => 'blink', 'shell' => $shell];

        return $out;
    }
    if (preg_match('/(?:fxios|firefox)\/([0-9.]+)/', $ua, $m) === 1) {
        $out = ['key' => 'firefox', 'name' => 'Firefox', 'version' => (float)$m[1], 'engine' => 'gecko', 'shell' => $shell];

        return $out;
    }
    if (preg_match('/version\/([0-9.]+).*safari/', $ua, $m) === 1) {
        $out = ['key' => 'safari', 'name' => 'Safari', 'version' => (float)$m[1], 'engine' => 'webkit', 'shell' => $shell];

        return $out;
    }
    if (str_contains($ua, 'android')) {
        $out = ['key' => 'android', 'name' => 'Android browser', 'version' => 0.0, 'engine' => 'blink', 'shell' => $shell];

        return $out;
    }

    $out = ['key' => 'other', 'name' => 'an unknown browser', 'version' => 0.0, 'engine' => 'unknown', 'shell' => $shell];

    return $out;
}

/** The version number of the browser, as a float (11.0, 120.0). */
function fd_browser_version(): float
{
    return (float)(fd_browser()['version'] ?? 0.0);
}

function fd_is_ie(): bool
{
    return (fd_browser()['key'] ?? '') === 'ie';
}

/** IE9 or older: the notice is shown, and the classic interface is forced. */
function fd_ie_ancient(): bool
{
    return fd_is_ie() && fd_browser_version() <= 9.0;
}

/** IE7 or older: the conditional-comment stylesheet is loaded as well. */
function fd_ie_very_old(): bool
{
    return fd_is_ie() && fd_browser_version() <= 7.0;
}

/** IE11's own "compatibility view" pretends to be IE7 - report that honestly in the notice. */
function fd_ie_compat_view(): bool
{
    return fd_is_ie() && str_contains(fd_ua(), 'trident/') && preg_match('/msie 7\.0/', fd_ua()) === 1;
}

/** A short label for the console and the notice: "Chrome 120", "Internet Explorer 8", "360 (Chrome 86)". */
function fd_browser_label(): string
{
    $b     = fd_browser();
    $label = (string)$b['name'];
    if ((float)$b['version'] > 0) {
        $label .= ' ' . rtrim(rtrim(number_format((float)$b['version'], 1, '.', ''), '0'), '.');
    }
    $shellNames = ['360' => '360', 'qq' => 'QQ', 'sogou' => 'Sogou', 'uc' => 'UC', 'maxthon' => 'Maxthon',
                   'liebao' => 'Liebao', 'theworld' => 'TheWorld'];
    if (($b['shell'] ?? '') !== '' && isset($shellNames[(string)$b['shell']])) {
        $label = $shellNames[(string)$b['shell']] . ' (' . $label . ')';
    }

    return $label;
}

/**
 * Which interface this visitor should get when they have NOT chosen one.
 *
 *   classic  the old interface: the only one IE can render, and the safe answer for anything unknown
 *   modern   UI 1.0
 *   modern2  UI 2.0  (the default for every modern engine, as asked: Chrome gets 2.0)
 *
 * Unknown browsers get the classic interface on purpose: a browser nobody recognises is far more likely to be
 * something ancient or a script than a modern engine that forgot to say who it is.
 */
function fd_ui_default(): string
{
    $b = fd_browser();

    if (($b['key'] ?? '') === 'ie') {
        return 'classic';      // every IE, not only the ancient ones: this is what they render correctly
    }
    if (($b['engine'] ?? '') === 'edgehtml') {
        // Edge Legacy (EdgeHTML, up to version 18) has no CSS variables, no :is()/:has() and no flex gap, so
        // UI 2.0 would come out half-dressed. The operator asked for the classic interface here, and it is the
        // honest answer: Edge Legacy is a dead engine that will never get those features.
        return 'classic';
    }
    if (($b['key'] ?? '') === 'android') {
        // An old Android WebView (the browser inside an app) reports as Chrome but is years behind it.
        return 'classic';
    }
    if (in_array((string)$b['engine'], ['blink', 'gecko', 'webkit'], true)) {
        return 'modern2';
    }

    return 'classic';
}

/**
 * Does this browser lack @media entirely? IE6, IE7 and IE8 do - and that matters here, because the advertising
 * rails are shown or hidden with a media query. In those browsers the rails would either always show (squeezing
 * the text column) or never show, decided by nothing at all. The operator's answer: no rails at all in IE8 and
 * below, so the text keeps the full width.
 */
function fd_ie_no_media(): bool
{
    return fd_is_ie() && fd_browser_version() <= 8.0;
}

/** Is this visitor one we should tell about a newer browser? */
function fd_browser_outdated(): bool
{
    return fd_ie_ancient();
}

/**
 * The notice, as HTML, or '' when there is nothing to say.
 *
 * Deliberately plain: no JavaScript (IE6 could not run it), no image, no external link styling beyond the site's
 * own. The two links are the two things a person can actually do about it today.
 */
function fd_browser_notice(): string
{
    if (!fd_browser_outdated()) {
        return '';
    }

    $zh = function_exists('lang') && is_zh();
    $b  = fd_browser_label();
    $v  = fd_browser_version();

    $head = $zh
        ? '你的浏览器太旧了（' . $b . '），本站的排版可能显示不正常。'
        : 'Your browser is very old (' . $b . '), so parts of this site may look broken.';
    $body = $zh
        ? '为了安全和正常显示，请尽快更新到新版本，或改用 Chrome、Edge、Firefox、Safari 等现代浏览器。'
          . '本站会自动为你切换到兼容版界面，功能仍然可用。'
        : 'Please update to a newer version soon, or switch to Chrome, Edge, Firefox or Safari. '
          . 'This site has switched you to its compatible interface automatically - everything still works.';
    $hint = $v > 0 && $v <= 7
        ? ($zh ? '（如果你用的是国内浏览器的「兼容模式」，请把地址栏旁的图标切换成「极速模式」。）'
               : '(If you are using the compatibility mode of a Chinese browser, switch it to its speed mode.)')
        : '';

    $out  = '<div class="msg error fd-browser-warn">' . "\n";
    $out .= '  <strong>' . h($head) . "</strong><br>\n";
    $out .= '  ' . h($body) . "\n";
    if ($hint !== '') {
        $out .= '  <br>' . h($hint) . "\n";
    }
    $out .= '  <br><span class="small">'
        . ($zh ? '仍在使用的浏览器：' : 'Browser in use: ') . h($b)
        . ' &middot; <a href="https://browsehappy.com/" rel="nofollow noopener" target="_blank">'
        . ($zh ? '升级浏览器' : 'upgrade your browser') . '</a>'
        . ' &middot; <a href="https://www.google.cn/chrome/" rel="nofollow noopener" target="_blank">Chrome</a>'
        . ' &middot; <a href="https://www.microsoft.com/edge" rel="nofollow noopener" target="_blank">Edge</a>'
        . "</span>\n</div>\n";

    return $out;
}

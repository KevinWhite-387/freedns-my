<?php
declare(strict_types=1);

/**
 * freedns.my - plain text HTML rendering.
 *
 * Every user visible string of the public pages comes from the language files through t();
 * the back office uses the same helpers with the English catalogue.
 */

function is_admin_area(): bool
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));

    return str_contains($script, '/admin/');
}

function nav_public(): array
{
    /* The front page menu, deliberately five entries: the registry asked for exactly this shape.
     * Everything else lives one click deeper - the console groups the per-holder pages, and the
     * domain page carries the request/WHOIS links in its own sub-bar. */
    return [
        url('index.php')        => t('nav.home'),
        url('blog/')            => (function_exists('lang') && is_zh() ? '博客' : 'Blog'),
        url('domains.php')      => (function_exists('lang') && is_zh() ? '域名' : 'Domains'),
        url('abuse.php')        => (function_exists('lang') && is_zh() ? '举报' : 'Report'),
        url('account/index.php') => (function_exists('lang') && is_zh() ? '控制台' : 'Console'),
    ];
}

function nav_admin(): array
{
    // A reviewer only ever sees what it may use; everything else is hidden and answers 403.
    if (!is_owner()) {
        return [
            'index.php'   => 'Dashboard',
            'domains.php' => 'Domains',
            'changes.php' => 'Changes',
        ];
    }

    // This array is the flat source of truth: nav_admin_groups() only says which group each key
    // belongs to, and a key that is not here is skipped no matter what the groups say. Adding a
    // page therefore means adding it HERE first.
    return [
        'index.php'            => 'Dashboard',
        'health.php'           => 'Health',
        'maintenance.php'      => 'Maintenance',
        'api-maintenance.php'  => 'API maintenance',
        'cron-key.php'         => 'Cron key',

        'domains.php'          => 'Domains',
        'stats.php'            => 'Registration charts',
        'home-stats.php'       => 'Home page counters',
        'demo-data.php'        => 'Demo names',
        'dnshe-bind.php'       => 'DNSHE name binding',
        'zones.php'            => 'Zones',
        'reserved.php'         => 'Reserved',
        'nameservers.php'      => 'Name servers',
        'dns.php'              => 'DNS',
        'dns-providers.php'    => 'DNS providers (Spaceship, ANYNS)',
        'bulk.php'             => 'Bulk records',
        'verify.php'           => 'Published records',
        'limits.php'           => 'Rate limits',
        'changes.php'          => 'Changes',

        'users.php'            => 'Users',
        'invites.php'          => 'Invite codes',
        'verify-users.php'     => 'Unverified users',
        'emails.php'           => 'E-mail export',
        'admins.php'           => 'Administrators',
        'tickets.php'          => 'Tickets',
        'abuse.php'            => 'Abuse',
        'chat.php'             => 'Chat',

        'mail.php'             => 'Mail',
        'email.php'            => 'Mailboxes',
        'vault.php'            => 'Mail vault',
        'legacy-mail.php'      => 'Legacy mail migration',
        'forward-requests.php' => 'Mail forwarding',

        'servers.php'          => 'Cloud servers',
        'holds.php'            => 'Holding page',

        'finance.php'          => 'About us and the money',
        'ads.php'              => 'Advertising',
        'shop.php'             => 'Shop',
        'market.php'           => 'Market',
        'partners.php'         => 'Other registries',
        'reviews.php'          => 'Reviews',

        'openplatform.php'     => 'Open platform',
        'settings.php'         => 'Settings',
        'logs.php'             => 'Audit log',
        'export.php'           => 'Export',
        'ui-browser.php'       => 'Interface and browsers',

        'vcci.php'             => 'vc.ci sign-in',
        'developers.php'       => 'Developer requests',
        'faq.php'              => 'FAQ',
        'faq-i18n.php'         => 'FAQ translations',

        '/blog/admin/index.php'    => 'Blog posts',
        '/blog/admin/comments.php' => 'Blog comments',
        '/blog/admin/authors.php'  => 'Blog authors',
        '/blog/admin/message.php'  => 'Blog messages',
    ];
}

/**
 * The back-office menu, grouped so 47 entries do not read as one long line.
 *
 * nav_admin() stays the flat source of truth: this function only says which group each key
 * belongs to, and anything it does not mention is appended to "Other" at the end. That fallback
 * is deliberate - adding a page must never make it disappear from the menu because nobody
 * remembered to add it here too.
 */
function nav_admin_groups(): array
{
    $groups = [
        'Overview' => ['index.php', 'health.php', 'maintenance.php', 'api-maintenance.php', 'cron-key.php'],
        'Domains'  => ['domains.php', 'dnshe-bind.php', 'stats.php', 'home-stats.php', 'demo-data.php', 'zones.php', 'reserved.php', 'nameservers.php', 'dns.php',
                       'bulk.php', 'verify.php', 'limits.php', 'changes.php'],
        'People'   => ['users.php', 'verify-users.php', 'emails.php', 'invites.php', 'admins.php', 'tickets.php', 'abuse.php', 'chat.php'],
        'Mail'     => ['mail.php', 'email.php', 'vault.php', 'legacy-mail.php', 'forward-requests.php'],
        'Servers'  => ['servers.php', 'holds.php'],
        'Money'    => ['finance.php', 'ads.php', 'shop.php', 'market.php', 'partners.php', 'reviews.php'],
        'Platform' => ['openplatform.php', 'settings.php', 'logs.php', 'export.php', 'ui-browser.php'],
        'Extras'   => ['dns-providers.php', 'vcci.php', 'developers.php', 'faq.php', 'faq-i18n.php'],
        'Blog'     => ['/blog/admin/index.php', '/blog/admin/comments.php',
                       '/blog/admin/authors.php', '/blog/admin/message.php'],
    ];

    $flat   = nav_admin();
    $byKey  = [];
    foreach ($flat as $href => $label) {
        $byKey[(string)$href] = (string)$label;
    }

    $out  = [];
    $used = [];
    foreach ($groups as $name => $keys) {
        $links = [];
        foreach ($keys as $key) {
            if (!array_key_exists($key, $byKey)) {
                continue;                       // reviewer: the page is not in his menu at all
            }
            $links[$key] = $byKey[$key];
            $used[$key]  = true;
        }
        if ($links !== []) {
            $out[$name] = $links;
        }
    }

    $rest = [];
    foreach ($byKey as $key => $label) {
        if (empty($used[$key])) {
            $rest[$key] = $label;
        }
    }
    if ($rest !== []) {
        $out['Other'] = $rest;
    }

    return $out;
}

/** "Language: English · 中文" with the language in use shown as plain text. */
function language_switcher(): string
{
    $current = lang();

    /*
     * The line reads "English - 简体中文 - 其它語言": the two complete languages stay in it, the language in use is
     * shown in bold, and every other language lives behind one link to language.php, which lists them with how
     * far each translation has got. lang_switcher_html() (all six in one line) is kept for anything that still
     * wants the long form.
     */
    $others = function_exists('lang_line_html') ? lang_line_html()
        : (function_exists('lang_switcher_html') ? lang_switcher_html() : '');

    return '<span class="small">' . h(t('nav.lang_title')) . ': ' . $others . '</span>';
}

/**
 * The short navigation: home, the site map, and then the console and sign-out which render_nav() appends.
 * This is what a visitor sees by default - four links instead of eighteen.
 */
function nav_public_min(): array
{
    /* Same five entries as nav_public(): two menu styles, one shape. Kept as a separate function
     * because the renderer picks by nav_style() and other code may call either name. */
    return nav_public();
}

/**
 * Which navigation to show.
 *
 *   * a visitor who is not signed in always gets the short one - it is the default the registry asked for;
 *   * a signed-in holder may switch, and the choice is kept for the session.
 *
 * Nothing is written to the session for a visitor, so an ordinary page view still sets no cookie.
 */
function nav_style(): string
{
    $user = current_user();
    if ($user === null) {
        return 'new';
    }

    $wanted = strtolower(trim((string)($_GET['nav'] ?? '')));
    if (in_array($wanted, ['new', 'old'], true)) {
        $_SESSION['fd_nav_style'] = $wanted;

        return $wanted;
    }

    $kept = strtolower((string)($_SESSION['fd_nav_style'] ?? ''));

    return in_array($kept, ['new', 'old'], true) ? $kept : 'new';
}

function render_nav(): string
{
    $links = [];

    if (!empty($GLOBALS['FD_ADMIN_GATE_PROMPT'])) {        // Before the hidden entry is opened, not even the menu of the back office is shown.
        return '';
    }

    if (is_admin_area()) {
        $fdGroups = function_exists('nav_admin_groups') ? nav_admin_groups() : ['All' => nav_admin()];
        foreach ($fdGroups as $fdGroupName => $fdGroupLinks) {
        if (function_exists('is_zh') && is_zh() && function_exists('admin_group_zh')) { $fdGroupName = admin_group_zh($fdGroupName); }
            $fdGroupLinks2 = [];
            foreach ($fdGroupLinks as $href => $label) {
                $navHref = str_starts_with((string)$href, '/') ? (string)$href : url('admin/' . $href);
                if (function_exists('is_zh') && is_zh() && function_exists('admin_nav_zh')) {
                    $fdZhMap = admin_nav_zh();
                    if (isset($fdZhMap[(string)$href])) { $label = $fdZhMap[(string)$href]; }
                }
                $fdGroupLinks2[] = '<a href="' . h($navHref) . '">' . h($label) . '</a>';
            }
            if ($fdGroupLinks2 !== []) {
                $links[] = '<details class="adnav-grp" name="fd-adnav"><summary>' . h($fdGroupName) . '</summary><div class="adnav-panel">' . implode('', $fdGroupLinks2) . '</div></details>';
            }
        }
        if (function_exists('admin_lang_switcher')) { $links[] = admin_lang_switcher(); }
        $links[] = '<a href="' . h(url('index.php')) . '">Public site</a>';
        if (is_admin()) {
            $links[] = '<a href="' . h('logout.php?t=' . urlencode(logout_token())) . '">Sign out</a>';
        }
    } else {
        $navStyle = nav_style();
        $items    = $navStyle === 'old' ? nav_public() : nav_public_min();
        foreach ($items as $href => $label) {
            if (theme_is_modern2() && basename((string)parse_url($href, PHP_URL_PATH)) === 'advertise.php') {
                continue; // Both short and full menus must omit advertisements in UI2.
            }
            $links[] = '<a href="' . h($href) . '">' . h($label) . '</a>';
        }

        // In the 2.0 interface the way back to the classic look belongs in the navigation itself,
        // so a holder finds it without reading the banner first. A holder signs back in with one
        // click (?back=1 needs no confirmations); a visitor has no session choice, so the link
        // points at the classic site root instead.
        if (theme_is_modern2()) {
            $zhHome   = function_exists('lang') && is_zh();
            $classic  = '';
            if (function_exists('current_user') && current_user() !== null) {
                $classic = url('account/theme.php?back=1');
            } else {
                $classic = lang_url(lang(), 'index.php?ui=classic');
            }
            if ($classic !== '') {
                $links[] = '<a href="' . h($classic) . '">'
                    . h($zhHome ? '回到旧版' : 'Classic look') . '</a>';
            }
        }

        if (theme_active() === 'classic' && theme_default() === 'modern2' && current_user() === null) {
            $links[] = '<a href="' . h(lang_url(lang(), 'index.php?ui=modern2')) . '">'
                . h(is_zh() ? '使用新版 UI2' : 'Use UI2') . '</a>';
        }

        // The user console. The back office has no link on purpose: it is opened by typing its
        // secret address (Settings -> Admin entry) and answers 404 otherwise.
        $user = current_user();
        if ($user === null) {
            $links[] = '<a href="' . h(url('account/login.php')) . '">' . h(t('nav.signin')) . '</a>';
            $links[] = '<a href="' . h(url('account/register.php')) . '">' . h(t('nav.register')) . '</a>';
        } else {
            $links[] = '<a href="' . h(url('account/index.php')) . '">'
                . h(t('nav.console', (string)$user['username'])) . '</a>';

            // Signed in only: the switch between the short navigation and the full list of pages.
            $zhHome  = function_exists('lang') && is_zh();
            $links[] = $navStyle === 'old'
                ? '<a href="?nav=new">' . h($zhHome ? '新版导航' : 'New menu') . '</a>'
                : '<a href="?nav=old">' . h($zhHome ? '旧版导航' : 'Full menu') . '</a>';

            $links[] = '<a href="' . h(url('account/logout.php')) . '">' . h(t('nav.signout')) . '</a>';
        }
    }

    return implode(' &middot; ', $links);
}

/* ------------------------------------------------------------------ *
 * Colour theme                                                        *
 *                                                                     *
 * The public pages can be shown dark. The visitor chooses with a plain *
 * link (no JavaScript): ?theme=dark stores the choice in a cookie and  *
 * redirects back to the same page. Without a choice the system setting *
 * decides, through the CSS media query. The back office always stays   *
 * light, exactly as before.                                            *
 * ------------------------------------------------------------------ */

const FD_THEME_COOKIE = 'fdtheme';
const FD_THEME_YEAR   = 31536000;

/** 'dark', 'light' or 'auto' (the visitor has not chosen yet). */
function theme(): string
{
    $value = strtolower(trim((string)($_COOKIE[FD_THEME_COOKIE] ?? '')));

    return in_array($value, ['dark', 'light'], true) ? $value : 'auto';
}

/** Current address with the theme parameter replaced. */
function theme_switch_url(string $value): string
{
    $query = is_array($_GET) ? $_GET : [];
    unset($query['theme']);
    if ($value !== 'auto') {
        $query['theme'] = $value;
    }

    $path = (string)($_SERVER['SCRIPT_NAME'] ?? '/index.php');
    $path = str_replace(["\r", "\n", "\0", '\\'], '', $path);

    return $query === [] ? $path : $path . '?' . http_build_query($query);
}

/** "Theme: Light · Dark · System" with the current choice shown as plain text. */
function theme_switcher(): string
{
    $current = theme();
    $parts   = [];

    foreach (['light', 'dark', 'auto'] as $value) {
        $label = h(t('nav.theme_' . $value));
        $parts[] = $value === $current
            ? '<strong>' . $label . '</strong>'
            : '<a href="' . h(theme_switch_url($value)) . '">' . $label . '</a>';
    }

    return '<span class="small">' . h(t('nav.theme_title')) . ': ' . implode(' &middot; ', $parts) . '</span>';
}

/**
 * Handles ?theme=dark|light|auto: stores the choice and returns to the same page without the
 * parameter. Called from the bootstrap, before any output.
 */
function fd_apply_theme_switch(): void
{
    if (is_admin_area()) {
        // The back office has no theme switch and always stays light.
        return;
    }
    if (!isset($_GET['theme'])) {
        return;
    }

    $value = strtolower(trim((string)$_GET['theme']));
    if (!in_array($value, ['dark', 'light', 'auto'], true)) {
        return;
    }

    $options = [
        'expires'  => $value === 'auto' ? time() - 3600 : time() + FD_THEME_YEAR,
        'path'     => '/',
        'httponly' => true,
        'secure'   => request_is_https(),
        'samesite' => 'Lax',
    ];
    setcookie(FD_THEME_COOKIE, $value === 'auto' ? '' : $value, $options);

    // Back to the same address, with the parameter removed and the rest of the query kept.
    $path  = (string)($_SERVER['REQUEST_URI'] ?? '/');
    $parts = parse_url($path);
    $query = [];
    if (isset($parts['query'])) {
        parse_str((string)$parts['query'], $query);
        unset($query['theme']);
    }
    $target = (string)($parts['path'] ?? '/');
    if ($query !== []) {
        $target .= '?' . http_build_query($query);
    }

    header('Location: ' . safe_relative_url($target, '/'), true, 303);
    exit;
}

function page_css(): string
{
    // One source of truth for both palettes; dark is the same list with other values.
    $light = [
        'bg' => '#ffffff', 'fg' => '#111111', 'link' => '#0000EE', 'visited' => '#551A8B',
        'active' => '#EE0000', 'muted' => '#444444', 'hint' => '#555555', 'border' => '#bbbbbb',
        'softline' => '#eeeeee', 'panel' => '#fafafa', 'code' => '#f2f2f2', 'field' => '#ffffff',
        'fieldborder' => '#999999', 'button' => '#f2f2f2', 'buttonhover' => '#e4e4e4',
        'error' => '#990000', 'ok' => '#006600', 'infoline' => '#666666', 'scheme' => 'light',
    ];
    $dark = [
        'bg' => '#111111', 'fg' => '#e8e8e8', 'link' => '#7ab7ff', 'visited' => '#c9a0ff',
        'active' => '#ff8a8a', 'muted' => '#b0b0b0', 'hint' => '#a0a0a0', 'border' => '#444444',
        'softline' => '#2a2a2a', 'panel' => '#1a1a1a', 'code' => '#1f1f1f', 'field' => '#1a1a1a',
        'fieldborder' => '#555555', 'button' => '#2a2a2a', 'buttonhover' => '#333333',
        'error' => '#ff8080', 'ok' => '#6fdc6f', 'infoline' => '#9a9a9a', 'scheme' => 'dark',
    ];

    $variables = static function (array $set): string {
        $out = '';
        foreach ($set as $name => $value) {
            $out .= $name === 'scheme'
                ? '  color-scheme: ' . $value . ";\n"
                : '  --' . $name . ': ' . $value . ";\n";
        }

        return $out;
    };

    $theme = ":root {\n" . $variables($light) . "}\n"
        . ".theme-dark {\n" . $variables($dark) . "}\n"
        // No choice yet: follow the system setting.
        . "@media (prefers-color-scheme: dark) {\n  .theme-auto {\n"
        . preg_replace('/^/m', '  ', rtrim($variables($dark))) . "\n  }\n}\n";

    return $theme . <<<CSS
body { font-family: ui-monospace, "DejaVu Sans Mono", Menlo, Consolas, "Courier New", monospace;
       font-size: 15px; line-height: 1.55; color: var(--fg); background: var(--bg); margin: 0; padding: 0; }
.wrap { max-width: 54em; margin: 0 auto; padding: 1.5em 1em 3em 1em; }
/* Links keep the classic browser colours, like nic.eu.org: blue, purple once visited. */
a:link { color: var(--link); text-decoration: underline; }
a:visited { color: var(--visited); text-decoration: underline; }
a:hover { color: var(--link); text-decoration: underline; }
a:active { color: var(--active); }
h1 { font-size: 1.35em; margin: 0 0 .2em 0; letter-spacing: .02em; }
h2 { font-size: 1em; margin: 1.6em 0 .5em 0; text-transform: uppercase; letter-spacing: .08em; }
h3 { font-size: 1em; margin: 1.2em 0 .3em 0; }
p.sub { margin: 0 0 .5em 0; color: var(--muted); }
hr { border: 0; border-top: 1px solid var(--border); margin: 1em 0; }
p { margin: .5em 0; }
ul, ol { margin: .5em 0 .5em 1.4em; padding: 0; }
li { margin: .15em 0; }
table { border-collapse: collapse; width: 100%; margin: .6em 0; }
th, td { text-align: left; vertical-align: top; padding: .25em .8em .25em 0; }
th { border-bottom: 1px solid var(--border); font-weight: normal; text-transform: uppercase; font-size: .85em; letter-spacing: .05em; }
td { border-bottom: 1px solid var(--softline); }
input[type=text], input[type=password], input[type=email], input[type=number], select, textarea {
       font-family: inherit; font-size: inherit; color: var(--fg); background: var(--field);
       border: 1px solid var(--fieldborder); padding: .3em .4em; max-width: 100%; box-sizing: border-box; }
input[type=text], input[type=password], input[type=email], input[type=number] { width: 100%; max-width: 32em; }
textarea { width: 100%; }
input[type=submit], button { font-family: inherit; font-size: inherit; padding: .35em .9em;
       border: 1px solid var(--fieldborder); background: var(--button); color: var(--fg); cursor: pointer; }
input[type=submit]:hover, button:hover { background: var(--buttonhover); }
label { display: block; margin: .9em 0 .15em 0; }
label.inline { display: inline; margin: 0; }
.hint { color: var(--hint); font-size: .85em; }
.msg { border: 1px solid var(--fieldborder); border-left-width: 4px; padding: .5em .8em; margin: 1em 0; }
.msg.error { border-color: var(--error); }
.msg.info { border-color: var(--infoline); }
.msg.ok { border-color: var(--ok); }
.err { color: var(--error); }
.ok { color: var(--ok); }
.small { font-size: .88em; color: var(--muted); }
.mono-box { border: 1px solid var(--fieldborder); padding: .6em .8em; margin: 1em 0; background: var(--panel); }
pre.wrap { white-space: pre-wrap; word-wrap: break-word; margin: .6em 0; font-family: inherit; }
code { background: var(--code); padding: 0 .2em; }
.code-block { border: 1px solid var(--border); background: var(--panel); padding: .6em .8em; overflow-x: auto; }
.field-error { color: var(--error); font-size: .88em; }
td.actions { white-space: nowrap; }
form.inline { display: inline; }
.pill { border: 1px solid var(--fieldborder); padding: 0 .35em; font-size: .85em; }
CSS;
}

/**
 * The tab icon of every page: whatever the operator uploaded to the site root, or a built-in
 * text icon so browsers stop asking for a missing /favicon.ico.
 *
 * Every address carries the modification time of the file, so replacing an icon takes effect at
 * once: browsers (and a CDN in front of the site) cache a favicon for a very long time, and a
 * changed address is the only reliable way to make them fetch the new one.
 */
function favicon_links(): string
{
    $base  = app_base_url();
    $links = [];

    // Only UI2 uses the new brand; keep classic, 1.0 and the back office unchanged.
    if (theme_is_modern2() && is_file(FD_ROOT . '/icon.jpg')) {
        $stamp = (int)filemtime(FD_ROOT . '/icon.jpg');
        $links[] = '<link rel="icon" type="image/jpeg" href="' . h($base . '/icon.jpg?v=' . $stamp) . '">';
        foreach (['icon.ico' => 'icon', 'icon-192.png' => 'icon', 'icon-180.png' => 'apple-touch-icon'] as $file => $rel) {
            $path = FD_ROOT . '/' . $file;
            if (is_file($path)) {
                $type = $file === 'icon.ico' ? 'image/x-icon' : 'image/png';
                $links[] = '<link rel="' . $rel . '" type="' . $type . '" href="'
                    . h($base . '/' . $file . '?v=' . (int)filemtime($path)) . '">';
            }
        }
        return implode("\n", $links);
    }

    foreach ([
        'favicon.svg'          => 'image/svg+xml',
        'favicon.ico'          => null,
        'favicon-192.png'      => 'image/png',
        'apple-touch-icon.png' => 'image/png',
    ] as $file => $type) {
        $path = FD_ROOT . '/' . $file;
        if (!is_file($path)) {
            continue;
        }

        $stamp  = @filemtime($path);
        $suffix = $stamp === false ? '' : '?v=' . (int)$stamp;

        if ($file === 'apple-touch-icon.png') {
            $links[] = '<link rel="apple-touch-icon" href="' . h($base . '/' . $file . $suffix) . '">';
        } else {
            $links[] = '<link rel="icon" href="' . h($base . '/' . $file . $suffix) . '"'
                . ($type === null ? ' sizes="any"' : ' type="' . $type . '"') . '>';
        }
    }

    if ($links === []) {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">'
            . '<rect width="32" height="32" fill="#ffffff"/>'
            . '<text x="16" y="24" text-anchor="middle" font-family="monospace" font-size="21"'
            . ' font-weight="bold" fill="#0000EE">fd</text></svg>';
        $links[] = '<link rel="icon" href="data:image/svg+xml,' . rawurlencode($svg) . '">';
    }

    return implode("\n", $links);
}

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

function page_header(string $title): void
{

    // Resolved before the first byte of output: the navigation asks whether somebody is signed in
    // to the console, and starting a session after output would not work.
    $nav = render_nav();

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: same-origin');
    }

    $siteTitle = setting_lang('site_title');
    if ($siteTitle === '') {
        $siteTitle = 'freedns.my';
    }
    $subtitle = setting_lang('site_subtitle');

    echo "<!DOCTYPE html>\n<html lang=\"" . h(lang()) . '"'
        . (is_admin_area() ? '' : ' class="theme-' . h(theme()) . '"') . ">\n<head>\n";
    echo "<meta charset=\"utf-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo favicon_links() . "\n";
    echo '<meta name="robots" content="' . (is_admin_area() ? 'noindex,nofollow' : 'index,follow') . "\">\n";

    /*
     * A description for search engines. The back office and other private areas get none - they are noindex
     * anyway. For a public page it is built as "<page title> - <site description>", so interior pages do not
     * all carry exactly the same sentence; the site description itself is a setting, so it can be reworded
     * without touching code, and it follows the language of the page.
     */
    if (!is_admin_area()) {
        $fdDesc = trim((string)setting_lang('meta_description'));
        if ($fdDesc === '') {
            $fdDesc = function_exists('lang') && is_zh()
                ? 'freedns.my 免费二级域名注册局：免费申请域名、免费 DNS 解析，并提供自建邮件服务，注册即可使用。'
                : 'freedns.my is a free second level domain registry: free names, free DNS hosting and a mail '
                  . 'service of its own, ready as soon as you register.';
        }
        $fdTitle = trim((string)$title);
        if ($fdTitle !== '') {
            $fdDesc = $fdTitle . ' — ' . $fdDesc;
        }
        $fdDesc = function_exists('mb_substr') ? mb_substr($fdDesc, 0, 300) : substr($fdDesc, 0, 300);
        echo '<meta name="description" content="' . h($fdDesc) . "\">\n";

        /*
         * hreflang: tell a search engine that the same page exists in the other languages, so it shows the right
         * one to the right reader instead of treating them as duplicates. A language is only listed when it can
         * actually be shown (English lives in the root, the others in a directory), and x-default points at
         * English - the address that always works.
         */
        if (function_exists('fd_languages') && function_exists('lang')) {
            $fdRoot  = defined('FD_ROOT') ? FD_ROOT : dirname(__DIR__);
            $fdPath  = '/' . ltrim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
            $fdStrip = rtrim((string)(parse_url(function_exists('app_base_url') ? app_base_url() : '', PHP_URL_PATH) ?? ''), '/');
            if ($fdStrip !== '' && str_starts_with($fdPath, $fdStrip . '/')) {
                $fdPath = substr($fdPath, strlen($fdStrip));
            }
            foreach (array_keys(fd_languages()) as $fdCode) {
                if ($fdCode !== 'en' && str_starts_with($fdPath, '/' . $fdCode . '/')) {
                    $fdPath = substr($fdPath, strlen($fdCode) + 1);
                    break;
                }
            }
            $fdBase = function_exists('app_base_url') ? rtrim(app_base_url(), '/') : '';
            foreach (fd_languages() as $fdCode => $fdNames) {
                if ($fdCode !== 'en' && !is_dir($fdRoot . '/' . $fdCode)) {
                    continue;
                }
                echo '<link rel="alternate" hreflang="' . h($fdCode) . '" href="'
                    . h($fdBase . ($fdCode === 'en' ? '' : '/' . $fdCode) . $fdPath) . "\">\n";
            }
            echo '<link rel="alternate" hreflang="x-default" href="' . h($fdBase . $fdPath) . "\">\n";
        }
    }
    echo '<title>' . h($title) . ' &mdash; ' . h($siteTitle) . "</title>\n";
    echo "<style>\n" . page_css() . "</style>\n";
    // The modern interface, when a signed-in holder switched to it: its stylesheet, and the class the
    // stylesheet hangs off. Visitors never reach either of them.
    echo theme_head();
    echo theme_marker();
    $ui2 = theme_is_modern2();
    echo "</head>\n<body class=\"" . h(theme_body_class()) . "\">\n";
    if ($ui2) {
        echo ui2_topbar($siteTitle, $nav);      // full width, so it sits outside the content column
    }
    if ($ui2 && ui2_console_shell()) {
        $GLOBALS['FD_UI2_SHELL'] = true;
        echo '<div class="ui2-shell">' . ui2_console_rail();
    }
    echo "<div class=\"wrap\">\n";

    // Preserve the live site's inline-ad buffer for classic/1.0; UI2 has no advertisements.
    if (!$ui2) {
        $GLOBALS['FD_BODY_BUFFER'] = true;
        ob_start();
    }

    // The two advertising rails: wide screens only, never in the back office, and 2.0 carries no ads at all.
    if (function_exists('ad_rails') && !is_admin_area() && !$ui2) {
        echo ad_rails();
    }

    if (theme_is_modern()) {
        // Always visible in the new look, and always one click back - no confirmations on the way out.
        // The wording is deliberately blunt: development on this interface has stopped.
        echo '<p class="theme-flag">'
            . h(function_exists('lang') && is_zh()
                ? '新版界面 1.0（实验）· 已停止进一步开发（2.0 正在开发中）· 可能存在风险，请谨慎使用 · 出现问题请及时上报'
                : 'New interface 1.0 (experimental) · no further development (2.0 is being built) · may be risky, use with care · please report problems')
            . ' &middot; <a href="' . h(url('account/theme.php?back=1')) . '">'
            . h(function_exists('lang') && is_zh() ? '一键回到旧版' : 'back to the classic look')
            . '</a> &middot; <a href="' . h(url('experimental.php')) . '">'
            . h(function_exists('lang') && is_zh() ? '说明' : 'what this means') . "</a></p>\n";
    }
    if ($ui2) {
        if (!is_admin_area()) { echo ui2_banner(); }
    }

    /*
     * One <h1> per page, and it says what the page is about. On the home page that is the site name; on every
     * other page the page title becomes the h1 and the site name moves to the line underneath (the same
     * "sub" line the templates already use, so nothing about the look changes).
     */
    $fdScriptName = basename((string)($_SERVER['SCRIPT_NAME'] ?? 'index.php'));
    $fdIsHome     = $fdScriptName === 'index.php' || trim((string)$title) === '';
    if ($ui2 && $fdIsHome) {
        if (!is_admin_area()) { echo ui2_hero(); }
    }
    if ($fdIsHome) {
        echo '<h1><a href="' . h(url('index.php')) . '">' . h($siteTitle) . "</a></h1>\n";
    } else {
        echo '<h1>' . h($title) . "</h1>\n";
        echo '<p class="sub"><a href="' . h(url('index.php')) . '">' . h($siteTitle) . "</a></p>\n";
    }

    /*
     * A language that is still being translated says so, in its own words. Better a reader knows why half the
     * page is in English than to wonder whether the site is broken. The line disappears by itself once the
     * catalogue of that language is complete (see lang_incomplete()).
     */
    if (function_exists('lang_incomplete') && lang_incomplete()) {
        $fdNotice = [
            'zh'      => '本页面的部分内容尚未翻译，未翻译处显示英文。我们正在补全，欢迎帮忙。',
            'zh-hant' => '本頁面的部分內容尚未翻譯，未翻譯處顯示英文。我們正在補全，歡迎幫忙。',
            'ja'      => 'このページには未翻訳の部分があり、その部分は英語で表示されます。順次翻訳を進めています。',
            'ko'      => '이 페이지의 일부는 아직 번역되지 않아 영어로 표시됩니다. 번역을 진행 중입니다.',
            'ms'      => 'Sebahagian halaman ini belum diterjemah dan dipaparkan dalam bahasa Inggeris. '
                       . 'Terjemahan sedang diusahakan.',
        ][lang()] ?? '';
        if ($fdNotice !== '') {
            echo '<div class="msg info">' . h($fdNotice) . "</div>\n";
        }
    }

    // A notice while maintenance mode is on: visitors should know what is closed before they run into it, not
    // only at the moment a page refuses them. Administrators see it as well - it is how they notice it is on.
    // The API page says so while the API is taken down, so a holder does not mistake downtime for a broken key.
    $fdScript = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (function_exists('api_maint_on') && api_maint_on() && str_contains($fdScript, '/account/api.php')) {
        echo '<div class="msg info"><strong>'
            . h(is_zh() ? '接口维护中' : 'The API is down for maintenance')
            . '</strong><br>' . nl2br(h(api_maint_note()))
            . (api_maint_until() !== ''
                ? '<br><span class="small">' . h((is_zh() ? '预计恢复：' : 'Expected back: ') . api_maint_until()) . '</span>'
                : '')
            . "</div>\n";
    }

    if (function_exists('maintenance_active') && maintenance_active()) {
        echo '<p class="msg error">'
            . h(function_exists('lang') && is_zh() ? '维护中：' : 'Maintenance: ')
            . nl2br(h(function_exists('maintenance_note') ? maintenance_note() : ''))
            . "</p>\n";
    }
    if ($subtitle !== '') {
        echo '<p class="sub">' . h($subtitle) . "</p>\n";
    }
    if ($ui2) {
        // The navigation is already in the top bar; only the language and light/dark switches remain here.
        echo '<p class="nav">' . (function_exists('is_admin_area') && is_admin_area() && function_exists('admin_lang_switcher')
            ? admin_lang_switcher() : language_switcher())
            . (function_exists('is_admin_area') && is_admin_area() ? '' : ' &middot; ' . theme_switcher()) . "</p>\n";
    } else {
        if (!function_exists('theme_is_modern2') || !theme_is_modern2()) {
            echo "<hr>\n<p class=\"nav\">" . $nav . "</p>\n";
        }

        if (!is_admin_area()) {
            echo '<p class="nav">' . (function_exists('is_admin_area') && is_admin_area() && function_exists('admin_lang_switcher')
                ? admin_lang_switcher() : language_switcher())
                . (function_exists('is_admin_area') && is_admin_area() ? '' : ' &middot; ' . theme_switcher()) . "</p>\n";
        }
        echo "<hr>\n";
    }

    if (is_admin_area()) {
        $admin = current_admin();
        if ($admin !== null) {
            echo '<p class="small">Signed in as <strong>' . h((string)$admin['username']) . '</strong>'
                . ' &middot; last sign-in ' . h(format_date((string)($admin['last_login_at'] ?? ''))) . "</p>\n";
        }
    }

    // In 2.0 the <h1> already carries the page title, so it is not repeated as an <h2> (the home page keeps it).
    if (!$ui2 || $fdIsHome) {
        echo '<h2>' . h($title) . "</h2>\n";
    }

    foreach (take_flashes() as $flash) {
        $type = in_array($flash['type'], ['info', 'ok', 'error'], true) ? $flash['type'] : 'info';
        echo '<div class="msg ' . $type . '">' . h((string)$flash['message']) . "</div>\n";
    }
}

function page_footer(): void
{
    if (!empty($GLOBALS['FD_BODY_BUFFER'])) {
        $fdBody = (string)ob_get_clean();
        $GLOBALS['FD_BODY_BUFFER'] = false;
        echo function_exists('ad_inline_inject') ? ad_inline_inject($fdBody) : $fdBody;
    }
    $contact   = trim((string)setting('contact_email', ''));
    $siteTitle = setting_lang('site_title');
    if ($siteTitle === '') {
        $siteTitle = 'freedns.my';
    }

    /*
     * The advertisement under the application form. It is printed from here rather than from request.php so the
     * page itself does not have to be touched: this runs on every page, and only the application page gets it.
     * An empty slot shows the hiring notice instead, so the space never looks broken.
     */
    if (function_exists('ad_request_bottom')
        && basename((string)($_SERVER['SCRIPT_NAME'] ?? '')) === 'request.php'
        && !is_admin_area()
        && !theme_is_modern2()) {
        echo ad_request_bottom();
    }

    echo "<hr>\n<p class=\"small\">";
    echo t('footer.tagline', h($siteTitle));
    if ($contact !== '') {
        echo ' ' . t('footer.contact') . ' <a href="mailto:' . h($contact) . '">' . h($contact) . '</a>.';
    }
    echo ' ' . t('footer.generated', h(date('Y-m-d H:i')), h(date_default_timezone_get()));
    echo "</p>\n";
    if (theme_is_modern2()) {
        echo ui2_feedback($contact);
    }

    if (!is_admin_area()) {
        // A second switch at the bottom, so a long page does not have to be scrolled back up.
        echo '<p class="nav">' . theme_switcher() . "</p>\n";
    }

    echo "</div>\n";
    if (!empty($GLOBALS['FD_UI2_SHELL'])) {
        echo "</div>\n";          // the shell that holds the console menu and the page
    }
    echo "</body>\n</html>\n";
}

/** Renders administrator-editable plain text without interpreting any markup. */
function text_block(string $text): string
{
    return '<pre class="wrap">' . h($text) . '</pre>';
}

function error_list(array $errors): string
{
    if ($errors === []) {
        return '';
    }
    $html = '<div class="msg error"><strong>' . h(t('error.list_title')) . '</strong><ul>';
    foreach ($errors as $error) {
        $html .= '<li>' . h((string)$error) . '</li>';
    }

    return $html . '</ul></div>';
}

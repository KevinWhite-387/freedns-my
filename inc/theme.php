<?php
declare(strict_types=1);

/**
 * freedns.my - the interface theme.
 *
 * Two interfaces are offered:
 *
 *   classic   the site as it always looked - monospace, rules, tables. The default, and what old
 *             engines (IE, Edge Legacy, old Android WebViews) are sent to;
 *   modern2   "new interface 2.0", the card look. assets/theme-modern2.css.
 *
 * The frozen 1.0 interface has been withdrawn from the menu at the operator's request. Its
 * stylesheet and page builders stay on disk so a session that already carried it keeps working,
 * but nothing offers it.
 *
 * How the interface is chosen, in this order - each step matters:
 *
 *   1. AN EXPLICIT CHOICE WINS. Somebody who switched interface in the console keeps it for the
 *      session (theme_store()). ?ui=classic / ?ui=modern2 lets a visitor look at either one without
 *      a cookie, which is also how the switch can be tested on a browser that auto-picks.
 *   2. THE BROWSER DECIDES. With no choice made, fd_ui_default() (inc/browser.php) answers for the
 *      user agent: Blink, Gecko and WebKit get UI 2.0; IE, Edge Legacy and old Android WebViews get
 *      the classic interface, because they cannot render the modern stylesheet properly.
 *   3. THE OPERATOR CAN TURN STEP 2 OFF. The setting `ui_auto_pick` (default on) restores the older
 *      behaviour: visitors get the classic interface and only holders see a modern one.
 *
 * Careful with names: theme() in views.php is the LIGHT/DARK mode, not the interface version.
 * Everything that could be confused with it is called theme_<something>() on purpose.
 */

/**
 * The interfaces, with what they are called on screen and the stylesheet each one inlines.
 *
 * 'file' is relative to the site root; '' means the classic look, which is the stylesheet that
 * views.php has always printed by itself.
 */
function themes(): array
{
    return [
        'classic' => [
            'en' => 'Classic',
            'zh' => '旧版界面',
            'file' => '',
        ],
        'modern2' => [
            'en' => 'New interface 2.0',
            'zh' => '新版界面 2.0',
            'file' => 'assets/theme-modern2.css',
        ],
        // Retired from the menu, still valid so an old session or a bookmark does not fall over.
        'modern' => [
            'en' => 'New interface 1.0 (withdrawn)',
            'zh' => '新版界面 1.0（已下线）',
            'file' => 'assets/theme-modern.css',
            'retired' => true,
        ],
    ];
}

/** The interfaces a holder may actually pick. */
function theme_choices(): array
{
    $out = [];
    foreach (themes() as $key => $info) {
        if (empty($info['retired'])) {
            $out[$key] = $info;
        }
    }

    return $out;
}

/** Is this an interface the registry knows? */
function theme_valid(string $theme): bool
{
    return array_key_exists($theme, themes());
}

/** What an interface is called on screen. */
function theme_label(string $theme): string
{
    $info = themes()[$theme] ?? null;
    if ($info === null) {
        return $theme;
    }
    $zh = function_exists('lang') && is_zh();

    return (string)($zh ? ($info['zh'] ?? $info['en']) : $info['en']);
}

/**
 * The interface that is in force for this request.
 */
function theme_active(): string
{
    /*
     * The back office gets the same interface as the site now. The operator asked for 2.0 there
     * too; the setting ui_admin_ui2 (default on) turns it off again if a table ever looks wrong,
     * and the frozen 1.0 is never returned here.
     */
    if (function_exists('is_admin_area') && is_admin_area()) {
        $adminUi2 = !function_exists('setting_bool') || setting_bool('ui_admin_ui2', true);

        return $adminUi2 ? 'modern2' : 'classic';
    }

    $kept = strtolower((string)($_SESSION['fd_theme'] ?? ''));
    if (theme_valid($kept)) {
        return $kept;
    }

    // A stateless look-at-it switch for visitors: ?ui=classic / ?ui=modern2. No cookie is written.
    $guest = strtolower(trim((string)($_GET['ui'] ?? '')));
    if (theme_valid($guest)) {
        return $guest;
    }

    $user = function_exists('current_user') ? current_user() : null;

    // Visitors, with the automatic pick switched off: classic, exactly as before that feature.
    if ($user === null && function_exists('setting_bool') && !setting_bool('ui_auto_pick', true)) {
        return 'classic';
    }

    if (function_exists('fd_ui_default')) {
        $auto = fd_ui_default();
        if (theme_valid($auto) && empty(themes()[$auto]['retired'])) {
            return $auto;
        }
    }

    return 'classic';
}

/** The 2.0 interface. This is the question the rest of the site asks. */
function theme_is_modern2(): bool
{
    return theme_active() === 'modern2';
}

/** The frozen 1.0 interface, kept because its stylesheet and page builders still exist. */
function theme_is_modern(): bool
{
    return theme_active() === 'modern';
}

/** Remembers the choice for the session. Called only from the console, after the confirmations. */
function theme_store(string $theme): bool
{
    if (!theme_valid($theme)) {
        return false;
    }
    $_SESSION['fd_theme'] = $theme;

    return true;
}

/**
 * The phrase a holder has to type before an interface switch goes through.
 *
 * "Confirm more than once" is taken literally: the console page asks for a checkbox AND this phrase,
 * and the phrase differs per direction, so a copy-and-paste from a screenshot cannot do it.
 */
function theme_confirm_phrase(string $target): string
{
    if ($target === 'modern2') {
        return 'SWITCH TO UI 2.0';
    }
    if ($target === 'modern') {
        return 'SWITCH TO NEW UI';
    }

    return 'BACK TO CLASSIC';
}

/**
 * Everything the interface adds to <head>.
 *
 * The stylesheet is written straight into the page when the file is readable, and only linked when it
 * is not: a missing file, a rule that blocks /assets/, or a stale copy on the web server used to make
 * a switch look like it did nothing at all, and inlining removes every one of those failure modes.
 */
function theme_head(): string
{
    $active = theme_active();
    $file   = (string)(themes()[$active]['file'] ?? '');
    if ($file === '') {
        return '';
    }

    $path = (defined('FD_ROOT') ? FD_ROOT : dirname(__DIR__)) . '/' . $file;
    if (is_readable($path)) {
        $css = (string)@file_get_contents($path);
        if (trim($css) !== '') {
            return "<style>\n/* " . $file . ", inlined */\n" . $css . "\n</style>\n";
        }
    }

    return '<link rel="stylesheet" href="'
        . h(function_exists('url') ? url($file) : $file) . '">' . "\n";
}

/** A comment in the source saying which interface this page is, for anybody reading the HTML. */
function theme_marker(): string
{
    return '<!-- interface: ' . theme_active() . " -->\n";
}

/** The class the <body> carries, so every interface can be told apart in one stylesheet. */
function theme_body_class(): string
{
    return 'theme-' . theme_active();
}

/**
 * The interface the operator chose as the default (setting "ui_default").
 *
 * Kept because the page code asks for it when it needs to know what a visitor with no session and
 * no browser match would get. A retired interface is never returned: if the setting still says
 * "modern", the answer falls back to the classic one, so the frozen 1.0 cannot come back through a
 * setting somebody forgot about.
 *
 * The database being unreachable must never break a page, hence the guard and the cache.
 */
function theme_default(): string
{
    static $cached = null;

    if ($cached === null) {
        $cached = 'classic';
        try {
            if (function_exists('setting')) {
                $value = strtolower(trim((string)setting('ui_default', 'classic')));
                if (theme_valid($value) && empty(themes()[$value]['retired'])) {
                    $cached = $value;
                }
            }
        } catch (\Throwable $e) {
            $cached = 'classic';
        }
    }

    return $cached;
}
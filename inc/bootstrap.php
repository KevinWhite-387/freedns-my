<?php
declare(strict_types=1);

/**
 * freedns.my - application bootstrap.
 * Every entry script starts with:
 *     require __DIR__ . '/inc/bootstrap.php';          (public pages)
 *     require dirname(__DIR__) . '/inc/bootstrap.php'; (admin pages)
 */

define('FD_ROOT', dirname(__DIR__));
define('FD_INC', __DIR__);
define('FD_VERSION', '1.0.0');

$fdConfigFile = FD_INC . '/config.php';

if (!is_file($fdConfigFile)) {
    http_response_code(503);
    header('Content-Type: text/plain; charset=utf-8');
    echo "freedns.my is not installed yet.\n\n";
    echo "Open install.php in the site root to create the database tables\n";
    echo "and the first administrator account.\n";
    exit;
}

/** @var array $FD_CONFIG */
$FD_CONFIG = require $fdConfigFile;
if (!is_array($FD_CONFIG)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "inc/config.php did not return a configuration array.\n";
    exit;
}
$GLOBALS['FD_CONFIG'] = $FD_CONFIG;

date_default_timezone_set((string)($FD_CONFIG['site']['timezone'] ?? 'UTC'));

if (!empty($FD_CONFIG['site']['debug'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);
    ini_set('display_errors', '0');
}

require FD_INC . '/db.php';
require FD_INC . '/functions.php';
require FD_INC . '/mail.php';
require FD_INC . '/auth.php';
require FD_INC . '/admin_gate.php';
require FD_INC . '/users.php';
require FD_INC . '/github.php';
require FD_INC . '/transfer.php';
require FD_INC . '/market.php';
require FD_INC . '/ns.php';
require FD_INC . '/shop.php';
require FD_INC . '/chat.php';
require FD_INC . '/ddns.php';
require FD_INC . '/api.php';
require FD_INC . '/openplatform.php';
require FD_INC . '/browser.php';
require FD_INC . '/mailroute.php';
require FD_INC . '/totp.php';
require FD_INC . '/mailpref.php';
require FD_INC . '/rainyun.php';
require FD_INC . '/mailvault.php';
require FD_INC . '/mailimap.php';
require FD_INC . '/billionmail.php';
require FD_INC . '/theme.php';
// Loaded last on purpose: this one looks at the request as soon as it is included and answers refused actions
// (registering, adding a name, adding a mailbox) with the maintenance notice.
require FD_INC . '/maintenance.php';
require FD_INC . '/ads.php';
require FD_INC . '/cloudflare.php';
require FD_INC . '/dnshe.php';
require FD_INC . '/publish.php';
require FD_INC . '/flood.php';
require FD_INC . '/whois.php';
require FD_INC . '/views.php';

/*
 * The colour theme of the public pages: ?theme=dark|light|auto stores the visitor's choice in a
 * cookie and sends them back to the same page. Handled here, before any output, so no page has to
 * know about it. The back office ignores it and always stays light.
 */
fd_apply_theme_switch();

/*
 * Anti-flood protection, when it is switched on and the visitor address can be trusted: a visitor
 * who keeps hammering the site gets a 429 page instead of the page. The back office, static files
 * and the cron entry point are not counted.
 */
flood_check();

/*
 * The holding page: once the operator switches it on, every visitor (except the back office, which keeps
 * working so the switch can be turned off again) gets hold.php with a reference code. That page writes the
 * visit down under the code, so a mail quoting the code can be looked up in the back office.
 *
 * It sits here, after every include, because it needs the settings and the database helpers.
 */
if (setting_bool('hold_mode', false) && !is_admin() && !defined('FD_HOLD_SHOWN')) {
    $fdScript = basename((string)($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!in_array($fdScript, ['hold.php', 'login.php'], true)) {
        // The flag stops the page from being entered twice: hold.php is often reached *through* this file,
        // and without it the two would call each other for ever.
        define('FD_HOLD_SHOWN', true);
        require FD_ROOT . '/hold.php';
        exit;
    }
}

// Remembers ?ref=<invite code> in a cookie so the attribution survives until the visitor registers.
shop_capture_ref();

/*
 * No session is started here on purpose: the informational public pages (home page, domain
 * list, policy page) must not set any cookie. Everything that needs a session - flash
 * messages, CSRF tokens, administrator sign-in - starts it through fd_start_session(), and
 * the pages that render a form start it before the first byte of output.
 */

/* ---- additional DNS providers (Spaceship, ANYNS) ----
 * Loaded here rather than next to the other requires so that an installation that never
 * runs upgrade-dns-providers.sql still works: the modules check their own columns before
 * touching them, and nothing else references them until a suffix selects them.
 */
require_once FD_INC . '/spaceship.php';
require_once FD_INC . '/anyns.php';
/* ---- vc.ci sign-in (OAuth client) ----
 * Loaded last for the same reason as the DNS providers: an installation that has not run
 * upgrade-vcci.sql yet must still work - the module checks its own columns before using them.
 */
require_once FD_INC . '/vcci.php';
/* vc.ci domain migration (read-only against the vc.ci database) */
require_once FD_INC . '/vcci-migrate.php';

/* record type matrix and developer gating */
require_once FD_INC . '/record-types.php';

/* developer status and the gated record type queue */
require_once FD_INC . '/developer.php';

/* wording for the pages added later (ui()) */
require_once FD_INC . '/ui-strings.php';

/* the blog under /blog/ */
require_once FD_INC . '/blog.php';
require_once FD_INC . '/market-transfer.php';
require_once FD_INC . '/ui-flash.php';
require_once FD_INC . '/retired.php';
require_once FD_INC . '/admin-lang.php';
require_once FD_INC . '/faq.php';
require_once FD_INC . '/invites.php';
require_once FD_INC . '/abuse-scan.php';
// The back office has a language of its own (?lang=zh|en, remembered in the session). Applied here,
// before any page runs, and only for a request whose script lives under /admin/ - the visitor-facing
// pages must never be touched by it.
if (isset($_SERVER['SCRIPT_NAME'])
    && str_contains(str_replace('\\', '/', (string)$_SERVER['SCRIPT_NAME']), '/admin/')) {
    admin_lang_apply();
}
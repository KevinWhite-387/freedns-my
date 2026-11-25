<?php
declare(strict_types=1);

/**
 * freedns.my - anti-flood protection.
 *
 * Every page view is counted per visitor address in a sliding window (60 hits in 60 seconds by
 * default). A visitor who goes over it is blocked for ten minutes; a repeat offender is blocked for
 * an hour, then a day. A blocked visitor gets a plain 429 page with the contact address instead of
 * the site.
 *
 * Behind Cloudflare the real address only arrives in a header, so the protection stays switched off
 * until "Trust reverse-proxy headers" is on - otherwise every visitor would share one address and
 * one block would hit everybody.
 *
 * Not counted: static files, the cron entry point, and the whole back office (it has its own gate and
 * sign-in throttling, and the operator must never lock himself out).
 */

function flood_table_ready(): bool
{
    if (isset($GLOBALS['FD_FLOOD_TABLE'])) {
        return (bool)$GLOBALS['FD_FLOOD_TABLE'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'rate\\_limits'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_FLOOD_TABLE'] = $ready;

    return $ready;
}

/** The switch is only effective when the visitor address can be trusted. */
function flood_enabled(): bool
{
    return setting_bool('flood_enabled', false);
}

function flood_trust_ok(): bool
{
    return setting_bool('trust_proxy', false);
}

function flood_limit(): int
{
    return max(5, min(100000, (int)setting('flood_limit', '60')));
}

function flood_window(): int
{
    return max(5, min(3600, (int)setting('flood_window', '60')));
}

function flood_block_seconds(int $strikes): int
{
    $base = max(1, min(10080, (int)setting('flood_block_minutes', '10'))) * 60;

    if (!setting_bool('flood_escalate', true)) {
        return $base;
    }

    // 10 minutes, then an hour, then a day for anything beyond.
    if ($strikes <= 0) {
        return $base;
    }
    if ($strikes === 1) {
        return max($base, 3600);
    }

    return max($base, 86400);
}

/** One entry per line; a single address or a network in CIDR notation. */
function flood_whitelist(): array
{
    $lines = preg_split('/[\r\n,;]+/', (string)setting('flood_whitelist', '')) ?: [];
    $out   = [];
    foreach ($lines as $line) {
        $entry = trim($line);
        if ($entry !== '') {
            $out[] = $entry;
        }
    }

    return $out;
}

/** True for an address that matches an entry exactly or falls inside its network. */
function flood_ip_matches(string $ip, string $entry): bool
{
    if (strcasecmp($ip, $entry) === 0) {
        return true;
    }
    if (!str_contains($entry, '/')) {
        return false;
    }

    [$network, $bits] = explode('/', $entry, 2);
    $bits = (int)$bits;
    $ipBinary      = @inet_pton($ip);
    $networkBinary = @inet_pton($network);
    if ($ipBinary === false || $networkBinary === false || strlen($ipBinary) !== strlen($networkBinary)) {
        return false;
    }

    $maxBits = strlen($ipBinary) * 8;
    if ($bits < 0 || $bits > $maxBits) {
        return false;
    }

    $wholeBytes = intdiv($bits, 8);
    $restBits   = $bits % 8;

    if ($wholeBytes > 0 && substr($ipBinary, 0, $wholeBytes) !== substr($networkBinary, 0, $wholeBytes)) {
        return false;
    }
    if ($restBits === 0) {
        return true;
    }

    $mask = 0xFF << (8 - $restBits) & 0xFF;

    return (ord($ipBinary[$wholeBytes]) & $mask) === (ord($networkBinary[$wholeBytes]) & $mask);
}

function flood_is_whitelisted(string $ip): bool
{
    foreach (flood_whitelist() as $entry) {
        if (flood_ip_matches($ip, $entry)) {
            return true;
        }
    }

    return false;
}

/** Static files, the cron entry point and the back office are left alone. */
function flood_exempt(): bool
{
    $script = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));

    if (str_contains($script, '/admin/')) {
        // The back office has the hidden entry and its own sign-in throttling; locking the operator
        // out of it would be worse than the flood itself.
        return true;
    }

    $path = strtolower((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH));
    foreach (['.ico', '.png', '.jpg', '.jpeg', '.gif', '.svg', '.webp', '.css', '.js', '.map',
        '.txt', '.xml', '.woff', '.woff2'] as $extension) {
        if (str_ends_with($path, $extension)) {
            return true;
        }
    }

    return false;
}

/** A signed-in administrator is never counted (and the session is only opened when one exists). */
function flood_exempt_admin(): bool
{
    $cookieName = (string)(config('site', [])['session_name'] ?? 'freednssid');
    if (empty($_COOKIE[$cookieName])) {
        return false;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        fd_start_session();
    }

    return function_exists('is_admin') && is_admin();
}

function flood_row(string $ip): ?array
{
    if (!flood_table_ready()) {
        return null;
    }

    return db_one('SELECT * FROM rate_limits WHERE ip = ?', [$ip]);
}

/** Remaining seconds of a block, 0 when the address may pass. */
function flood_block_remaining(?array $row): int
{
    if ($row === null || empty($row['blocked_until'])) {
        return 0;
    }
    $until = strtotime((string)$row['blocked_until']);
    if ($until === false || $until <= time()) {
        return 0;
    }

    return $until - time();
}

function flood_block_now(string $ip, int $minutes, string $note = 'manual', bool $countStrike = false): void
{
    if (!flood_table_ready() || $ip === '') {
        return;
    }

    $minutes = max(1, min(10080, $minutes));
    $until   = date('Y-m-d H:i:s', time() + $minutes * 60);

    db_query(
        'INSERT INTO rate_limits (ip, hits, window_started, blocked_until, strikes, note, last_seen)
         VALUES (?, 0, NOW(), ?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE blocked_until = VALUES(blocked_until),
             strikes = strikes + ?, note = VALUES(note), last_seen = NOW()',
        [$ip, $until, $countStrike ? 1 : 0, $note, $countStrike ? 1 : 0]
    );
}

function flood_unblock(string $ip): void
{
    if (!flood_table_ready()) {
        return;
    }

    db_query('DELETE FROM rate_limits WHERE ip = ?', [$ip]);
}

/** Housekeeping: rows nobody touched for two days are dropped. */
function flood_prune(): void
{
    if (!flood_table_ready()) {
        return;
    }

    try {
        db_query('DELETE FROM rate_limits WHERE last_seen < ? AND (blocked_until IS NULL OR blocked_until < NOW())', [
            date('Y-m-d H:i:s', time() - 172800),
        ]);
    } catch (Throwable $e) {
        // Housekeeping must never break a page.
    }
}

/** The 429 page: plain, bilingual, with the contact address and the remaining time. */
function flood_blocked_page(int $remaining): void
{
    // Before handing the visitor over, ask whether this looks like an attack rather than one busy person:
    // the number of DIFFERENT addresses that hit the limit in the last few minutes. Protection mode is off
    // by default (hold_auto), because taking the site down is a drastic answer.
    if (setting_bool('hold_auto', false) && !setting_bool('hold_mode', false)) {
        try {
            $window = max(1, min(60, (int)setting('hold_auto_window', '5')));
            $limit  = max(5, min(5000, (int)setting('hold_auto_ips', '40')));
            $seen   = 0;
            if (db_one("SHOW TABLES LIKE 'hold\\_events'") !== null) {
                $seen = (int)db_value(
                    "SELECT COUNT(DISTINCT ip) FROM hold_events
                     WHERE reason = 'flood' AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
                    [$window],
                    0
                );
            }
            // This visitor is not in the table yet, so he counts as one more.
            if (($seen + 1) >= $limit) {
                $minutes = max(5, min(720, (int)setting('hold_auto_minutes', '30')));
                set_setting('hold_mode', '1');
                set_setting('hold_auto_at', date('Y-m-d H:i:s'));
                set_setting('hold_auto_until', date('Y-m-d H:i:s', time() + $minutes * 60));
                log_action('hold_auto_on', 'settings', null,
                    'protection mode switched itself on: ' . ($seen + 1) . ' address(es) limited within '
                    . $window . ' minute(s) (threshold ' . $limit . '), off again in ' . $minutes . ' minute(s)');

                $admin = trim((string)setting('notify_admin_email', ''));
                if ($admin !== '') {
                    $previous = lang_switch('en');
                    send_mail($admin, 'freedns.my: protection mode is on', implode("\n", [
                        t('mail.dear', $admin), '',
                        'The site was being hammered, so the holding page switched itself on.',
                        '',
                        'Addresses that hit the limit in the last ' . $window . ' minute(s): ' . ($seen + 1),
                        'It switches itself off again at ' . (string)setting('hold_auto_until', '') . '.',
                        '',
                        rtrim(app_base_url(), '/') . '/admin/holds.php',
                    ]));
                    lang_switch($previous);
                }
            }
        } catch (Throwable $e) {
            // Never let this check break the page a limited visitor is about to see.
        }
    }

    // A visitor who hammers the site is sent to the holding page, which hands out a reference code and
    // writes the visit down - so "the site blocked me" becomes a code the operator can look up, instead of
    // a bare 429 nobody can talk about. hold.php reads exactly these two values.
    $_GET['reason'] = 'flood';
    $_GET['status'] = '429';
    if (is_file(FD_ROOT . '/hold.php')) {
        require FD_ROOT . '/hold.php';
        exit;
    }

    $contact = trim((string)setting('contact_email', ''));

    http_response_code(429);
    if (!headers_sent()) {
        header('Retry-After: ' . max(30, $remaining));
    }

    page_header(t('block.heading'));
    echo '<div class="msg error"><strong>' . h(t('block.heading')) . '</strong></div>';
    echo '<p>' . h(t('block.message')) . '</p>';
    if ($remaining > 0) {
        echo '<p>' . h(t('block.remaining', (string)max(1, (int)ceil($remaining / 60)))) . '</p>';
    }
    if ($contact !== '') {
        echo '<p>' . t('block.contact', '<a href="mailto:' . h($contact) . '">' . h($contact) . '</a>') . '</p>';
    }
    echo '<p class="hint">' . h(t('block.hint')) . '</p>';
    page_footer();
    exit;
}

/**
 * Called from the bootstrap on every request.
 */
function flood_check(): void
{
    if (!flood_enabled() || !flood_trust_ok() || !flood_table_ready() || flood_exempt()) {
        return;
    }

    $ip = client_ip();
    if ($ip === '' || flood_is_whitelisted($ip)) {
        return;
    }
    if (flood_exempt_admin()) {
        return;
    }

    // Occasionally drop the rows nobody touched for two days.
    if (random_int(1, 100) === 1) {
        flood_prune();
    }

    $row       = flood_row($ip);
    $remaining = flood_block_remaining($row);
    if ($remaining > 0) {
        flood_blocked_page($remaining);
    }

    $limit   = flood_limit();
    $window  = flood_window();
    $started = $row === null ? null : strtotime((string)$row['window_started']);
    $restart = $started === false || $started === null || (time() - $started) > $window;

    if ($row === null || $restart) {
        db_query(
            'INSERT INTO rate_limits (ip, hits, window_started, blocked_until, strikes, last_seen)
             VALUES (?, 1, NOW(), NULL, 0, NOW())
             ON DUPLICATE KEY UPDATE hits = 1, window_started = NOW(), blocked_until = NULL, last_seen = NOW()',
            [$ip]
        );

        return;
    }

    db_query('UPDATE rate_limits SET hits = hits + 1, last_seen = NOW() WHERE ip = ?', [$ip]);
    $hits = (int)$row['hits'] + 1;
    if ($hits <= $limit) {
        return;
    }

    $strikes = (int)$row['strikes'];
    $seconds = flood_block_seconds($strikes);
    flood_block_now($ip, (int)ceil($seconds / 60), 'auto', true);

    log_action(
        'flood_block',
        'rate',
        null,
        $ip . ' blocked for ' . (int)ceil($seconds / 60) . ' minute(s) after ' . $hits
            . ' requests in ' . $window . ' seconds (strike ' . ($strikes + 1) . ')'
    );

    flood_blocked_page($seconds);
}

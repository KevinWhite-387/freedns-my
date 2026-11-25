<?php
declare(strict_types=1);

/**
 * freedns.my - registrant accounts for the user console (/account/).
 *
 * An account is registered with user name + e-mail + password. Names are linked to an account by
 * the e-mail address they were requested with, so everything that arrived through the old
 * "request code + e-mail" channel shows up in the console as well, and that channel keeps working.
 */

const FD_USER_SESSION = 'fd_user_id';
const FD_USER_VERIFY_TTL = 259200;   // verification link: three days
const FD_USER_RESET_TTL  = 7200;     // password reset link: two hours

/** True when the users table exists (the console may be installed before the upgrade is imported). */
function users_table_ready(): bool
{
    if (isset($GLOBALS['FD_USERS_READY'])) {
        return (bool)$GLOBALS['FD_USERS_READY'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'users'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_USERS_READY'] = $ready;

    return $ready;
}

/**
 * Starts the session only when the visitor already has one, so the informational public pages
 * (home page, domain list, policy) keep setting no cookie at all.
 */
function user_session_available(): bool
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return true;
    }

    $name = (string)(config('site', [])['session_name'] ?? 'freednssid');
    if (empty($_COOKIE[$name])) {
        return false;
    }

    fd_start_session();

    return session_status() === PHP_SESSION_ACTIVE;
}

function current_user(): ?array
{
    if (array_key_exists('FD_CURRENT_USER', $GLOBALS)) {
        return $GLOBALS['FD_CURRENT_USER'];
    }

    $GLOBALS['FD_CURRENT_USER'] = null;

    if (!user_session_available() || !users_table_ready()) {
        return null;
    }

    $id = (int)($_SESSION[FD_USER_SESSION] ?? 0);
    if ($id <= 0) {
        return null;
    }

    $row = db_one('SELECT * FROM users WHERE id = ?', [$id]);
    if ($row !== null && (string)$row['status'] !== 'suspended') {
        $GLOBALS['FD_CURRENT_USER'] = $row;
    }

    return $GLOBALS['FD_CURRENT_USER'];
}

function is_user(): bool
{
    return current_user() !== null;
}

/** Signs a name out of the console only; the old channel needs no session at all. */
function require_user(): array
{
    $user = current_user();
    if ($user === null) {
        fd_start_session();
        $_SESSION['after_user_login'] = safe_relative_url((string)($_SERVER['REQUEST_URI'] ?? ''), '/');
        flash(t('account.please_sign_in'), 'error');
        redirect(url('account/login.php'));
    }

    // Optional second factor: when this account uses an authenticator app, the code is asked for once per
    // session before anything else in the console opens.
    if (totp_columns_ready() && totp_user_on($user) && !totp_session_passed('user', (int)$user['id'])) {
        totp_pending_start('user', (int)$user['id']);
        redirect(url('totp.php'));
    }

    return $user;
}

/** Names that may not be registered as a user name. */
function reserved_usernames(): array
{
    return array_values(array_unique(array_merge(reserved_labels(), [
        'admin', 'admins', 'administrator', 'administrators', 'root', 'webmaster', 'hostmaster',
        'postmaster', 'abuse', 'support', 'registry', 'nic', 'freedns', 'moderator', 'staff',
        'official', 'help', 'info', 'contact', 'security', 'billing', 'sales', 'system', 'service',
        'account', 'accounts', 'console', 'panel', 'user', 'users', 'customer', 'customers',
        'register', 'signup', 'signin', 'login', 'logout', 'password', 'domain', 'domains',
        'host', 'hosting', 'cloud', 'dns', 'database', 'db', 'mysql', 'demo', 'test', 'dev',
        'staging', 'beta', 'alpha', 'guest', 'nobody', 'everyone', 'team', 'company', 'corp',
    ])));
}

/** Returns the i18n key of the problem, or '' when the user name is acceptable. */
function username_problem(string $username, int $exceptId = 0): string
{
    $name = trim($username);
    $length = function_exists('mb_strlen') ? mb_strlen($name, 'UTF-8') : strlen($name);

    if ($length < 3) {
        return 'val.username_short';
    }
    if ($length > 32) {
        return 'val.username_long';
    }
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name)) {
        return 'val.username_chars';
    }
    if (in_array(strtolower($name), reserved_usernames(), true)) {
        return 'val.username_reserved';
    }
    if (!users_table_ready()) {
        return 'val.user_no_table';
    }
    if (db_one('SELECT id FROM users WHERE username_lower = ? AND id <> ?', [strtolower($name), $exceptId]) !== null) {
        return 'val.username_taken';
    }

    return '';
}

/** Returns the i18n key of the problem, or '' when the password is acceptable. */
function password_problem(string $password, string $username = '', string $email = ''): string
{
    $length = strlen($password);
    if ($length < 8) {
        return 'val.password_short';
    }
    if ($length > 200) {
        return 'val.password_long';
    }

    $lower = strtolower($password);
    $local = strtolower((string)(strstr($email, '@', true) ?: ''));
    if (($username !== '' && $lower === strtolower(trim($username)))
        || ($local !== '' && $lower === $local)) {
        return 'val.password_weak';
    }

    return '';
}

function user_by_login(string $login): ?array
{
    if (!users_table_ready()) {
        return null;
    }

    $login = trim($login);
    if ($login === '') {
        return null;
    }

    if (str_contains($login, '@')) {
        return db_one('SELECT * FROM users WHERE email_lower = ?', [strtolower($login)]);
    }

    return db_one('SELECT * FROM users WHERE username_lower = ?', [strtolower($login)]);
}

/** How many accounts were opened from one address in the last day. */
function user_registrations_from_ip(string $ip): int
{
    if (!users_table_ready()) {
        return 0;
    }

    try {
        return (int)db_value(
            'SELECT COUNT(*) FROM users WHERE created_ip = ? AND created_at >= ?',
            [$ip, date('Y-m-d H:i:s', time() - 86400)],
            0
        );
    } catch (Throwable $e) {
        return 0;
    }
}

/** Registers a pending account and sends the verification mail. */
/* ---------------------------------------------------------------- daily registration cap */

/** How many accounts may be created per calendar day; 0 switches the cap off. */
function user_register_daily_limit(): int
{
    $raw = trim((string)setting('register_daily_limit', '45'));
    if ($raw === '') {
        return 45;
    }
    $n = (int)$raw;

    return $n > 0 ? $n : 0;
}

/** Accounts created today (the server's day, which is what the operators think in). */
function user_registrations_today(): int
{
    if (!function_exists('users_table_ready') || !users_table_ready()) {
        return 0;
    }

    return (int)db_value('SELECT COUNT(*) FROM users WHERE created_at >= CURDATE()', [], 0);
}

/** True when today's allowance is used up. */
function user_registrations_full(): bool
{
    $limit = user_register_daily_limit();
    if ($limit <= 0) {
        return false;
    }

    return user_registrations_today() >= $limit;
}

function user_register(string $username, string $email, string $password, string $language = 'en'): array
{
    $username = trim($username);
    $email    = trim($email);

    if (!users_table_ready()) {
        return ['ok' => false, 'error' => 'val.user_no_table', 'user' => null, 'mailed' => false];
    }

    // Daily allowance: the count is global, not per address, so a hundred fresh addresses cannot
    // walk past it. Checked here as well as on the form, because a hand-made POST never sees the form.
    if (user_registrations_full()) {
        log_action('register_daily_full', 'user', null, 'refused: daily limit ' . user_register_daily_limit() . ' reached');

        return ['ok' => false, 'error' => 'val.register_daily_full', 'user' => null, 'mailed' => false];
    }

    $error = username_problem($username);
    if ($error !== '') {
        return ['ok' => false, 'error' => $error, 'user' => null, 'mailed' => false];
    }
    if ($email === '') {
        return ['ok' => false, 'error' => 'val.email_required', 'user' => null, 'mailed' => false];
    }
    if (!valid_email_addr($email)) {
        return ['ok' => false, 'error' => 'val.email_invalid', 'user' => null, 'mailed' => false];
    }
    if (db_one('SELECT id FROM users WHERE email_lower = ?', [strtolower($email)]) !== null) {
        return ['ok' => false, 'error' => 'val.user_email_taken', 'user' => null, 'mailed' => false];
    }
    $error = password_problem($password, $username, $email);
    if ($error !== '') {
        return ['ok' => false, 'error' => $error, 'user' => null, 'mailed' => false];
    }

    $token   = bin2hex(random_bytes(24));
    $expires = date('Y-m-d H:i:s', time() + FD_USER_VERIFY_TTL);

    db_query(
        'INSERT INTO users (username, username_lower, email, email_lower, password_hash, status,
            language, verify_token, verify_expires, verify_sent_at, created_at, created_ip)
         VALUES (?, ?, ?, ?, ?, \'pending\', ?, ?, ?, NOW(), NOW(), ?)',
        [
            $username,
            strtolower($username),
            $email,
            strtolower($email),
            password_hash($password, PASSWORD_DEFAULT),
            $language === '' ? 'en' : $language,
            $token,
            $expires,
            client_ip(),
        ]
    );

    $user   = db_one('SELECT * FROM users WHERE username_lower = ?', [strtolower($username)]);
    $mailed = false;
    if ($user !== null) {
        $mailed = user_send_verification($user, $token);
        log_action('user_registered', 'user', (int)$user['id'], $username . ' <' . $email . '>');
    }

    return ['ok' => true, 'error' => '', 'user' => $user, 'mailed' => $mailed];
}

/** "http://…/account/verify.php?u=<id>&token=<token>" in the user's language. */
function user_verify_url(array $user, string $token): string
{
    return url('account/verify.php')
        . '?u=' . (int)$user['id'] . '&token=' . rawurlencode($token);
}

function user_reset_url(array $user, string $token): string
{
    return url('account/reset.php')
        . '?u=' . (int)$user['id'] . '&token=' . rawurlencode($token);
}

function user_send_verification(array $user, string $token): bool
{
    $previous = lang_switch((string)($user['language'] ?? 'en'));

    $subject = t('mail.user_verify_subject', (string)setting('site_title', 'freedns.my'));
    $lines   = [
        t('mail.dear', (string)$user['username']),
        '',
        t('mail.user_verify_intro', (string)$user['username']),
        '',
        user_verify_url($user, $token),
        '',
        t('mail.user_verify_expire'),
        '',
        t('mail.user_verify_ignore'),
        '',
        setting('site_title', 'freedns.my'),
    ];

    lang_switch($previous);

    return send_mail((string)$user['email'], $subject, implode("\n", $lines));
}

/** Creates a fresh token and mails it; used by the register page and the "resend" link. */
function user_verify_send(array $user): bool
{
    if (!users_table_ready()) {
        return false;
    }

    $token   = bin2hex(random_bytes(24));
    $expires = date('Y-m-d H:i:s', time() + FD_USER_VERIFY_TTL);

    db_query(
        'UPDATE users SET verify_token = ?, verify_expires = ?, verify_sent_at = NOW() WHERE id = ?',
        [$token, $expires, (int)$user['id']]
    );

    return user_send_verification($user, $token);
}

/** Checks a verification link. Returns true when the account was activated. */
function user_verify(int $userId, string $token): bool
{
    if (!users_table_ready() || $userId <= 0 || $token === '') {
        return false;
    }

    $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        return false;
    }
    if ((string)$user['status'] === 'active' && trim((string)($user['verify_token'] ?? '')) === '') {
        // Already active and verified: treat a repeated click as success.
        return true;
    }

    $expected = (string)($user['verify_token'] ?? '');
    $expires  = (string)($user['verify_expires'] ?? '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        return false;
    }
    if ($expires !== '' && strtotime($expires) !== false && strtotime($expires) < time()) {
        return false;
    }
    if ((string)$user['status'] === 'suspended') {
        return false;
    }

    db_query(
        'UPDATE users SET status = \'active\', verify_token = NULL, verify_expires = NULL WHERE id = ?',
        [$userId]
    );
    log_action('user_verified', 'user', $userId, (string)$user['username']);

    return true;
}

function user_login(string $login, string $password): array
{
    if (!users_table_ready()) {
        return ['ok' => false, 'error' => 'val.user_no_table', 'user' => null];
    }

    $key = portal_key('user-login', strtolower(trim($login)) . '|' . client_ip());
    if (portal_throttled($key)) {
        return ['ok' => false, 'error' => 'val.throttled', 'user' => null];
    }

    $user = user_by_login($login);
    if ($user === null) {
        record_portal_attempt($key, false);

        return ['ok' => false, 'error' => 'val.user_credentials', 'user' => null];
    }
    if (!password_verify($password, (string)$user['password_hash'])) {
        record_portal_attempt($key, false);

        return ['ok' => false, 'error' => 'val.user_credentials', 'user' => $user];
    }
    if ((string)$user['status'] === 'suspended') {
        return ['ok' => false, 'error' => 'val.user_suspended', 'user' => $user];
    }
    if ((string)$user['status'] !== 'active') {
        return ['ok' => false, 'error' => 'val.user_pending', 'user' => $user];
    }

    record_portal_attempt($key, true);
    fd_start_session();
    session_regenerate_id(true);
    $_SESSION[FD_USER_SESSION] = (int)$user['id'];
    db_query(
        'UPDATE users SET last_login_at = NOW(), last_login_ip = ?, language = ? WHERE id = ?',
        [client_ip(), lang(), (int)$user['id']]
    );
    unset($GLOBALS['FD_CURRENT_USER']);
    log_action('user_signed_in', 'user', (int)$user['id'], (string)$user['username'] . ' from ' . client_ip());

    return ['ok' => true, 'error' => '', 'user' => $user];
}

function user_logout(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        unset($_SESSION[FD_USER_SESSION]);
        unset($_SESSION['after_user_login']);
    }
    unset($GLOBALS['FD_CURRENT_USER']);
}

/** Sends a reset link. Always silent: it must not reveal whether an address is registered. */
function user_reset_send(string $email): void
{
    if (!users_table_ready()) {
        return;
    }

    $key = portal_key('user-reset', client_ip());
    if (portal_throttled($key)) {
        return;
    }
    record_portal_attempt($key, true);

    $email = trim($email);
    if (!valid_email_addr($email)) {
        return;
    }

    $user = db_one('SELECT * FROM users WHERE email_lower = ?', [strtolower($email)]);
    if ($user === null || (string)$user['status'] === 'suspended') {
        return;
    }

    user_reset_send_now($user);
}

/**
 * Creates a reset token and mails it. Used by the operator from the back office (no throttling,
 * the operator knows what he is doing) and by user_reset_send() after its own throttle.
 */
function user_reset_send_now(array $user): bool
{
    if (!users_table_ready()) {
        return false;
    }

    $token   = bin2hex(random_bytes(24));
    $expires = date('Y-m-d H:i:s', time() + FD_USER_RESET_TTL);
    db_query('UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?', [$token, $expires, (int)$user['id']]);
    log_action('user_reset_requested', 'user', (int)$user['id'], (string)$user['username']);

    $previous = lang_switch((string)($user['language'] ?? 'en'));
    $subject  = t('mail.user_reset_subject', (string)setting('site_title', 'freedns.my'));
    $lines    = [
        t('mail.dear', (string)$user['username']),
        '',
        t('mail.user_reset_intro'),
        '',
        user_reset_url($user, $token),
        '',
        t('mail.user_reset_expire'),
        '',
        t('mail.user_reset_ignore'),
        '',
        setting('site_title', 'freedns.my'),
    ];
    lang_switch($previous);

    return send_mail((string)$user['email'], $subject, implode("\n", $lines));
}

function user_by_reset_token(int $userId, string $token): ?array
{
    if (!users_table_ready() || $userId <= 0 || $token === '') {
        return null;
    }

    $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if ($user === null || (string)$user['status'] === 'suspended') {
        return null;
    }

    $expected = (string)($user['reset_token'] ?? '');
    $expires  = (string)($user['reset_expires'] ?? '');
    if ($expected === '' || !hash_equals($expected, $token)) {
        return null;
    }
    if ($expires !== '' && strtotime($expires) !== false && strtotime($expires) < time()) {
        return null;
    }

    return $user;
}

/** Sets a new password from a reset link. Returns the i18n key of the problem or ''. */
function user_reset_apply(array $user, string $token, string $password): string
{
    $fresh = user_by_reset_token((int)$user['id'], $token);
    if ($fresh === null) {
        return 'val.user_token';
    }

    $error = password_problem($password, (string)$fresh['username'], (string)$fresh['email']);
    if ($error !== '') {
        return $error;
    }

    db_query(
        'UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL,
            status = IF(status = \'suspended\', status, \'active\') WHERE id = ?',
        [password_hash($password, PASSWORD_DEFAULT), (int)$fresh['id']]
    );
    log_action('user_password_reset', 'user', (int)$fresh['id'], (string)$fresh['username']);

    return '';
}

/** The names of one account, with the number of records and queued changes. */
function user_domains(array $user): array
{
    if (!users_table_ready()) {
        return [];
    }

    return db_all(
        'SELECT d.*,
                (SELECT COUNT(*) FROM records r WHERE r.domain_id = d.id) AS record_count,
                (SELECT COUNT(*) FROM change_requests c WHERE c.domain_id = d.id AND c.status = \'pending\')
                    AS change_count
         FROM domains d
         WHERE LOWER(d.registrant_email) = ?
         ORDER BY FIELD(d.status, \'active\', \'pending\', \'suspended\', \'rejected\', \'deleted\'),
                  d.name ASC',
        [strtolower((string)$user['email'])]
    );
}

function user_owns_domain(array $user, array $domain): bool
{
    return strcasecmp(
        trim((string)($domain['registrant_email'] ?? '')),
        trim((string)($user['email'] ?? ''))
    ) === 0;
}

/** Loads one of the account's names; null when it does not exist or belongs to somebody else. */
function user_domain(array $user, int $id): ?array
{
    if ($id <= 0 || !users_table_ready()) {
        return null;
    }

    $domain = db_one('SELECT * FROM domains WHERE id = ?', [$id]);
    if ($domain === null || !user_owns_domain($user, $domain)) {
        return null;
    }

    return $domain;
}

/* ------------------------------------------------------------------ *
 * Registration quota                                                  *
 *                                                                     *
 * One total per e-mail address, counted across every suffix. A name   *
 * the applicant still holds (pending, active or suspended) counts; a  *
 * rejected or deleted one does not. An account may be unlimited, may  *
 * carry a personal number, or falls back to the global default.       *
 * ------------------------------------------------------------------ */

/** True when the users table carries the quota columns. */
function quota_columns_ready(): bool
{
    if (isset($GLOBALS['FD_QUOTA_COLS'])) {
        return (bool)$GLOBALS['FD_QUOTA_COLS'];
    }

    $ready = false;
    try {
        $ready = users_table_ready() && db_one("SHOW COLUMNS FROM users LIKE 'quota_unlimited'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_QUOTA_COLS'] = $ready;

    return $ready;
}

function quota_enabled(): bool
{
    return setting_bool('quota_enabled', false);
}

function quota_default_limit(): int
{
    return max(1, min(1000, (int)setting('quota_per_email', '3')));
}

/** Names this address still holds. */
function quota_used(string $email): int
{
    $email = trim($email);
    if ($email === '') {
        return 0;
    }

    try {
        return (int)db_value(
            "SELECT COUNT(*) FROM domains WHERE LOWER(registrant_email) = ?
             AND status IN ('pending', 'active', 'suspended')",
            [strtolower($email)],
            0
        );
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * The quota that applies to an e-mail address.
 *
 * @return array{unlimited: bool, limit: int, used: int, source: string, bonus: int}
 */
function quota_for_email(string $email): array
{
    $used = quota_used($email);

    if (!quota_enabled()) {
        return ['unlimited' => true, 'limit' => 0, 'used' => $used, 'source' => 'disabled', 'bonus' => 0];
    }

    $global = [
        'unlimited' => false,
        'limit'     => quota_default_limit(),
        'used'      => $used,
        'source'    => 'global',
        'bonus'     => 0,
    ];

    $email = trim($email);
    if ($email === '' || !quota_columns_ready() || !users_table_ready()) {
        return $global;
    }

    $user = db_one('SELECT * FROM users WHERE email_lower = ?', [strtolower($email)]);
    if ($user === null) {
        return $global;
    }

    // The bonus earned by starring the repository is added on top of whatever else applies.
    $bonus = (int)($user['quota_bonus'] ?? 0);

    if ((int)($user['quota_unlimited'] ?? 0) === 1) {
        return [
            'unlimited' => true, 'limit' => 0, 'used' => $used,
            'source' => 'account-unlimited', 'bonus' => $bonus,
        ];
    }

    $own = $user['quota_limit'] ?? null;
    if ($own !== null && (string)$own !== '' && (int)$own > 0) {
        return [
            'unlimited' => false, 'limit' => (int)$own + $bonus, 'used' => $used,
            'source' => 'account', 'bonus' => $bonus,
        ];
    }

    $global['limit'] += $bonus;
    $global['bonus'] = $bonus;

    return $global;
}

/** How many more names this address may still register (a big number while unlimited). */
function quota_remaining(string $email): int
{
    $quota = quota_for_email($email);

    return $quota['unlimited'] ? 999999 : max(0, $quota['limit'] - $quota['used']);
}

/** Marks every account that exists right now as unlimited: the grandfathering step. */
function quota_grandfather_existing(): int
{
    if (!quota_columns_ready()) {
        return 0;
    }

    $before = (int)db_value('SELECT COUNT(*) FROM users WHERE quota_unlimited = 1', [], 0);
    db_query('UPDATE users SET quota_unlimited = 1, quota_limit = NULL');
    set_setting('quota_grandfather_at', date('Y-m-d H:i:s'));
    log_action(
        'quota_grandfathered',
        'settings',
        null,
        'All accounts that existed at this moment were set to unlimited'
    );

    $after = (int)db_value('SELECT COUNT(*) FROM users WHERE quota_unlimited = 1', [], 0);

    return max(0, $after - $before);
}

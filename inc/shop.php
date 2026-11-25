<?php
declare(strict_types=1);

/**
 * freedns.my - the Shop: non-paid ways to raise the registration allowance.
 *
 *   1. GitHub star      - once per account, checked against the stargazer list (inc/github.php)
 *   2. promote us       - the holder submits a link, an owner approves it, the reward is granted
 *   3. invite code      - a personal code/link; registrations that arrive with it are attributed to that
 *                         account, so the console and the back office can both see who brought how many
 *
 * Every reward lands in users.quota_bonus, which quota_for_email() already adds on top of the normal
 * allowance - so nothing else in the registry had to change.
 */

const FD_SHOP_REF_COOKIE = 'fdref';

function shop_table_ready(): bool
{
    if (isset($GLOBALS['FD_SHOP_TABLE'])) {
        return (bool)$GLOBALS['FD_SHOP_TABLE'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'shop\\_promos'") !== null
            && db_one("SHOW COLUMNS FROM users LIKE 'invite_code'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_SHOP_TABLE'] = $ready;

    return $ready;
}

function shop_enabled(): bool
{
    return setting_bool('shop_enabled', true) && shop_table_ready();
}

function shop_promo_amount(): int
{
    return max(1, min(100, (int)setting('promo_bonus_amount', '2')));
}

function shop_promo_cap(): int
{
    return max(1, min(1000, (int)setting('promo_max_total', '10')));
}

/** How much promotion reward this account has already collected. */
function shop_promo_granted(int $userId): int
{
    if (!shop_table_ready()) {
        return 0;
    }

    return (int)db_value(
        "SELECT COALESCE(SUM(bonus), 0) FROM shop_promos WHERE user_id = ? AND status = 'approved'",
        [$userId],
        0
    );
}

function shop_promo_left(int $userId): int
{
    return max(0, shop_promo_cap() - shop_promo_granted($userId));
}

/* ------------------------------------------------------------------ *
 * Invite code and attribution                                         *
 * ------------------------------------------------------------------ */

/** The personal code of an account, created on first use. */
function shop_invite_code(array $user): string
{
    if (!shop_table_ready()) {
        return '';
    }

    $code = strtoupper(trim((string)($user['invite_code'] ?? '')));
    if ($code !== '') {
        return $code;
    }

    $base = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string)$user['username']) ?? '');
    $base = substr($base === '' ? 'USER' : $base, 0, 8);

    for ($try = 0; $try < 20; $try++) {
        $candidate = $base . strtoupper(bin2hex(random_bytes(2)));
        if (db_one('SELECT id FROM users WHERE invite_code = ?', [$candidate]) === null) {
            try {
                db_query('UPDATE users SET invite_code = ? WHERE id = ?', [$candidate, (int)$user['id']]);
            } catch (Throwable $e) {
                // The column is missing (upgrade-shop.sql not imported): stay silent, the page says so.
                return '';
            }
            unset($GLOBALS['FD_CURRENT_USER']);

            return $candidate;
        }
    }

    return '';
}

function shop_invite_url(string $code): string
{
    return $code === '' ? '' : url('account/register.php') . '?ref=' . rawurlencode($code);
}

function shop_user_by_code(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '' || !shop_table_ready()) {
        return null;
    }

    return db_one('SELECT * FROM users WHERE invite_code = ?', [$code]);
}

/**
 * Remembers ?ref=CODE in a cookie so the attribution survives browsing to the registration page.
 * Called from the bootstrap on every request; it never writes anything else.
 */
function shop_capture_ref(): void
{
    if (!shop_table_ready()) {
        return;
    }

    $given = strtoupper(trim((string)($_GET['ref'] ?? '')));
    if ($given === '' || !preg_match('/^[A-Z0-9]{4,32}$/', $given)) {
        return;
    }
    if (($_COOKIE[FD_SHOP_REF_COOKIE] ?? '') === $given) {
        return;
    }
    if (shop_user_by_code($given) === null) {
        return;
    }
    if (headers_sent()) {
        return;
    }

    $secure = function_exists('request_is_https') && request_is_https();
    setcookie(FD_SHOP_REF_COOKIE, $given, [
        'expires'  => time() + 30 * 86400,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[FD_SHOP_REF_COOKIE] = $given;
}

/** The code the visitor arrived with, if any. */
function shop_ref_code(): string
{
    $code = strtoupper(trim((string)($_COOKIE[FD_SHOP_REF_COOKIE] ?? '')));

    return preg_match('/^[A-Z0-9]{4,32}$/', $code) === 1 ? $code : '';
}

/**
 * A code typed into the registration form: remembered exactly like ?ref=CODE, so the new account is
 * attributed to that code on its first visit to the console. Returns false when the code is unknown.
 */
function shop_remember_ref(string $code): bool
{
    $code = strtoupper(trim($code));
    if ($code === '') {
        return true;
    }
    if (!shop_table_ready() || preg_match('/^[A-Z0-9]{4,32}$/', $code) !== 1) {
        return false;
    }
    if (shop_user_by_code($code) === null) {
        return false;
    }
    if (!headers_sent()) {
        $secure = function_exists('request_is_https') && request_is_https();
        setcookie(FD_SHOP_REF_COOKIE, $code, [
            'expires'  => time() + 30 * 86400,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
    $_COOKIE[FD_SHOP_REF_COOKIE] = $code;

    return true;
}

/**
 * Attributes the signed-in account to the referrer it arrived with (once). Called from the console, so
 * a registration that came through an invite link is counted from the first console visit on.
 */
function shop_attribute_user(array $user): void
{
    $code = shop_ref_code();
    if ($code === '' || !shop_table_ready() || trim((string)($user['referred_by'] ?? '')) !== '') {
        return;
    }
    $referrer = shop_user_by_code($code);
    if ($referrer === null || (int)$referrer['id'] === (int)$user['id']) {
        return;
    }

    db_query('UPDATE users SET referred_by = ? WHERE id = ?', [$code, (int)$user['id']]);
    unset($GLOBALS['FD_CURRENT_USER']);
    log_action('shop_referral', 'user', (int)$user['id'],
        (string)$user['username'] . ' came through the invite code of ' . (string)$referrer['username']);
}

/**
 * What an invite code brought in: people and how many of their names are active.
 *
 * @return array{people: int, active: int, pending: int}
 */
function shop_referral_stats(string $code): array
{
    $code = strtoupper(trim($code));
    $out  = ['people' => 0, 'active' => 0, 'pending' => 0];
    if ($code === '' || !shop_table_ready()) {
        return $out;
    }

    $out['people'] = (int)db_value('SELECT COUNT(*) FROM users WHERE referred_by = ?', [$code], 0);
    if ($out['people'] > 0) {
        $out['active'] = (int)db_value(
            "SELECT COUNT(*) FROM domains d JOIN users u ON LOWER(u.email) = LOWER(d.registrant_email)
             WHERE u.referred_by = ? AND d.status = 'active'",
            [$code],
            0
        );
        $out['pending'] = (int)db_value(
            "SELECT COUNT(*) FROM domains d JOIN users u ON LOWER(u.email) = LOWER(d.registrant_email)
             WHERE u.referred_by = ? AND d.status = 'pending'",
            [$code],
            0
        );
    }

    return $out;
}

/** Everybody with an invite code, ranked by the number of people they brought. */
function shop_referral_ranking(int $limit = 200): array
{
    if (!shop_table_ready()) {
        return [];
    }

    return db_all(
        "SELECT u.id, u.username, u.email, u.invite_code, u.referred_by,
                (SELECT COUNT(*) FROM users x WHERE x.referred_by = u.invite_code) AS people,
                (SELECT COUNT(*) FROM domains d JOIN users y ON LOWER(y.email) = LOWER(d.registrant_email)
                 WHERE y.referred_by = u.invite_code AND d.status = 'active') AS active_names,
                (SELECT COALESCE(SUM(p.bonus), 0) FROM shop_promos p
                 WHERE p.user_id = u.id AND p.status = 'approved') AS promo_bonus,
                u.quota_bonus
         FROM users u
         WHERE u.invite_code IS NOT NULL AND u.invite_code <> ''
         ORDER BY people DESC, active_names DESC, u.id ASC LIMIT " . max(1, $limit)
    );
}

/* ------------------------------------------------------------------ *
 * Promotion submissions                                               *
 * ------------------------------------------------------------------ */

function shop_promos_of(int $userId): array
{
    if (!shop_table_ready()) {
        return [];
    }

    return db_all('SELECT * FROM shop_promos WHERE user_id = ? ORDER BY id DESC LIMIT 50', [$userId]);
}

function shop_promos_pending(): array
{
    if (!shop_table_ready()) {
        return [];
    }

    return db_all("SELECT * FROM shop_promos WHERE status = 'pending' ORDER BY id ASC LIMIT 200");
}

function shop_promos_recent(int $limit = 50): array
{
    if (!shop_table_ready()) {
        return [];
    }

    return db_all('SELECT * FROM shop_promos ORDER BY id DESC LIMIT ' . max(1, $limit));
}

/** The holder submits a place where he promotes the registry. */
function shop_promo_submit(array $user, string $url, string $description): array
{
    if (!shop_enabled()) {
        return ['ok' => false, 'error' => t('val.shop_off')];
    }
    if (shop_promo_left((int)$user['id']) <= 0) {
        return ['ok' => false, 'error' => t('val.shop_cap', (string)shop_promo_cap())];
    }

    $url = trim($url);
    if ($url === '' || strlen($url) > 255 || !preg_match('#^https?://#i', $url)) {
        return ['ok' => false, 'error' => t('val.shop_url')];
    }
    if (trim($description) === '') {
        return ['ok' => false, 'error' => t('val.shop_description')];
    }

    db_query(
        'INSERT INTO shop_promos (user_id, user_email, url, description, status, created_at)
         VALUES (?, ?, ?, ?, \'pending\', NOW())',
        [(int)$user['id'], (string)$user['email'], $url, $description]
    );
    $id = (int)db()->lastInsertId();
    log_action('shop_promo_submitted', 'user', (int)$user['id'],
        (string)$user['username'] . ' submitted ' . $url);

    $admin = trim((string)setting('notify_admin_email', ''));
    if ($admin !== '') {
        shop_mail($admin, 'mail.shop_new_subject', [(string)$user['username']],
            t('mail.shop_new_body', (string)$user['username'], (string)$user['email'], $url, $description), 'en');
    }

    return ['ok' => true, 'error' => '', 'id' => $id];
}

/** The owner decides: approving adds the reward to the account's bonus quota. */
function shop_promo_decide(int $id, bool $approve, string $reason, string $staff): array
{
    $promo = shop_table_ready() ? db_one('SELECT * FROM shop_promos WHERE id = ?', [$id]) : null;
    if ($promo === null) {
        return ['ok' => false, 'error' => 'That submission does not exist.'];
    }
    if ((string)$promo['status'] !== 'pending') {
        return ['ok' => false, 'error' => 'That submission was already decided.'];
    }

    if ($approve && shop_promo_left((int)$promo['user_id']) <= 0) {
        return ['ok' => false, 'error' => 'That account already reached the promotion cap.'];
    }

    $bonus = $approve ? min(shop_promo_amount(), shop_promo_left((int)$promo['user_id'])) : 0;
    db_query(
        'UPDATE shop_promos SET status = ?, reason = ?, bonus = ?, decided_by = ?, decided_at = NOW() WHERE id = ?',
        [$approve ? 'approved' : 'rejected', $approve ? null : $reason, $bonus, $staff, $id]
    );

    if ($approve && $bonus > 0) {
        db_query('UPDATE users SET quota_bonus = quota_bonus + ? WHERE id = ?', [$bonus, (int)$promo['user_id']]);
        unset($GLOBALS['FD_CURRENT_USER']);
    }

    log_action($approve ? 'shop_promo_approved' : 'shop_promo_rejected', 'user', (int)$promo['user_id'],
        (string)$promo['user_email'] . ' ' . $promo['url'] . ($approve ? ' (+' . $bonus . ')' : ': ' . $reason));

    shop_mail(
        (string)$promo['user_email'],
        $approve ? 'mail.shop_approved_subject' : 'mail.shop_rejected_subject',
        [],
        $approve
            ? t('mail.shop_approved_body', (string)$bonus, (string)shop_promo_left((int)$promo['user_id']))
            : t('mail.shop_rejected_body', $reason),
        'en'
    );

    return ['ok' => true, 'error' => '', 'bonus' => $bonus];
}

function shop_mail(string $to, string $subjectKey, array $args, string $body, string $language = 'en'): bool
{
    // The account may have switched shop notices off; every shop mail passes through here.
    if (!mail_pref_allows($to, 'shop')) {
        log_action('mail_skipped', 'shop', null, 'shop notice to ' . $to . ' not wanted');

        return true;
    }

    $previous = lang_switch($language);
    $subject  = t($subjectKey, ...$args);
    $lines    = array_merge(
        [t('mail.dear', $to), '', $body, '', url('account/shop.php'), '',
         setting('site_title', 'freedns.my')]
    );
    lang_switch($previous);

    return send_mail($to, $subject, implode("\n", $lines));
}

/** A short overview for the console: what this account has earned so far. */
function shop_overview(array $user): array
{
    $quota = quota_for_email((string)$user['email']);
    $code  = shop_invite_code($user);

    return [
        'quota'      => $quota,
        'bonus'      => (int)($user['quota_bonus'] ?? 0),
        'promo_done' => shop_promo_granted((int)$user['id']),
        'promo_left' => shop_promo_left((int)$user['id']),
        'promo_cap'  => shop_promo_cap(),
        'code'       => $code,
        'link'       => shop_invite_url($code),
        'stats'      => shop_referral_stats($code),
        'star_taken' => star_bonus_taken($user),
        'star_amount' => star_bonus_amount(),
        'star_ready' => star_bonus_enabled(),
    ];
}

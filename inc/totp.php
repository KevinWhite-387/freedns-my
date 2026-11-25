<?php
declare(strict_types=1);

/**
 * freedns.my - optional two-factor authentication (RFC 6238, TOTP).
 *
 * An account may switch it on for itself; nobody is forced. A secret is stored only after the account has
 * confirmed one code from it, so a half-finished setup can never lock anybody out, and switching it off
 * needs a current code. No QR image is produced: the secret and an otpauth:// address are printed, which
 * every authenticator app accepts by hand.
 */

const FD_TOTP_PERIOD = 30;
const FD_TOTP_DIGITS = 6;

function totp_columns_ready(): bool
{
    if (isset($GLOBALS['FD_TOTP_COLS'])) {
        return (bool)$GLOBALS['FD_TOTP_COLS'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW COLUMNS FROM users LIKE 'totp_secret'") !== null
            && db_one("SHOW COLUMNS FROM admins LIKE 'totp_secret'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_TOTP_COLS'] = $ready;

    return $ready;
}

function totp_base32_encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits     = '';
    foreach (str_split($data) as $char) {
        $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        $out .= $alphabet[bindec(str_pad($chunk, 5, '0', STR_PAD_RIGHT))];
    }

    return $out;
}

function totp_base32_decode(string $secret): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = strtoupper(preg_replace('/[^A-Z2-7]/i', '', $secret) ?? '');
    $bits     = '';
    foreach (str_split($secret) as $char) {
        $index = strpos($alphabet, $char);
        if ($index === false) {
            continue;
        }
        $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $out .= chr(bindec($chunk));
        }
    }

    return $out;
}

/** A fresh secret (20 random bytes, base32 - what authenticator apps expect). */
function totp_secret_new(): string
{
    return totp_base32_encode(random_bytes(20));
}

function totp_code(string $secret, ?int $slice = null): string
{
    $slice = $slice ?? (int)floor(time() / FD_TOTP_PERIOD);
    $key   = totp_base32_decode($secret);
    $hash  = hash_hmac('sha1', pack('N*', 0) . pack('N*', $slice), $key, true);
    $offset = ord(substr($hash, -1)) & 0x0F;
    $part   = substr($hash, $offset, 4);
    $value  = unpack('N', $part)[1] & 0x7FFFFFFF;

    return str_pad((string)($value % (10 ** FD_TOTP_DIGITS)), FD_TOTP_DIGITS, '0', STR_PAD_LEFT);
}

/** True when the code matches now, one step before or one step after (clock drift). */
function totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if ($secret === '' || strlen($code) !== FD_TOTP_DIGITS) {
        return false;
    }
    $now = (int)floor(time() / FD_TOTP_PERIOD);
    for ($step = -$window; $step <= $window; $step++) {
        if (hash_equals(totp_code($secret, $now + $step), $code)) {
            return true;
        }
    }

    return false;
}

function totp_uri(string $account, string $secret): string
{
    $issuer = (string)setting('site_title', 'freedns.my');
    $label  = rawurlencode($issuer . ':' . $account);

    return 'otpauth://totp/' . $label . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer) . '&digits=' . FD_TOTP_DIGITS . '&period=' . FD_TOTP_PERIOD;
}

/* ------------------------------------------------------------------ *
 * The account of the visitor (console)                                *
 * ------------------------------------------------------------------ */

function totp_user_secret(array $user): string
{
    return trim((string)($user['totp_secret'] ?? ''));
}

function totp_user_on(array $user): bool
{
    return totp_user_secret($user) !== '' && trim((string)($user['totp_at'] ?? '')) !== '';
}

function totp_user_enable(int $userId, string $secret): void
{
    db_query('UPDATE users SET totp_secret = ?, totp_at = NOW() WHERE id = ?', [$secret, $userId]);
    log_action('totp_enabled', 'user', $userId, 'Two-factor authentication switched on');
    unset($GLOBALS['FD_CURRENT_USER']);
}

function totp_user_disable(int $userId): void
{
    db_query('UPDATE users SET totp_secret = NULL, totp_at = NULL WHERE id = ?', [$userId]);
    log_action('totp_disabled', 'user', $userId, 'Two-factor authentication switched off');
    unset($GLOBALS['FD_CURRENT_USER']);
}

/* ------------------------------------------------------------------ *
 * The account of the operator (back office)                           *
 * ------------------------------------------------------------------ */

function totp_admin_on(array $admin): bool
{
    return trim((string)($admin['totp_secret'] ?? '')) !== '' && trim((string)($admin['totp_at'] ?? '')) !== '';
}

function totp_admin_enable(int $adminId, string $secret): void
{
    db_query('UPDATE admins SET totp_secret = ?, totp_at = NOW() WHERE id = ?', [$secret, $adminId]);
    log_action('totp_enabled', 'admin', $adminId, 'Two-factor authentication switched on');
}

function totp_admin_disable(int $adminId): void
{
    db_query('UPDATE admins SET totp_secret = NULL, totp_at = NULL WHERE id = ?', [$adminId]);
    log_action('totp_disabled', 'admin', $adminId, 'Two-factor authentication switched off');
}

/* ------------------------------------------------------------------ *
 * The pending step of a sign-in                                       *
 * ------------------------------------------------------------------ */

/** After the password was accepted: remember who is half way in and ask for the code. */
function totp_pending_start(string $kind, int $id): void
{
    fd_start_session();
    $_SESSION['fd_totp_kind'] = $kind;
    $_SESSION['fd_totp_id']   = $id;
    $_SESSION['fd_totp_at']   = time();
}

function totp_pending_kind(): string
{
    fd_start_session();
    $kind = (string)($_SESSION['fd_totp_kind'] ?? '');
    $at   = (int)($_SESSION['fd_totp_at'] ?? 0);

    return ($at > 0 && (time() - $at) < 600) ? $kind : '';
}

function totp_pending_id(): int
{
    fd_start_session();

    return (int)($_SESSION['fd_totp_id'] ?? 0);
}

function totp_pending_clear(): void
{
    fd_start_session();
    unset($_SESSION['fd_totp_kind'], $_SESSION['fd_totp_id'], $_SESSION['fd_totp_at']);
}

/** True once this session has already given the code of that account. */
function totp_session_passed(string $kind, int $id): bool
{
    fd_start_session();

    return (int)($_SESSION['fd_totp_done_' . $kind] ?? 0) === $id;
}

/** The account that is waiting for its code, or null. */
function totp_pending_user(): ?array
{
    if (totp_pending_kind() !== 'user' || !totp_columns_ready()) {
        return null;
    }

    return db_one('SELECT * FROM users WHERE id = ?', [totp_pending_id()]);
}

function totp_pending_admin(): ?array
{
    if (totp_pending_kind() !== 'admin' || !totp_columns_ready()) {
        return null;
    }

    return db_one('SELECT * FROM admins WHERE id = ?', [totp_pending_id()]);
}

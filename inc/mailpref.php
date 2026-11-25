<?php
declare(strict_types=1);

/**
 * freedns.my - mail preferences.
 *
 * An account says which notifications it does not want. Everything is on unless the account switches it
 * off, so the default behaviour never changes; the few messages that are needed to get back into the
 * account (a password reset) ignore the preferences on purpose - see mail_pref_allows().
 *
 * A notification about something the account itself did (a confirmation of its own action) is always sent,
 * because it is the record of that action, not a notification about somebody else.
 */

function mailpref_column_ready(): bool
{
    if (isset($GLOBALS['FD_MAILOFF'])) {
        return (bool)$GLOBALS['FD_MAILOFF'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW COLUMNS FROM users LIKE 'mail_off'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_MAILOFF'] = $ready;

    return $ready;
}

/** The kinds an account can switch off. */
function mail_kinds(): array
{
    return ['chat', 'transfer', 'market', 'shop', 'domain', 'account'];
}

function mail_kind_label(string $kind): string
{
    return t('notify.kind_' . $kind);
}

/** The kinds this account switched off, as a list. */
function mail_off_of(?array $user): array
{
    if ($user === null || !mailpref_column_ready()) {
        return [];
    }

    $out = [];
    foreach (explode(',', (string)($user['mail_off'] ?? '')) as $kind) {
        $kind = strtolower(trim($kind));
        if ($kind !== '' && in_array($kind, mail_kinds(), true)) {
            $out[] = $kind;
        }
    }

    return $out;
}

/** Stores the choice of an account; an empty list means "send everything". */
function mail_off_set(int $userId, array $off): void
{
    if (!mailpref_column_ready()) {
        return;
    }

    $clean = [];
    foreach ($off as $kind) {
        $kind = strtolower(trim((string)$kind));
        if (in_array($kind, mail_kinds(), true) && !in_array($kind, $clean, true)) {
            $clean[] = $kind;
        }
    }

    db_query('UPDATE users SET mail_off = ? WHERE id = ?',
        [$clean === [] ? null : implode(',', $clean), $userId]);
    log_action('mail_prefs', 'user', $userId,
        'notifications switched off: ' . ($clean === [] ? 'none' : implode(', ', $clean)));
    unset($GLOBALS['FD_CURRENT_USER']);
}

/**
 * May this address be mailed about that kind of event?
 *
 * Unknown addresses (an applicant without an account, a visitor who left a message) always receive the
 * message: they have no preference page to change anything.
 */
function mail_pref_allows(string $email, string $kind): bool
{
    $kind = strtolower(trim($kind));
    if ($kind === '' || $kind === 'account') {
        // Password resets and address confirmations are always sent: they are how somebody gets back in.
        return true;
    }
    if (!mailpref_column_ready()) {
        return true;
    }

    $email = strtolower(trim($email));
    if ($email === '') {
        return true;
    }

    try {
        $row = db_one('SELECT mail_off FROM users WHERE LOWER(email) = ? LIMIT 1', [$email]);
    } catch (Throwable $e) {
        return true;
    }
    if ($row === null) {
        return true;
    }

    return !in_array($kind, mail_off_of($row), true);
}

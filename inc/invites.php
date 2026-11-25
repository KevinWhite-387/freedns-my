<?php
declare(strict_types=1);

/**
 * freedns.my - invite codes, the door in front of registration.
 *
 * Registration is by invitation only: an operator creates a code (normally after a request in the GitHub
 * issue tracker), mails it to that person, and the code is spent when the account is created. The codes
 * here are separate from the personal referral codes in inc/shop.php on purpose - those count who brought
 * whom, while these decide whether an account may exist at all.
 */

/** Whether the gate is switched on (setting "invite_required", on by default). */
function invite_required(): bool
{
    return function_exists('setting_bool') ? setting_bool('invite_required', true) : true;
}

/** True when the table and the user column exist. */
function invites_ready(): bool
{
    return db_one("SHOW TABLES LIKE 'invites'") !== null
        && db_one("SHOW COLUMNS FROM users LIKE 'invite_used'") !== null;
}

/** A readable code: FD-XXXX-XXXX without characters that get mistyped. */
function invite_new_code(): string
{
    $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $make = static function (int $len) use ($alphabet): string {
        $out = '';
        for ($i = 0; $i < $len; $i++) {
            $out .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $out;
    };

    return 'FD-' . $make(4) . '-' . $make(4);
}

/** One row by code, or null. */
function invite_by_code(string $code): ?array
{
    $code = strtoupper(trim($code));
    if ($code === '' || !invites_ready()) {
        return null;
    }

    return db_one('SELECT * FROM invites WHERE code = ?', [$code]);
}

/**
 * Can this code be used right now?
 *
 * @return array{ok:bool,error:string,row:?array}
 */
function invite_check(string $code): array
{
    if (!invite_required()) {
        return ['ok' => true, 'error' => '', 'row' => null];
    }
    $code = trim($code);
    if ($code === '') {
        return ['ok' => false, 'error' => 'val.invite_required', 'row' => null];
    }
    if (!invites_ready()) {
        return ['ok' => false, 'error' => 'val.invite_not_ready', 'row' => null];
    }
    $row = invite_by_code($code);
    if ($row === null) {
        return ['ok' => false, 'error' => 'val.invite_unknown', 'row' => null];
    }
    if ((int)$row['disabled'] === 1) {
        return ['ok' => false, 'error' => 'val.invite_disabled', 'row' => $row];
    }
    if ((int)$row['used_count'] >= (int)$row['max_uses']) {
        return ['ok' => false, 'error' => 'val.invite_spent', 'row' => $row];
    }
    $expires = trim((string)($row['expires_at'] ?? ''));
    if ($expires !== '' && strtotime($expires) !== false && strtotime($expires) < time()) {
        return ['ok' => false, 'error' => 'val.invite_expired', 'row' => $row];
    }

    return ['ok' => true, 'error' => '', 'row' => $row];
}

/** Spends one use. Returns false when somebody else took the last one in the meantime. */
function invite_consume(int $id): bool
{
    if ($id <= 0 || !invites_ready()) {
        return false;
    }
    db_query(
        'UPDATE invites SET used_count = used_count + 1, last_used_at = NOW()
          WHERE id = ? AND used_count < max_uses',
        [$id]
    );

    return true;
}

/** Creates $count fresh codes; returns them so the caller can print/copy them. */
function invite_create(string $note, int $maxUses, int $count, string $createdBy = ''): array
{
    if (!invites_ready()) {
        return [];
    }
    $count   = max(1, min(200, $count));
    $maxUses = max(1, min(100, $maxUses));
    $note    = mb_substr(trim($note), 0, 180);
    $out     = [];

    for ($i = 0; $i < $count; $i++) {
        $code = invite_new_code();
        for ($try = 0; $try < 5; $try++) {
            if (invite_by_code($code) === null) {
                break;
            }
            $code = invite_new_code();
        }
        try {
            db_query(
                'INSERT INTO invites (code, note, max_uses, used_count, disabled, created_by, created_at)
                 VALUES (?, ?, ?, 0, 0, ?, NOW())',
                [$code, $note, $maxUses, $createdBy]
            );
            $out[] = $code;
        } catch (Throwable $e) {
            // a collision is the only realistic failure; skip that one code and carry on
        }
    }

    return $out;
}
<?php
declare(strict_types=1);

/**
 * freedns.my - the mail vault: the archive of messages and the passwords that guard it.
 *
 * What it does
 *   * stores a message encrypted (AES-256-GCM with the site key, the same one the server bindings use);
 *   * opens nothing without the OPERATOR MASTER PASSWORD, and a single message can carry its own password
 *     on top - both are stored as hashes only, so a copy of the database does not open anything;
 *   * losing a password does not hand out a new one: the reset link is mailed to postmaster@freedns.my
 *     ONLY, while the second address (3008@live.cn by default) gets a notice WITHOUT the link. Anyone who
 *     steals an admin session still cannot reset a password, because they cannot read that mailbox;
 *   * keeps the result of the content check next to the message, so "dangerous" messages can be held for a
 *     human instead of being forwarded.
 *
 * It is deliberately usable before the mail actually flows through us: the back office can create a test
 * entry and the whole password path can be exercised with it.
 */

function mailvault_table_ready(): bool
{
    if (isset($GLOBALS['FD_VAULT_TABLE'])) {
        return (bool)$GLOBALS['FD_VAULT_TABLE'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'mail\\_archive'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_VAULT_TABLE'] = $ready;

    return $ready;
}

function mailvault_postmaster(): string
{
    $address = trim((string)setting('mail_vault_postmaster', 'postmaster@freedns.my'));

    return $address === '' ? 'postmaster@freedns.my' : $address;
}

function mailvault_notify_address(): string
{
    return trim((string)setting('mail_vault_notify', '3008@live.cn'));
}

/* ------------------------------------------------------------------ *
 * The master password                                                 *
 * ------------------------------------------------------------------ */

/** Has a master password been set at all? Without one the vault stays shut. */
function mailvault_master_ready(): bool
{
    return trim((string)setting('mail_vault_hash', '')) !== '';
}

function mailvault_master_set(string $password): bool
{
    if (strlen($password) < 10) {
        return false;
    }
    set_setting('mail_vault_hash', password_hash($password, PASSWORD_DEFAULT));
    log_action('vault_master_set', 'settings', null, 'the master password of the vault was set');

    return true;
}

function mailvault_master_ok(string $password): bool
{
    $hash = (string)setting('mail_vault_hash', '');
    if ($hash === '' || $password === '') {
        return false;
    }

    return password_verify($password, $hash);
}

/* ------------------------------------------------------------------ *
 * Storing and reading a message                                       *
 * ------------------------------------------------------------------ */

/**
 * Stores one message. The body is encrypted with the row id as context, so a ciphertext cannot be moved to
 * another row and still open.
 *
 * @param array{user_id?: int, direction?: string, mail_from?: string, mail_to?: string, subject?: string,
 *              body?: string} $message
 * @return int the new id, or 0 when it could not be stored
 */
function mailvault_store(array $message): int
{
    if (!mailvault_table_ready() || !rainyun_crypto_ready()) {
        return 0;
    }

    $body = (string)($message['body'] ?? '');
    db_query(
        'INSERT INTO mail_archive (user_id, direction, mail_from, mail_to, subject, body_cipher, body_size,
            scan_state, review_state, created_at)
         VALUES (?, ?, ?, ?, ?, NULL, ?, \'unscanned\', \'none\', NOW())',
        [
            isset($message['user_id']) && (int)$message['user_id'] > 0 ? (int)$message['user_id'] : null,
            (string)($message['direction'] ?? 'in') === 'out' ? 'out' : 'in',
            substr((string)($message['mail_from'] ?? ''), 0, 190),
            substr((string)($message['mail_to'] ?? ''), 0, 190),
            substr((string)($message['subject'] ?? ''), 0, 255),
            strlen($body),
        ]
    );
    $id = (int)db()->lastInsertId();

    if ($body !== '') {
        db_query('UPDATE mail_archive SET body_cipher = ? WHERE id = ?',
            [rainyun_encrypt($body, 'mail:' . $id), $id]);
    }

    $scan = mailvault_scan($body, (string)($message['subject'] ?? ''));
    db_query('UPDATE mail_archive SET scan_state = ?, review_state = ? WHERE id = ?',
        [$scan['state'], $scan['state'] === 'dangerous' ? 'queued' : 'none', $id]);

    log_action('vault_stored', 'mail', $id,
        'message archived (' . strlen($body) . ' byte(s), scan: ' . $scan['state'] . ')');

    return $id;
}

function mailvault_entry(int $id): ?array
{
    if (!mailvault_table_ready() || $id <= 0) {
        return null;
    }

    return db_one('SELECT * FROM mail_archive WHERE id = ?', [$id]);
}

function mailvault_list(int $limit = 100, string $review = ''): array
{
    if (!mailvault_table_ready()) {
        return [];
    }

    $limit = max(1, min(500, $limit));
    if (in_array($review, ['none', 'queued', 'released', 'blocked'], true)) {
        return db_all('SELECT * FROM mail_archive WHERE review_state = ? ORDER BY id DESC LIMIT ' . $limit,
            [$review]);
    }

    return db_all('SELECT * FROM mail_archive ORDER BY id DESC LIMIT ' . $limit);
}

/**
 * Opens one message: needs the master password, and that message's own password when it has one.
 *
 * @return array{ok: bool, body: string, error: string}
 */
function mailvault_open(array $entry, string $master, string $entryPassword, string $staff): array
{
    if (!mailvault_master_ready()) {
        return ['ok' => false, 'body' => '', 'error' => 'No master password has been set yet.'];
    }
    if (!mailvault_master_ok($master)) {
        log_action('vault_denied', 'mail', (int)$entry['id'], 'wrong master password');

        return ['ok' => false, 'body' => '', 'error' => 'The master password is not right.'];
    }

    $hash = (string)($entry['pass_hash'] ?? '');
    if ($hash !== '') {
        if ($entryPassword === '' || !password_verify($entryPassword, $hash)) {
            log_action('vault_denied', 'mail', (int)$entry['id'], 'wrong password of that message');

            return ['ok' => false, 'body' => '', 'error' => 'This message has its own password; it is needed as well.'];
        }
    }

    $body = rainyun_decrypt($entry['body_cipher'] ?? null, 'mail:' . (int)$entry['id']);
    if ($body === '' && (int)($entry['body_size'] ?? 0) > 0) {
        return ['ok' => false, 'body' => '', 'error' => 'The stored body cannot be decrypted with this site key.'];
    }

    db_query('UPDATE mail_archive SET opened_at = NOW(), opened_by = ? WHERE id = ?',
        [$staff, (int)$entry['id']]);
    log_action('vault_opened', 'mail', (int)$entry['id'], 'opened by ' . $staff);

    return ['ok' => true, 'body' => $body, 'error' => ''];
}

/** Gives one message its own password (or removes it with an empty string). */
function mailvault_set_entry_password(array $entry, string $password, string $hint, string $staff): array
{
    if ($password !== '' && strlen($password) < 6) {
        return ['ok' => false, 'error' => 'A message password needs at least six characters.'];
    }

    db_query('UPDATE mail_archive SET pass_hash = ?, pass_hint = ? WHERE id = ?',
        [$password === '' ? null : password_hash($password, PASSWORD_DEFAULT),
         $hint === '' ? null : substr($hint, 0, 64), (int)$entry['id']]);
    log_action('vault_pass_set', 'mail', (int)$entry['id'],
        $password === '' ? 'its own password was removed' : 'its own password was set');

    return ['ok' => true, 'error' => ''];
}

function mailvault_review(array $entry, string $state, string $note, string $staff): bool
{
    if (!in_array($state, ['none', 'queued', 'released', 'blocked'], true)) {
        return false;
    }
    db_query('UPDATE mail_archive SET review_state = ?, review_note = ? WHERE id = ?',
        [$state, $note === '' ? null : substr($note, 0, 255), (int)$entry['id']]);
    log_action('vault_review', 'mail', (int)$entry['id'], $state . ($note === '' ? '' : ': ' . $note)
        . ' by ' . $staff);

    return true;
}

/* ------------------------------------------------------------------ *
 * The content check                                                  *
 * ------------------------------------------------------------------ */

/**
 * A rules-based check for content that must not be forwarded silently.
 *
 * It is deliberately simple and explainable ("why was this held?"): a list of words and patterns that
 * appear in threats, extortion and similar mail. AI can be plugged in later through mail_scan_mode = ai,
 * but that means sending the message to a third party - a privacy decision that has to be made on purpose.
 *
 * @return array{state: string, reasons: array<int, string>}
 */
function mailvault_scan(string $body, string $subject = ''): array
{
    $mode = strtolower(trim((string)setting('mail_scan_mode', 'rules')));
    if ($mode === 'off') {
        return ['state' => 'clean', 'reasons' => []];
    }

    $text = strtolower($subject . "\n" . $body);
    $patterns = [
        'threat'   => ['\bkill you\b', '\bwe will hurt\b', '\bhurt you\b', '\bbreak your legs\b',
                       '杀了你', '弄死你', '打断你的腿', '报复你', '找人收拾你'],
        'extortion' => ['\bransom\b', '\bblackmail\b', '\bextort', '\bpay .{0,20}or (?:we|i) will\b',
                        '勒索', '敲诈', '封口费', '不给钱就', '曝光你的'],
        'coercion' => ['\byou have no choice\b', '\bor else\b', '\bdo it now or\b', '\byou must pay\b',
                       '你必须付', '否则后果自负', '不然就'],
        'danger'   => ['\bbomb\b', '\bexplosive\b', '\bpoison\b', '\bterror', '\bkill myself\b',
                       '\bsuicide\b', '炸弹', '爆炸物', '投毒', '自杀', '同归于尽'],
        'illegal'  => ['\bchild porn\b', '\bdrugs? for sale\b', '\bfake passport\b', '\bhitman\b',
                       '毒品交易', '假护照', '杀手', '枪支买卖'],
    ];

    $reasons = [];
    foreach ($patterns as $label => $list) {
        foreach ($list as $pattern) {
            $regex = str_starts_with($pattern, '\\b') || preg_match('/^[\x{4e00}-\x{9fff}]/u', $pattern) === 1
                ? '/' . $pattern . '/u'
                : '/' . preg_quote($pattern, '/') . '/u';
            if (@preg_match($regex, $text) === 1) {
                $reasons[] = $label;
                break;
            }
        }
    }

    $reasons = array_values(array_unique($reasons));

    return ['state' => $reasons === [] ? 'clean' : 'dangerous', 'reasons' => $reasons];
}

/* ------------------------------------------------------------------ *
 * "I lost a password"                                                 *
 * ------------------------------------------------------------------ */

/**
 * Starts a reset: the link goes to postmaster@freedns.my, the second address only hears that it happened.
 *
 * @return array{ok: bool, error: string, token: string}
 */
function mailvault_reset_request(string $scope, int $refId, string $staff): array
{
    if (!mailvault_table_ready()) {
        return ['ok' => false, 'error' => 'The vault tables are missing.', 'token' => ''];
    }
    if (!in_array($scope, ['master', 'entry'], true)) {
        return ['ok' => false, 'error' => 'Unknown scope.', 'token' => ''];
    }
    if ($scope === 'entry' && mailvault_entry($refId) === null) {
        return ['ok' => false, 'error' => 'That message does not exist.', 'token' => ''];
    }

    $token = bin2hex(random_bytes(20));
    db_query(
        'INSERT INTO mail_vault_resets (scope, ref_id, token_hash, requested_by, expires_at, created_at)
         VALUES (?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 2 HOUR), NOW())',
        [$scope, $scope === 'entry' ? $refId : null, hash('sha256', $token), $staff]
    );

    // What the link can do: set a new master password, or a new password for that one message.
    $link = rtrim(app_base_url(), '/') . '/admin/vault-reset.php?token=' . rawurlencode($token)
        . ($scope === 'entry' ? '&entry=' . $refId : '');

    $previous = lang_switch('en');
    $what = $scope === 'master' ? 'the master password of the mail vault' : 'the password of message #' . $refId;
    $toPostmaster = [
        'A password reset was requested for ' . $what . '.',
        '',
        'Requested by: ' . $staff . ' at ' . date('Y-m-d H:i:s'),
        'The link below is valid for two hours and can be used once:',
        '',
        $link,
        '',
        'If you did not ask for this, ignore it: nothing changes without the link.',
    ];
    $toNotify = [
        'A password reset was requested for ' . $what . '.',
        '',
        'Requested by: ' . $staff . ' at ' . date('Y-m-d H:i:s'),
        '',
        'As agreed, the reset LINK was NOT sent to this address - it went to ' . mailvault_postmaster() . ' only.',
        'If you did not expect this, check the back office: somebody may be trying something.',
    ];
    send_mail(mailvault_postmaster(), 'Mail vault: reset link (' . $scope . ')', implode("\n", $toPostmaster));
    $notify = mailvault_notify_address();
    if ($notify !== '') {
        send_mail($notify, 'Mail vault: a reset was requested', implode("\n", $toNotify));
    }
    lang_switch($previous);

    log_action('vault_reset_requested', 'mail', $scope === 'entry' ? $refId : null,
        $scope . ' reset requested by ' . $staff . ' (link sent to ' . mailvault_postmaster() . ')');

    return ['ok' => true, 'error' => '', 'token' => $token];
}

/** Uses a reset token: sets a new master password, or a new password for one message. */
function mailvault_reset_apply(string $token, string $newPassword): array
{
    if (!mailvault_table_ready() || strlen($newPassword) < 6) {
        return ['ok' => false, 'error' => 'That token or password is not acceptable.'];
    }

    $row = db_one(
        'SELECT * FROM mail_vault_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1',
        [hash('sha256', $token)]
    );
    if ($row === null) {
        log_action('vault_reset_denied', 'settings', null, 'an invalid or expired reset token was used');

        return ['ok' => false, 'error' => 'That link is no longer valid.'];
    }

    if ((string)$row['scope'] === 'master') {
        if (!mailvault_master_set($newPassword)) {
            return ['ok' => false, 'error' => 'The master password needs at least ten characters.'];
        }
    } else {
        $entry = mailvault_entry((int)$row['ref_id']);
        if ($entry === null) {
            return ['ok' => false, 'error' => 'The message of that link is gone.'];
        }
        db_query('UPDATE mail_archive SET pass_hash = ? WHERE id = ?',
            [password_hash($newPassword, PASSWORD_DEFAULT), (int)$entry['id']]);
        log_action('vault_pass_reset', 'mail', (int)$entry['id'], 'its own password was reset with a link');
    }

    db_query('UPDATE mail_vault_resets SET used_at = NOW() WHERE id = ?', [(int)$row['id']]);

    return ['ok' => true, 'error' => ''];
}

/* ------------------------------------------------------------------ *
 * How long a message may be looked at, downloaded, or kept at all      *
 * ------------------------------------------------------------------ */

/**
 * The three stages the operator asked for:
 *
 *   up to mail_vault_view_days (90)          - it can be opened in the back office
 *   up to mail_vault_download_days (180)     - it can no longer be read on screen, but may be downloaded
 *                                              as a file (so it is still recoverable for a while)
 *   after that                               - it is deleted for good
 *
 * The rule is enforced here rather than in the pages, so every way of reaching a message obeys it.
 */
function mailvault_view_days(): int
{
    return max(1, min(3650, (int)setting('mail_vault_view_days', '90')));
}

function mailvault_download_days(): int
{
    return max(mailvault_view_days(), min(3650, (int)setting('mail_vault_download_days', '180')));
}

function mailvault_age_days(array $entry): int
{
    $stamp = strtotime((string)($entry['created_at'] ?? ''));
    if ($stamp === false) {
        return 0;
    }

    return (int)floor((time() - $stamp) / 86400);
}

/** May it be shown on screen? (also false when it is old enough to be read-only) */
function mailvault_can_view(array $entry): bool
{
    return mailvault_age_days($entry) <= mailvault_view_days();
}

/** May it still be downloaded before it disappears? */
function mailvault_can_download(array $entry): bool
{
    return mailvault_age_days($entry) <= mailvault_download_days();
}

/** How the state of one message is described to the operator. */
function mailvault_stage(array $entry): string
{
    if (mailvault_can_view($entry)) {
        return 'viewable';
    }

    return mailvault_can_download($entry) ? 'download-only' : 'due-for-deletion';
}

/**
 * Deletes what passed the last stage. Returns how many were removed.
 *
 * @return array{deleted: int, days: int}
 */
function mailvault_prune(int $limit = 500): array
{
    $out = ['deleted' => 0, 'days' => mailvault_download_days()];
    if (!mailvault_table_ready()) {
        return $out;
    }

    $rows = db_all(
        'SELECT id FROM mail_archive WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) LIMIT ' . max(1, $limit),
        [$out['days']]
    );
    foreach ($rows as $row) {
        db_query('DELETE FROM mail_archive WHERE id = ?', [(int)$row['id']]);
        $out['deleted']++;
    }
    if ($out['deleted'] > 0) {
        log_action('vault_pruned', 'settings', null,
            $out['deleted'] . ' archived message(s) older than ' . $out['days'] . ' day(s) were deleted');
    }

    return $out;
}

/** A plain-text copy of one message, for the download that is allowed in the middle stage. */
function mailvault_export(array $entry, string $body): string
{
    $lines = [
        'freedns.my mail vault - message #' . (int)$entry['id'],
        'archived: ' . (string)$entry['created_at'],
        'from:     ' . (string)($entry['mail_from'] ?? ''),
        'to:       ' . (string)($entry['mail_to'] ?? ''),
        'subject:  ' . (string)($entry['subject'] ?? ''),
        'scan:     ' . (string)($entry['scan_state'] ?? ''),
        'review:   ' . (string)($entry['review_state'] ?? ''),
        str_repeat('-', 60),
        '',
        $body,
    ];

    return implode("\n", $lines);
}

<?php
declare(strict_types=1);

/**
 * freedns.my - transfer requests (tickets) for names published through DNSHE.
 *
 * The holder applies in the console, the owner answers in the back office and, when approving, pastes the
 * transfer code obtained manually in the DNSHE panel; the registry relays that code to the recipient by
 * mail and starts a 72 hour clock. The recipient redeems it at DNSHE and confirms here (with the account
 * whose e-mail the ticket names), which moves the registry's ownership. While a ticket is open or
 * approved the records of that name are locked.
 */

const FD_TRANSFER_HOURS = 72;

function transfer_table_ready(): bool
{
    if (isset($GLOBALS['FD_TRANSFER_TABLE'])) {
        return (bool)$GLOBALS['FD_TRANSFER_TABLE'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'transfer\\_requests'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_TRANSFER_TABLE'] = $ready;

    return $ready;
}

function transfer_enabled(): bool
{
    return setting_bool('transfer_enabled', true) && transfer_table_ready();
}

/** DNSHE does not allow a registered subdomain to be deleted, so a transfer is the only way out. */
function transfer_suffix_ok(string $zoneName): bool
{
    return publish_provider($zoneName) === 'dnshe';
}

function transfer_by_id(int $id): ?array
{
    if (!transfer_table_ready() || $id <= 0) {
        return null;
    }

    return db_one('SELECT * FROM transfer_requests WHERE id = ?', [$id]);
}

function transfer_messages(int $requestId): array
{
    if (!transfer_table_ready()) {
        return [];
    }

    return db_all('SELECT * FROM transfer_messages WHERE request_id = ? ORDER BY id ASC', [$requestId]);
}

/** The ticket that currently holds a name (open or approved), or null. */
function transfer_active_for_domain(int $domainId): ?array
{
    if (!transfer_table_ready()) {
        return null;
    }

    return db_one(
        "SELECT * FROM transfer_requests WHERE domain_id = ? AND status IN ('open', 'approved')
         ORDER BY id DESC LIMIT 1",
        [$domainId]
    );
}

/** True while the records of that name may not be changed. */
function transfer_domain_locked(int $domainId): bool
{
    return transfer_active_for_domain($domainId) !== null;
}

function transfer_idle_message(): string
{
    return t('transfer.locked');
}

function transfer_seconds_left(?array $request): int
{
    if ($request === null || (string)$request['status'] !== 'approved' || empty($request['expires_at'])) {
        return 0;
    }
    $until = strtotime((string)$request['expires_at']);

    return ($until === false || $until <= time()) ? 0 : $until - time();
}

function transfer_status_text(string $status): string
{
    return t('transfer.status_' . $status);
}

/** Expires the tickets nobody accepted in time. */
function transfer_sweep(): int
{
    if (!transfer_table_ready()) {
        return 0;
    }

    $rows = db_all(
        "SELECT id, domain_id FROM transfer_requests WHERE status = 'approved' AND expires_at IS NOT NULL
         AND expires_at < NOW()"
    );
    foreach ($rows as $row) {
        db_query("UPDATE transfer_requests SET status = 'expired', updated_at = NOW() WHERE id = ?",
            [(int)$row['id']]);
        log_action('transfer_expired', 'domain', (int)$row['domain_id'], 'Ticket ' . (int)$row['id'] . ' expired');
    }

    return count($rows);
}

/** Adds one message to the thread and returns its id. */
function transfer_message_add(int $requestId, string $author, string $name, string $body, bool $mailed): int
{
    db_query(
        'INSERT INTO transfer_messages (request_id, author, author_name, body, mailed, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())',
        [$requestId, $author, $name, $body, $mailed ? 1 : 0]
    );

    return (int)db()->lastInsertId();
}

/** Mails one line of the thread. $to is the address that should read it. */
function transfer_mail(string $to, string $subjectKey, array $args, string $body, string $language): bool
{
    // The account may have switched transfer notices off (see inc/mailpref.php). Everything about a
    // transfer goes through here, so one check covers the whole feature.
    if (!mail_pref_allows($to, 'transfer')) {
        log_action('mail_skipped', 'transfer', null, 'transfer notice to ' . $to . ' not wanted');

        return true;
    }

    $previous = lang_switch($language);
    $subject  = t($subjectKey, ...$args);
    $lines    = array_merge(
        [t('mail.dear', $to), ''],
        [$body],
        ['', url('account/transfers.php'), '', t('mail.transfer_footer'), '', setting('site_title', 'freedns.my')]
    );
    lang_switch($previous);

    return send_mail($to, $subject, implode("\n", $lines));
}

/** The holder applies for a transfer. */
function transfer_create(array $domain, array $user, string $recipientName, string $recipientEmail, string $reason): array
{
    if (!transfer_enabled()) {
        return ['ok' => false, 'error' => t('val.transfer_off')];
    }
    if (!transfer_suffix_ok((string)$domain['zone'])) {
        return ['ok' => false, 'error' => t('val.transfer_suffix')];
    }
    if ((string)$domain['status'] !== 'active') {
        return ['ok' => false, 'error' => t('val.transfer_not_active')];
    }
    if (transfer_active_for_domain((int)$domain['id']) !== null) {
        return ['ok' => false, 'error' => t('val.transfer_pending')];
    }
    if (!valid_email_addr($recipientEmail)) {
        return ['ok' => false, 'error' => t('val.transfer_recipient')];
    }
    if (strcasecmp($recipientEmail, (string)$user['email']) === 0) {
        return ['ok' => false, 'error' => t('val.transfer_self')];
    }
    if (trim($recipientName) === '') {
        return ['ok' => false, 'error' => t('val.transfer_recipient')];
    }

    db_query(
        'INSERT INTO transfer_requests (domain_id, requester_email, requester_user, recipient_name,
            recipient_email, reason, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, \'open\', NOW(), NOW())',
        [
            (int)$domain['id'], (string)$user['email'], (int)$user['id'],
            $recipientName, $recipientEmail, $reason,
        ]
    );
    $id = (int)db()->lastInsertId();
    log_action('transfer_opened', 'domain', (int)$domain['id'],
        'Ticket ' . $id . ' for ' . (string)$domain['name'] . ' to ' . $recipientEmail);

    $admin = trim((string)setting('notify_admin_email', ''));
    if ($admin !== '') {
        transfer_mail(
            $admin,
            'mail.transfer_new_subject',
            [(string)$domain['name']],
            t('mail.transfer_new_body', (string)$domain['name'], (string)$user['email'], $recipientName, $recipientEmail),
            'en'
        );
    }

    return ['ok' => true, 'error' => '', 'id' => $id];
}

/** The owner approves: the code he took from DNSHE is relayed to the recipient and the clock starts. */
function transfer_approve(int $id, string $code, string $note, string $staff): array
{
    $request = transfer_by_id($id);
    if ($request === null) {
        return ['ok' => false, 'error' => 'That ticket does not exist.'];
    }
    if ((string)$request['status'] !== 'open') {
        return ['ok' => false, 'error' => 'Only an open ticket can be approved.'];
    }
    if (trim($code) === '') {
        return ['ok' => false, 'error' => 'Paste the transfer code you obtained in the DNSHE panel first.'];
    }

    $domain = db_one('SELECT * FROM domains WHERE id = ?', [(int)$request['domain_id']]);
    $until  = date('Y-m-d H:i:s', time() + FD_TRANSFER_HOURS * 3600);

    db_query(
        "UPDATE transfer_requests SET status = 'approved', transfer_code = ?, issued_at = NOW(),
            expires_at = ?, decided_by = ?, updated_at = NOW() WHERE id = ?",
        [$code, $until, $staff, $id]
    );
    if ($note !== '') {
        transfer_message_add($id, 'staff', $staff, $note, false);
    }
    log_action('transfer_approved', 'domain', (int)$request['domain_id'],
        'Ticket ' . $id . ' approved; code issued, expires ' . $until);

    $language = (string)($domain['language'] ?? 'en');
    if (mail_pref_allows((string)$request['recipient_email'], 'transfer')) {
        transfer_mail(
            (string)$request['recipient_email'],
            'mail.transfer_code_subject',
            [(string)($domain['name'] ?? '')],
            t(
                'mail.transfer_code_body',
                (string)($domain['name'] ?? ''),
                $code,
                (string)$until,
                (string)FD_TRANSFER_HOURS,
                (string)$request['requester_email']
            ),
            $language
        );
    }
    transfer_mail(
        (string)$request['requester_email'],
        'mail.transfer_approved_subject',
        [(string)($domain['name'] ?? '')],
        t('mail.transfer_approved_body', (string)($domain['name'] ?? ''), (string)$request['recipient_email'],
            (string)$until),
        $language
    );

    return ['ok' => true, 'error' => ''];
}

function transfer_reject(int $id, string $note, string $staff): array
{
    $request = transfer_by_id($id);
    if ($request === null) {
        return ['ok' => false, 'error' => 'That ticket does not exist.'];
    }
    if (!in_array((string)$request['status'], ['open', 'approved'], true)) {
        return ['ok' => false, 'error' => 'That ticket is already closed.'];
    }

    db_query(
        "UPDATE transfer_requests SET status = 'rejected', transfer_code = NULL, decided_by = ?,
            updated_at = NOW() WHERE id = ?",
        [$staff . ($note === '' ? '' : ': ' . $note), $id]
    );
    if ($note !== '') {
        transfer_message_add($id, 'staff', $staff, $note, false);
    }
    log_action('transfer_rejected', 'domain', (int)$request['domain_id'], 'Ticket ' . $id . ' rejected');

    $domain   = db_one('SELECT * FROM domains WHERE id = ?', [(int)$request['domain_id']]);
    $language = (string)($domain['language'] ?? 'en');
    transfer_mail(
        (string)$request['requester_email'],
        'mail.transfer_rejected_subject',
        [(string)($domain['name'] ?? '')],
        t('mail.transfer_rejected_body', (string)($domain['name'] ?? ''), $note),
        $language
    );

    return ['ok' => true, 'error' => ''];
}

/**
 * Moves the ownership to the recipient. The recipient has to be signed in with the account whose e-mail
 * the ticket names, which is exactly the address the transfer code was sent to.
 */
function transfer_complete(int $id, string $staff, bool $byRecipient): array
{
    $request = transfer_by_id($id);
    if ($request === null) {
        return ['ok' => false, 'error' => 'That ticket does not exist.'];
    }
    if ((string)$request['status'] !== 'approved') {
        return ['ok' => false, 'error' => 'That ticket is not waiting for an acceptance.'];
    }

    $domain = db_one('SELECT * FROM domains WHERE id = ?', [(int)$request['domain_id']]);
    if ($domain === null) {
        return ['ok' => false, 'error' => 'The name of that ticket no longer exists.'];
    }

    db_query(
        'UPDATE domains SET registrant_name = ?, registrant_email = ?, updated_at = NOW() WHERE id = ?',
        [(string)$request['recipient_name'], (string)$request['recipient_email'], (int)$domain['id']]
    );
    db_query(
        "UPDATE transfer_requests SET status = 'accepted', decided_by = ?, updated_at = NOW() WHERE id = ?",
        [$staff, $id]
    );
    log_action('transfer_completed', 'domain', (int)$domain['id'],
        'Ticket ' . $id . ': ' . (string)$domain['name'] . ' moved to ' . (string)$request['recipient_email']
        . ($byRecipient ? ' (confirmed by the recipient)' : ' (confirmed by the operator)'));

    $language = (string)($domain['language'] ?? 'en');
    transfer_mail(
        (string)$request['recipient_email'],
        'mail.transfer_done_subject',
        [(string)$domain['name']],
        t('mail.transfer_done_body', (string)$domain['name'], (string)$request['requester_email']),
        $language
    );
    transfer_mail(
        (string)$request['requester_email'],
        'mail.transfer_done_subject',
        [(string)$domain['name']],
        t('mail.transfer_done_holder_body', (string)$domain['name'], (string)$request['recipient_email']),
        $language
    );

    return ['ok' => true, 'error' => ''];
}

/** The tickets of one address: as the holder and as the recipient. */
function transfer_list_for_email(string $email): array
{
    if (!transfer_table_ready()) {
        return [];
    }

    return db_all(
        'SELECT r.*, d.name, d.zone, d.status AS domain_status FROM transfer_requests r
         JOIN domains d ON d.id = r.domain_id
         WHERE r.requester_email = ? OR r.recipient_email = ?
         ORDER BY (r.status IN (\'open\', \'approved\')) DESC, r.id DESC LIMIT 50',
        [$email, $email]
    );
}

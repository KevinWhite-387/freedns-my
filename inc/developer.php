<?php
declare(strict_types=1);

/**
 * freedns.my - developer accounts and the requests that unlock the gated record types.
 *
 * Two kinds of request live in the same table (developer_requests), told apart by record_type:
 *
 *   record_type = '*'      asking for the developer status itself; approving it sets an unlimited
 *                          right to use every gated type, no per-record approval needed.
 *   record_type = 'TLSA'   (anything else) asking to write one specific record; approving it
 *                          writes that record straight away.
 *
 * Only the open types (A AAAA TXT MX CNAME SRV by default) skip this whole page. Everything else
 * is decided here, first by the provider's capability (see inc/record-types.php) and then by this
 * queue.
 */

function developer_requests_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'developer_requests'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

/** Is a developer-status request of this account still waiting? */
function developer_status_pending(int $userId): bool
{
    if (!developer_requests_ready()) {
        return false;
    }
    try {
        return db_one(
            "SELECT id FROM developer_requests WHERE user_id = ? AND record_type = '*' AND status = 'pending' LIMIT 1",
            [$userId]
        ) !== null;
    } catch (Throwable $e) {
        return false;
    }
}

/** Ask for the developer status (one open request at a time). */
function developer_apply(int $userId, string $reason): array
{
    if (!developer_requests_ready()) {
        return ['ok' => false, 'error' => 'The developer_requests table is missing: import upgrade-record-types.sql.'];
    }
    $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        return ['ok' => false, 'error' => 'Unknown user.'];
    }
    if (user_is_developer($user)) {
        return ['ok' => false, 'error' => 'This account is already a developer.'];
    }
    if (developer_status_pending($userId)) {
        return ['ok' => false, 'error' => 'A request is already waiting for a decision.'];
    }

    db_query(
        "INSERT INTO developer_requests (user_id, username, email, record_type, reason, status, created_at)
         VALUES (?, ?, ?, '*', ?, 'pending', NOW())",
        [$userId, (string)$user['username'], (string)$user['email'], $reason === '' ? null : $reason]
    );
    log_action('developer_requested', 'user', $userId, (string)$user['username']);

    return ['ok' => true, 'error' => '', 'id' => (int)db()->lastInsertId()];
}

/** Ask to write one gated record on one of my names. */
function developer_request_record(int $userId, int $domainId, string $type, string $sub, string $value, string $reason): array
{
    if (!developer_requests_ready()) {
        return ['ok' => false, 'error' => 'The developer_requests table is missing.'];
    }
    $user = db_one('SELECT * FROM users WHERE id = ?', [$userId]);
    if ($user === null) {
        return ['ok' => false, 'error' => 'Unknown user.'];
    }
    if (!user_is_developer($user)) {
        return ['ok' => false, 'error' => 'Apply for the developer status first.'];
    }
    $domain = db_one('SELECT * FROM domains WHERE id = ?', [$domainId]);
    if ($domain === null || (string)$domain['registrant_email'] !== (string)$user['email']) {
        return ['ok' => false, 'error' => 'That name does not belong to this account.'];
    }

    $type = strtoupper(trim($type));
    if (record_type_is_open($type)) {
        return ['ok' => false, 'error' => $type . ' is an open type, no request needed.'];
    }
    $allowed = record_type_allowed($type, (string)$domain['zone'], $user);
    if (!$allowed['supported']) {
        return ['ok' => false, 'error' => $allowed['reason']];
    }

    db_query(
        "INSERT INTO developer_requests (user_id, username, email, domain_id, domain_name,
            record_type, record_sub, record_value, reason, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())",
        [
            $userId, (string)$user['username'], (string)$user['email'],
            $domainId, (string)$domain['name'],
            $type, $sub, $value, $reason === '' ? null : $reason,
        ]
    );
    log_action('developer_record_requested', 'domain', $domainId, $type . ' ' . $sub . ' ' . (string)$domain['name']);

    return ['ok' => true, 'error' => '', 'id' => (int)db()->lastInsertId()];
}

/** @return array<int,array> */
function developer_requests_of_user(int $userId, int $limit = 50): array
{
    if (!developer_requests_ready()) {
        return [];
    }
    try {
        return db_all('SELECT * FROM developer_requests WHERE user_id = ? ORDER BY id DESC LIMIT ' . max(1, $limit), [$userId]);
    } catch (Throwable $e) {
        return [];
    }
}

/** @return array<int,array> */
function developer_requests_queue(string $status = 'pending', int $limit = 100): array
{
    if (!developer_requests_ready()) {
        return [];
    }
    $status = in_array($status, ['pending', 'approved', 'rejected'], true) ? $status : 'pending';
    try {
        return db_all(
            'SELECT * FROM developer_requests WHERE status = ? ORDER BY id ASC LIMIT ' . max(1, $limit),
            [$status]
        );
    } catch (Throwable $e) {
        return [];
    }
}

function developer_pending_count(): int
{
    if (!developer_requests_ready()) {
        return 0;
    }
    try {
        return (int)db_value("SELECT COUNT(*) FROM developer_requests WHERE status = 'pending'", [], 0);
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Approve a request.
 *
 * A status request flips users.developer; a record request writes the record and publishes it.
 *
 * @return array{ok:bool,error:string}
 */
function developer_approve(int $requestId, string $admin, string $note = ''): array
{
    if (!developer_requests_ready()) {
        return ['ok' => false, 'error' => 'The developer_requests table is missing.'];
    }
    $req = db_one('SELECT * FROM developer_requests WHERE id = ?', [$requestId]);
    if ($req === null) {
        return ['ok' => false, 'error' => 'Unknown request.'];
    }
    if ((string)$req['status'] !== 'pending') {
        return ['ok' => false, 'error' => 'That request was already decided.'];
    }

    $userId = (int)$req['user_id'];

    if ((string)$req['record_type'] === '*') {
        db_query('UPDATE users SET developer = 1, developer_since = NOW() WHERE id = ?', [$userId]);
        db_query("UPDATE developer_requests SET status = 'approved', admin_note = ?, decided_by = ?, decided_at = NOW()
                  WHERE id = ?", [$note === '' ? null : $note, $admin, $requestId]);
        log_action('developer_granted', 'user', $userId, (string)$req['username'] . ' by ' . $admin);

        return ['ok' => true, 'error' => ''];
    }

    /* A single record. Validate it again, write it, and publish like any other change. */
    $domainId = (int)($req['domain_id'] ?? 0);
    $domain   = $domainId > 0 ? db_one('SELECT * FROM domains WHERE id = ?', [$domainId]) : null;
    if ($domain === null) {
        return ['ok' => false, 'error' => 'The name of this request no longer exists.'];
    }

    $type  = strtoupper((string)$req['record_type']);
    $sub   = normalize_record_sub((string)$req['record_sub'], (string)$domain['name']);
    $value = trim((string)$req['record_value']);

    if (!valid_record_type($type) || $value === '') {
        return ['ok' => false, 'error' => 'The stored request is not a valid record.'];
    }
    $check = record_type_allowed($type, (string)$domain['zone'], db_one('SELECT * FROM users WHERE id = ?', [$userId]));
    if (!$check['supported']) {
        return ['ok' => false, 'error' => $check['reason']];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_query(
            'INSERT IGNORE INTO records (domain_id, sub, type, value, ttl, position, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [$domainId, $sub, $type, $value, (int)($domain['ttl'] ?? 3600), 1]
        );
        db_query("UPDATE developer_requests SET status = 'approved', admin_note = ?, decided_by = ?, decided_at = NOW()
                  WHERE id = ?", [$note === '' ? null : $note, $admin, $requestId]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return ['ok' => false, 'error' => 'Could not write the record: ' . $e->getMessage()];
    }

    log_action('developer_record_approved', 'domain', $domainId, $type . ' ' . $sub . ' by ' . $admin);

    /* Publish, but never undo the approval when the provider refuses. */
    if (function_exists('publish_zone_ready') && publish_zone_ready((string)$domain['zone'])) {
        try {
            publish_sync_domain(
                $domainId,
                (string)$domain['name'],
                (string)$domain['zone'],
                publish_records_of(domain_records($domainId)),
                !empty($domain['proxied'])
            );
        } catch (Throwable $e) {
            log_action('publish_failed', 'domain', $domainId, (string)$domain['name'] . ': ' . $e->getMessage());
        }
    }

    return ['ok' => true, 'error' => ''];
}

/** @return array{ok:bool,error:string} */
function developer_reject(int $requestId, string $admin, string $note = ''): array
{
    if (!developer_requests_ready()) {
        return ['ok' => false, 'error' => 'The developer_requests table is missing.'];
    }
    $stmt = db_query(
        "UPDATE developer_requests SET status = 'rejected', admin_note = ?, decided_by = ?, decided_at = NOW()
         WHERE id = ? AND status = 'pending'",
        [$note === '' ? null : $note, $admin, $requestId]
    );
    if ($stmt->rowCount() !== 1) {
        return ['ok' => false, 'error' => 'That request was already decided.'];
    }
    log_action('developer_rejected', 'settings', null, 'request ' . $requestId . ' by ' . $admin);

    return ['ok' => true, 'error' => ''];
}

/** Gated types this account may already use (approved record requests). */
function developer_types_for_user(int $userId): array
{
    if (!developer_requests_ready()) {
        return [];
    }
    try {
        $rows = db_all(
            "SELECT DISTINCT record_type FROM developer_requests
             WHERE user_id = ? AND status = 'approved' AND record_type <> '*'",
            [$userId]
        );
    } catch (Throwable $e) {
        return [];
    }
    $out = [];
    foreach ($rows as $r) {
        $out[] = (string)$r['record_type'];
    }

    return $out;
}

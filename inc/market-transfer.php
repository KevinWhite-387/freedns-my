<?php
declare(strict_types=1);

/**
 * freedns.my - public give-away of names ("domain market", transfer mode).
 *
 * Ownership in this registry is the address in domains.registrant_email, so handing a name over is
 * a single UPDATE of that column plus the name shown next to it. Records are untouched: they hang
 * on domain_id, which does not change. Everything is logged, and the holder sees an explicit
 * warning that the transfer cannot be undone before it happens.
 */

function mt_table_ready(): bool
{
    static $ok = null;
    if ($ok !== null) {
        return $ok;
    }
    try {
        $ok = db_one("SHOW TABLES LIKE 'mt_listings'") !== null
            && db_one("SHOW TABLES LIKE 'mt_applications'") !== null;
    } catch (Throwable $e) {
        $ok = false;
    }

    return $ok;
}

/** The listing of one name, if it is open. */
function mt_open_listing(string $name): ?array
{
    if (!mt_table_ready()) {
        return null;
    }
    $row = db_one(
        "SELECT * FROM mt_listings WHERE domain_name = ? AND status = 'open' LIMIT 1",
        [$name]
    );

    return $row === null ? null : $row;
}

/** List a name. Returns [ok, error]. */
function mt_list(string $name, string $email, ?int $userId, string $title, string $note): array
{
    if (!mt_table_ready()) {
        return [false, 'The market tables are missing: import upgrade-market-transfer.sql first.'];
    }
    $domain = db_one('SELECT id, name, zone, registrant_email FROM domains WHERE name = ? LIMIT 1', [$name]);
    if ($domain === null) {
        return [false, 'That name is not in this registry.'];
    }
    if (strcasecmp((string)$domain['registrant_email'], $email) !== 0) {
        return [false, 'That name is not registered to your address.'];
    }
    if (mt_open_listing($name) !== null) {
        return [false, 'That name is already listed.'];
    }

    db_query(
        'INSERT INTO mt_listings (domain_id, domain_name, zone, from_email, from_user_id, title, note, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, \'open\', NOW())',
        [(int)$domain['id'], (string)$domain['name'], (string)$domain['zone'],
         (string)$domain['registrant_email'], $userId, mb_substr($title, 0, 190), $note]
    );
    log_action('market_listed', 'domain', (int)$domain['id'], (string)$domain['name']);

    return [true, ''];
}

/** Withdraw a listing. */
function mt_cancel(int $listingId, string $email): bool
{
    if (!mt_table_ready()) {
        return false;
    }
    $row = db_one('SELECT * FROM mt_listings WHERE id = ? LIMIT 1', [$listingId]);
    if ($row === null || strcasecmp((string)$row['from_email'], $email) !== 0) {
        return false;
    }
    db_query("UPDATE mt_listings SET status = 'cancelled', closed_at = NOW() WHERE id = ?", [$listingId]);
    db_query("UPDATE mt_applications SET status = 'rejected', decided_at = NOW() WHERE listing_id = ? AND status = 'pending'", [$listingId]);
    log_action('market_cancelled', 'settings', $listingId, (string)$row['domain_name']);

    return true;
}

/** Apply for one listing. */
function mt_apply(int $listingId, array $user, string $reason): array
{
    if (!mt_table_ready()) {
        return [false, 'The market tables are missing.'];
    }
    $row = db_one("SELECT * FROM mt_listings WHERE id = ? AND status = 'open' LIMIT 1", [$listingId]);
    if ($row === null) {
        return [false, 'That listing is no longer open.'];
    }
    if (strcasecmp((string)$row['from_email'], (string)$user['email']) === 0) {
        return [false, 'This is your own listing.'];
    }
    if (db_one('SELECT id FROM mt_applications WHERE listing_id = ? AND email = ? LIMIT 1', [$listingId, strtolower((string)$user['email'])]) !== null) {
        return [false, 'You have already applied for this name.'];
    }

    db_query(
        'INSERT INTO mt_applications (listing_id, user_id, email, username, reason, status, created_at)
         VALUES (?, ?, ?, ?, ?, \'pending\', NOW())',
        [$listingId, (int)$user['id'], strtolower((string)$user['email']),
         mb_substr((string)$user['username'], 0, 64), $reason]
    );
    log_action('market_applied', 'settings', $listingId, (string)$user['email'] . ' -> ' . (string)$row['domain_name']);

    return [true, ''];
}

/**
 * Approve one applicant: this is the irreversible part.
 *
 * The name is moved by rewriting registrant_email / registrant_name; records stay where they are
 * because they are keyed on domain_id. The listing is closed and every other application rejected.
 */
function mt_approve(int $applicationId, string $ownerEmail): array
{
    if (!mt_table_ready()) {
        return [false, 'The market tables are missing.'];
    }
    $app = db_one('SELECT * FROM mt_applications WHERE id = ? LIMIT 1', [$applicationId]);
    if ($app === null || (string)$app['status'] !== 'pending') {
        return [false, 'That application is not pending.'];
    }
    $listing = db_one('SELECT * FROM mt_listings WHERE id = ? LIMIT 1', [(int)$app['listing_id']]);
    if ($listing === null || (string)$listing['status'] !== 'open') {
        return [false, 'That listing is not open.'];
    }
    if (strcasecmp((string)$listing['from_email'], $ownerEmail) !== 0) {
        return [false, 'Only the current holder can approve a transfer.'];
    }
    $domain = db_one('SELECT id, name, registrant_email FROM domains WHERE id = ? LIMIT 1', [(int)$listing['domain_id']]);
    if ($domain === null) {
        return [false, 'The name is no longer in the registry.'];
    }

    $newOwner = db_one('SELECT id, username, email FROM users WHERE email_lower = ? LIMIT 1', [(string)$app['email']]);
    if ($newOwner === null) {
        return [false, 'The applicant account no longer exists.'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        db_query(
            'UPDATE domains SET registrant_email = ?, registrant_name = ?, updated_at = NOW(),
                admin_note = CONCAT(COALESCE(admin_note, \'\'), ?) WHERE id = ?',
            [strtolower((string)$newOwner['email']), (string)$newOwner['username'],
             ' [transferred from ' . (string)$domain['registrant_email'] . ']', (int)$domain['id']]
        );
        db_query("UPDATE mt_applications SET status = 'approved', decided_at = NOW() WHERE id = ?", [$applicationId]);
        db_query("UPDATE mt_applications SET status = 'rejected', decided_at = NOW() WHERE listing_id = ? AND id <> ? AND status = 'pending'", [(int)$listing['id'], $applicationId]);
        db_query("UPDATE mt_listings SET status = 'closed', closed_at = NOW() WHERE id = ?", [(int)$listing['id']]);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        return [false, 'Transfer failed: ' . $e->getMessage()];
    }

    log_action('market_transferred', 'domain', (int)$domain['id'],
        (string)$domain['name'] . ': ' . (string)$domain['registrant_email'] . ' -> ' . (string)$newOwner['email']);

    return [true, (string)$domain['name']];
}

/** Reject one applicant. */
function mt_reject(int $applicationId, string $ownerEmail): bool
{
    if (!mt_table_ready()) {
        return false;
    }
    $app = db_one('SELECT * FROM mt_applications WHERE id = ? LIMIT 1', [$applicationId]);
    if ($app === null) {
        return false;
    }
    $listing = db_one('SELECT * FROM mt_listings WHERE id = ? LIMIT 1', [(int)$app['listing_id']]);
    if ($listing === null || strcasecmp((string)$listing['from_email'], $ownerEmail) !== 0) {
        return false;
    }
    db_query("UPDATE mt_applications SET status = 'rejected', decided_at = NOW() WHERE id = ?", [$applicationId]);

    return true;
}
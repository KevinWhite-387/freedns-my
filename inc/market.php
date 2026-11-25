<?php
declare(strict_types=1);

/**
 * freedns.my - the market place: console accounts offer a registrable domain they own elsewhere.
 *
 * The registry is NOT part of the deal: it only lists (after a manual review), lets the operator pin an
 * offer, and introduces buyer and seller by mail once a buyer applies. The disclaimer therefore belongs on
 * top of every market page AND inside every market mail - see market_disclaimer().
 */

function market_table_ready(): bool
{
    if (isset($GLOBALS['FD_MARKET_TABLE'])) {
        return (bool)$GLOBALS['FD_MARKET_TABLE'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'market\\_listings'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_MARKET_TABLE'] = $ready;

    return $ready;
}

function market_enabled(): bool
{
    return setting_bool('market_enabled', true) && market_table_ready();
}

/** The plain-text disclaimer used at the top of the pages and inside the mails. */
function market_disclaimer(): string
{
    return t('market.disclaimer');
}

function market_currencies(): array
{
    return ['USD' => 'USD $', 'CNY' => 'CNY ¥', 'EUR' => 'EUR €'];
}

function market_currency_text(string $code): string
{
    $list = market_currencies();

    return $list[$code] ?? $code;
}

function market_status_text(string $status): string
{
    return t('market.status_' . $status);
}

function market_by_id(int $id): ?array
{
    if (!market_table_ready() || $id <= 0) {
        return null;
    }

    return db_one('SELECT * FROM market_listings WHERE id = ?', [$id]);
}

function market_money(?string $value): string
{
    if ($value === null || trim($value) === '') {
        return '-';
    }

    return number_format((float)$value, 2, '.', ',');
}

/**
 * Why a domain may not be listed - the empty string means it is fine.
 * Only registrable names from somewhere else: a name of this registry is not for sale here.
 */
function market_domain_problem(string $domain): string
{
    $domain = strtolower(trim($domain));
    if (!whois_valid_domain($domain)) {
        return 'val.market_domain';
    }
    if (count(explode('.', $domain)) < 2) {
        return 'val.market_domain';
    }

    foreach (zone_list() as $zone) {
        $zoneName = strtolower((string)$zone['name']);
        if ($zoneName !== '' && ($domain === $zoneName || str_ends_with($domain, '.' . $zoneName))) {
            return 'val.market_own_suffix';
        }
    }
    if (whois_own_zone($domain) !== '') {
        return 'val.market_own_suffix';
    }

    return '';
}

function market_listed_by_other(string $domain, int $exceptId = 0): bool
{
    if (!market_table_ready()) {
        return false;
    }

    return db_one(
        "SELECT id FROM market_listings WHERE domain = ? AND id <> ?
         AND status IN ('pending', 'active') LIMIT 1",
        [strtolower(trim($domain)), $exceptId]
    ) !== null;
}

/* ------------------------------------------------------------------ *
 * Mail                                                                *
 * ------------------------------------------------------------------ */

/** Every market mail starts with the disclaimer, as asked. */
function market_mail(string $to, string $subjectKey, array $args, array $lines, string $language = 'en'): bool
{
    // The account may have switched market notices off; every market mail passes through here.
    if (!mail_pref_allows($to, 'market')) {
        log_action('mail_skipped', 'market', null, 'market notice to ' . $to . ' not wanted');

        return true;
    }

    $previous = lang_switch($language);
    $subject  = t($subjectKey, ...$args);
    $body     = array_merge(
        [market_disclaimer(), '', '----------------------------------------', ''],
        $lines,
        ['', url('market.php'), '', setting('site_title', 'freedns.my')]
    );
    lang_switch($previous);

    return send_mail($to, $subject, implode("\n", $body));
}

/* ------------------------------------------------------------------ *
 * Listing                                                             *
 * ------------------------------------------------------------------ */

function market_create(array $user, array $data): array
{
    if (!market_enabled()) {
        return ['ok' => false, 'error' => t('val.market_off')];
    }

    $domain = strtolower(trim((string)($data['domain'] ?? '')));
    $problem = market_domain_problem($domain);
    if ($problem !== '') {
        return ['ok' => false, 'error' => t($problem)];
    }
    if (market_listed_by_other($domain)) {
        return ['ok' => false, 'error' => t('val.market_taken')];
    }

    $currency = strtoupper(trim((string)($data['currency'] ?? 'USD')));
    if (!isset(market_currencies()[$currency])) {
        $currency = 'USD';
    }
    $contact = trim((string)($data['contact_email'] ?? ''));
    if ($contact === '') {
        $contact = (string)$user['email'];
    }
    if (!valid_email_addr($contact)) {
        return ['ok' => false, 'error' => t('val.email_invalid')];
    }
    if (trim((string)($data['price'] ?? '')) === '') {
        return ['ok' => false, 'error' => t('val.market_price')];
    }

    db_query(
        'INSERT INTO market_listings (user_id, seller_email, contact_email, domain, registered_on, expires_on,
            registrar, transfer_price, price, currency, negotiable, description, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending\', NOW(), NOW())',
        [
            (int)$user['id'], (string)$user['email'], $contact, $domain,
            market_date($data['registered_on'] ?? ''), market_date($data['expires_on'] ?? ''),
            trim((string)($data['registrar'] ?? '')),
            market_decimal($data['transfer_price'] ?? ''), market_decimal($data['price'] ?? ''),
            $currency, empty($data['negotiable']) ? 0 : 1,
            trim((string)($data['description'] ?? '')),
        ]
    );
    $id = (int)db()->lastInsertId();

    log_action('market_submitted', 'market', $id, $domain . ' by ' . (string)$user['username']);

    $admin = trim((string)setting('notify_admin_email', ''));
    if ($admin !== '') {
        market_mail(
            $admin,
            'mail.market_new_subject',
            [$domain],
            [t('mail.market_new_body', $domain, (string)$user['email'], market_money((string)($data['price'] ?? '')) . ' ' . $currency)],
            'en'
        );
    }

    return ['ok' => true, 'error' => '', 'id' => $id];
}

function market_date($value): ?string
{
    $value = trim((string)$value);
    if ($value === '' || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
        return null;
    }
    $stamp = strtotime($value);

    return $stamp === false ? null : date('Y-m-d', $stamp);
}

function market_decimal($value): ?string
{
    $value = trim((string)$value);
    if ($value === '' || !is_numeric($value)) {
        return null;
    }

    return number_format((float)$value, 2, '.', '');
}

function market_update(int $id, array $user, array $data): array
{
    $listing = market_by_id($id);
    if ($listing === null || (int)$listing['user_id'] !== (int)$user['id']) {
        return ['ok' => false, 'error' => t('val.market_own')];
    }
    if (!in_array((string)$listing['status'], ['pending', 'active', 'rejected'], true)) {
        return ['ok' => false, 'error' => t('val.market_closed')];
    }

    $domain  = strtolower(trim((string)($data['domain'] ?? $listing['domain'])));
    $problem = market_domain_problem($domain);
    if ($problem !== '') {
        return ['ok' => false, 'error' => t($problem)];
    }
    if (market_listed_by_other($domain, $id)) {
        return ['ok' => false, 'error' => t('val.market_taken')];
    }

    $currency = strtoupper(trim((string)($data['currency'] ?? $listing['currency'])));
    if (!isset(market_currencies()[$currency])) {
        $currency = 'USD';
    }
    $contact = trim((string)($data['contact_email'] ?? ''));
    if ($contact === '') {
        $contact = (string)$user['email'];
    }
    if (!valid_email_addr($contact)) {
        return ['ok' => false, 'error' => t('val.email_invalid')];
    }

    // An edited offer goes back to the review queue, unless only the seller side of it changed.
    $status = (string)$listing['status'] === 'active' && $domain !== (string)$listing['domain']
        ? 'pending' : (string)$listing['status'];

    db_query(
        'UPDATE market_listings SET domain = ?, registered_on = ?, expires_on = ?, registrar = ?,
            transfer_price = ?, price = ?, currency = ?, negotiable = ?, description = ?, contact_email = ?,
            status = ?, reject_reason = NULL, updated_at = NOW() WHERE id = ?',
        [
            $domain, market_date($data['registered_on'] ?? ''), market_date($data['expires_on'] ?? ''),
            trim((string)($data['registrar'] ?? '')),
            market_decimal($data['transfer_price'] ?? ''), market_decimal($data['price'] ?? ''),
            $currency, empty($data['negotiable']) ? 0 : 1,
            trim((string)($data['description'] ?? '')), $contact, $status, $id,
        ]
    );
    log_action('market_updated', 'market', $id, $domain . ' by ' . (string)$user['username']);

    return ['ok' => true, 'error' => ''];
}

/** The seller withdraws an offer or marks it sold. */
function market_seller_status(int $id, array $user, string $status): array
{
    $listing = market_by_id($id);
    if ($listing === null || (int)$listing['user_id'] !== (int)$user['id']) {
        return ['ok' => false, 'error' => t('val.market_own')];
    }
    if (!in_array($status, ['withdrawn', 'sold'], true)) {
        return ['ok' => false, 'error' => t('val.market_closed')];
    }

    db_query(
        'UPDATE market_listings SET status = ?, sold_at = ' . ($status === 'sold' ? 'NOW()' : 'NULL')
        . ', pinned = 0, updated_at = NOW() WHERE id = ?',
        [$status, $id]
    );
    log_action('market_' . $status, 'market', $id, (string)$listing['domain'] . ' by ' . (string)$user['username']);

    return ['ok' => true, 'error' => ''];
}

/** Approve or reject a submitted offer (operator only). */
function market_decide(int $id, bool $approve, string $reason, string $staff): array
{
    $listing = market_by_id($id);
    if ($listing === null) {
        return ['ok' => false, 'error' => 'That offer does not exist.'];
    }
    if ((string)$listing['status'] !== 'pending') {
        return ['ok' => false, 'error' => 'Only a pending offer can be decided.'];
    }

    db_query(
        'UPDATE market_listings SET status = ?, reject_reason = ?, decided_by = ?, updated_at = NOW() WHERE id = ?',
        [$approve ? 'active' : 'rejected', $approve ? null : $reason, $staff, $id]
    );
    log_action($approve ? 'market_approved' : 'market_rejected', 'market', $id,
        (string)$listing['domain'] . ' by ' . $staff . ($approve || $reason === '' ? '' : ': ' . $reason));

    market_mail(
        (string)$listing['seller_email'],
        $approve ? 'mail.market_approved_subject' : 'mail.market_rejected_subject',
        [(string)$listing['domain']],
        [$approve
            ? t('mail.market_approved_body', (string)$listing['domain'])
            : t('mail.market_rejected_body', (string)$listing['domain'], $reason)],
        'en'
    );

    return ['ok' => true, 'error' => ''];
}

function market_pin(int $id, bool $pinned): void
{
    db_query('UPDATE market_listings SET pinned = ?, updated_at = NOW() WHERE id = ?', [$pinned ? 1 : 0, $id]);
    log_action($pinned ? 'market_pinned' : 'market_unpinned', 'market', $id, 'Offer ' . $id);
}

function market_remove(int $id, string $staff): void
{
    db_query("UPDATE market_listings SET status = 'withdrawn', pinned = 0, updated_at = NOW() WHERE id = ?", [$id]);
    log_action('market_removed', 'market', $id, 'Offer ' . $id . ' taken down by ' . $staff);
}

/* ------------------------------------------------------------------ *
 * Reading                                                             *
 * ------------------------------------------------------------------ */

/** The public list: pinned first, then newest. */
function market_public(array $filters = [], int $limit = 50, int $offset = 0): array
{
    if (!market_table_ready()) {
        return [];
    }

    $where  = ["status = 'active'"];
    $params = [];

    $suffix = strtolower(trim((string)($filters['suffix'] ?? '')));
    if ($suffix !== '') {
        $where[]  = 'domain LIKE ?';
        $params[] = '%.' . addcslashes($suffix, '%_\\');
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $where[]  = '(domain LIKE ? OR description LIKE ? OR registrar LIKE ?)';
        $like     = '%' . addcslashes($q, '%_\\') . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }
    $max = trim((string)($filters['max'] ?? ''));
    if ($max !== '' && is_numeric($max)) {
        $where[]  = 'price IS NOT NULL AND price <= ?';
        $params[] = (float)$max;
    }

    return db_all(
        'SELECT * FROM market_listings WHERE ' . implode(' AND ', $where)
        . ' ORDER BY pinned DESC, created_at DESC LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset),
        $params
    );
}

function market_public_count(array $filters = []): int
{
    if (!market_table_ready()) {
        return 0;
    }

    $where  = ["status = 'active'"];
    $params = [];
    $suffix = strtolower(trim((string)($filters['suffix'] ?? '')));
    if ($suffix !== '') {
        $where[]  = 'domain LIKE ?';
        $params[] = '%.' . addcslashes($suffix, '%_\\');
    }
    $q = trim((string)($filters['q'] ?? ''));
    if ($q !== '') {
        $where[]  = '(domain LIKE ? OR description LIKE ? OR registrar LIKE ?)';
        $like     = '%' . addcslashes($q, '%_\\') . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    return (int)db_value('SELECT COUNT(*) FROM market_listings WHERE ' . implode(' AND ', $where), $params, 0);
}

function market_mine(int $userId): array
{
    if (!market_table_ready()) {
        return [];
    }

    return db_all(
        'SELECT * FROM market_listings WHERE user_id = ? ORDER BY (status = \'pending\') DESC, id DESC LIMIT 100',
        [$userId]
    );
}

function market_enquiries(int $listingId): array
{
    if (!market_table_ready()) {
        return [];
    }

    return db_all('SELECT * FROM market_enquiries WHERE listing_id = ? ORDER BY id DESC LIMIT 100', [$listingId]);
}

function market_enquiries_for_user(int $userId): array
{
    if (!market_table_ready()) {
        return [];
    }

    return db_all(
        'SELECT e.*, l.domain FROM market_enquiries e JOIN market_listings l ON l.id = e.listing_id
         WHERE l.user_id = ? ORDER BY e.id DESC LIMIT 100',
        [$userId]
    );
}

/* ------------------------------------------------------------------ *
 * Buying                                                              *
 * ------------------------------------------------------------------ */

/** The buyer applies: both sides receive each other's address and the message. */
function market_enquire(array $listing, array $user, string $email, string $name, string $message): array
{
    if (!market_enabled()) {
        return ['ok' => false, 'error' => t('val.market_off')];
    }
    if ((string)$listing['status'] !== 'active') {
        return ['ok' => false, 'error' => t('val.market_closed')];
    }
    $email = trim($email) === '' ? (string)$user['email'] : trim($email);
    if (!valid_email_addr($email)) {
        return ['ok' => false, 'error' => t('val.email_invalid')];
    }
    if (strcasecmp($email, (string)$listing['seller_email']) === 0) {
        return ['ok' => false, 'error' => t('val.market_self')];
    }

    db_query(
        'INSERT INTO market_enquiries (listing_id, buyer_user, buyer_email, buyer_name, message, created_at)
         VALUES (?, ?, ?, ?, ?, NOW())',
        [(int)$listing['id'], (int)$user['id'], $email, $name, $message]
    );
    log_action('market_enquiry', 'market', (int)$listing['id'],
        (string)$listing['domain'] . ': ' . $email . ' wants to buy');

    $price = market_money((string)($listing['price'] ?? '')) . ' ' . (string)$listing['currency'];

    // Buyer gets the seller's address, seller gets the buyer's: from here they write to each other.
    market_mail(
        $email,
        'mail.market_buyer_subject',
        [(string)$listing['domain']],
        [t('mail.market_buyer_body', (string)$listing['domain'], $price, (string)$listing['contact_email'])],
        'en'
    );
    market_mail(
        (string)$listing['contact_email'],
        'mail.market_seller_subject',
        [(string)$listing['domain']],
        [t('mail.market_seller_body', (string)$listing['domain'], $name, $email, $message)],
        'en'
    );

    db_query('UPDATE market_enquiries SET mailed = 1 WHERE id = LAST_INSERT_ID()');

    return ['ok' => true, 'error' => ''];
}

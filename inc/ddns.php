<?php
declare(strict_types=1);

/**
 * freedns.my - dynamic updates (DDNS).
 *
 * Each name can carry a secret token; a router, a NAS or a small script calls
 * /ddns.php?token=...&ip=... and the A (or AAAA) record of that name follows the reported address.
 * Only records that already exist are changed: the holder decides which record types the name has.
 */

const FD_DDNS_MIN_SECONDS = 10;

function ddns_columns_ready(): bool
{
    if (isset($GLOBALS['FD_DDNS_COLS'])) {
        return (bool)$GLOBALS['FD_DDNS_COLS'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW COLUMNS FROM domains LIKE 'ddns_token'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_DDNS_COLS'] = $ready;

    return $ready;
}

function ddns_enabled(): bool
{
    return ddns_columns_ready() && setting_bool('ddns_enabled', true);
}

function ddns_url(string $token, string $host = ''): string
{
    $url = rtrim(app_base_url(), '/') . '/ddns.php?token=' . rawurlencode($token);

    return $host === '' ? $url : $url . '&host=' . rawurlencode($host);
}

/** The token of a name, created on demand. */
function ddns_token(array $domain, bool $create = true): string
{
    $token = trim((string)($domain['ddns_token'] ?? ''));
    if ($token !== '' || !$create || !ddns_columns_ready()) {
        return $token;
    }

    $token = bin2hex(random_bytes(16));
    db_query('UPDATE domains SET ddns_token = ?, updated_at = NOW() WHERE id = ?', [$token, (int)$domain['id']]);
    log_action('ddns_token_created', 'domain', (int)$domain['id'],
        'Dynamic update token created for ' . (string)$domain['name']);

    return $token;
}

/** A fresh token; the old address stops working at once. */
function ddns_reset_token(array $domain): string
{
    if (!ddns_columns_ready()) {
        return '';
    }

    $token = bin2hex(random_bytes(16));
    db_query('UPDATE domains SET ddns_token = ?, ddns_updated_at = NULL, updated_at = NOW() WHERE id = ?',
        [$token, (int)$domain['id']]);
    log_action('ddns_token_reset', 'domain', (int)$domain['id'],
        'Dynamic update token reset for ' . (string)$domain['name']);

    return $token;
}

function ddns_revoke_token(array $domain): void
{
    if (!ddns_columns_ready()) {
        return;
    }

    db_query('UPDATE domains SET ddns_token = NULL, ddns_updated_at = NULL, updated_at = NOW() WHERE id = ?',
        [(int)$domain['id']]);
    log_action('ddns_token_revoked', 'domain', (int)$domain['id'],
        'Dynamic update token removed for ' . (string)$domain['name']);
}

function ddns_domain_by_token(string $token): ?array
{
    $token = trim($token);
    if ($token === '' || strlen($token) > 64 || !ddns_columns_ready()) {
        return null;
    }

    return db_one('SELECT * FROM domains WHERE ddns_token = ? LIMIT 1', [$token]);
}

/**
 * Applies one update.
 *
 * @return array{status: string, ip: string, message: string}
 */
function ddns_apply(array $domain, string $ipv4, string $ipv6): array
{
    $name = (string)$domain['name'];

    if ((string)$domain['status'] !== 'active') {
        return ['status' => 'nohost', 'ip' => '', 'message' => 'The name is not active (yet).'];
    }
    if (ns_domain_delegated((int)$domain['id'])) {
        return ['status' => 'nohost', 'ip' => '', 'message' => 'The name is delegated to its own name servers.'];
    }

    $wanted = [];
    if ($ipv4 !== '') {
        if (!filter_var($ipv4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['status' => 'badip', 'ip' => $ipv4, 'message' => 'Not a valid IPv4 address.'];
        }
        $wanted['A'] = $ipv4;
    }
    if ($ipv6 !== '') {
        if (!filter_var($ipv6, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return ['status' => 'badip', 'ip' => $ipv6, 'message' => 'Not a valid IPv6 address.'];
        }
        $wanted['AAAA'] = $ipv6;
    }
    if ($wanted === []) {
        return ['status' => 'badip', 'ip' => '', 'message' => 'No address was sent.'];
    }

    $rows    = domain_records((int)$domain['id']);
    $changed = false;
    $seen    = [];
    $apex    = ['@', '', $name];

    foreach ($rows as $index => $row) {
        $type = strtoupper((string)($row['type'] ?? ''));
        $host = strtolower(trim((string)($row['name'] ?? '')));
        if (!isset($wanted[$type]) || !in_array($host, $apex, true) && $host !== $apex[0]) {
            continue;
        }
        $seen[$type] = true;
        if ((string)($row['value'] ?? '') !== $wanted[$type]) {
            $rows[$index]['value'] = $wanted[$type];
            $changed                = true;
        }
    }

    if ($seen === []) {
        return ['status' => 'nohost', 'ip' => '', 'message' =>
            'This name has no ' . implode(' / ', array_keys($wanted)) . ' record at the top of the name; '
            . 'add one in the console first.'];
    }

    $reported = $ipv4 !== '' ? $ipv4 : $ipv6;
    if (!$changed) {
        db_query('UPDATE domains SET ddns_updated_at = NOW() WHERE id = ?', [(int)$domain['id']]);

        return ['status' => 'nochg', 'ip' => $reported, 'message' => 'The address is the same as stored.'];
    }

    // Same write path as the console: replace the record set, then publish it.
    replace_records((int)$domain['id'], $rows);
    $published = publish_domain((int)$domain['id'], $domain);
    if (isset($published['ok']) && !$published['ok']) {
        return ['status' => '911', 'ip' => $reported,
            'message' => 'Stored, but publishing failed: ' . (string)($published['error'] ?? 'unknown')];
    }

    db_query('UPDATE domains SET ddns_updated_at = NOW() WHERE id = ?', [(int)$domain['id']]);
    log_action('ddns_update', 'domain', (int)$domain['id'],
        $name . ' -> ' . implode(' / ', array_map(static function (string $t, string $v): string {
            return $t . ' ' . $v;
        }, array_keys($wanted), array_values($wanted))));

    return ['status' => 'good', 'ip' => $reported, 'message' => 'Updated.'];
}

function ddns_throttled(array $domain): bool
{
    $last = (string)($domain['ddns_updated_at'] ?? '');
    if ($last === '') {
        return false;
    }
    $stamp = strtotime($last);

    return $stamp !== false && (time() - $stamp) < FD_DDNS_MIN_SECONDS;
}

function ddns_status_text(string $status): string
{
    return t('ddns.status_' . $status);
}

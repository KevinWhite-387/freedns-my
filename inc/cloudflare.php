<?php
declare(strict_types=1);

/**
 * freedns.my - publishing approved records to Cloudflare.
 *
 * The review stays manual: an operator approves a request or a record change in the back office,
 * and only then the API is called. Every record the registry pushed is remembered with the
 * identifier Cloudflare returned (table dns_records), so suspending or deleting a name removes
 * exactly those records and never touches a record the operator added by hand.
 *
 * Failure policy (Settings -> DNS): with "strict" (the default) a failed API call aborts the
 * approval, so the registry never shows a name as active while its records are missing. With
 * "warn" the approval goes through and the failure is only reported.
 */

const CF_API = 'https://api.cloudflare.com/client/v4';

function cf_enabled(): bool
{
    return setting_bool('cf_enabled', false);
}

/** The token used when a suffix has none of its own. */
function cf_global_token(): string
{
    return trim((string)setting('cf_token', ''));
}

/**
 * The token for one suffix: its own when set, otherwise the general one.
 *
 * A Cloudflare API token belongs to a single account, so suffixes that live in different
 * Cloudflare accounts each need their own token.
 */
function cf_zone_token(string $zoneName): string
{
    $zone = zone_by_name($zoneName);
    $own  = $zone === null ? '' : trim((string)($zone['cf_token'] ?? ''));

    return $own !== '' ? $own : cf_global_token();
}

function cf_token(): string
{
    return cf_global_token();
}

function cf_strict(): bool
{
    return strtolower(trim((string)setting('cf_failure_policy', 'strict'))) !== 'warn';
}

/** Zones that are tied to a Cloudflare zone id. */
function cf_zone_id(string $zoneName): string
{
    $zone = zone_by_name($zoneName);

    return $zone === null ? '' : trim((string)($zone['cf_zone_id'] ?? ''));
}

/**
 * True when a suffix can be published automatically: switched on, a zone id and a token.
 * Everything the application does checks this one function.
 */
function cf_zone_ready(string $zoneName): bool
{
    return cf_enabled() && cf_zone_id($zoneName) !== '' && cf_zone_token($zoneName) !== '';
}

function cf_mapping_ready(): bool
{
    if (isset($GLOBALS['FD_CF_MAPPING'])) {
        return (bool)$GLOBALS['FD_CF_MAPPING'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'dns\\_records'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_CF_MAPPING'] = $ready;

    return $ready;
}

/** True when the zones table carries the per-suffix token column (upgrade-cf-tokens.sql). */
function cf_token_column_ready(): bool
{
    if (isset($GLOBALS['FD_CF_TOKEN_COL'])) {
        return (bool)$GLOBALS['FD_CF_TOKEN_COL'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW COLUMNS FROM zones LIKE 'cf_token'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_CF_TOKEN_COL'] = $ready;

    return $ready;
}

/**
 * One call against the Cloudflare API.
 *
 * @param string $token the API token to use; empty means the general one
 * @return array{ok: bool, status: int, data: array, error: string}
 */
function cf_request(string $method, string $path, ?array $body = null, string $token = '', int $timeout = 20): array
{
    $token = $token !== '' ? $token : cf_global_token();
    if ($token === '') {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'No API token is configured.'];
    }

    $url     = str_starts_with($path, 'http') ? $path : CF_API . $path;
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];
    $payload = $body === null ? null : json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    $raw    = '';
    $status = 0;
    $error  = '';

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => false,
        ]);
        if ($payload !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $payload);
        }
        $result = curl_exec($handle);
        if ($result === false) {
            $error = 'cURL: ' . (string)curl_error($handle);
        } else {
            $raw = (string)$result;
        }
        $status = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);
    } else {
        // No cURL: PHP's stream wrapper. Some hosts disable it, so the error is reported plainly.
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $payload ?? '',
                'timeout'       => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $result = @file_get_contents($url, false, $context);
        if ($result === false) {
            $error = 'The request failed (no cURL extension and allow_url_fopen seems to be off).';
        } else {
            $raw = (string)$result;
            foreach ($http_response_header ?? [] as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                    $status = (int)$m[1];
                }
            }
        }
    }

    if ($error !== '') {
        return ['ok' => false, 'status' => $status, 'data' => [], 'error' => $error];
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        return ['ok' => false, 'status' => $status, 'data' => [], 'error' => 'The API answered something that is not JSON.'];
    }

    if (empty($data['success'])) {
        $messages = [];
        foreach ((array)($data['errors'] ?? []) as $item) {
            $messages[] = (string)($item['code'] ?? '') . ' ' . (string)($item['message'] ?? '');
        }
        if ($messages === []) {
            $messages[] = 'HTTP ' . $status;
        }

        return [
            'ok'     => false,
            'status' => $status,
            'data'   => $data,
            'error'  => trim(implode('; ', $messages)),
        ];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
}

/** Checks a token and returns the zones it may see: id, name, status. */
function cf_list_zones(string $token = ''): array
{
    $response = cf_request('GET', '/zones?per_page=50', null, $token);
    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error'], 'zones' => []];
    }

    $zones = [];
    foreach ((array)($response['data']['result'] ?? []) as $item) {
        $zones[] = [
            'id'     => (string)($item['id'] ?? ''),
            'name'   => (string)($item['name'] ?? ''),
            'status' => (string)($item['status'] ?? ''),
        ];
    }

    return ['ok' => true, 'error' => '', 'zones' => $zones];
}

function cf_verify_token(string $token = ''): array
{
    $response = cf_request('GET', '/user/tokens/verify', null, $token);
    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error']];
    }

    return ['ok' => true, 'error' => '', 'status' => (string)($response['data']['result']['status'] ?? '')];
}

/** "www.myname.freedns.my" for a sub-name, "myname.freedns.my" for the name itself. */
function cf_fqdn(string $name, string $sub): string
{
    $sub = trim($sub);

    return $sub === '' ? $name : $sub . '.' . $name;
}

/** The records the registry remembers having pushed for one name. */
function cf_mapped_records(int $domainId): array
{
    if (!cf_mapping_ready()) {
        return [];
    }

    return db_all('SELECT * FROM dns_records WHERE domain_id = ? ORDER BY id ASC', [$domainId]);
}

function cf_forget_mapping(int $domainId): void
{
    if (cf_mapping_ready()) {
        db_query('DELETE FROM dns_records WHERE domain_id = ?', [$domainId]);
    }
}

/** Builds the API payload for one record. TXT can never be proxied. */
function cf_record_payload(string $name, string $sub, string $type, string $value, int $ttl, bool $proxied): array
{
    $payload = [
        'type'    => $type,
        'name'    => cf_fqdn($name, $sub),
        'content' => $value,
        'ttl'     => $ttl > 0 ? $ttl : 1,
    ];
    if (in_array($type, ['A', 'AAAA', 'CNAME'], true)) {
        $payload['proxied'] = $proxied;
    }

    return $payload;
}

/**
 * Makes Cloudflare hold exactly the given record set for one name.
 *
 * New records are created first, then the ones that are gone are deleted, so a change never leaves
 * the name without records. Nothing is written to dns_records here; the caller stores the returned
 * rows together with the local change, inside the same transaction.
 *
 * @param array<int, array{sub: string, type: string, value: string}> $records
 * @return array{ok: bool, error: string, created: int, updated: int, deleted: int, rows: array, log: array}
 */
function cf_sync_domain(int $domainId, string $name, string $zoneName, array $records, bool $proxied): array
{
    $result = ['ok' => false, 'error' => '', 'created' => 0, 'updated' => 0, 'deleted' => 0, 'rows' => [], 'log' => []];
    $log    = [];

    if (!cf_enabled()) {
        $result['error'] = 'Automatic publishing is switched off (Settings -> DNS).';

        return $result;
    }
    if (!cf_mapping_ready()) {
        $result['error'] = 'The dns_records table does not exist yet - import upgrade-cloudflare.sql.';

        return $result;
    }

    $zoneId = cf_zone_id($zoneName);
    if ($zoneId === '') {
        $result['error'] = 'No Cloudflare zone id is set for ' . $zoneName . ' (DNS page).';

        return $result;
    }

    $token = cf_zone_token($zoneName);
    if ($token === '') {
        $result['error'] = 'No Cloudflare API token is configured for ' . $zoneName
            . ' (DNS page: give the suffix its own token, or fill in the general one).';

        return $result;
    }

    $ttl     = max(60, (int)setting('default_ttl', '3600'));
    $desired = [];
    foreach ($records as $record) {
        $sub  = (string)($record['sub'] ?? '');
        $type = strtoupper((string)($record['type'] ?? ''));
        $value = (string)($record['value'] ?? '');
        if ($type === '' || $value === '') {
            continue;
        }
        $desired[] = [
            'sub'     => $sub,
            'type'    => $type,
            'value'   => $value,
            'proxied' => in_array($type, ['A', 'AAAA', 'CNAME'], true) ? ($proxied ? 1 : 0) : 0,
            'fqdn'    => cf_fqdn($name, $sub),
        ];
    }

    $existing = [];
    foreach (cf_mapped_records($domainId) as $row) {
        // The name is part of the key: renaming a domain moves its records instead of keeping them.
        $existing[strtolower((string)$row['fqdn']) . '|' . strtoupper((string)$row['type']) . '|' . (string)$row['value']] = $row;
    }

    $rows = [];

    // 1. create what is missing, update what changed its proxy flag
    foreach ($desired as $record) {
        $key = strtolower($record['fqdn']) . '|' . $record['type'] . '|' . $record['value'];

        if (isset($existing[$key])) {
            $row = $existing[$key];
            if ((int)$row['proxied'] !== $record['proxied']) {
                $response = cf_request(
                    'PATCH',
                    '/zones/' . rawurlencode($zoneId) . '/dns_records/' . rawurlencode((string)$row['remote_id']),
                    cf_record_payload($name, $record['sub'], $record['type'], $record['value'], (int)($record['ttl'] ?? $ttl), (bool)$record['proxied']),
                    $token
                );
                $log[] = 'PATCH ' . $record['fqdn'] . ' ' . $record['type'] . ': ' . ($response['ok'] ? 'ok' : $response['error']);
                if (!$response['ok']) {
                    $result['error'] = 'Updating ' . $record['fqdn'] . ' (' . $record['type'] . ') failed: ' . $response['error'];
                    $result['log']   = $log;

                    return $result;
                }
                $result['updated']++;
                $rows[] = [
                    'remote_id' => (string)$row['remote_id'],
                    'fqdn'      => $record['fqdn'],
                    'sub'       => $record['sub'],
                    'type'      => $record['type'],
                    'value'     => $record['value'],
                    'proxied'   => $record['proxied'],
                ];
            } else {
                $rows[] = [
                    'remote_id' => (string)$row['remote_id'],
                    'fqdn'      => $record['fqdn'],
                    'sub'       => $record['sub'],
                    'type'      => $record['type'],
                    'value'     => $record['value'],
                    'proxied'   => (int)$row['proxied'],
                ];
            }
            unset($existing[$key]);
            continue;
        }

        $response = cf_request(
            'POST',
            '/zones/' . rawurlencode($zoneId) . '/dns_records',
            cf_record_payload($name, $record['sub'], $record['type'], $record['value'], (int)($record['ttl'] ?? $ttl), (bool)$record['proxied']),
            $token
        );
        $log[] = 'POST ' . $record['fqdn'] . ' ' . $record['type'] . ': ' . ($response['ok'] ? 'ok' : $response['error']);
        if (!$response['ok']) {
            $result['error'] = 'Creating ' . $record['fqdn'] . ' (' . $record['type'] . ') failed: ' . $response['error'];
            $result['log']   = $log;

            return $result;
        }

        $remoteId = (string)($response['data']['result']['id'] ?? '');
        if ($remoteId === '') {
            $result['error'] = 'Cloudflare accepted ' . $record['fqdn'] . ' without returning an identifier.';
            $result['log']   = $log;

            return $result;
        }

        $result['created']++;
        $rows[] = [
            'remote_id' => $remoteId,
            'fqdn'      => $record['fqdn'],
            'sub'       => $record['sub'],
            'type'      => $record['type'],
            'value'     => $record['value'],
            'proxied'   => $record['proxied'],
        ];
    }

    // 2. delete what is no longer wanted
    foreach ($existing as $row) {
        $response = cf_request(
            'DELETE',
            '/zones/' . rawurlencode($zoneId) . '/dns_records/' . rawurlencode((string)$row['remote_id']),
            null,
            $token
        );
        $log[] = 'DELETE ' . (string)$row['sub'] . ' ' . (string)$row['type'] . ': ' . ($response['ok'] ? 'ok' : $response['error']);
        if (!$response['ok'] && (int)$response['status'] !== 404) {
            $result['error'] = 'Removing the old record ' . (string)$row['value'] . ' failed: ' . $response['error'];
            $result['log']   = $log;

            return $result;
        }
        $result['deleted']++;
    }

    $result['ok']   = true;
    $result['rows'] = $rows;
    $result['log']  = $log;

    return $result;
}

/** Removes every record the registry pushed for one name. */
function cf_remove_domain(int $domainId, string $zoneName): array
{
    $result = ['ok' => false, 'error' => '', 'deleted' => 0, 'log' => []];

    if (!cf_enabled() || !cf_mapping_ready()) {
        // Nothing to do: no automatic publishing, or nothing was ever pushed.
        $result['ok'] = true;

        return $result;
    }

    $rows = cf_mapped_records($domainId);
    if ($rows === []) {
        $result['ok'] = true;

        return $result;
    }

    $zoneId = cf_zone_id($zoneName);
    if ($zoneId === '') {
        $result['error'] = 'No Cloudflare zone id is set for ' . $zoneName . '.';

        return $result;
    }

    $token = cf_zone_token($zoneName);
    if ($token === '') {
        $result['error'] = 'No Cloudflare API token is configured for ' . $zoneName . '.';

        return $result;
    }

    foreach ($rows as $row) {
        $response = cf_request(
            'DELETE',
            '/zones/' . rawurlencode($zoneId) . '/dns_records/' . rawurlencode((string)$row['remote_id']),
            null,
            $token
        );
        $result['log'][] = 'DELETE ' . (string)$row['sub'] . ' ' . (string)$row['type']
            . ': ' . ($response['ok'] ? 'ok' : $response['error']);
        if (!$response['ok'] && (int)$response['status'] !== 404) {
            $result['error'] = 'Removing ' . (string)$row['value'] . ' failed: ' . $response['error'];

            return $result;
        }
        $result['deleted']++;
    }

    $result['ok'] = true;

    return $result;
}

/** Writes the mapping rows of a successful sync (inside the caller's transaction). */
function cf_store_mapping(int $domainId, array $rows): void
{
    if (!cf_mapping_ready()) {
        return;
    }

    cf_forget_mapping($domainId);
    foreach ($rows as $row) {
        db_query(
            'INSERT INTO dns_records (domain_id, provider, remote_id, fqdn, sub, type, value, proxied, created_at, updated_at)
             VALUES (?, \'cloudflare\', ?, ?, ?, ?, ?, ?, NOW(), NOW())',
            [
                $domainId,
                (string)$row['remote_id'],
                (string)($row['fqdn'] ?? ''),
                (string)$row['sub'],
                strtoupper((string)$row['type']),
                (string)$row['value'],
                (int)$row['proxied'],
            ]
        );
    }
}

/** Publishes the current (or proposed) record set of one name and remembers the mapping. */
function cf_publish(int $domainId, array $domain, array $records): array
{
    $result = cf_sync_domain(
        $domainId,
        (string)$domain['name'],
        (string)$domain['zone'],
        $records,
        !empty($domain['proxied'])
    );

    if ($result['ok']) {
        cf_store_mapping($domainId, $result['rows']);
    }

    return $result;
}

/** Takes the records of a name out of Cloudflare (suspend, delete, reject). */
function cf_withdraw(int $domainId, array $domain): array
{
    $result = cf_remove_domain($domainId, (string)$domain['zone']);
    if ($result['ok']) {
        cf_forget_mapping($domainId);
    }

    return $result;
}

/**
 * The record set of one name as the registry would publish it, in the shape cf_publish() expects.
 *
 * @param array<int, array<string, mixed>> $records rows of the records table or of a change snapshot
 */
function cf_records_of(array $records): array
{
    $out = [];
    $defaultTtl = default_ttl();

    foreach ($records as $record) {
        $row = [
            'sub'   => (string)($record['sub'] ?? ''),
            'type'  => strtoupper((string)($record['type'] ?? '')),
            'value' => (string)($record['value'] ?? ''),
        ];
        // Each record may carry its own TTL and its own proxy flag (the console and the bulk import set
        // them); the registry default is used when a record does not say anything.
        $row['ttl'] = isset($record['ttl']) ? max(60, min(86400, (int)$record['ttl'])) : $defaultTtl;
        if (array_key_exists('proxied', $record)) {
            $row['proxied'] = empty($record['proxied']) ? 0 : 1;
        }
        $out[] = $row;
    }

    return $out;
}

/** True when a name is published by the registry itself and therefore belongs in Cloudflare. */
function cf_should_publish(array $domain): bool
{
    return in_array((string)($domain['status'] ?? ''), ['active'], true);
}

/**
 * The records Cloudflare already holds for one full name.
 *
 * A suffix that is not bound to Cloudflare (no zone id, no token) counts as "no records": the
 * registry does not manage that zone, so a request for a name in it is simply accepted.
 *
 * @return array{ok: bool, error: string, records: array<int, array{type: string, value: string, proxied: int}>}
 */
function cf_name_records(string $zoneName, string $fqdn): array
{
    $result = ['ok' => true, 'error' => '', 'records' => []];

    $zoneId = cf_zone_id($zoneName);
    $token  = cf_zone_token($zoneName);
    if ($zoneId === '' || $token === '') {
        return $result;
    }

    $fqdn     = strtolower(rtrim(trim($fqdn), '.'));
    $response = cf_request(
        'GET',
        '/zones/' . rawurlencode($zoneId) . '/dns_records?per_page=100&name=' . rawurlencode($fqdn),
        null,
        $token
    );

    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error'], 'records' => []];
    }

    foreach ((array)($response['data']['result'] ?? []) as $item) {
        // The filter of the API also matches longer names; compare exactly.
        if (strcasecmp((string)($item['name'] ?? ''), $fqdn) !== 0) {
            continue;
        }
        $result['records'][] = [
            'type'    => strtoupper((string)($item['type'] ?? '')),
            'value'   => (string)($item['content'] ?? ''),
            'proxied' => !empty($item['proxied']) ? 1 : 0,
        ];
    }

    return $result;
}

/** "A 203.0.113.7, CNAME example.net" for a message, at most $max entries. */
function cf_records_summary(array $records, int $max = 5): string
{
    $parts = [];
    foreach (array_slice($records, 0, $max) as $record) {
        $parts[] = (string)($record['type'] ?? '') . ' ' . (string)($record['value'] ?? '');
    }
    if ($parts === []) {
        return '';
    }

    $text = implode(', ', $parts);
    if (count($records) > $max) {
        $text .= ' (+' . (count($records) - $max) . ')';
    }

    return $text;
}

/** Reconciles every active name; used by the back office button and the cron entry point. */function cf_reconcile_all(): array
{
    $summary = ['ok' => true, 'checked' => 0, 'changed' => 0, 'failed' => 0, 'messages' => []];

    if (!cf_enabled()) {
        $summary['ok'] = false;
        $summary['messages'][] = 'Automatic publishing is switched off.';

        return $summary;
    }

    $domains = db_all("SELECT * FROM domains WHERE status = 'active' ORDER BY zone ASC, name ASC");
    foreach ($domains as $domain) {
        $summary['checked']++;
        $domainId = (int)$domain['id'];
        $records  = domain_records($domainId);

        if ($records === [] && !empty($domain['reserved'])) {
            // Nothing to publish; a reserved placeholder never exists without records.
            continue;
        }

        $result = cf_publish($domainId, $domain, $records);
        if ($result['ok']) {
            if ($result['created'] > 0 || $result['updated'] > 0 || $result['deleted'] > 0) {
                $summary['changed']++;
            }
        } else {
            $summary['failed']++;
            $summary['ok'] = false;
            $summary['messages'][] = (string)$domain['name'] . ': ' . $result['error'];
        }
    }

    return $summary;
}

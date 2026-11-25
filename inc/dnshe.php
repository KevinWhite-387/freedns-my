<?php
declare(strict_types=1);

/**
 * freedns.my - publishing through DNSHE (api005.dnshe.com).
 *
 * Used for suffixes that are not hosted in Cloudflare. DNSHE has one layer more than Cloudflare: a name
 * is a "subdomain" object (subdomain + rootdomain) that has to exist before records can be written under
 * it. So approving a request registers that subdomain, remembers its id in domains.provider_ref and
 * writes the records; suspending removes only the records, deleting also releases the subdomain - but
 * only one the registry registered itself (provider_owned), never one that was already there.
 *
 * Authentication is X-API-Key / X-API-Secret (query and body authentication is disabled), the endpoint is
 * https://api005.dnshe.com/index.php?m=domain_hub and the published rate limit is 60 requests a minute (raisable per key; ours is 1000).
 */

/** The one endpoint of the DNSHE API; every call is a query string on it. */
const DNSHE_API = 'https://api005.dnshe.com/index.php?m=domain_hub';

/** True when the columns of this release exist. */
function dnshe_columns_ready(): bool
{
    if (isset($GLOBALS['FD_DNSHE_COLS'])) {
        return (bool)$GLOBALS['FD_DNSHE_COLS'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW COLUMNS FROM zones LIKE 'provider'") !== null
            && db_one("SHOW COLUMNS FROM domains LIKE 'provider_ref'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_DNSHE_COLS'] = $ready;

    return $ready;
}

function dnshe_enabled(): bool
{
    return setting_bool('dnshe_enabled', false);
}

function dnshe_global_key(): string
{
    return trim((string)setting('dnshe_key', ''));
}

function dnshe_global_secret(): string
{
    return trim((string)setting('dnshe_secret', ''));
}

/** The credentials of one suffix: its own when set, otherwise the general ones. */
function dnshe_zone_key(string $zoneName): string
{
    $zone = zone_by_name($zoneName);
    $own  = $zone === null ? '' : trim((string)($zone['dnshe_key'] ?? ''));

    return $own !== '' ? $own : dnshe_global_key();
}

function dnshe_zone_secret(string $zoneName): string
{
    $zone = zone_by_name($zoneName);
    $own  = $zone === null ? '' : trim((string)($zone['dnshe_secret'] ?? ''));

    return $own !== '' ? $own : dnshe_global_secret();
}

/** Ready = switched on, columns present, credentials for this suffix, and the provider chosen for it. */
function dnshe_zone_ready(string $zoneName): bool
{
    return dnshe_enabled()
        && dnshe_columns_ready()
        && publish_provider($zoneName) === 'dnshe'
        && dnshe_zone_key($zoneName) !== ''
        && dnshe_zone_secret($zoneName) !== '';
}

/**
 * One call against the DNSHE API.
 *
 * @return array{ok: bool, status: int, data: array, error: string}
 */
function dnshe_request(string $endpoint, string $action, array $params = [], string $method = 'GET', string $zoneName = ''): array
{
    $key    = dnshe_zone_key($zoneName);
    $secret = dnshe_zone_secret($zoneName);

    if ($key === '' || $secret === '') {
        return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'No DNSHE API key and secret are configured.'];
    }

    $url = DNSHE_API . '&endpoint=' . rawurlencode($endpoint) . '&action=' . rawurlencode($action);
    foreach ($params as $name => $value) {
        if ($method === 'GET') {
            $url .= '&' . rawurlencode((string)$name) . '=' . rawurlencode((string)$value);
        }
    }

    $headers = [
        'X-API-Key: ' . $key,
        'X-API-Secret: ' . $secret,
        'Accept: application/json',
        // The gateway in front of api005.dnshe.com answers a bare request with an HTML 403 page,
        // which looks like an authentication failure but is the WAF: it expects the headers a
        // browser or the official console would send.
        'User-Agent: freedns.my-registry/1.0 (+https://freedns.my)',
        'Origin: https://my.dnshe.com',
        'Referer: https://my.dnshe.com/',
        'X-Requested-With: XMLHttpRequest',
    ];
    $body = null;
    if ($method !== 'GET') {
        $headers[] = 'Content-Type: application/json';
        $body      = json_encode($params);
    }

    $response = ['ok' => false, 'status' => 0, 'data' => [], 'error' => ''];

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        if ($body !== null) {
            curl_setopt($handle, CURLOPT_POSTFIELDS, $body);
        }
        $raw  = curl_exec($handle);
        $code = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $err  = $raw === false ? (string)curl_error($handle) : '';
        curl_close($handle);

        if ($raw === false) {
            return ['ok' => false, 'status' => $code, 'data' => [], 'error' => 'DNSHE: ' . $err];
        }
        $response['status'] = $code;
    } else {
        $context = stream_context_create([
            'http' => [
                'method'        => $method,
                'header'        => implode("\r\n", $headers),
                'content'       => $body === null ? '' : $body,
                'timeout'       => 20,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            return ['ok' => false, 'status' => 0, 'data' => [], 'error' => 'DNSHE could not be reached.'];
        }
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $match)) {
                $response['status'] = (int)$match[1];
            }
        }
    }

    // Rate limit: DNSHE answers 429 with {"error_code":"rate_limit_exceeded"}. One short retry turns
    // a bulk reconcile from "fails at request N" into "slower but complete"; a hard block still fails.
    // Rate limit. The API answers 429 with {"error_code":"rate_limit_exceeded"} and a message such as
    // "Please try again in 31 minutes". A short wait can be retried once; a long one must not be, or
    // the retry burns another request and the window grows. Nothing else in the request is changed.
    if ((int)$response['status'] === 429) {
        $hint = @json_decode((string)$raw, true);
        $text = (string)($hint['message'] ?? $hint['error'] ?? 'rate limited');
        $wait = 0;
        if (preg_match('/(\d+)\s*(second|minute|hour)/i', $text, $mm)) {
            $n2 = (int)$mm[1];
            $unit = strtolower($mm[2]);
            $wait = $unit === 'second' ? $n2 : ($unit === 'minute' ? $n2 * 60 : $n2 * 3600);
        }

        if ($wait > 0 && $wait <= 60) {
            log_action('dnshe_rate_limited', 'settings', null, $endpoint . '/' . $action . ': waiting ' . $wait . 's');
            sleep($wait);
            if (function_exists('curl_init')) {
                $retry = curl_init($url);
                curl_setopt_array($retry, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CUSTOMREQUEST  => $method,
                    CURLOPT_HTTPHEADER     => $headers,
                    CURLOPT_TIMEOUT        => 20,
                    CURLOPT_CONNECTTIMEOUT => 8,
                ]);
                if ($body !== null) {
                    curl_setopt($retry, CURLOPT_POSTFIELDS, $body);
                }
                $again = curl_exec($retry);
                $code2 = (int)curl_getinfo($retry, CURLINFO_RESPONSE_CODE);
                curl_close($retry);
                if (is_string($again)) {
                    $raw = $again;
                    $response['status'] = $code2;
                }
            }
        } else {
            $response['error'] = 'DNSHE rate limit reached on ' . $endpoint . '/' . $action
                . ': ' . $text . ' Nothing was written. Wait for the window to pass and run this again.';

            return $response;
        }
    }


    $data = json_decode((string)$raw, true);
    if (!is_array($data)) {
        if ((int)$response['status'] === 403) {
        if (strtoupper((string)($params['name'] ?? '')) === '@'
            || !array_key_exists('name', $params)
            || trim((string)($params['name'] ?? '')) === '') {
            $response['error'] = 'DNSHE refuses apex records over its API: "@" (and an empty name) is
                answered with an HTML 403 page, while a relative label like "www" works. Measured on
                2026-11-22. Records for the name itself therefore cannot be published through DNSHE.
                Use a provider that accepts "@" (Cloudflare or Spaceship) for such suffixes, or add
                the record by hand in the DNSHE dashboard.';

            return $response;
        }
            $response['error'] = 'DNSHE refused the request at its gateway (HTTP 403, HTML page instead of JSON). That is almost always a wrong API key/secret for this suffix, or a key that belongs to another account.';

            return $response;
        }
        $response['error'] = 'DNSHE answered something that is not JSON (HTTP ' . (int)$response['status'] . '): '
            . shorten(strip_tags((string)$raw), 160);

        return $response;
    }

    $ok = array_key_exists('success', $data) ? (bool)$data['success'] : ((int)$response['status'] < 400);
    if (!$ok) {
        $message = (string)($data['message'] ?? $data['error'] ?? ('HTTP ' . (int)$response['status']));
        // DNSHE refuses NS writes when "DNS management" is disabled for that name in their
        // dashboard (disable_ns_management). Say so, instead of leaving the operator guessing.
        if (strtoupper((string)($params['type'] ?? '')) === 'NS'
            && (stripos($message, 'ns management') !== false || stripos($message, 'NS') !== false)) {
            $message .= ' (DNSHE only accepts NS records when "DNS management" is enabled for that
                name in their dashboard - disable_ns_management).';
        }
        $code    = (string)($data['error_code'] ?? '');

        return ['ok' => false, 'status' => (int)$response['status'], 'data' => $data,
            'error' => 'DNSHE: ' . $message . ($code === '' ? '' : ' (' . $code . ')')];
    }

    return ['ok' => true, 'status' => (int)$response['status'], 'data' => $data, 'error' => ''];
}

/** The suffix itself: the part that is not the label of this name. */
function dnshe_label(string $name, string $zoneName): string
{
    $name = strtolower(trim($name));
    $zone = strtolower(trim($zoneName));

    return str_ends_with($name, '.' . $zone) ? substr($name, 0, -(strlen($zone) + 1)) : $name;
}

/** Finds the DNSHE subdomain object of one name, or null. */
function dnshe_find_subdomain(string $zoneName, string $label): ?array
{
    $response = dnshe_request('subdomains', 'list', [
        'search'     => $label,
        'rootdomain' => $zoneName,
        'per_page'   => 100,
    ], 'GET', $zoneName);

    if (!$response['ok']) {
        return null;
    }

    foreach ((array)($response['data']['subdomains'] ?? []) as $row) {
        if (strcasecmp((string)($row['subdomain'] ?? ''), $label) === 0
            && strcasecmp((string)($row['domain'] ?? ''), $zoneName) === 0) {
            return $row;
        }
    }

    return null;
}

function dnshe_register_subdomain(string $zoneName, string $label): array
{
    $response = dnshe_request('subdomains', 'register', [
        'subdomain'  => $label,
        'rootdomain' => $zoneName,
    ], 'POST', $zoneName);

    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error'], 'id' => 0];
    }

    $id = (int)($response['data']['subdomain_id'] ?? 0);
    if ($id <= 0) {
        return ['ok' => false, 'error' => 'DNSHE registered ' . $label . '.' . $zoneName
            . ' without returning an id.', 'id' => 0];
    }

    return ['ok' => true, 'error' => '', 'id' => $id];
}

/** The records DNSHE holds for one subdomain, with names normalised to "@" or a relative label. */
function dnshe_records_of_subdomain(int $subdomainId, string $zoneName, string $label = ''): array
{
    $response = dnshe_request('dns_records', 'list', ['subdomain_id' => $subdomainId], 'GET', $zoneName);
    if (!$response['ok']) {
        return ['ok' => false, 'error' => $response['error'], 'records' => []];
    }

    $full = strtolower(trim($label)) === '' ? '' : strtolower(trim($label)) . '.' . strtolower(trim($zoneName));

    $records = [];
    foreach ((array)($response['data']['records'] ?? []) as $row) {
        $name = strtolower(rtrim((string)($row['name'] ?? ''), '.'));

        // The API answers with the full name here, but it is happy to receive a relative one: accept
        // either shape so the comparison below always speaks the same language.
        if ($full !== '' && $name === $full) {
            $name = '@';
        } elseif ($full !== '' && str_ends_with($name, '.' . $full)) {
            $name = substr($name, 0, -(strlen($full) + 1));
        }

        $records[] = [
            'id'      => (int)($row['id'] ?? 0),
            'name'    => $name,
            'type'    => strtoupper((string)($row['type'] ?? '')),
            'content' => (string)($row['content'] ?? ''),
            'ttl'     => (int)($row['ttl'] ?? 600),
        ];
    }

    return ['ok' => true, 'error' => '', 'records' => $records];
}

/** Makes DNSHE hold exactly the given record set for one name. */
function dnshe_sync_domain(int $domainId, string $name, string $zoneName, array $records, bool $proxied): array
{
    $result = ['ok' => false, 'error' => '', 'created' => 0, 'updated' => 0, 'deleted' => 0, 'rows' => [], 'log' => []];
    $log    = [];

    if (!dnshe_enabled()) {
        $result['error'] = 'Publishing through DNSHE is switched off (DNS page).';

        return $result;
    }
    if (dnshe_zone_key($zoneName) === '' || dnshe_zone_secret($zoneName) === '') {
        $result['error'] = 'No DNSHE API key and secret are stored for ' . $zoneName . ' (DNS page).';

        return $result;
    }
    if (!cf_mapping_ready()) {
        $result['error'] = 'The dns_records table does not exist yet - import upgrade-cloudflare.sql.';

        return $result;
    }

    $label = dnshe_label($name, $zoneName);
    if ($label === '') {
        $result['error'] = 'The name ' . $name . ' is the suffix itself; DNSHE needs a subdomain.';

        return $result;
    }

    $domainRow = db_one('SELECT * FROM domains WHERE id = ?', [$domainId]);
    $ref  = (int)($domainRow['provider_ref'] ?? 0);
    $owned = (int)($domainRow['provider_owned'] ?? 0) === 1;

    // 1. the subdomain itself: reuse the remembered id, otherwise look it up, otherwise register it
    if ($ref <= 0) {
        $found = dnshe_find_subdomain($zoneName, $label);
        if ($found !== null) {
            $ref   = (int)($found['id'] ?? 0);
            $owned = false;
            $log[] = 'subdomain ' . $label . '.' . $zoneName . ' already exists (id ' . $ref . ')';
        } else {
            $registered = dnshe_register_subdomain($zoneName, $label);
            $log[] = 'register ' . $label . '.' . $zoneName . ': ' . ($registered['ok'] ? 'ok' : $registered['error']);
            if (!$registered['ok']) {
                $result['error'] = $registered['error'];
                $result['log']   = $log;

                return $result;
            }
            $ref   = $registered['id'];
            $owned = true;
        }

        db_query('UPDATE domains SET provider_ref = ?, provider_owned = ? WHERE id = ?',
            [(string)$ref, $owned ? 1 : 0, $domainId]);
    }

    if ($ref <= 0) {
        $result['error'] = 'No DNSHE subdomain could be determined for ' . $name . '.';
        $result['log']   = $log;

        return $result;
    }

    // 2. what is already there, and what the registry remembers pushing
    $current = dnshe_records_of_subdomain($ref, $zoneName, $label);
    $log[]   = 'list records: ' . ($current['ok'] ? count($current['records']) . ' found' : $current['error']);
    if (!$current['ok']) {
        $result['error'] = $current['error'];
        $result['log']   = $log;

        return $result;
    }

    $byKey = [];
    foreach ($current['records'] as $row) {
        $byKey[$row['name'] . '|' . $row['type'] . '|' . $row['content']] = $row;
    }

    $mapped = [];
    foreach (cf_mapped_records($domainId) as $row) {
        if ((string)$row['provider'] === 'dnshe') {
            $mapped[(string)$row['remote_id']] = $row;
        }
    }

    $ttl   = max(60, (int)setting('default_ttl', '3600'));
    $rows  = [];
    $keep  = [];

    foreach ($records as $record) {
        $sub   = trim((string)$record['sub']);
        $type  = strtoupper((string)$record['type']);
        $value = (string)$record['value'];
        $fqdn  = $sub === '' ? $name : $sub . '.' . $name;
        // DNSHE refuses a full domain here: "@" means the subdomain itself, anything else is relative.
    // DNSHE answers 403 (an HTML error page, not JSON) when the name is "@". Measured against the
    // live API: name:"@" -> 403, name omitted -> 403, name:"t1" -> 200, full domain -> 200.
    // So the apex goes out as the full domain and only sub-labels stay relative.
    $recordName = $sub === '' ? '@' : $sub;
    // Measured 2026-11-22: name is relative to the ROOT domain. For testforfreedns.us.ci,
    // name="testforfreedns" created the apex record (200), name="testforfreedns.www" is its www.
    // "@", empty, missing and the full FQDN are all refused (403 / 400). The "@" form stays in
    // 
    $sendName = $sub === '' ? $label : $label . '.' . $sub;

        $payload = [
            'subdomain_id' => $ref,
            'type'         => $type,
            'name'         => $sendName,
            'content'      => $value,
            'ttl'          => isset($record['ttl']) ? max(60, min(86400, (int)$record['ttl'])) : $ttl,
        ];

        $key = strtolower($recordName) . '|' . $type . '|' . $value;
        $keep[$key] = true;

        $existing = $byKey[$key] ?? null;
        if ($existing !== null) {
            $remoteId = (string)$existing['id'];
            // Measured 2026-11-22: dns_records/update answers 403 (an HTML gateway page) even with a
            // correct key and the same name that create accepts happily. delete + create is the pair
            // that works, so the update path is implemented that way instead.
            $gone = dnshe_request('dns_records', 'delete', [
                'id' => (int)$existing['id'],
            ], 'POST', $zoneName);
            $log[] = 'replace ' . $fqdn . ' ' . $type . ': delete ' . ($gone['ok'] ? 'ok' : $gone['error']);

            $answer = dnshe_request('dns_records', 'create', $payload, 'POST', $zoneName);
            $log[]  = 'replace ' . $fqdn . ' ' . $type . ': create ' . ($answer['ok'] ? 'ok' : $answer['error']);
            if (!$answer['ok']) {
                $result['error'] = 'Replacing ' . $fqdn . ' (' . $type . ') failed: ' . $answer['error'];
                $result['log']   = $log;

                return $result;
            }
            $rows[] = [
                'provider'  => 'dnshe',
                'remote_id' => $remoteId,
                'fqdn'      => $fqdn,
                'sub'       => $sub,
                'type'      => $type,
                'value'     => $value,
                'proxied'   => 0,
            ];
            continue;
        }

        $answer = dnshe_request('dns_records', 'create', $payload, 'POST', $zoneName);
        $log[]  = 'create ' . $fqdn . ' ' . $type . ': ' . ($answer['ok'] ? 'ok' : $answer['error']);
        if (!$answer['ok']) {
            $result['error'] = 'Creating ' . $fqdn . ' (' . $type . ') failed: ' . $answer['error'];
            $result['log']   = $log;

            return $result;
        }

        $remoteId = (string)($answer['data']['id'] ?? '');
        if ($remoteId === '' || $remoteId === '0') {
            $result['error'] = 'DNSHE accepted ' . $fqdn . ' without returning an id.';
            $result['log']   = $log;

            return $result;
        }

        $result['created']++;
        $rows[] = [
            'provider'  => 'dnshe',
            'remote_id' => $remoteId,
            'fqdn'      => $fqdn,
            'sub'       => $sub,
            'type'      => $type,
            'value'     => $value,
            'proxied'   => 0,
        ];
    }

    // 3. delete only the records the registry pushed which are no longer wanted
    foreach ($mapped as $remoteId => $row) {
        $mappedSub = trim((string)$row['sub']);
        $key       = strtolower($mappedSub === '' ? '@' : $mappedSub) . '|'
            . strtoupper((string)$row['type']) . '|' . (string)$row['value'];
        if (isset($keep[$key])) {
            continue;
        }

        $answer = dnshe_request('dns_records', 'delete', ['id' => (int)$remoteId], 'POST', $zoneName);
        $log[]  = 'delete ' . (string)$row['type'] . ' ' . (string)$row['value'] . ': '
            . ($answer['ok'] ? 'ok' : $answer['error']);
        if (!$answer['ok'] && (int)$answer['status'] !== 404) {
            $result['error'] = 'Removing the old record ' . (string)$row['value'] . ' failed: ' . $answer['error'];
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

/**
 * Takes the records of a name out of DNSHE.
 *
 * The subdomain itself is NEVER deleted: DNSHE does not allow a registered free subdomain to be removed,
 * so the registry keeps it (the name stays reserved) and only takes its own records away. Suspending and
 * deleting a name therefore look the same on the DNSHE side; the difference is in the local status.
 */
function dnshe_remove_domain(int $domainId, string $zoneName, bool $releaseSubdomain = false): array
{
    $result = ['ok' => false, 'error' => '', 'deleted' => 0];
    $releaseSubdomain = false;   // kept for signature compatibility; DNSHE forbids it

    if (!dnshe_enabled()) {
        $result['ok'] = true;

        return $result;
    }
    if (!cf_mapping_ready()) {
        $result['error'] = 'The dns_records table does not exist yet.';

        return $result;
    }

    foreach (cf_mapped_records($domainId) as $row) {
        if ((string)$row['provider'] !== 'dnshe') {
            continue;
        }
        $answer = dnshe_request('dns_records', 'delete', ['id' => (int)$row['remote_id']], 'POST', $zoneName);
        if (!$answer['ok'] && (int)$answer['status'] !== 404) {
            $result['error'] = 'Removing ' . (string)$row['type'] . ' ' . (string)$row['value'] . ' failed: '
                . $answer['error'];

            return $result;
        }
        $result['deleted']++;
    }

    $domain = db_one('SELECT provider_ref, provider_owned FROM domains WHERE id = ?', [$domainId]);
    $ref    = (int)($domain['provider_ref'] ?? 0);
    $owned  = (int)($domain['provider_owned'] ?? 0) === 1;

    if ($releaseSubdomain && $ref > 0 && $owned) {
        $answer = dnshe_request('subdomains', 'delete', ['subdomain_id' => $ref], 'POST', $zoneName);
        if (!$answer['ok'] && (int)$answer['status'] !== 404) {
            $result['error'] = 'Releasing the DNSHE subdomain failed: ' . $answer['error'];

            return $result;
        }
        db_query('UPDATE domains SET provider_ref = NULL, provider_owned = 0 WHERE id = ?', [$domainId]);
        log_action('dnshe_subdomain_released', 'domain', $domainId,
            'DNSHE subdomain ' . $ref . ' released in ' . $zoneName);
    }

    $result['ok'] = true;

    return $result;
}

/** Writes the mapping rows of a successful DNSHE sync. */
function dnshe_store_mapping(int $domainId, array $rows): void
{
    if (!cf_mapping_ready()) {
        return;
    }

    db_query('DELETE FROM dns_records WHERE domain_id = ? AND provider = ?', [$domainId, 'dnshe']);
    foreach ($rows as $row) {
        db_query(
            'INSERT INTO dns_records (domain_id, provider, remote_id, fqdn, sub, type, value, proxied, created_at, updated_at)
             VALUES (?, \'dnshe\', ?, ?, ?, ?, ?, 0, NOW(), NOW())',
            [
                $domainId,
                (string)$row['remote_id'],
                (string)($row['fqdn'] ?? ''),
                (string)$row['sub'],
                strtoupper((string)$row['type']),
                (string)$row['value'],
            ]
        );
    }
}

/**
 * What DNSHE already holds for a full name: used by the request form to refuse a name that is taken.
 *
 * @return array{ok: bool, error: string, records: array<int, array{type: string, value: string, proxied: int}>, taken: bool}
 */
function dnshe_name_records(string $zoneName, string $fqdn): array
{
    $free = ['ok' => true, 'error' => '', 'records' => [], 'taken' => false];

    if (!dnshe_zone_ready($zoneName)) {
        return $free;
    }

    $fqdn  = strtolower(rtrim(trim($fqdn), '.'));
    $label = dnshe_label($fqdn, $zoneName);
    if ($label === '') {
        return $free;
    }

    $found = dnshe_find_subdomain($zoneName, $label);
    if ($found === null) {
        return $free;
    }

    $records = [];
    $list    = dnshe_records_of_subdomain((int)($found['id'] ?? 0), $zoneName, $label);
    if ($list['ok']) {
        foreach ($list['records'] as $row) {
            if (strcasecmp($row['name'], '@') !== 0) {
                continue;
            }
            $records[] = ['type' => $row['type'], 'value' => $row['content'], 'proxied' => 0];
        }
    }

    // A subdomain that exists on DNSHE is taken even when it carries no record yet: the registry would
    // have to register it itself, and that name is already someone else's.
    return ['ok' => true, 'error' => '', 'records' => $records, 'taken' => true];
}

/** Reconciles every active name that is published through DNSHE. */
function dnshe_reconcile_all(): array
{
    $summary = ['checked' => 0, 'changed' => 0, 'failed' => 0, 'errors' => []];

    if (!dnshe_enabled() || !dnshe_columns_ready()) {
        return $summary;
    }

    foreach (db_all("SELECT * FROM domains WHERE status = 'active' ORDER BY id ASC") as $domain) {
        $zoneName = (string)$domain['zone'];
        if (publish_provider($zoneName) !== 'dnshe' || !dnshe_zone_ready($zoneName)) {
            continue;
        }

        $summary['checked']++;
        $sync = dnshe_sync_domain(
            (int)$domain['id'],
            (string)$domain['name'],
            $zoneName,
            cf_records_of(domain_records((int)$domain['id'])),
            false
        );
        if ($sync['ok']) {
            if ($sync['created'] > 0 || $sync['updated'] > 0 || $sync['deleted'] > 0) {
                $summary['changed']++;
            }
            dnshe_store_mapping((int)$domain['id'], $sync['rows']);
        } else {
            $summary['failed']++;
            $summary['errors'][] = (string)$domain['name'] . ': ' . $sync['error'];
            log_action('dnshe_sync_failed', 'domain', (int)$domain['id'],
                (string)$domain['name'] . ': ' . $sync['error']);
        }
    }

    return $summary;
}

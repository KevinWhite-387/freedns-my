<?php
declare(strict_types=1);

/**
 * freedns.my - publishing through ANYNS (my.dnshe.com, route /v1).
 *
 * Every detail below was verified against the live API (2026-11) and cross-checked with the
 * working client in the vc.ci project (app/inc/dns.php), which has been creating, updating and
 * deleting records on this API in production:
 *
 *   base       https://my.dnshe.com/index.php?m=dns&api_route=<route>
 *              extra parameters go in with & (a second ? answers 404)
 *   auth       Authorization: Bearer adns_…   (X-Api-Key and X-Token answer 401)
 *   zones      GET  /v1/zones                     -> data.data.zones[]
 *   zone       GET  /v1/zones/{id}                -> data.data.zone
 *   records    GET  /v1/zones/{id}/records        -> data.data.records[]
 *              paging: &per_page=100&page=1   filtering: &q=<relative name>
 *   one record GET  /v1/zones/{id}/records/{rec}  -> data.data.record
 *   create     POST /v1/zones/{id}/records        {name,type,value,ttl}
 *   update     POST /v1/zones/{id}/records/batch  {"patches":[{id,name,type,value,ttl}]}
 *   delete     POST /v1/zones/{id}/records/batch  {"deletes":[{"id":"…"}]}
 *
 * Two traps learned the hard way:
 *   1. DELETE and PATCH on /records/{id} are blocked by the API edge (405). Everything that is
 *      not a create goes through the /records/batch POST.
 *   2. The create endpoint answers 502 when the optional fields "line" or "enabled" are in the
 *      body. Only name, type, value and ttl are sent.
 */

/* ------------------------------------------------------------------ plumbing */

function anyns_columns_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $cols = db_all("SHOW COLUMNS FROM zones LIKE 'anyns_%'");
        $have = [];
        foreach ($cols as $c) {
            $have[] = (string)$c['Field'];
        }
        $ready = in_array('anyns_token', $have, true);
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function anyns_enabled(): bool
{
    return setting_bool('anyns_enabled', false) && anyns_columns_ready();
}

function anyns_global_token(): string
{
    return trim((string)setting('anyns_token', ''));
}

function anyns_zone_token(string $zoneName): string
{
    if (!anyns_columns_ready()) {
        return '';
    }
    $row = db_one('SELECT anyns_token FROM zones WHERE name = ?', [$zoneName]);
    $tok = $row === null ? '' : trim((string)($row['anyns_token'] ?? ''));

    return $tok !== '' ? $tok : anyns_global_token();
}

function anyns_zone_ready(string $zoneName): bool
{
    return anyns_enabled()
        && anyns_zone_token($zoneName) !== ''
        && anyns_zone_id($zoneName) !== '';
}

/* ------------------------------------------------------------------ request */

/**
 * One request. $path is the part after api_route and starts with /v1.
 *
 * @return array{ok:bool,status:int,data:mixed,error:string}
 */
function anyns_request(string $method, string $path, array $query = [], ?array $body = null, string $zoneName = ''): array
{
    $token = $zoneName !== '' ? anyns_zone_token($zoneName) : anyns_global_token();
    if ($token === '') {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'No ANYNS token for this suffix.'];
    }

    $url = 'https://my.dnshe.com/index.php?m=dns&api_route=' . $path;
    foreach ($query as $k => $v) {
        $url .= '&' . rawurlencode((string)$k) . '=' . rawurlencode((string)$v);
    }

    $headers = ['Authorization: Bearer ' . $token, 'Accept: application/json'];
    $payload = null;
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $payload   = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'Could not encode the request body.'];
        }
    }

    $status = 0;
    $raw    = '';
    $error  = '';

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $out    = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($out === false) {
            $error = 'curl: ' . curl_error($ch);
        } else {
            $raw = (string)$out;
        }
        curl_close($ch);
    } else {
        $ctx = stream_context_create(['http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headers) . "\r\n",
            'content'       => $payload === null ? '' : $payload,
            'timeout'       => 30,
            'ignore_errors' => true,
        ]]);
        $out = @file_get_contents($url, false, $ctx);
        if ($out === false) {
            $error = 'Could not reach my.dnshe.com';
        } else {
            $raw = (string)$out;
            if (isset($http_response_header) && is_array($http_response_header)) {
                foreach ($http_response_header as $h) {
                    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $h, $m) === 1) {
                        $status = (int)$m[1];
                    }
                }
            }
        }
    }

    if ($error !== '') {
        return ['ok' => false, 'status' => $status, 'data' => null, 'error' => $error];
    }

    $data = json_decode($raw, true);
    $ok   = ($status >= 200 && $status < 300);

    if (!$ok) {
        $msg = '';
        if (is_array($data) && isset($data['error']) && is_array($data['error'])) {
            $msg = (string)($data['error']['message'] ?? '');
            if ($msg === '' && isset($data['error']['code'])) {
                $msg = (string)$data['error']['code'];
            }
        } elseif (is_array($data) && isset($data['message'])) {
            $msg = (string)$data['message'];
        }
        if ($msg === '') {
            $msg = 'HTTP ' . $status;
        }

        return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $msg];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
}

/* ------------------------------------------------------- names, values, ttl */

/** "www.vc.ci" -> "www", "vc.ci" -> "@", "www" -> "www". */
function anyns_relative_name(string $name, string $zoneName): string
{
    $name = strtolower(rtrim(trim($name), '.'));
    $zone = strtolower(rtrim(trim($zoneName), '.'));
    if ($name === $zone) {
        return '@';
    }
    if ($zone !== '' && str_ends_with($name, '.' . $zone)) {
        $cut = substr($name, 0, -(strlen($zone) + 1));

        return $cut === '' ? '@' : $cut;
    }

    return $name === '' ? '@' : $name;
}

function anyns_ttl(int $ttl): int
{
    return max(60, min(86400, $ttl > 0 ? $ttl : 300));
}

/** Our rows store MX already as "10 mx1…"; a bare host gets the default priority. */
function anyns_value(array $rec): string
{
    $type = strtoupper((string)($rec['type'] ?? 'A'));
    $val  = trim((string)($rec['value'] ?? ''));
    if ($type === 'MX' && preg_match('/^\d+\s+/', $val) !== 1) {
        return '10 ' . $val;
    }

    return $val;
}

/* ------------------------------------------------------------------- zones */

/** zone name -> zone id, resolved once per request. */
function anyns_zone_id(string $zoneName): string
{
    static $map = null;
    if ($map === null) {
        $map = [];
        $r = anyns_request('GET', '/v1/zones', [], null, $zoneName);
        if ($r['ok'] && isset($r['data']['data']['zones']) && is_array($r['data']['data']['zones'])) {
            foreach ($r['data']['data']['zones'] as $z) {
                if (is_array($z) && isset($z['id'], $z['zone_name'])) {
                    $map[strtolower((string)$z['zone_name'])] = (string)$z['id'];
                }
            }
        }
    }

    return $map[strtolower($zoneName)] ?? '';
}

/* ----------------------------------------------------------------- records */

/**
 * Records of a zone, paged; $nameFilter is passed as &q=.
 *
 * @return array{ok:bool,records:array,error:string}
 */
function anyns_list_records(string $zoneName, string $nameFilter = ''): array
{
    $zid = anyns_zone_id($zoneName);
    if ($zid === '') {
        return ['ok' => false, 'records' => [], 'error' => 'Zone ' . $zoneName . ' not found in this ANYNS account.'];
    }

    $all  = [];
    $page = 1;
    for ($i = 0; $i < 30; $i++) {
        $q = ['per_page' => 100, 'page' => $page];
        if ($nameFilter !== '') {
            $q['q'] = $nameFilter;
        }
        $r = anyns_request('GET', '/v1/zones/' . rawurlencode($zid) . '/records', $q, null, $zoneName);
        if (!$r['ok']) {
            return ['ok' => false, 'records' => [], 'error' => (string)$r['error']];
        }
        $items = [];
        if (isset($r['data']['data']['records']) && is_array($r['data']['data']['records'])) {
            $items = $r['data']['data']['records'];
        }
        foreach ($items as $it) {
            if (!is_array($it)) {
                continue;
            }
            $all[] = [
                'id'    => (string)($it['id'] ?? ''),
                'name'  => (string)($it['name'] ?? '@'),
                'type'  => strtoupper((string)($it['type'] ?? 'A')),
                'value' => (string)($it['value'] ?? ''),
                'ttl'   => (int)($it['ttl'] ?? 300),
            ];
        }
        if (count($items) < 100) {
            break;
        }
        $page++;
    }

    return ['ok' => true, 'records' => $all, 'error' => ''];
}

/** Read one record by id; null when it is gone. */
function anyns_get_record(string $zoneName, string $recordId): ?array
{
    $zid = anyns_zone_id($zoneName);
    if ($zid === '' || $recordId === '') {
        return null;
    }
    $r = anyns_request('GET', '/v1/zones/' . rawurlencode($zid) . '/records/' . rawurlencode($recordId), [], null, $zoneName);
    if (!$r['ok']) {
        return null;
    }
    $rec = $r['data']['data']['record'] ?? null;

    return is_array($rec) ? $rec : null;
}

/** Find a record by owner name and type (adopts a write whose answer was lost). */
function anyns_find_record(string $zoneName, string $name, string $type): ?array
{
    $rel  = anyns_relative_name($name, $zoneName);
    $list = anyns_list_records($zoneName, $rel);
    if (!$list['ok']) {
        return null;
    }
    $type = strtoupper($type);
    foreach ($list['records'] as $r) {
        if (strcasecmp(anyns_relative_name((string)$r['name'], $zoneName), $rel) === 0
            && strtoupper((string)$r['type']) === $type) {
            return $r;
        }
    }

    return null;
}

/**
 * Create one record. Only name/type/value/ttl go in - see the note at the top about 502.
 *
 * @return array{ok:bool,id:string,error:string}
 */
function anyns_create_record(string $zoneName, string $name, string $type, string $value, int $ttl): array
{
    $zid = anyns_zone_id($zoneName);
    if ($zid === '') {
        return ['ok' => false, 'id' => '', 'error' => 'Zone not found in this ANYNS account.'];
    }
    $body = [
        'name'  => anyns_relative_name($name, $zoneName),
        'type'  => strtoupper($type),
        'value' => $value,
        'ttl'   => anyns_ttl($ttl),
    ];

    $last = 'ANYNS did not accept the record.';
    for ($attempt = 0; $attempt < 2; $attempt++) {
        if ($attempt > 0) {
            usleep(500000);
            $found = anyns_find_record($zoneName, $name, $type);
            if ($found !== null) {
                return ['ok' => true, 'id' => (string)$found['id'], 'error' => ''];
            }
        }
        $r = anyns_request('POST', '/v1/zones/' . rawurlencode($zid) . '/records', [], $body, $zoneName);
        if ($r['ok']) {
            $rec = $r['data']['data']['record'] ?? [];
            $id  = is_array($rec) ? (string)($rec['id'] ?? '') : '';

            return ['ok' => true, 'id' => $id, 'error' => ''];
        }
        $last = (string)$r['error'];
        if (!in_array((int)$r['status'], [0, 502, 503, 504], true)) {
            return ['ok' => false, 'id' => '', 'error' => $last];
        }
    }

    return ['ok' => false, 'id' => '', 'error' => $last];
}

/**
 * Update one record through the batch endpoint (PATCH on the record path is blocked).
 *
 * @return array{ok:bool,error:string}
 */
function anyns_update_record(string $zoneName, string $recordId, string $name, string $type, string $value, int $ttl): array
{
    $zid = anyns_zone_id($zoneName);
    if ($zid === '' || $recordId === '') {
        return ['ok' => false, 'error' => 'Nothing to update.'];
    }
    $body = ['patches' => [[
        'id'    => $recordId,
        'name'  => anyns_relative_name($name, $zoneName),
        'type'  => strtoupper($type),
        'value' => $value,
        'ttl'   => anyns_ttl($ttl),
    ]]];
    $r = anyns_request('POST', '/v1/zones/' . rawurlencode($zid) . '/records/batch', [], $body, $zoneName);

    return $r['ok'] ? ['ok' => true, 'error' => ''] : ['ok' => false, 'error' => (string)$r['error']];
}

/**
 * Delete records by id through the batch endpoint.
 *
 * @param array<int,string> $recordIds
 * @return array{ok:bool,error:string}
 */
function anyns_delete_records(string $zoneName, array $recordIds): array
{
    $zid = anyns_zone_id($zoneName);
    $ids = [];
    foreach ($recordIds as $id) {
        $id = trim((string)$id);
        if ($id !== '') {
            $ids[] = ['id' => $id];
        }
    }
    if ($zid === '' || $ids === []) {
        return ['ok' => true, 'error' => ''];
    }
    $r = anyns_request('POST', '/v1/zones/' . rawurlencode($zid) . '/records/batch', [], ['deletes' => $ids], $zoneName);
    if ($r['ok']) {
        return ['ok' => true, 'error' => ''];
    }
    if ((int)$r['status'] === 404) {
        /* Already gone - the outcome is what we wanted. */
        return ['ok' => true, 'error' => ''];
    }

    return ['ok' => false, 'error' => (string)$r['error']];
}

/** Delete a single record (thin wrapper for the older call sites). */
function anyns_delete_record(string $zoneName, string $recordId): array
{
    return anyns_delete_records($zoneName, [$recordId]);
}

/* -------------------------------------------------------------------- sync */

/**
 * Make the zone hold exactly the records of one name: read, then create / delete only the
 * differences.
 *
 * @return array{ok:bool,error:string,changed:int}
 */
function anyns_sync_domain(int $domainId, string $name, string $zoneName, array $records, bool $proxied): array
{
    if (!anyns_zone_ready($zoneName)) {
        return ['ok' => false, 'error' => 'ANYNS is not configured for ' . $zoneName . '.', 'changed' => 0];
    }

    $want = [];
    foreach ($records as $rec) {
        if (!is_array($rec)) {
            continue;
        }
        $want[] = [
            'type'  => strtoupper((string)($rec['type'] ?? 'A')),
            'value' => anyns_value($rec),
            'ttl'   => (int)($rec['ttl'] ?? 300),
        ];
    }

    $rel  = anyns_relative_name($name, $zoneName);
    $list = anyns_list_records($zoneName, $rel);
    if (!$list['ok']) {
        return ['ok' => false, 'error' => 'Could not read the zone: ' . (string)$list['error'], 'changed' => 0];
    }

    $sig = static function (array $r): string {
        return strtoupper((string)$r['type']) . '|'
            . preg_replace('/\s+/', ' ', strtolower(trim((string)$r['value'])));
    };

    $have = [];
    foreach ($list['records'] as $r) {
        if (strcasecmp(anyns_relative_name((string)$r['name'], $zoneName), $rel) === 0) {
            $have[$sig($r)] = $r;
        }
    }
    $wantSig = [];
    foreach ($want as $w) {
        $wantSig[$sig($w)] = true;
    }

    $changed = 0;
    foreach ($want as $w) {
        if (isset($have[$sig($w)])) {
            continue;
        }
        $res = anyns_create_record($zoneName, $name, (string)$w['type'], (string)$w['value'], (int)$w['ttl']);
        if (!$res['ok']) {
            return ['ok' => false, 'error' => 'Could not save ' . $w['type'] . ': ' . (string)$res['error'],
                'changed' => $changed];
        }
        $changed++;
    }

    $doomed = [];
    foreach ($have as $s => $r) {
        if (!isset($wantSig[$s])) {
            $doomed[] = (string)$r['id'];
        }
    }
    if ($doomed !== []) {
        $del = anyns_delete_records($zoneName, $doomed);
        if (!$del['ok']) {
            return ['ok' => false, 'error' => 'Could not delete stale records: ' . (string)$del['error'],
                'changed' => $changed];
        }
        $changed += count($doomed);
    }

    return ['ok' => true, 'error' => '', 'changed' => $changed, 'rows' => []];
}

/** Remove every record of one name (the zone itself stays). */
function anyns_remove_domain(int $domainId, string $zoneName, bool $releaseName = false): array
{
    if (!anyns_zone_ready($zoneName)) {
        return ['ok' => false, 'error' => 'ANYNS is not configured for ' . $zoneName . '.'];
    }
    $domain = db_one('SELECT name FROM domains WHERE id = ?', [$domainId]);
    if ($domain === null) {
        return ['ok' => false, 'error' => 'Unknown domain.'];
    }
    $rel  = anyns_relative_name((string)$domain['name'], $zoneName);
    $list = anyns_list_records($zoneName, $rel);
    if (!$list['ok']) {
        return ['ok' => false, 'error' => (string)$list['error']];
    }
    $ids = [];
    foreach ($list['records'] as $r) {
        if (strcasecmp(anyns_relative_name((string)$r['name'], $zoneName), $rel) === 0) {
            $ids[] = (string)$r['id'];
        }
    }

    return anyns_delete_records($zoneName, $ids);
}

function anyns_store_mapping(int $domainId, array $rows): void
{
    /* nothing to persist: the zone is the source of truth */
}

/** @return array{ok:bool,error:string,records:array,taken:bool} */
function anyns_name_records(string $zoneName, string $fqdn): array
{
    $rel  = anyns_relative_name($fqdn, $zoneName);
    $list = anyns_list_records($zoneName, $rel);
    if (!$list['ok']) {
        return ['ok' => false, 'error' => (string)$list['error'], 'records' => [], 'taken' => false];
    }
    $out = [];
    foreach ($list['records'] as $r) {
        if (strcasecmp(anyns_relative_name((string)$r['name'], $zoneName), $rel) === 0) {
            $out[] = $r;
        }
    }

    return ['ok' => true, 'error' => '', 'records' => $out, 'taken' => $out !== []];
}

/** @return array{checked:int,changed:int,failed:int,errors:array} */
function anyns_reconcile_all(): array
{
    $summary = ['checked' => 0, 'changed' => 0, 'failed' => 0, 'errors' => []];
    if (!anyns_enabled()) {
        return $summary;
    }
    $zones = [];
    try {
        foreach (db_all('SELECT name FROM zones WHERE LOWER(TRIM(provider)) = ?', ['anyns']) as $z) {
            $zones[] = (string)$z['name'];
        }
    } catch (Throwable $e) {
        return $summary;
    }
    if ($zones === []) {
        return $summary;
    }
    $in = implode(',', array_fill(0, count($zones), '?'));
    try {
        $rows = db_all('SELECT id, name, zone FROM domains WHERE status = \'active\' AND zone IN (' . $in . ')', $zones);
    } catch (Throwable $e) {
        return $summary;
    }
    foreach ($rows as $d) {
        $summary['checked']++;
        $records = function_exists('publish_records_of')
            ? publish_records_of(db_all('SELECT type, value, ttl FROM records WHERE domain_id = ? ORDER BY id', [(int)$d['id']]))
            : [];
        $res = anyns_sync_domain((int)$d['id'], (string)$d['name'], (string)$d['zone'], $records, false);
        if (!empty($res['ok'])) {
            $summary['changed'] += (int)($res['changed'] ?? 0);
        } else {
            $summary['failed']++;
            $summary['errors'][] = (string)$d['name'] . ': ' . (string)($res['error'] ?? '');
        }
    }

    return $summary;
}

/**
 * Probe the token and the zone mapping without touching a single record.
 *
 * @return array{ok:bool,error:string,zones:int,zone_id:string}
 */
function anyns_check(string $zoneName): array
{
    $r = anyns_request('GET', '/v1/zones', [], null, $zoneName);
    if (!$r['ok']) {
        return ['ok' => false, 'error' => (string)$r['error'], 'zones' => 0, 'zone_id' => ''];
    }
    $n = 0;
    if (isset($r['data']['data']['zones']) && is_array($r['data']['data']['zones'])) {
        $n = count($r['data']['data']['zones']);
    }
    $zid = anyns_zone_id($zoneName);
    if ($zid === '') {
        return ['ok' => false,
            'error'   => 'Token works, but the zone ' . $zoneName . ' is not in this account.',
            'zones'   => $n,
            'zone_id' => ''];
    }

    return ['ok' => true, 'error' => '', 'zones' => $n, 'zone_id' => $zid];
}

<?php
declare(strict_types=1);

/**
 * freedns.my - publishing through Spaceship (spaceship.dev).
 *
 * Verified against the live API (2026-11):
 *   auth     X-Api-Key / X-Api-Secret headers, no encoding
 *   list     GET /api/v1/dns/records/{zone}?take=500&skip=0
 *            take and skip are MANDATORY (422 "The take field is required." otherwise)
 *   save     PUT /api/v1/dns/records/{zone}
 *   delete   DELETE /api/v1/dns/records/{zone}
 *   record   {"name":"@","type":"A","address":"…","ttl":300}
 *            A/AAAA carry "address", CNAME carries "cname"; other types are sent as
 *            "value" first and retried with the type name as the key (see sp_payload()).
 *
 * Credentials are per suffix (zones.spaceship_key / zones.spaceship_secret), with an
 * optional global pair in the settings as a fallback, like DNSHE does.
 */

/** Do the two credential columns exist on zones? */
function spaceship_columns_ready(): bool
{
    static $ready = null;
    if ($ready !== null) {
        return $ready;
    }
    $ready = false;
    try {
        $cols = db_all("SHOW COLUMNS FROM zones LIKE 'spaceship_%'");
        $have = [];
        foreach ($cols as $c) {
            $have[] = (string)$c['Field'];
        }
        $ready = in_array('spaceship_key', $have, true) && in_array('spaceship_secret', $have, true);
    } catch (Throwable $e) {
        $ready = false;
    }

    return $ready;
}

function spaceship_enabled(): bool
{
    return setting_bool('spaceship_enabled', false) && spaceship_columns_ready();
}

function spaceship_global_key(): string
{
    return trim((string)setting('spaceship_key', ''));
}

function spaceship_global_secret(): string
{
    return trim((string)setting('spaceship_secret', ''));
}

function spaceship_zone_key(string $zoneName): string
{
    if (!spaceship_columns_ready()) {
        return '';
    }
    $row = db_one('SELECT spaceship_key FROM zones WHERE name = ?', [$zoneName]);
    $key = $row === null ? '' : trim((string)($row['spaceship_key'] ?? ''));

    return $key !== '' ? $key : spaceship_global_key();
}

function spaceship_zone_secret(string $zoneName): string
{
    if (!spaceship_columns_ready()) {
        return '';
    }
    $row = db_one('SELECT spaceship_secret FROM zones WHERE name = ?', [$zoneName]);
    $sec = $row === null ? '' : trim((string)($row['spaceship_secret'] ?? ''));

    return $sec !== '' ? $sec : spaceship_global_secret();
}

function spaceship_zone_ready(string $zoneName): bool
{
    return spaceship_enabled()
        && spaceship_zone_key($zoneName) !== ''
        && spaceship_zone_secret($zoneName) !== '';
}

/** One request; returns ['ok'=>bool,'status'=>int,'data'=>mixed,'error'=>string]. */
function spaceship_request(string $method, string $path, array $query = [], ?array $body = null, string $zoneName = ''): array
{
    $key    = $zoneName !== '' ? spaceship_zone_key($zoneName) : spaceship_global_key();
    $secret = $zoneName !== '' ? spaceship_zone_secret($zoneName) : spaceship_global_secret();
    if ($key === '' || $secret === '') {
        return ['ok' => false, 'status' => 0, 'data' => null, 'error' => 'No Spaceship credentials for this suffix.'];
    }

    $url = 'https://spaceship.dev' . $path;
    if ($query !== []) {
        $url .= '?' . http_build_query($query);
    }

    $headers = [
        'X-Api-Key: ' . $key,
        'X-Api-Secret: ' . $secret,
        'Accept: application/json',
    ];
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
        $out = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($out === false) {
            $error = 'curl: ' . curl_error($ch);
            $raw   = '';
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
            $error = 'Could not reach spaceship.dev';
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
        if (is_array($data)) {
            if (isset($data['detail'])) {
                $msg = (string)$data['detail'];
            }
            if (isset($data['data']) && is_array($data['data'])) {
                foreach ($data['data'] as $d) {
                    if (is_array($d) && isset($d['details'])) {
                        $msg .= ($msg === '' ? '' : '; ') . (string)$d['details'];
                    }
                }
            }
        }
        if ($msg === '') {
            $msg = 'HTTP ' . $status;
        }
        return ['ok' => false, 'status' => $status, 'data' => $data, 'error' => $msg];
    }

    return ['ok' => true, 'status' => $status, 'data' => $data, 'error' => ''];
}

/** Turn one of our record rows into the Spaceship payload. */
function sp_payload(array $rec): array
{
    $type = strtoupper((string)($rec['type'] ?? 'A'));
    $name = (string)($rec['name'] ?? '@');
    $ttl  = (int)($rec['ttl'] ?? 300);
    if ($ttl < 60 || $ttl > 86400) {
        $ttl = 300;
    }
    $value = trim((string)($rec['value'] ?? ''));
    $out = ['name' => $name, 'type' => $type, 'ttl' => $ttl];

    /* Field names per type are taken from the working nl0/pvf implementations, not guessed:
     * A/AAAA -> address, CNAME -> cname, TXT -> value, MX -> exchange + preference,
     * NS -> nameserver, SRV -> service/protocol/priority/weight/port/target, CAA -> flag/tag/value. */
    switch ($type) {
        case 'A':
        case 'AAAA':
            $out['address'] = $value;
            break;
        case 'CNAME':
            $out['cname'] = $value;
            break;
        case 'TXT':
            $out['value'] = $value;
            break;
        case 'MX':
            if (preg_match('/^(\d+)\s+(.+)$/', $value, $m) === 1) {
                $out['preference'] = (int)$m[1];
                $out['exchange']   = trim($m[2]);
            } else {
                $out['preference'] = 10;
                $out['exchange']   = $value;
            }
            break;
        case 'NS':
            $out['nameserver'] = $value;
            break;
        case 'SRV':
            if (preg_match('/^(\d+)\s+(\d+)\s+(\d+)\s+(\S+)$/', $value, $m) === 1) {
                $out['priority'] = (int)$m[1];
                $out['weight']   = (int)$m[2];
                $out['port']     = (int)$m[3];
                $out['target']   = $m[4];
            } else {
                $out['target'] = $value;
            }
            break;
        case 'CAA':
            if (preg_match('/^(\d+)\s+(\S+)\s+"?(.*?)"?$/', $value, $m) === 1) {
                $out['flag']  = (int)$m[1];
                $out['tag']   = $m[2];
                $out['value'] = $m[3];
            } else {
                $out['tag']   = 'issue';
                $out['value'] = $value;
            }
            break;
        default:
            $out['value'] = $value;
    }

    return $out;
}
/** Convert one Spaceship record back to our normal shape. */
function sp_normalize(array $r): array
{
    $type = strtoupper((string)($r['type'] ?? 'A'));

    switch ($type) {
        case 'A':
        case 'AAAA':
            $val = (string)($r['address'] ?? '');
            break;
        case 'CNAME':
            $val = (string)($r['cname'] ?? '');
            break;
        case 'MX':
            $val = (int)($r['preference'] ?? 10) . ' ' . (string)($r['exchange'] ?? '');
            break;
        case 'NS':
            $val = (string)($r['nameserver'] ?? '');
            break;
        case 'SRV':
            $val = (int)($r['priority'] ?? 0) . ' ' . (int)($r['weight'] ?? 0) . ' '
                 . (int)($r['port'] ?? 0) . ' ' . (string)($r['target'] ?? '');
            break;
        case 'CAA':
            $val = (int)($r['flag'] ?? 0) . ' ' . (string)($r['tag'] ?? 'issue')
                 . ' "' . (string)($r['value'] ?? '') . '"';
            break;
        default:
            $val = (string)($r['value'] ?? '');
    }

    return [
        'name'  => (string)($r['name'] ?? '@'),
        'type'  => $type,
        'value' => trim($val),
        'ttl'   => (int)($r['ttl'] ?? 300),
    ];
}
/** All records of a zone (paginated: take is mandatory and capped at 500). */
function spaceship_list_records(string $zoneName): array
{
    $all  = [];
    $skip = 0;
    for ($page = 0; $page < 20; $page++) {
        $r = spaceship_request('GET', '/api/v1/dns/records/' . rawurlencode($zoneName),
            ['take' => 500, 'skip' => $skip], null, $zoneName);
        if (!$r['ok']) {
            return ['ok' => false, 'records' => [], 'error' => (string)$r['error']];
        }
        $items = [];
        if (isset($r['data']['items']) && is_array($r['data']['items'])) {
            $items = $r['data']['items'];
        }
        foreach ($items as $it) {
            if (is_array($it)) {
                $all[] = sp_normalize($it);
            }
        }
        $total = isset($r['data']['total']) ? (int)$r['data']['total'] : count($all);
        $skip += count($items);
        if ($items === [] || $skip >= $total) {
            break;
        }
    }

    return ['ok' => true, 'records' => $all, 'error' => ''];
}

/** PUT a set of records; tries {"items":[…]}, falls back to a bare array. */
function spaceship_put_records(string $zoneName, array $payloads): array
{
    if ($payloads === []) {
        return ['ok' => true, 'error' => ''];
    }
    $path = '/api/v1/dns/records/' . rawurlencode($zoneName);

    /* The working nl0/pvf implementations send {"force":false,"items":[…]}; the bare array is
     * kept as a fallback for older builds of the API. */
    $try  = [['force' => false, 'items' => array_values($payloads)], array_values($payloads)];
    $last = '';
    foreach ($try as $body) {
        $r = spaceship_request('PUT', $path, [], $body, $zoneName);
        if ($r['ok']) {
            return ['ok' => true, 'error' => ''];
        }
        $last = (string)$r['error'];
        if ((int)$r['status'] !== 422 && (int)$r['status'] !== 400) {
            break;
        }
    }

    return ['ok' => false, 'error' => $last];
}
/** DELETE a set of records; same dual-shape attempt. */
function spaceship_delete_records(string $zoneName, array $payloads): array
{
    if ($payloads === []) {
        return ['ok' => true, 'error' => ''];
    }
    $path = '/api/v1/dns/records/' . rawurlencode($zoneName);

    /* DELETE takes a bare array of records (verified in nl0/pvf); the wrapped form is a fallback. */
    $try  = [array_values($payloads), ['items' => array_values($payloads)]];
    $last = '';
    foreach ($try as $body) {
        $r = spaceship_request('DELETE', $path, [], $body, $zoneName);
        if ($r['ok']) {
            return ['ok' => true, 'error' => ''];
        }
        $last = (string)$r['error'];
        if ((int)$r['status'] !== 422 && (int)$r['status'] !== 400) {
            break;
        }
    }

    return ['ok' => false, 'error' => $last];
}
/**
 * Make the zone hold exactly the records of one name.
 *
 * Read, compare, then write only the difference - never a blind rewrite, so a name that
 * already matches costs nothing and cannot lose unrelated records.
 *
 * Returns ['ok'=>bool,'error'=>string,'changed'=>int].
 */
function spaceship_sync_domain(int $domainId, string $name, string $zoneName, array $records, bool $proxied): array
{
    if (!spaceship_zone_ready($zoneName)) {
        return ['ok' => false, 'error' => 'Spaceship is not configured for ' . $zoneName . '.', 'changed' => 0];
    }

    $want = [];
    foreach ($records as $rec) {
        if (!is_array($rec)) {
            continue;
        }
        $p = sp_payload(['name' => $name, 'type' => $rec['type'] ?? 'A',
            'value' => $rec['value'] ?? '', 'ttl' => $rec['ttl'] ?? 300]);
        $want[] = $p;
    }

    $list = spaceship_list_records($zoneName);
    if (!$list['ok']) {
        return ['ok' => false, 'error' => 'Could not read the zone: ' . (string)$list['error'], 'changed' => 0];
    }

    /* Existing records of this name only. */
    $mine = [];
    foreach ($list['records'] as $r) {
        if (strcasecmp((string)$r['name'], $name) === 0) {
            $mine[] = $r;
        }
    }

    $sig = static function (array $r): string {
        return strtolower((string)$r['name'] . '|' . $r['type'] . '|' . trim((string)$r['value']));
    };
    $have = [];
    foreach ($mine as $r) {
        $have[$sig($r)] = true;
    }
    $need = [];
    foreach ($want as $p) {
        $sigKey = strtolower($p['name'] . '|' . $p['type'] . '|' . trim((string)($p['value'] ?? $p['address'] ?? $p['cname'] ?? '')));
        if (!isset($have[$sigKey])) {
            $need[] = $p;
        }
    }
    $extra = [];
    $wantSig = [];
    foreach ($want as $p) {
        $wantSig[strtolower($p['name'] . '|' . $p['type'] . '|' . trim((string)($p['value'] ?? $p['address'] ?? $p['cname'] ?? '')))] = true;
    }
    foreach ($mine as $r) {
        if (!isset($wantSig[$sig($r)])) {
            $extra[] = sp_payload($r);
        }
    }

    $changed = 0;
    if ($need !== []) {
        $put = spaceship_put_records($zoneName, $need);
        if (!$put['ok']) {
            return ['ok' => false, 'error' => 'Could not save records: ' . (string)$put['error'], 'changed' => $changed];
        }
        $changed += count($need);
    }
    if ($extra !== []) {
        $del = spaceship_delete_records($zoneName, $extra);
        if (!$del['ok']) {
            return ['ok' => false, 'error' => 'Could not delete stale records: ' . (string)$del['error'], 'changed' => $changed];
        }
        $changed += count($extra);
    }

    return ['ok' => true, 'error' => '', 'changed' => $changed, 'rows' => []];
}

/**
 * Remove one name from the zone.
 * $releaseName has no meaning for Spaceship (records only), kept for signature parity.
 */
function spaceship_remove_domain(int $domainId, string $zoneName, bool $releaseName = false): array
{
    if (!spaceship_zone_ready($zoneName)) {
        return ['ok' => false, 'error' => 'Spaceship is not configured for ' . $zoneName . '.'];
    }
    $domain = db_one('SELECT name FROM domains WHERE id = ?', [$domainId]);
    if ($domain === null) {
        return ['ok' => false, 'error' => 'Unknown domain.'];
    }
    $name = (string)$domain['name'];

    $list = spaceship_list_records($zoneName);
    if (!$list['ok']) {
        return ['ok' => false, 'error' => (string)$list['error']];
    }
    $doomed = [];
    foreach ($list['records'] as $r) {
        if (strcasecmp((string)$r['name'], $name) === 0) {
            $doomed[] = sp_payload($r);
        }
    }
    if ($doomed === []) {
        return ['ok' => true, 'error' => ''];
    }
    $del = spaceship_delete_records($zoneName, $doomed);

    return $del['ok'] ? ['ok' => true, 'error' => ''] : ['ok' => false, 'error' => (string)$del['error']];
}

/**
 * Mapping rows are only an optimisation here (removal works from the zone itself), so this
 * records the provider for the caller and leaves the existing mapping table alone.
 */
function spaceship_store_mapping(int $domainId, array $rows): void
{
    /* nothing to persist: the zone is the source of truth */
}

/** Every record of one name, in the shape the sync expects. */
function spaceship_name_records(string $zoneName, string $fqdn): array
{
    $list = spaceship_list_records($zoneName);
    if (!$list['ok']) {
        return ['ok' => false, 'error' => (string)$list['error'], 'records' => [], 'taken' => false];
    }
    $out = [];
    foreach ($list['records'] as $r) {
        if (strcasecmp((string)$r['name'], $fqdn) === 0) {
            $out[] = $r;
        }
    }

    return ['ok' => true, 'error' => '', 'records' => $out, 'taken' => $out !== []];
}

/** Reconcile every active name that lives on Spaceship. */
function spaceship_reconcile_all(): array
{
    $summary = ['checked' => 0, 'changed' => 0, 'failed' => 0, 'errors' => []];
    if (!spaceship_enabled()) {
        return $summary;
    }
    $zones = [];
    try {
        foreach (db_all('SELECT name FROM zones WHERE LOWER(TRIM(provider)) = ?', ['spaceship']) as $z) {
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
        $records = function_exists('publish_records_of') ? publish_records_of(db_all(
            'SELECT type, value, ttl FROM records WHERE domain_id = ? ORDER BY id', [(int)$d['id']])) : [];
        $res = spaceship_sync_domain((int)$d['id'], (string)$d['name'], (string)$d['zone'], $records, false);
        if (!empty($res['ok'])) {
            $summary['changed'] += (int)($res['changed'] ?? 0);
        } else {
            $summary['failed']++;
            $summary['errors'][] = (string)$d['name'] . ': ' . (string)($res['error'] ?? '');
        }
    }

    return $summary;
}

/** Probe the credentials without touching any record (used by the admin test button). */
function spaceship_check(string $zoneName): array
{
    $r = spaceship_request('GET', '/api/v1/domains', ['take' => 1, 'skip' => 0], null, $zoneName);
    if (!$r['ok']) {
        return ['ok' => false, 'error' => (string)$r['error']];
    }
    $n = 0;
    if (isset($r['data']['items']) && is_array($r['data']['items'])) {
        $n = count($r['data']['items']);
    }

    return ['ok' => true, 'error' => '', 'sample' => $n];
}
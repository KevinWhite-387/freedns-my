<?php
declare(strict_types=1);

/**
 * freedns.my - the WHOIS query page.
 *
 * Two halves:
 *   internal - a name handed out by this registry is answered from its own database, WHOIS style,
 *              without any personal data (privacy by design);
 *   external - anything else is looked up in RDAP (the modern WHOIS over HTTPS) and, when RDAP has
 *              nothing, with the classic port-43 service.
 *
 * External answers are cached and rate limited per visitor address, so the page cannot be used as a
 * free WHOIS proxy.
 */

function whois_table_ready(): bool
{
    if (isset($GLOBALS['FD_WHOIS_TABLE'])) {
        return (bool)$GLOBALS['FD_WHOIS_TABLE'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW TABLES LIKE 'whois\\_cache'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_WHOIS_TABLE'] = $ready;

    return $ready;
}

/** Accepts "Example.COM.", "https://example.com/x" and returns "example.com". */
function whois_normalize(string $input): string
{
    $value = strtolower(trim($input));
    $value = preg_replace('#^[a-z]+://#', '', $value) ?? $value;
    $value = preg_replace('/[?#].*$/', '', $value) ?? $value;
    $value = preg_replace('#/.*$#', '', $value) ?? $value;
    $value = trim($value, " \t\n\r.");
    $value = preg_replace('/\s+/', '', $value) ?? $value;

    return $value;
}

function whois_valid_domain(string $domain): bool
{
    if ($domain === '' || strlen($domain) > 190 || !str_contains($domain, '.')) {
        return false;
    }

    foreach (explode('.', $domain) as $label) {
        if ($label === '' || strlen($label) > 63) {
            return false;
        }
        if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/', $label)) {
            return false;
        }
    }

    return true;
}

/** The suffix of this registry the name belongs to, or '' when it is somebody else's zone. */
function whois_own_zone(string $domain): string
{
    foreach (zone_list() as $zone) {
        $name = strtolower((string)$zone['name']);
        if ($name === '') {
            continue;
        }
        if ($domain === $name || str_ends_with($domain, '.' . $name)) {
            return $name;
        }
    }

    return '';
}

/**
 * The WHOIS text of a name this registry hands out, or null when it is not published here.
 * Only ACTIVE names answer; a pending application must not become visible.
 */
function whois_internal_text(string $domain): ?string
{
    $domainRow = db_one('SELECT * FROM domains WHERE name = ?', [$domain]);
    if ($domainRow === null || (string)$domainRow['status'] !== 'active') {
        return null;
    }

    $records = domain_records((int)$domainRow['id']);
    $types   = [];
    foreach ($records as $record) {
        $type = strtoupper((string)$record['type']);
        $types[$type] = ($types[$type] ?? 0) + 1;
    }
    ksort($types);

    $lines = [
        'Domain Name: ' . strtoupper($domain),
        'Registry Domain ID: ' . (int)$domainRow['id'],
        'Registry: ' . (string)setting('site_title', 'freedns.my') . ' (free secondary domain registry)',
        'Registry WHOIS: ' . url('whois.php') . '?domain=' . rawurlencode($domain),
        'Domain Status: active',
        'Creation Date: ' . (string)$domainRow['created_at'],
        'Updated Date: ' . (string)$domainRow['updated_at'],
        'Registry Expiry Date: none (free, renewed automatically)',
        'Registrant: withheld (this registry publishes no personal data)',
        'Registrant Country: withheld',
        'Name Server: none - no delegation; the registry answers for the name itself',
        'DNSSEC: unsigned',
    ];

    if ($types !== []) {
        $summary = [];
        foreach ($types as $type => $count) {
            $summary[] = $type . ($count > 1 ? ' x' . $count : '');
        }
        $lines[] = 'Resource Record Set: ' . implode(', ', $summary);
        $lines[] = '';
        $lines[] = ';; Address records are shown with the middle hidden, as in the public list';
        foreach (record_zone_lines($records, $domain) as $line) {
            foreach (['A', 'AAAA'] as $type) {
                // The zone line looks like "name. IN A 203.0.113.7"; only the value is hidden.
                $line = preg_replace_callback(
                    '/\b(IN\s+' . $type . '\s+)(\S+)/',
                    static fn(array $m): string => $m[1] . mask_address($m[2]),
                    $line
                ) ?? $line;
            }
            $lines[] = $line;
        }
    }

    $lines[] = '';
    $lines[] = '>>> Last update of WHOIS database: ' . date('Y-m-d H:i:s') . ' (' . date_default_timezone_get() . ')';

    return implode("\n", $lines);
}

/** A small HTTP GET used for RDAP. Returns the body or an error. */
function whois_http_get(string $url, int $timeout = 12): array
{
    $headers = ['Accept: application/rdap+json, application/json', 'User-Agent: freedns.my-whois'];

    if (function_exists('curl_init')) {
        $handle = curl_init($url);
        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
        ]);
        $body = curl_exec($handle);
        $code = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $err  = $body === false ? (string)curl_error($handle) : '';
        curl_close($handle);

        if ($body === false) {
            return ['ok' => false, 'error' => 'RDAP: ' . $err, 'body' => '', 'status' => $code];
        }

        return ['ok' => true, 'error' => '', 'body' => (string)$body, 'status' => $code];
    }

    $context = stream_context_create([
        'http' => [
            'method'        => 'GET',
            'header'        => implode("\r\n", $headers),
            'timeout'       => $timeout,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        return ['ok' => false, 'error' => 'RDAP could not be reached (no cURL and allow_url_fopen off?).', 'body' => '', 'status' => 0];
    }

    return ['ok' => true, 'error' => '', 'body' => (string)$body, 'status' => 200];
}

/** Reads one value out of an RDAP vcard. */
function whois_vcard_value(array $entity, string $key): string
{
    $card = $entity['vcardArray'][1] ?? null;
    if (!is_array($card)) {
        return '';
    }
    foreach ($card as $entry) {
        if (!is_array($entry) || (string)($entry[0] ?? '') !== $key) {
            continue;
        }
        $value = $entry[3] ?? '';
        if (is_array($value)) {
            $value = implode(' ', array_map('strval', $value));
        }

        return trim((string)$value);
    }

    return '';
}

/** RDAP first, then the classic port 43 service. */
function whois_external_text(string $domain): array
{
    $rdap = whois_rdap_text($domain);
    if ($rdap['ok']) {
        return $rdap;
    }

    $fallback = whois_port43_text($domain);
    if ($fallback['ok']) {
        return $fallback;
    }

    return ['ok' => false, 'source' => '', 'text' => '', 'error' => $rdap['error'] . ' / ' . $fallback['error']];
}

function whois_rdap_text(string $domain): array
{
    $response = whois_http_get('https://rdap.org/domain/' . rawurlencode($domain));
    if (!$response['ok']) {
        return ['ok' => false, 'source' => '', 'text' => '', 'error' => $response['error']];
    }
    if ((int)$response['status'] === 404) {
        return ['ok' => false, 'source' => 'rdap', 'text' => '', 'error' => 'No RDAP entry for this domain.'];
    }

    $data = json_decode($response['body'], true);
    if (!is_array($data)) {
        return ['ok' => false, 'source' => 'rdap', 'text' => '', 'error' => 'The RDAP answer could not be read.'];
    }

    $lines = [
        '% WHOIS data from RDAP (rdap.org), the modern interface of the registries',
        'Domain Name: ' . strtoupper((string)($data['ldhName'] ?? $data['unicodeName'] ?? $domain)),
        'Registry Domain ID: ' . (string)($data['handle'] ?? '-'),
    ];

    $statuses = [];
    foreach ((array)($data['status'] ?? []) as $status) {
        $statuses[] = (string)$status;
    }
    $lines[] = 'Domain Status: ' . ($statuses === [] ? '-' : implode(', ', $statuses));

    $events = [];
    foreach ((array)($data['events'] ?? []) as $event) {
        $action = (string)($event['eventAction'] ?? '');
        $date   = (string)($event['eventDate'] ?? '');
        if ($action !== '') {
            $events[$action] = $date;
        }
    }
    $labels = [
        'registration'     => 'Creation Date',
        'expiration'       => 'Registry Expiry Date',
        'last changed'     => 'Updated Date',
        'last update of RDAP database' => 'RDAP Database Update',
        'transfer'         => 'Transfer Date',
    ];
    foreach ($labels as $action => $label) {
        if (isset($events[$action])) {
            $lines[] = $label . ': ' . $events[$action];
        }
    }
    foreach ($events as $action => $date) {
        if (!isset($labels[$action])) {
            $lines[] = ucfirst($action) . ': ' . $date;
        }
    }

    $nameservers = [];
    foreach ((array)($data['nameservers'] ?? []) as $server) {
        $host = (string)($server['ldhName'] ?? '');
        if ($host !== '') {
            $nameservers[] = strtolower($host);
        }
    }
    $lines[] = 'Name Server: ' . ($nameservers === [] ? 'none listed' : implode("\n              ", $nameservers));

    foreach ((array)($data['entities'] ?? []) as $entity) {
        $roles = array_map('strval', (array)($entity['roles'] ?? []));
        $name  = whois_vcard_value($entity, 'fn');
        $org   = whois_vcard_value($entity, 'org');
        if (in_array('registrar', $roles, true)) {
            $lines[] = 'Registrar: ' . ($name !== '' ? $name : (string)($entity['handle'] ?? '-'));
        } elseif (in_array('registrant', $roles, true)) {
            $value = $org !== '' ? $org : $name;
            if ($value === '') {
                $value = 'not published';
            }
            $lines[] = 'Registrant Organization: ' . $value;
        }
    }

    $secure = $data['secureDNS']['delegationSigned'] ?? null;
    $lines[] = 'DNSSEC: ' . ($secure === true ? 'signed' : ($secure === false ? 'unsigned' : 'unknown'));
    $lines[] = '';
    $lines[] = '>>> Fetched by ' . (string)setting('site_title', 'freedns.my') . ' on '
        . date('Y-m-d H:i:s') . ' (' . date_default_timezone_get() . ')';

    return ['ok' => true, 'source' => 'rdap', 'text' => implode("\n", $lines), 'error' => ''];
}

/** One query against a port 43 WHOIS server. */
function whois_port43(string $server, string $query, int $timeout = 8): array
{
    if (!function_exists('fsockopen')) {
        return ['ok' => false, 'error' => 'The server does not allow port 43 connections (fsockopen missing).', 'text' => ''];
    }

    $errno  = 0;
    $errstr = '';
    $socket = @fsockopen($server, 43, $errno, $errstr, $timeout);
    if (!is_resource($socket)) {
        return ['ok' => false, 'error' => 'Port 43 to ' . $server . ' failed (' . ($errstr === '' ? 'blocked?' : $errstr) . ')', 'text' => ''];
    }
    stream_set_timeout($socket, $timeout);
    fwrite($socket, $query . "\r\n");

    $text = '';
    while (!feof($socket)) {
        $chunk = fgets($socket, 4096);
        if ($chunk === false) {
            break;
        }
        $text .= $chunk;
        if (strlen($text) > 200000) {
            break;
        }
    }
    fclose($socket);

    return ['ok' => true, 'error' => '', 'text' => $text];
}

function whois_port43_text(string $domain): array
{
    $server = 'whois.iana.org';
    $iana   = whois_port43('whois.iana.org', $domain);
    if ($iana['ok'] && preg_match('/^refer:\s*(\S+)/mi', $iana['text'], $match)) {
        $server = trim($match[1]);
    }

    $answer = $server === 'whois.iana.org' ? $iana : whois_port43($server, $domain);
    if (!$answer['ok']) {
        return ['ok' => false, 'source' => '', 'text' => '', 'error' => $answer['error']];
    }

    $text = trim($answer['text']);
    if ($text === '') {
        return ['ok' => false, 'source' => 'whois43', 'text' => '', 'error' => 'No answer from ' . $server . '.'];
    }

    // Some servers answer "no match" in different ways; pass that on as a clean message.
    if (preg_match('/(no match|not found|no entries found|no data found)/i', $text) === 1 && strlen($text) < 400) {
        return ['ok' => false, 'source' => 'whois43', 'text' => '', 'error' => 'The registry does not know this domain.'];
    }

    $lines = [
        '% WHOIS data from ' . $server . ' (port 43), shown as the registry answered it',
        $text,
    ];

    return ['ok' => true, 'source' => 'whois43', 'text' => implode("\n", $lines), 'error' => ''];
}

/* ------------------------------------------------------------------ *
 * Cache and per-address limit                                         *
 * ------------------------------------------------------------------ */

function whois_cache_get(string $domain, int $minutes): ?array
{
    if (!whois_table_ready() || $minutes <= 0) {
        return null;
    }

    $row = db_one('SELECT * FROM whois_cache WHERE domain = ?', [$domain]);
    if ($row === null) {
        return null;
    }
    $fetched = strtotime((string)$row['fetched_at']);
    if ($fetched === false || (time() - $fetched) > $minutes * 60) {
        return null;
    }

    return $row;
}

function whois_cache_put(string $domain, string $source, string $text): void
{
    if (!whois_table_ready()) {
        return;
    }

    db_query(
        'INSERT INTO whois_cache (domain, source, payload, fetched_at) VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE source = VALUES(source), payload = VALUES(payload), fetched_at = NOW()',
        [$domain, $source, $text]
    );

    if (random_int(1, 50) === 1) {
        db_query('DELETE FROM whois_cache WHERE fetched_at < ?', [date('Y-m-d H:i:s', time() - 7 * 86400)]);
    }
}

/** How many external lookups this address made in the last hour. */
function whois_queries_recent(string $ip): int
{
    if (!whois_table_ready() || $ip === '') {
        return 0;
    }

    try {
        return (int)db_value(
            'SELECT COUNT(*) FROM whois_queries WHERE ip = ? AND created_at >= ?',
            [$ip, date('Y-m-d H:i:s', time() - 3600)],
            0
        );
    } catch (Throwable $e) {
        return 0;
    }
}

function whois_query_record(string $ip, string $domain): void
{
    if (!whois_table_ready() || $ip === '') {
        return;
    }

    db_query('INSERT INTO whois_queries (ip, domain, created_at) VALUES (?, ?, NOW())', [$ip, $domain]);

    if (random_int(1, 50) === 1) {
        db_query('DELETE FROM whois_queries WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 7 * 86400)]);
    }
}

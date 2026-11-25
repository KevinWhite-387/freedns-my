<?php
declare(strict_types=1);

/**
 * freedns.my - own name servers (delegation) for the suffixes whose upstream allows it.
 *
 * When a holder switches on his own name servers, the registry stops answering for that name: the
 * records it pushed upstream are removed and only the NS records (plus glue for name servers inside the
 * name itself) are published. From then on the name resolves through the holder's own DNS service.
 * Switching back removes the delegation - the records have to be entered again, because they were taken
 * down when the delegation started.
 */

/** True when the columns of this release exist. */
function ns_columns_ready(): bool
{
    if (isset($GLOBALS['FD_NS_COLS'])) {
        return (bool)$GLOBALS['FD_NS_COLS'];
    }

    $ready = false;
    try {
        $ready = db_one("SHOW COLUMNS FROM domains LIKE 'delegated'") !== null
            && db_one("SHOW COLUMNS FROM zones LIKE 'allow_ns'") !== null;
    } catch (Throwable $e) {
        $ready = false;
    }
    $GLOBALS['FD_NS_COLS'] = $ready;

    return $ready;
}

/** Does this suffix allow its holders to bring their own name servers? */
function ns_zone_allows(string $zoneName): bool
{
    $zone = zone_by_name($zoneName);

    return ns_columns_ready() && $zone !== null && (int)($zone['allow_ns'] ?? 0) === 1;
}

function ns_domain_delegated(int $domainId): bool
{
    if (!ns_columns_ready() || $domainId <= 0) {
        return false;
    }

    return (int)db_value('SELECT delegated FROM domains WHERE id = ?', [$domainId], 0) === 1;
}

/** The stored name servers of one name, as a list. */
function ns_of_domain(array $domain): array
{
    $out = [];
    foreach (preg_split('/[\s,;]+/', (string)($domain['nameservers'] ?? '')) ?: [] as $entry) {
        $entry = strtolower(trim($entry, " \t\n\r."));
        if ($entry !== '') {
            $out[] = $entry;
        }
    }

    return $out;
}

/** The glue records of one name: host => [ipv4, ipv6]. */
function ns_glue_of_domain(array $domain): array
{
    $out = [];
    foreach (preg_split('/[\r\n]+/', (string)($domain['glue'] ?? '')) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '=')) {
            continue;
        }
        [$host, $value] = explode('=', $line, 2);
        $host  = strtolower(trim($host, " \t."));
        $value = trim($value);
        if ($host === '' || $value === '') {
            continue;
        }
        $out[$host][] = $value;
    }

    return $out;
}

/**
 * Checks a submitted delegation and returns the cleaned data.
 *
 * @return array{ok: bool, error: string, nameservers: string, glue: string}
 */
function ns_validate(array $domain, array $nameservers, array $glueHosts, array $glueValues): array
{
    $name = strtolower((string)$domain['name']);
    $zone = strtolower((string)$domain['zone']);

    $clean = [];
    foreach ($nameservers as $entry) {
        $host = strtolower(trim((string)$entry, " \t\n\r."));
        if ($host === '') {
            continue;
        }
        if (!whois_valid_domain($host)) {
            return ['ok' => false, 'error' => t('val.ns_host', $host), 'nameservers' => '', 'glue' => ''];
        }
        if ($host === $name || !str_contains($host, '.')) {
            return ['ok' => false, 'error' => t('val.ns_host', $host), 'nameservers' => '', 'glue' => ''];
        }
        // Never let a name point back at this registry: that would resolve in a circle.
        if ($host === 'ns1.' . $zone || $host === 'ns2.' . $zone || whois_own_zone($host) !== '') {
            return ['ok' => false, 'error' => t('val.ns_ours'), 'nameservers' => '', 'glue' => ''];
        }
        if (!in_array($host, $clean, true)) {
            $clean[] = $host;
        }
    }

    if (count($clean) < 2) {
        return ['ok' => false, 'error' => t('val.ns_few'), 'nameservers' => '', 'glue' => ''];
    }
    if (count($clean) > 4) {
        return ['ok' => false, 'error' => t('val.ns_many'), 'nameservers' => '', 'glue' => ''];
    }

    // Glue: only for name servers that live inside the delegated name itself.
    $glueLines = [];
    foreach ($glueHosts as $index => $hostRaw) {
        $host  = strtolower(trim((string)$hostRaw, " \t\n\r."));
        $value = trim((string)($glueValues[$index] ?? ''));
        if ($host === '' && $value === '') {
            continue;
        }
        if ($host === '' || $value === '') {
            return ['ok' => false, 'error' => t('val.ns_glue_pair'), 'nameservers' => '', 'glue' => ''];
        }
        if (!str_ends_with($host, '.' . $name)) {
            return ['ok' => false, 'error' => t('val.ns_glue_inside', $host, $name), 'nameservers' => '', 'glue' => ''];
        }
        if (!in_array($host, $clean, true)) {
            return ['ok' => false, 'error' => t('val.ns_glue_listed', $host), 'nameservers' => '', 'glue' => ''];
        }
        $type = str_contains($value, ':') ? 'AAAA' : 'A';
        if ($type === 'A' && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return ['ok' => false, 'error' => t('val.ns_glue_ip', $value), 'nameservers' => '', 'glue' => ''];
        }
        if ($type === 'AAAA' && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return ['ok' => false, 'error' => t('val.ns_glue_ip', $value), 'nameservers' => '', 'glue' => ''];
        }
        $glueLines[] = $host . '=' . $value;
    }

    // A name server inside the name needs at least one glue address, otherwise nothing can find it.
    foreach ($clean as $host) {
        if (!str_ends_with($host, '.' . $name)) {
            continue;
        }
        $found = false;
        foreach ($glueLines as $line) {
            if (str_starts_with($line, $host . '=')) {
                $found = true;
            }
        }
        if (!$found) {
            return ['ok' => false, 'error' => t('val.ns_glue_needed', $host), 'nameservers' => '', 'glue' => ''];
        }
    }

    return ['ok' => true, 'error' => '', 'nameservers' => implode("\n", $clean), 'glue' => implode("\n", $glueLines)];
}

/** Publishes the delegation (NS records plus glue) and takes the registry's own records down. */
function ns_apply(array $domain, string $nameservers, string $glue): array
{
    $domainId = (int)$domain['id'];
    $name     = (string)$domain['name'];
    $zone     = (string)$domain['zone'];

    if (!ns_zone_allows($zone)) {
        return ['ok' => false, 'error' => t('val.ns_zone_off')];
    }
    if ((string)$domain['status'] !== 'active') {
        return ['ok' => false, 'error' => t('val.ns_not_active')];
    }
    if (!publish_zone_ready($zone)) {
        return ['ok' => false, 'error' => t('val.ns_publish')];
    }

    // The provider has to be able to publish NS records at all. DNSHE and ANYNS cannot, so a
    // delegation there would be saved and then silently dropped at publish time - refuse it here
    // instead, with a message that says why.
    $nsProvider = function_exists('publish_provider') ? publish_provider($zone) : '';
    $nsTypes    = function_exists('provider_record_types_for') ? provider_record_types_for($nsProvider) : [];
    if ($nsProvider !== '' && !in_array('NS', $nsTypes, true)) {
        return ['ok' => false, 'error' => t('val.ns_provider', publish_provider_label($nsProvider))];
    }
    // The delegation itself, in the shape the publishing layer expects.
    $records = [];
    foreach (preg_split('/[\r\n]+/', $nameservers) ?: [] as $host) {
        $host = trim($host);
        if ($host !== '') {
            $records[] = ['sub' => '', 'type' => 'NS', 'value' => $host];
        }
    }
    foreach (preg_split('/[\r\n]+/', $glue) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || !str_contains($line, '=')) {
            continue;
        }
        [$host, $value] = explode('=', $line, 2);
        $host = strtolower(trim($host, " \t."));
        if ($host === '' || !str_ends_with($host, '.' . strtolower($name))) {
            continue;
        }
        // The glue lives one label below the name: ns1.myname.example -> sub "ns1".
        $sub     = substr($host, 0, -(strlen($name) + 1));
        $records[] = [
            'sub'   => $sub,
            'type'  => str_contains($value, ':') ? 'AAAA' : 'A',
            'value' => trim($value),
        ];
    }

    $sync = publish_sync_domain($domainId, $name, $zone, $records, false);
    if (!$sync['ok']) {
        return ['ok' => false, 'error' => $sync['error'], 'log' => $sync['log'] ?? []];
    }

    publish_store_mapping($domainId, $sync['rows']);
    db_query('UPDATE domains SET delegated = 1, nameservers = ?, glue = ?, updated_at = NOW() WHERE id = ?',
        [$nameservers, $glue === '' ? null : $glue, $domainId]);
    log_action('ns_delegated', 'domain', $domainId,
        $name . ' -> ' . str_replace("\n", ', ', $nameservers));

    return ['ok' => true, 'error' => '', 'created' => (int)$sync['created'], 'updated' => (int)$sync['updated'],
        'deleted' => (int)$sync['deleted']];
}

/** Withdraws the delegation: the name returns to this registry, its records have to be filled in again. */
function ns_clear(array $domain): array
{
    $domainId = (int)$domain['id'];
    $name     = (string)$domain['name'];
    $zone     = (string)$domain['zone'];

    if (!ns_domain_delegated($domainId)) {
        return ['ok' => true, 'error' => ''];
    }

    // Publish the (empty) registry record set: that removes the NS and glue records we pushed.
    $sync = publish_sync_domain($domainId, $name, $zone, [], false);
    if (!$sync['ok']) {
        return ['ok' => false, 'error' => $sync['error']];
    }

    publish_forget_mapping($domainId);
    db_query('UPDATE domains SET delegated = 0, nameservers = NULL, glue = NULL, updated_at = NOW() WHERE id = ?',
        [$domainId]);
    log_action('ns_delegation_removed', 'domain', $domainId, $name . ' answers through this registry again');

    return ['ok' => true, 'error' => ''];
}

<?php
declare(strict_types=1);

/**
 * freedns.my - the publishing layer.
 *
 * Every suffix is published through one service, chosen per suffix on the Zones page:
 *
 *   cloudflare - the zone id plus an API token per suffix (or the general token), records are created
 *                and deleted in the zone by name;
 *   dnshe      - an API key plus secret per suffix (or the general pair); the registry registers the
 *                subdomain object first and then writes the records under it.
 *
 * The rest of the application only calls the publish_* functions here, so adding a third service means
 * writing one module and one branch, not touching the review queue.
 */

/** Which service publishes this suffix: 'cloudflare', 'dnshe' or '' (not published automatically). */
function publish_provider(string $zoneName): string
{
    $zone     = zone_by_name($zoneName);
    $explicit = $zone === null ? '' : strtolower(trim((string)($zone['provider'] ?? '')));

    if (in_array($explicit, ['cloudflare', 'dnshe', 'spaceship', 'anyns'], true)) {
        return $explicit;
    }

    // A suffix that was published before this release carries only a Cloudflare zone id.
    if ($zone !== null && trim((string)($zone['cf_zone_id'] ?? '')) !== '') {
        return 'cloudflare';
    }

    return '';
}

function publish_provider_label(string $provider): string
{
    if ($provider === 'cloudflare') {
        return 'Cloudflare';
    }
    if ($provider === 'dnshe') {
        return 'DNSHE';
    }
    if ($provider === 'spaceship') {
        return 'Spaceship';
    }
    if ($provider === 'anyns') {
        return 'ANYNS';
    }

    return 'not published automatically';
}

/** True when this suffix can be published: the chosen service is switched on and configured. */
function publish_zone_ready(string $zoneName): bool
{
    $provider = publish_provider($zoneName);

    if ($provider === 'cloudflare') {
        return cf_zone_ready($zoneName);
    }
    if ($provider === 'dnshe') {
        return dnshe_zone_ready($zoneName);
    }
    if ($provider === 'spaceship') {
        return spaceship_zone_ready($zoneName);
    }
    if ($provider === 'anyns') {
        return anyns_zone_ready($zoneName);
    }

    return false;
}

function publish_mapping_ready(): bool
{
    return cf_mapping_ready();
}

/** The records the registry remembers having pushed for one name (any service). */
function publish_mapped_records(int $domainId): array
{
    return cf_mapped_records($domainId);
}

function publish_forget_mapping(int $domainId): void
{
    cf_forget_mapping($domainId);
}

/**
 * Makes the chosen service hold exactly this record set.
 *
 * @param array<int, array{sub: string, type: string, value: string}> $records
 * @return array{ok: bool, error: string, created: int, updated: int, deleted: int, rows: array, log: array}
 */
function publish_sync_domain(int $domainId, string $name, string $zoneName, array $records, bool $proxied): array
{
    $filtered = publish_drop_unsupported($zoneName, $records);
    $records  = $filtered['records'];
    $sp = publish_provider($zoneName);
    if ($sp === 'dnshe') {
        return dnshe_sync_domain($domainId, $name, $zoneName, $records, $proxied);
    }
    if ($sp === 'spaceship') {
        return spaceship_sync_domain($domainId, $name, $zoneName, $records, $proxied);
    }
    if ($sp === 'anyns') {
        return anyns_sync_domain($domainId, $name, $zoneName, $records, $proxied);
    }

    return cf_sync_domain($domainId, $name, $zoneName, $records, $proxied ? true : false);
}

/**
 * Removes the records of a name. $releaseName additionally frees the name itself where the service has
 * such a notion (DNSHE subdomains) - used when a name is deleted, not when it is only suspended.
 */
function publish_remove_domain(int $domainId, string $zoneName, bool $releaseName = false): array
{
    $sp2 = publish_provider($zoneName);
    if ($sp2 === 'dnshe') {
        return dnshe_remove_domain($domainId, $zoneName, $releaseName);
    }
    if ($sp2 === 'spaceship') {
        return spaceship_remove_domain($domainId, $zoneName, $releaseName);
    }
    if ($sp2 === 'anyns') {
        return anyns_remove_domain($domainId, $zoneName, $releaseName);
    }

    return cf_remove_domain($domainId, $zoneName);
}

/** Writes the mapping rows of a successful sync (inside the caller's transaction). */
function publish_store_mapping(int $domainId, array $rows): void
{
    if ($rows === []) {
        return;
    }

    $provider = strtolower((string)($rows[0]['provider'] ?? ''));
    if ($provider === '') {
        // Cloudflare rows do not carry the key; the suffix decides.
        $domain   = db_one('SELECT zone FROM domains WHERE id = ?', [$domainId]);
        $provider = $domain === null ? 'cloudflare' : (publish_provider((string)$domain['zone']) ?: 'cloudflare');
    }

    if ($provider === 'spaceship') {
        spaceship_store_mapping($domainId, $rows);

        return;
    }
    if ($provider === 'anyns') {
        anyns_store_mapping($domainId, $rows);

        return;
    }
    if ($provider === 'dnshe') {
        dnshe_store_mapping($domainId, $rows);

        return;
    }

    cf_store_mapping($domainId, $rows);
}

/** The record set of one name in the shape the sync expects. */
function publish_records_of(array $records): array
{
    return cf_records_of($records);
}
/**
 * What the chosen service already holds for a full name: used by the request form to refuse a taken name.
 *
 * @return array{ok: bool, error: string, records: array, taken: bool}
 */
function publish_name_records(string $zoneName, string $fqdn): array
{
    $sp3 = publish_provider($zoneName);
    if ($sp3 === 'dnshe') {
        return dnshe_name_records($zoneName, $fqdn);
    }
    if ($sp3 === 'spaceship') {
        return spaceship_name_records($zoneName, $fqdn);
    }
    if ($sp3 === 'anyns') {
        return anyns_name_records($zoneName, $fqdn);
    }

    $result = cf_name_records($zoneName, $fqdn);
    $result['taken'] = ($result['records'] ?? []) !== [];

    return $result;
}

/** Publishes (or withdraws) one name according to its status; used by the reconcile runs. */
function publish_domain(int $domainId, array $domain): array
{
    $records = domain_records($domainId);

    if (cf_should_publish($domain)) {
        $result = publish_sync_domain(
            $domainId,
            (string)$domain['name'],
            (string)$domain['zone'],
            publish_records_of($records),
            !empty($domain['proxied'])
        );
        if ($result['ok']) {
            publish_store_mapping($domainId, $result['rows']);
        }

        return $result;
    }

    $result = publish_remove_domain($domainId, (string)$domain['zone'], (string)$domain['status'] === 'deleted');
    if ($result['ok']) {
        publish_forget_mapping($domainId);
    }

    return $result;
}

/** Reconciles every active name, through Cloudflare and through DNSHE. */
function publish_reconcile_all(): array
{
    $summary = ['ok' => true, 'checked' => 0, 'changed' => 0, 'failed' => 0, 'messages' => []];

    if (cf_enabled() && cf_mapping_ready()) {
        $cf = cf_reconcile_all();
        $summary['checked'] += (int)$cf['checked'];
        $summary['changed'] += (int)$cf['changed'];
        $summary['failed']  += (int)$cf['failed'];
        foreach ((array)($cf['messages'] ?? []) as $message) {
            $summary['messages'][] = 'Cloudflare: ' . $message;
        }
        if (empty($cf['ok'])) {
            $summary['ok'] = false;
        }
    }

    foreach ([['spaceship', 'Spaceship'], ['anyns', 'ANYNS']] as $sp4) {
        $fn = $sp4[0] . '_enabled';
        $rc = $sp4[0] . '_reconcile_all';
        if (function_exists($fn) && $fn() && function_exists($rc)) {
            $res = $rc();
            $summary['checked'] += (int)$res['checked'];
            $summary['changed'] += (int)$res['changed'];
            $summary['failed']  += (int)$res['failed'];
            foreach ((array)($res['errors'] ?? []) as $message) {
                $summary['messages'][] = $sp4[1] . ': ' . $message;
            }
            if ((int)$res['failed'] > 0) {
                $summary['ok'] = false;
            }
        }
    }
    if (dnshe_enabled() && dnshe_columns_ready()) {
        $dnshe = dnshe_reconcile_all();
        $summary['checked'] += (int)$dnshe['checked'];
        $summary['changed'] += (int)$dnshe['changed'];
        $summary['failed']  += (int)$dnshe['failed'];
        foreach ((array)($dnshe['errors'] ?? []) as $message) {
            $summary['messages'][] = 'DNSHE: ' . $message;
        }
        if ((int)$dnshe['failed'] > 0) {
            $summary['ok'] = false;
        }
    }

    if (!cf_enabled() && !dnshe_enabled()
        && !(function_exists('spaceship_enabled') && spaceship_enabled())
        && !(function_exists('anyns_enabled') && anyns_enabled())) {
        $summary['ok'] = false;
        $summary['messages'][] = 'No publishing service is switched on.';
    }

    return $summary;
}

/**
 * Drops records whose type the suffix's provider does not accept, and logs each one.
 *
 * This is the last line of defence: the editors already filter, but records can also arrive from
 * the API, from DDNS, or from a suffix whose provider was switched after the fact. Silently
 * pushing a type the provider refuses would report a publish that never happened.
 *
 * @param array<int,array> $records
 * @return array{records:array,dropped:array}
 */
function publish_drop_unsupported(string $zoneName, array $records): array
{
    if (!function_exists('record_type_supported')) {
        return ['records' => $records, 'dropped' => []];
    }

    $keep    = [];
    $dropped = [];
    foreach ($records as $rec) {
        $type = strtoupper(trim((string)($rec['type'] ?? '')));
        if ($type !== '' && !record_type_supported($type, $zoneName)) {
            $dropped[$type] = ($dropped[$type] ?? 0) + 1;
            continue;
        }
        $keep[] = $rec;
    }

    if ($dropped !== []) {
        $parts = [];
        foreach ($dropped as $type => $count) {
            $parts[] = $type . '×' . $count;
        }
        log_action('publish_type_unsupported', 'settings', null,
            $zoneName . ': ' . implode(', ', $parts));
    }

    return ['records' => $keep, 'dropped' => $dropped];
}
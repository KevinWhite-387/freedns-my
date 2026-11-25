<?php
declare(strict_types=1);

/**
 * freedns.my - which record types may be used where, and by whom.
 *
 * Two separate questions, answered here so that no page has to duplicate the rules:
 *
 *   1. does the provider of this suffix support the type at all?
 *        cloudflare  21 types      anyns   7 types      dnshe   7 types      spaceship  12 types
 *   2. is the type open to everybody, or does it need a developer account?
 *        open (default): A AAAA TXT MX CNAME SRV
 *        gated:          everything else - NS PTR CAA TLSA SVCB HTTPS CERT DNSKEY DS
 *                        LOC NAPTR OPENPGPKEY SMIMEA SSHFP URL
 *
 * "Gated" means the user needs users.developer = 1 and, for each record, an approved row in
 * developer_requests. The approval itself is checked by the caller (it holds the request id).
 */

/** What each provider really accepts, as given by the operator. */
function provider_record_types(): array
{
    return [
        'cloudflare' => [
            'A', 'AAAA', 'TXT', 'MX', 'CNAME', 'CAA', 'SRV', 'CERT', 'DNSKEY', 'DS',
            'HTTPS', 'LOC', 'NAPTR', 'PTR', 'NS', 'OPENPGPKEY', 'SMIMEA', 'SSHFP',
            'SVCB', 'TLSA', 'URL',
        ],
        'spaceship' => [
            'A', 'AAAA', 'MX', 'CNAME', 'SRV', 'TXT', 'CAA', 'NS', 'PTR', 'TLSA', 'SVCB', 'HTTPS',
        ],
        'anyns' => ['A', 'AAAA', 'TXT', 'MX', 'CNAME', 'CAA', 'SRV'],
        'dnshe' => ['A', 'AAAA', 'TXT', 'MX', 'CNAME', 'CAA', 'SRV', 'NS', 'PTR'],
    ];
}

function provider_record_types_for(string $provider): array
{
    $all = provider_record_types();

    return $all[strtolower(trim($provider))] ?? [];
}

/** The types the suffix's current provider accepts (empty when nothing is published there). */
function zone_record_types(string $zoneName): array
{
    if (!function_exists('publish_provider')) {
        return [];
    }

    return provider_record_types_for((string)publish_provider($zoneName));
}

/** Is the type acceptable for this suffix right now? */
function record_type_supported(string $type, string $zoneName): bool
{
    $type = strtoupper(trim($type));
    $list = zone_record_types($zoneName);

    /* No provider configured: the suffix is published by hand, so the database is the only limit. */
    if ($list === []) {
        return function_exists('record_types') ? in_array($type, record_types(), true) : true;
    }

    return in_array($type, $list, true);
}

/** Types everybody may submit without asking. */
function record_types_open(): array
{
    $raw = trim((string)setting('record_types_open', 'A,AAAA,TXT,MX,CNAME,SRV,NS,CAA'));
    $out = [];
    foreach (preg_split('/[\s,]+/', strtoupper($raw)) ?: [] as $t) {
        if ($t !== '') {
            $out[] = $t;
        }
    }

    return $out !== [] ? $out : ['A', 'AAAA', 'TXT', 'MX', 'CNAME', 'SRV'];
}

function record_type_is_open(string $type): bool
{
    return in_array(strtoupper(trim($type)), record_types_open(), true);
}

function record_type_needs_developer(string $type): bool
{
    return !record_type_is_open($type);
}

function user_is_developer(?array $user): bool
{
    if ($user === null) {
        return false;
    }
    if (isset($user['developer']) && (int)$user['developer'] === 1) {
        return true;
    }

    return false;
}

/**
 * The full answer for one attempt.
 *
 * @return array{ok:bool,reason:string,needs_developer:bool,supported:bool}
 */
function record_type_allowed(string $type, string $zoneName, ?array $user): array
{
    $type  = strtoupper(trim($type));
    $open  = record_type_is_open($type);
    $sup   = record_type_supported($type, $zoneName);

    if (!$sup) {
        $provider = function_exists('publish_provider_label')
            ? publish_provider_label((string)publish_provider($zoneName)) : '';
        $reason = $provider !== ''
            ? ($provider . ' 不支持 ' . $type . ' 记录。')
            : ('这个后缀还没有配置服务商，' . $type . ' 记录无法自动发布。');

        return ['ok' => false, 'reason' => $reason, 'needs_developer' => !$open, 'supported' => false];
    }

    if (!$open && !user_is_developer($user)) {
        return ['ok' => false,
            'reason'          => $type . ' 记录只对开发者开放：请先在「开发者」页面申请。',
            'needs_developer' => true,
            'supported'       => true];
    }

    if (!$open) {
        return ['ok' => false,
            'reason'          => $type . ' 记录需要单独申请，批准后才会写入。',
            'needs_developer' => true,
            'supported'       => true];
    }

    return ['ok' => true, 'reason' => '', 'needs_developer' => false, 'supported' => true];
}

/** The list a type dropdown should offer: open types plus, for developers, everything supported. */
function record_types_for_ui(string $zoneName, ?array $user): array
{
    $supported = zone_record_types($zoneName);
    $open      = record_types_open();
    $dev       = user_is_developer($user);

    $out = [];
    foreach (($supported !== [] ? $supported : (function_exists('record_types') ? record_types() : $open)) as $t) {
        if (in_array($t, $open, true) || $dev) {
            $out[] = $t;
        }
    }

    /* Keep a stable, familiar order. */
    $order = function_exists('record_types') ? record_types() : $open;
    usort($out, static function (string $a, string $b) use ($order): int {
        $ia = array_search($a, $order, true);
        $ib = array_search($b, $order, true);

        return ($ia === false ? 99 : $ia) <=> ($ib === false ? 99 : $ib);
    });

    return array_values(array_unique($out));
}

/** Human summary, used in the admin zone table. */
function provider_record_types_summary(string $zoneName): string
{
    $list = zone_record_types($zoneName);

    return $list === [] ? '' : (count($list) . ' 种：' . implode(' ', $list));
}

<?php
declare(strict_types=1);

/**
 * freedns.my - the shared part of "other registries".
 *
 * Both the public page (partners.php) and the back office page (admin/partners.php) need to read the same list,
 * so the reading lives here instead of in one of them: the back office must never include the public page, or it
 * would render the whole thing inside itself.
 *
 * The list itself is a setting, one partner per line:
 *
 *     category | name | url | one line about it | limits | free or paid
 */

/** Categories, with the heading each one is shown under. */
function partner_categories(): array
{
    return [
        'free2ld' => ['Free second level domains', '免费二级域名'],
        'dns'     => ['DNS hosting', 'DNS 托管'],
        'ddns'    => ['Dynamic DNS', '动态 DNS'],
        'paid'    => ['Commercial registrars', '收费注册商'],
    ];
}

/**
 * The list, parsed from the settings.
 *
 * Unknown categories are dropped rather than guessed at, a line with fewer than three fields is skipped, a line
 * starting with # is ignored, and a link that is not http(s) loses its entry - so a mistyped line can never turn
 * into something else on the page.
 *
 * @return array<string, array<int, array<string, string>>>
 */
function partner_list(): array
{
    $out = [];
    foreach (array_keys(partner_categories()) as $key) {
        $out[$key] = [];
    }

    $raw = trim((string)setting_lang('partners_list'));
    if ($raw === '') {
        return $out;
    }

    foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $parts = array_map('trim', explode('|', $line));
        if (count($parts) < 3) {
            continue;
        }
        $category = strtolower($parts[0]);
        if (!array_key_exists($category, $out)) {
            continue;
        }
        if (preg_match('#^https?://#i', $parts[2]) !== 1) {
            continue;
        }
        $out[$category][] = [
            'name'   => $parts[1],
            'url'    => $parts[2],
            'note'   => $parts[3] ?? '',
            'limits' => $parts[4] ?? '',
            'cost'   => strtolower($parts[5] ?? ''),
        ];
    }

    return $out;
}

/** How many entries the list holds altogether. */
function partner_count(): int
{
    $total = 0;
    foreach (partner_list() as $rows) {
        $total += count($rows);
    }

    return $total;
}
